<?php

/**
 * MagicPreview shares service: public share links to drafts, stored in the
 * magicpreview_shares table. Every link is live — it renders whatever the
 * creator's draft holds at view time, so this service depends on the drafts
 * service (never the reverse). Obtain via MagicPreview::shares().
 *
 * @package magicpreview
 */
class MagicPreviewShares
{
    private MagicPreview $magicpreview;
    private modX $modx;

    /**
     * @param MagicPreview $magicpreview
     */
    public function __construct(MagicPreview $magicpreview)
    {
        $this->magicpreview = $magicpreview;
        $this->modx = $magicpreview->modx;
    }

    /**
     * Creates a shareable public link to a draft of a resource. The link is
     * live: at view time it renders whatever the creator's draft for the
     * resource currently holds (see resolveShare()).
     *
     * The raw token is returned ONCE here (embedded in the url) and is never
     * stored — only its sha256 hash is persisted, so a database leak can't be
     * used to mint working links.
     *
     * @param int $resourceId
     * @param int $userId The user creating the share.
     * @param string $contextKey
     * @param int|null $ttl Lifetime in seconds; null = the magicpreview.share_link_ttl setting, 0 = never expires.
     * @param string $label
     * @return array|null ['id' => int, 'token' => string, 'url' => string, 'expires_at' => int], or null on failure.
     */
    public function createShare(
        int $resourceId,
        int $userId,
        string $contextKey = 'web',
        ?int $ttl = null,
        string $label = ''
    ): ?array
    {
        if ($ttl === null) {
            $ttl = (int) $this->modx->getOption('magicpreview.share_link_ttl', null, 604800);
        }
        $now = time();

        // Generate an unguessable token; only its hash is stored. The unique
        // index on token_hash stays authoritative — this loop merely avoids a
        // doomed insert on the (astronomically unlikely) collision.
        $token = null;
        $tokenHash = null;
        for ($i = 0; $i < 3; $i++) {
            $candidate = bin2hex(random_bytes(32));
            $candidateHash = hash('sha256', $candidate);
            if (!$this->modx->getObject('mpShare', ['token_hash' => $candidateHash])) {
                $token = $candidate;
                $tokenHash = $candidateHash;
                break;
            }
        }
        if ($token === null) {
            return null;
        }

        /** @var mpShare $share */
        $share = $this->modx->newObject('mpShare');
        $share->fromArray([
            'token_hash' => $tokenHash,
            'resource_id' => $resourceId,
            'user_id' => $userId,
            'context_key' => $contextKey,
            'label' => $label,
            'createdon' => $now,
            'expires_at' => $ttl > 0 ? $now + $ttl : 0,
        ]);
        if (!$share->save()) {
            return null;
        }

        $this->magicpreview->garbageCollectExpired();

        return [
            'id' => (int) $share->get('id'),
            'token' => $token,
            'url' => $this->buildShareUrl($token, $contextKey),
            'expires_at' => (int) $share->get('expires_at'),
        ];
    }

    /**
     * Resolves a raw share token to a renderable draft, or null if the token
     * is unknown or expired — or if the creator's draft no longer exists.
     *
     * Deliberately performs no writes on misses, so probing random tokens
     * can't generate database load; the view counter is only bumped for
     * valid shares.
     *
     * @param string $token The raw token from the share URL.
     * @return array|null ['resource_id' => int, 'context_key' => string, 'data' => array]
     */
    public function getValidShare(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        /** @var mpShare|null $share */
        $share = $this->modx->getObject('mpShare', ['token_hash' => hash('sha256', $token)]);
        if (!$share) {
            return null;
        }

        $resolved = $this->resolveShare($share);
        if ($resolved === null) {
            return null;
        }

        // Best-effort view tracking.
        $share->set('views', (int) $share->get('views') + 1);
        $share->set('last_viewed_at', time());
        $share->save();

        return $resolved;
    }

    /**
     * Whether the current user may see and manage every share link on a
     * resource rather than only their own: sudo users and members of the
     * Administrator group get the oversight (and kill-switch) view.
     *
     * @return bool
     */
    public function currentUserSeesAllShares(): bool
    {
        $user = $this->modx->user;
        if (!$user) {
            return false;
        }
        return (bool) $user->get('sudo') || $user->isMember('Administrator');
    }

    /**
     * Returns the active (non-expired) share links for a resource, newest
     * first — limited to a single creator unless $userId is null (the
     * admin oversight view, which includes each creator's username).
     * Neither the token hash nor any draft data are included — the raw
     * token is shown once at creation and cannot be reconstructed, so the
     * listing is metadata only.
     *
     * @param int $resourceId
     * @param int|null $userId Limit to this creator's links; null = all users.
     * @return array
     */
    public function listSharesForResource(int $resourceId, ?int $userId = null): array
    {
        $where = [
            'resource_id' => $resourceId,
            [
                'expires_at' => 0,
                'OR:expires_at:>' => time(),
            ],
        ];
        if ($userId !== null) {
            $where['user_id'] = $userId;
        }

        $query = $this->modx->newQuery('mpShare');
        $query->where($where);
        $query->sortby('createdon', 'DESC');

        $shares = [];
        /** @var mpShare $share */
        foreach ($this->modx->getCollection('mpShare', $query) as $share) {
            $shares[] = [
                'id' => (int) $share->get('id'),
                'label' => (string) $share->get('label'),
                'user_id' => (int) $share->get('user_id'),
                'createdon' => (int) $share->get('createdon'),
                'expires_at' => (int) $share->get('expires_at'),
                'last_viewed_at' => (int) $share->get('last_viewed_at'),
                'views' => (int) $share->get('views'),
            ];
        }

        // Resolve creator usernames in one query. The model declares no
        // aggregates, so this avoids cross-package join quirks on 2.x/3.x.
        if (!empty($shares)) {
            $names = [];
            $userIds = array_unique(array_column($shares, 'user_id'));
            foreach ($this->modx->getCollection('modUser', ['id:IN' => $userIds]) as $user) {
                $names[(int) $user->get('id')] = (string) $user->get('username');
            }
            foreach ($shares as &$row) {
                $row['username'] = $names[$row['user_id']] ?? '';
            }
            unset($row);
        }

        return $shares;
    }

