<?php
$_lang['magicpreview'] = 'Magic Preview';
$_lang['magicpreview.preview'] = 'Preview for ';
$_lang['magicpreview.preparing_preview'] = 'Preparing your preview...';
$_lang['magicpreview.close_panel'] = 'Close preview panel';
$_lang['magicpreview.reload_preview'] = 'Reload preview';
$_lang['magicpreview.preview_button'] = 'Preview';
$_lang['magicpreview.preview_button_tooltip'] = 'Preview unsaved changes';
$_lang['magicpreview.view_button_tooltip'] = 'View saved resource';
$_lang['magicpreview.idle_message'] = 'Click <strong>Preview</strong> to generate a preview.';

$_lang['magicpreview.bp_full'] = 'Full';
$_lang['magicpreview.bp_desktop'] = 'Desktop';
$_lang['magicpreview.bp_tablet'] = 'Tablet';
$_lang['magicpreview.bp_mobile'] = 'Mobile';

// Settings
$_lang['setting_magicpreview.breakpoint_desktop'] = 'Breakpoint - Desktop Width';
$_lang['setting_magicpreview.breakpoint_desktop_desc'] = 'Desktop breakpoint width in pixels. Default: 1280px';
$_lang['setting_magicpreview.breakpoint_tablet'] = 'Breakpoint - Tablet Width';
$_lang['setting_magicpreview.breakpoint_tablet_desc'] = 'Tablet breakpoint width in pixels. Default: 768px';
$_lang['setting_magicpreview.breakpoint_mobile'] = 'Breakpoint - Mobile Width';
$_lang['setting_magicpreview.breakpoint_mobile_desc'] = 'Mobile breakpoint width in pixels. Default: 320px';
$_lang['setting_magicpreview.custom_preview_tpl'] = 'Custom Preview Template';
$_lang['setting_magicpreview.custom_preview_tpl_desc'] = 'Filename of a custom Smarty template in the magicpreview templates/ directory (e.g. "my_preview.tpl"). Leave empty to use the default template.';
$_lang['setting_magicpreview.custom_preview_css'] = 'Custom Preview CSS';
$_lang['setting_magicpreview.custom_preview_css_desc'] = 'URL of a custom CSS file to load on the preview page (e.g. "/assets/css/preview-custom.css"). Leave empty for no additional CSS.';
$_lang['setting_magicpreview.preview_mode'] = 'Preview Mode';
$_lang['setting_magicpreview.preview_mode_desc'] = 'How to display the preview. "New Window" opens a new browser window (default). "Panel" shows an inline side panel in the manager.';
$_lang['setting_magicpreview.panel_layout'] = 'Panel Layout';
$_lang['setting_magicpreview.panel_layout_desc'] = 'How the preview panel interacts with the editor. "Overlay" floats on top (default). "On Page" shrinks the editor to make room for a permanent column.';
$_lang['setting_magicpreview.auto_refresh_interval'] = 'Auto Refresh Interval';
$_lang['setting_magicpreview.auto_refresh_interval_desc'] = 'Seconds between automatic preview refreshes while the panel is open. The preview only refreshes when form data has changed. Set to 0 to disable. Default: 5.';
$_lang['setting_magicpreview.template_filter_mode'] = 'Template Filter Mode';
$_lang['setting_magicpreview.template_filter_mode_desc'] = 'Restrict where the Preview button appears based on template ID. "None" shows the button on every resource (default). "Block Listed" hides the button on resources using a template ID listed in Template Filter IDs. "Allow Listed Only" shows the button only on resources using a listed template ID (an empty list will hide it everywhere).';
$_lang['setting_magicpreview.template_filter_ids'] = 'Template Filter IDs';
$_lang['setting_magicpreview.template_filter_ids_desc'] = 'Comma-separated list of template IDs (e.g. 1,5,12) used by Template Filter Mode. Ignored when mode is "None".';

// Drafts
$_lang['magicpreview.save_draft'] = 'Save draft';
$_lang['magicpreview.draft_saved'] = 'Draft saved';
$_lang['magicpreview.draft_discarded'] = 'Draft discarded';
$_lang['magicpreview.draft_discard_failed'] = 'Could not discard the draft.';
$_lang['magicpreview.draft_banner_msg'] = 'A draft from [[+date]] is available';
$_lang['magicpreview.draft_restore'] = 'Restore';
$_lang['magicpreview.draft_discard'] = 'Discard';
$_lang['magicpreview.draft_discard_live_confirm'] = 'You have [[+count]] live share link(s) showing this draft. Discarding the draft will also remove those links. Continue?';
$_lang['magicpreview.draft_view'] = 'View';
$_lang['magicpreview.draft_share'] = 'Share';

