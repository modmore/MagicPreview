<?php
$_lang['magicpreview'] = 'Magische Vorschau';
$_lang['magicpreview.preview'] = 'Vorschau für ';
$_lang['magicpreview.preparing_preview'] = 'Ihre Vorschau wird vorbereitet...';
$_lang['magicpreview.close_panel'] = 'Vorschau-Panel schließen';
$_lang['magicpreview.reload_preview'] = 'Vorschau neu laden';
$_lang['magicpreview.preview_button'] = 'Vorschau';
$_lang['magicpreview.preview_button_tooltip'] = 'Nicht gespeicherte Änderungen als Vorschau anzeigen';
$_lang['magicpreview.view_button_tooltip'] = 'Gespeicherte Ressource ansehen';
$_lang['magicpreview.idle_message'] = 'Klicken Sie auf <strong>Vorschau</strong>, um eine Vorschau zu erstellen.';
$_lang['magicpreview.bp_full'] = 'Volle Breite';
$_lang['magicpreview.bp_desktop'] = 'Desktop';
$_lang['magicpreview.bp_tablet'] = 'Tablet';
$_lang['magicpreview.bp_mobile'] = 'Mobil';

// Settings
$_lang['setting_magicpreview.breakpoint_desktop'] = 'Breakpoint - Desktop-Breite';
$_lang['setting_magicpreview.breakpoint_desktop_desc'] = 'Desktop Breakpoint-Breite in Pixeln. Standard: 1280px';
$_lang['setting_magicpreview.breakpoint_tablet'] = 'Breakpoint - Tablet-Breite';
$_lang['setting_magicpreview.breakpoint_tablet_desc'] = 'Tablet Breakpoint-Breite in Pixeln. Standard: 768px';
$_lang['setting_magicpreview.breakpoint_mobile'] = 'Breakpoint - Mobil-Breite';
$_lang['setting_magicpreview.breakpoint_mobile_desc'] = 'Mobil Breakpoint-Breite in Pixeln. Standard: 320px';
$_lang['setting_magicpreview.custom_preview_tpl'] = 'Benutzerdefinierte Vorschau-Vorlage';
$_lang['setting_magicpreview.custom_preview_tpl_desc'] = 'Dateiname einer benutzerdefinierten Smarty-Vorlage im magicpreview templates/-Verzeichnis (z.B. "my_preview.tpl"). Leer lassen, um die Standardvorlage zu verwenden.';
$_lang['setting_magicpreview.custom_preview_css'] = 'Benutzerdefinierte Vorschau-CSS';
$_lang['setting_magicpreview.custom_preview_css_desc'] = 'URL einer benutzerdefinierten CSS-Datei für die Vorschauseite (z.B. "/assets/css/preview-custom.css"). Leer lassen für kein zusätzliches CSS.';
$_lang['setting_magicpreview.preview_mode'] = 'Vorschau-Modus';
$_lang['setting_magicpreview.preview_mode_desc'] = 'Wie die Vorschau angezeigt wird. "New Window" öffnet ein neues Browserfenster (Standard). "Panel" zeigt ein Seitenpanel im Manager.';
$_lang['setting_magicpreview.panel_layout'] = 'Panel-Layout';
$_lang['setting_magicpreview.panel_layout_desc'] = 'Wie das Vorschau-Panel mit dem Editor interagiert. "Overlay" schwebt darüber (Standard). "On Page" verkleinert den Editor für eine permanente Spalte.';
$_lang['setting_magicpreview.auto_refresh_interval'] = 'Automatisches Aktualisierungsintervall';
$_lang['setting_magicpreview.auto_refresh_interval_desc'] = 'Sekunden zwischen automatischen Vorschau-Aktualisierungen, während das Panel geöffnet ist. Die Vorschau wird nur aktualisiert, wenn sich Formulardaten geändert haben. Auf 0 setzen zum Deaktivieren. Standard: 5.';
$_lang['setting_magicpreview.click_to_field'] = 'Klick zum Feld';
$_lang['setting_magicpreview.click_to_field_desc'] = 'Wenn aktiviert, scrollt ein Klick auf ein Feld in der Vorschau das Ressourcenformular zu diesem Feld. Erfordert ContentBlocks oder ein Template, das data-magicpreview-field-Attribute setzt. Standard: Ja.';
$_lang['setting_magicpreview.template_filter_mode'] = 'Vorlagen-Filtermodus';
$_lang['setting_magicpreview.template_filter_mode_desc'] = 'Begrenzt, wo der Vorschau-Button erscheint, basierend auf der Vorlagen-ID. "None" zeigt den Button für alle Ressourcen (Standard). "Block Listed" blendet den Button für Ressourcen mit einer in "Vorlagen-Filter-IDs" aufgelisteten Vorlagen-ID aus. "Allow Listed Only" zeigt den Button nur für Ressourcen mit aufgelisteter Vorlagen-ID (leere Liste blendet ihn überall aus).';
$_lang['setting_magicpreview.template_filter_ids'] = 'Vorlagen-Filter-IDs';
$_lang['setting_magicpreview.template_filter_ids_desc'] = 'Komma-separierte Liste von Vorlagen-IDs (z.B. 1,5,12), die vom Vorlagen-Filtermodus verwendet wird. Wird ignoriert, wenn der Modus "None" ist.';