    /**
     * Revokes (deletes) a share link by id, optionally constrained to a
     * resource and/or creator so callers can't reach across to another
     * resource's — or another user's — links.
     *
     * @param int $shareId
     * @param int|null $resourceId
     * @param int|null $userId Require this creator; null = any (admin view).
     * @return bool
     */
    public function revokeShare(int $shareId, ?int $resourceId = null, ?int $userId = null): bool
    {
        $criteria = ['id' => $shareId];
        if ($resourceId !== null) {
            $criteria['resource_id'] = $resourceId;
        }
        if ($userId !== null) {
            $criteria['user_id'] = $userId;
        }
        $share = $this->modx->getObject('mpShare', $criteria);
        if (!$share) {
            return false;
        }
        return $share->remove();
    }

    /**
     * Counts the user's working (non-expired) share links for a resource —
     * every link is live, so all of them would stop resolving if their
     * draft were removed.
     *
     * @param int $resourceId
     * @param int $userId
     * @return int
     */
    public function countLiveShares(int $resourceId, int $userId): int
    {
        return (int) $this->modx->getCount('mpShare', [
            'resource_id' => $resourceId,
            'user_id' => $userId,
            [
                'expires_at' => 0,
                'OR:expires_at:>' => time(),
            ],
        ]);
    }

    /**
     * Removes the user's share links for a resource (expired ones included).
     * Called when the draft they resolve against is discarded.
     *
     * @param int $resourceId
     * @param int $userId
     * @return int The number of links removed.
     */
    public function removeLiveShares(int $resourceId, int $userId): int
    {
        $removed = $this->modx->removeCollection('mpShare', [
            'resource_id' => $resourceId,
            'user_id' => $userId,
        ]);
        return $removed === false ? 0 : (int) $removed;
    }

    /**
     * Builds the absolute public URL for a share token, using the target
     * context's site_url so multi-context sites link to the right host.
     *
     * @param string $token
     * @param string $contextKey
     * @return string
     */
    public function buildShareUrl(string $token, string $contextKey = 'web'): string
    {
        $siteUrl = '';
        $context = $this->modx->getContext($contextKey);
        if ($context) {
            $siteUrl = (string) $context->getOption('site_url', null, '');
        }
        if ($siteUrl === '') {
            $siteUrl = (string) $this->modx->getOption('site_url');
        }

        // assetsUrl is usually relative (/assets/components/magicpreview/) but
        // can be configured absolute; only prefix the site URL when relative.
        $assetsUrl = (string) $this->magicpreview->config['assetsUrl'];
        if (strpos($assetsUrl, 'http://') !== 0 && strpos($assetsUrl, 'https://') !== 0) {
            $assetsUrl = rtrim($siteUrl, '/') . '/' . ltrim($assetsUrl, '/');
        }

        return $assetsUrl . 'share.php?t=' . $token;
    }

    /**
     * Deletes expired share links. Cheap thanks to the expires_at index;
     * called via MagicPreview::garbageCollectExpired() on writes.
     *
     * @return int The number of rows removed.
     */
    public function garbageCollect(): int
    {
        // removeCollection() returns false on failure; normalise to 0 so the
        // return type stays a plain int (union types would require PHP 8.0,
        // and the project floor is 7.4).
        $removed = $this->modx->removeCollection('mpShare', [
            'expires_at:>' => 0,
            'expires_at:<' => time(),
        ]);
        return $removed === false ? 0 : (int) $removed;
    }

    /**
     * Resolves a share row to its renderable data: enforces expiry and
     * fetches the creator's current draft — every share link is live, so it
     * renders whatever that draft holds at view time.
     *
     * @param mpShare $share
     * @return array|null ['resource_id' => int, 'context_key' => string, 'data' => array]
     */
    private function resolveShare(mpShare $share): ?array
    {
        $expiresAt = (int) $share->get('expires_at');
        if ($expiresAt > 0 && $expiresAt < time()) {
            return null;
        }

        $draft = $this->magicpreview->drafts()->getDraft(
            (int) $share->get('resource_id'),
            (int) $share->get('user_id')
        );
        if ($draft === null || !is_array($draft['data']) || empty($draft['data'])) {
            return null;
        }

        return [
            'resource_id' => (int) $share->get('resource_id'),
            'context_key' => (string) $share->get('context_key'),
            'data' => $draft['data'],
        ];
    }
}
