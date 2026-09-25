<?php
$snippets = [];

$snippets[0] = $modx->newObject('modSnippet');
$snippets[0]->fromArray([
    'id' => 1,
    'name' => 'mpField',
    'description' => 'Returns click-to-field data attributes for a resource field or TV.',
    'snippet' => getSnippetContent($sources['snippets'] . 'mpfield.snippet.php'),
    'category' => 0,
], '', true, true);

return $snippets;