// Entwürfe
$_lang['magicpreview.save_draft'] = 'Entwurf speichern';
$_lang['magicpreview.draft_saved'] = 'Entwurf gespeichert';
$_lang['magicpreview.draft_discarded'] = 'Entwurf verworfen';
$_lang['magicpreview.draft_discard_failed'] = 'Der Entwurf konnte nicht verworfen werden.';
$_lang['magicpreview.draft_banner_msg'] = 'Ein Entwurf vom [[+date]] ist verfügbar';
$_lang['magicpreview.draft_restore'] = 'Wiederherstellen';
$_lang['magicpreview.draft_discard'] = 'Verwerfen';
$_lang['magicpreview.draft_discard_live_confirm'] = 'Sie haben [[+count]] Live-Freigabelink(s), die diesen Entwurf anzeigen. Beim Verwerfen des Entwurfs werden diese Links ebenfalls entfernt. Fortfahren?';
$_lang['magicpreview.draft_view'] = 'Ansehen';
$_lang['magicpreview.draft_share'] = 'Teilen';

// Entwurf-Einstellungen
$_lang['setting_magicpreview.draft_ttl'] = 'Entwurf-TTL';
$_lang['setting_magicpreview.draft_ttl_desc'] = 'Wie lange gespeicherte Entwürfe aufbewahrt werden, in Sekunden. Auf 0 setzen, um Entwürfe unbegrenzt zu behalten (bis die Ressource gespeichert oder der Entwurf manuell verworfen wird). Standard: 0.';

// Symbol-Einstellungen
$_lang['setting_magicpreview.icon_save_draft'] = 'Entwurf-Symbol';
$_lang['setting_magicpreview.icon_save_draft_desc'] = 'Symbol für den Entwurf-Button in der Aktionsleiste. Geben Sie einen FontAwesome-Symbolnamen ein (z.B. "icon-bookmark-o") oder lassen Sie es leer für das Standard-Symbol.';
$_lang['setting_magicpreview.icon_view'] = 'Ansicht-Symbol';
$_lang['setting_magicpreview.icon_view_desc'] = 'Symbol für den Ansicht-Button in der Aktionsleiste. Geben Sie einen FontAwesome-Symbolnamen ein (z.B. "icon-external-link") oder lassen Sie es leer für das Standard-Symbol.';