// Draft settings
$_lang['setting_magicpreview.draft_ttl'] = 'Draft TTL';
$_lang['setting_magicpreview.draft_ttl_desc'] = 'How long saved drafts are kept, in seconds. Set to 0 to keep drafts indefinitely (until the resource is saved or the draft is manually discarded). Default: 0.';

// Icon settings
$_lang['setting_magicpreview.icon_save_draft'] = 'Save Draft Icon';
$_lang['setting_magicpreview.icon_save_draft_desc'] = 'Icon for the Save Draft button in the action bar. Enter a FontAwesome icon name (e.g. "icon-bookmark-o") or leave empty for the default icon.';
$_lang['setting_magicpreview.icon_view'] = 'View Icon';
$_lang['setting_magicpreview.icon_view_desc'] = 'Icon for the View button in the action bar. Enter a FontAwesome icon name (e.g. "icon-external-link") or leave empty for the default icon.';

// Per-resource settings
$_lang['magicpreview.resource_preview_mode'] = 'Preview Mode';
$_lang['magicpreview.resource_preview_mode_desc'] = 'Override the system-wide preview mode for this resource. Leave as "System Default" to inherit the system setting.';
$_lang['magicpreview.resource_panel_layout'] = 'Panel Layout';
$_lang['magicpreview.resource_panel_layout_desc'] = 'Override the system-wide panel layout for this resource. Leave as "System Default" to inherit the system setting.';
$_lang['magicpreview.resource_enabled'] = 'Use Magic Preview?';
$_lang['magicpreview.resource_enabled_desc'] = 'Override the template filter for this resource. "Yes" forces the Preview button to appear even if the template is excluded by the system filter. "No" hides the button even if the template would otherwise allow it.';
$_lang['magicpreview.system_default'] = 'System Default';

// Share links
$_lang['magicpreview.share_title'] = 'Share draft';
$_lang['magicpreview.share_label'] = 'Label';
$_lang['magicpreview.share_label_emptytext'] = 'e.g. Homepage redesign';
$_lang['magicpreview.share_expiry_default'] = 'Default (system setting)';
$_lang['magicpreview.share_expiry_default_value'] = 'Default ([[+duration]])';
$_lang['magicpreview.share_expiry_1day'] = '1 day';
$_lang['magicpreview.share_expiry_1week'] = '1 week';
$_lang['magicpreview.share_expiry_30days'] = '30 days';
$_lang['magicpreview.share_expiry_never'] = 'Never';
$_lang['magicpreview.share_create'] = 'Create link';
$_lang['magicpreview.share_created'] = 'Share link created';
$_lang['magicpreview.share_copy'] = 'Copy';
$_lang['magicpreview.share_copied'] = 'Link copied to clipboard';
$_lang['magicpreview.share_link_note'] = 'Copy it now — for security the link cannot be shown again.';
$_lang['magicpreview.share_existing'] = 'Active links for this resource';
$_lang['magicpreview.share_none'] = 'No active share links.';
$_lang['magicpreview.share_col_user'] = 'Shared by';
$_lang['magicpreview.share_col_label'] = 'Label';
$_lang['magicpreview.share_col_created'] = 'Created';
$_lang['magicpreview.share_col_expires'] = 'Expires';
$_lang['magicpreview.share_col_expires_in'] = 'Time left';
$_lang['magicpreview.share_expired'] = 'Expired';
$_lang['magicpreview.share_time_minute'] = '1 minute';
$_lang['magicpreview.share_time_minutes'] = '[[+n]] minutes';
$_lang['magicpreview.share_time_hour'] = '1 hour';
$_lang['magicpreview.share_time_hours'] = '[[+n]] hours';
$_lang['magicpreview.share_time_day'] = '1 day';
$_lang['magicpreview.share_time_days'] = '[[+n]] days';
$_lang['magicpreview.share_col_views'] = 'Views';
$_lang['magicpreview.share_revoke'] = 'Revoke';
$_lang['magicpreview.share_revoke_confirm'] = 'Revoke this share link? Anyone using it will lose access immediately.';
$_lang['magicpreview.share_revoked'] = 'Share link revoked';
$_lang['magicpreview.share_failed'] = 'Could not create the share link.';
$_lang['magicpreview.share_unavailable'] = 'This preview link has expired or is no longer available.';

// Share settings
$_lang['setting_magicpreview.share_link_ttl'] = 'Share Link TTL';
$_lang['setting_magicpreview.share_link_ttl_desc'] = 'Default lifetime of draft share links, in seconds. Used when no expiry is picked while creating a link. Set to 0 for links that never expire. Default: 604800 (7 days).';