// Ressourcen-spezifische Einstellungen
$_lang['magicpreview.resource_preview_mode'] = 'Vorschau-Modus';
$_lang['magicpreview.resource_preview_mode_desc'] = 'Überschreibt den systemweiten Vorschau-Modus für diese Ressource. Belassen Sie "Systemstandard", um die Systemeinstellung zu übernehmen.';
$_lang['magicpreview.resource_panel_layout'] = 'Panel-Layout';
$_lang['magicpreview.resource_panel_layout_desc'] = 'Überschreibt das systemweite Panel-Layout für diese Ressource. Belassen Sie "Systemstandard", um die Systemeinstellung zu übernehmen.';
$_lang['magicpreview.resource_enabled'] = 'Magic Preview verwenden?';
$_lang['magicpreview.resource_enabled_desc'] = 'Überschreibt den Vorlagenfilter für diese Ressource. "Ja" erzwingt, dass der Vorschau-Button erscheint, auch wenn die Vorlage durch den Systemfilter ausgeschlossen ist. "Nein" blendet den Button auch dann aus, wenn die Vorlage ihn sonst zulassen würde.';
$_lang['magicpreview.system_default'] = 'Systemstandard';

// Freigabelinks
$_lang['magicpreview.share_title'] = 'Entwurf teilen';
$_lang['magicpreview.share_label'] = 'Bezeichnung';
$_lang['magicpreview.share_label_emptytext'] = 'z.B. Startseiten-Relaunch';
$_lang['magicpreview.share_expiry_default'] = 'Standard (Systemeinstellung)';
$_lang['magicpreview.share_expiry_default_value'] = 'Standard ([[+duration]])';
$_lang['magicpreview.share_expiry_1day'] = '1 Tag';
$_lang['magicpreview.share_expiry_1week'] = '1 Woche';
$_lang['magicpreview.share_expiry_30days'] = '30 Tage';
$_lang['magicpreview.share_expiry_never'] = 'Nie';
$_lang['magicpreview.share_create'] = 'Link erstellen';
$_lang['magicpreview.share_created'] = 'Freigabelink erstellt';
$_lang['magicpreview.share_copy'] = 'Kopieren';
$_lang['magicpreview.share_copied'] = 'Link in die Zwischenablage kopiert';
$_lang['magicpreview.share_link_note'] = 'Jetzt kopieren — aus Sicherheitsgründen kann der Link nicht erneut angezeigt werden.';
$_lang['magicpreview.share_existing'] = 'Aktive Links für diese Ressource';
$_lang['magicpreview.share_none'] = 'Keine aktiven Freigabelinks.';
$_lang['magicpreview.share_col_user'] = 'Geteilt von';
$_lang['magicpreview.share_col_label'] = 'Bezeichnung';
$_lang['magicpreview.share_col_created'] = 'Erstellt';
$_lang['magicpreview.share_col_expires'] = 'Läuft ab';
$_lang['magicpreview.share_col_expires_in'] = 'Verbleibend';
$_lang['magicpreview.share_expired'] = 'Abgelaufen';
$_lang['magicpreview.share_time_minute'] = '1 Minute';
$_lang['magicpreview.share_time_minutes'] = '[[+n]] Minuten';
$_lang['magicpreview.share_time_hour'] = '1 Stunde';
$_lang['magicpreview.share_time_hours'] = '[[+n]] Stunden';
$_lang['magicpreview.share_time_day'] = '1 Tag';
$_lang['magicpreview.share_time_days'] = '[[+n]] Tage';
$_lang['magicpreview.share_col_views'] = 'Aufrufe';
$_lang['magicpreview.share_revoke'] = 'Widerrufen';
$_lang['magicpreview.share_revoke_confirm'] = 'Diesen Freigabelink widerrufen? Jeder, der ihn verwendet, verliert sofort den Zugriff.';
$_lang['magicpreview.share_revoked'] = 'Freigabelink widerrufen';
$_lang['magicpreview.share_failed'] = 'Der Freigabelink konnte nicht erstellt werden.';
$_lang['magicpreview.share_unavailable'] = 'Dieser Vorschau-Link ist abgelaufen oder nicht mehr verfügbar.';

// Freigabe-Einstellungen
$_lang['setting_magicpreview.share_link_ttl'] = 'Freigabelink-TTL';
$_lang['setting_magicpreview.share_link_ttl_desc'] = 'Standard-Lebensdauer von Entwurf-Freigabelinks, in Sekunden. Wird verwendet, wenn beim Erstellen eines Links keine Ablaufzeit gewählt wird. Auf 0 setzen für Links, die nie ablaufen. Standard: 604800 (7 Tage).';
