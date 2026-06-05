<?php
$_lang['magicpreview'] = 'Magisk forhåndsvisning';
$_lang['magicpreview.preview'] = 'Forhåndsvisning af ';
$_lang['magicpreview.preparing_preview'] = 'Forbereder din forhåndsvisning...';
$_lang['magicpreview.close_panel'] = 'Luk forhåndsvisningspanel';
$_lang['magicpreview.reload_preview'] = 'Genindlæs forhåndsvisning';
$_lang['magicpreview.preview_button'] = 'Forhåndsvisning';
$_lang['magicpreview.preview_button_tooltip'] = 'Forhåndsvis ikke-gemte ændringer';
$_lang['magicpreview.view_button_tooltip'] = 'Vis gemt ressource';
$_lang['magicpreview.idle_message'] = 'Klik på <strong>Forhåndsvisning</strong> for at generere en forhåndsvisning.';

$_lang['magicpreview.bp_full'] = 'Fuld størrelse';
$_lang['magicpreview.bp_desktop'] = 'Desktop';
$_lang['magicpreview.bp_tablet'] = 'Tablet';
$_lang['magicpreview.bp_mobile'] = 'Mobil';

// Settings
$_lang['setting_magicpreview.breakpoint_desktop'] = 'Breakpoint - Desktop-bredde';
$_lang['setting_magicpreview.breakpoint_desktop_desc'] = 'Desktop breakpoint-bredde i pixels. Standard: 1280px';
$_lang['setting_magicpreview.breakpoint_tablet'] = 'Breakpoint - Tablet-bredde';
$_lang['setting_magicpreview.breakpoint_tablet_desc'] = 'Tablet breakpoint-bredde i pixels. Standard: 768px';
$_lang['setting_magicpreview.breakpoint_mobile'] = 'Breakpoint - Mobil-bredde';
$_lang['setting_magicpreview.breakpoint_mobile_desc'] = 'Mobil breakpoint-bredde i pixels. Standard: 320px';
$_lang['setting_magicpreview.custom_preview_tpl'] = 'Brugerdefineret forhåndsvisningsskabelon';
$_lang['setting_magicpreview.custom_preview_tpl_desc'] = 'Filnavn på en brugerdefineret Smarty-skabelon i magicpreview templates/-mappen (f.eks. "my_preview.tpl"). Lad feltet være tomt for at bruge standardskabelonen.';
$_lang['setting_magicpreview.custom_preview_css'] = 'Brugerdefineret forhåndsvisnings-CSS';
$_lang['setting_magicpreview.custom_preview_css_desc'] = 'URL til en brugerdefineret CSS-fil til forhåndsvisningssiden (f.eks. "/assets/css/preview-custom.css"). Lad feltet være tomt for ingen ekstra CSS.';
$_lang['setting_magicpreview.preview_mode'] = 'Forhåndsvisningstilstand';
$_lang['setting_magicpreview.preview_mode_desc'] = 'Hvordan forhåndsvisningen vises. "New Window" åbner et nyt browservindue (standard). "Panel" viser et sidepanel i manageren.';
$_lang['setting_magicpreview.panel_layout'] = 'Panel-layout';
$_lang['setting_magicpreview.panel_layout_desc'] = 'Hvordan forhåndsvisningspanelet interagerer med editoren. "Overlay" svæver ovenpå (standard). "On Page" formindsker editoren for en permanent kolonne.';
$_lang['setting_magicpreview.auto_refresh_interval'] = 'Automatisk opdateringsinterval';
$_lang['setting_magicpreview.auto_refresh_interval_desc'] = 'Sekunder mellem automatiske forhåndsvisningsopdateringer, mens panelet er åbent. Forhåndsvisningen opdateres kun, når formulardata er ændret. Sæt til 0 for at deaktivere. Standard: 5.';
$_lang['setting_magicpreview.template_filter_mode'] = 'Skabelon-filtertilstand';
$_lang['setting_magicpreview.template_filter_mode_desc'] = 'Begrænser hvor Forhåndsvisnings-knappen vises baseret på skabelon-ID. "None" viser knappen på alle ressourcer (standard). "Block Listed" skjuler knappen på ressourcer, der bruger et skabelon-ID på listen i "Skabelon-filter-ID\'er". "Allow Listed Only" viser kun knappen på ressourcer med et skabelon-ID på listen (en tom liste skjuler den overalt).';
$_lang['setting_magicpreview.template_filter_ids'] = 'Skabelon-filter-ID\'er';
$_lang['setting_magicpreview.template_filter_ids_desc'] = 'Komma-separeret liste af skabelon-ID\'er (f.eks. 1,5,12), der bruges af Skabelon-filtertilstand. Ignoreres, når tilstanden er "None".';

// Kladder
$_lang['magicpreview.save_draft'] = 'Gem kladde';
$_lang['magicpreview.draft_saved'] = 'Kladde gemt';
$_lang['magicpreview.draft_discarded'] = 'Kladde kasseret';
$_lang['magicpreview.draft_banner_msg'] = 'En kladde fra [[+date]] er tilgængelig.';
$_lang['magicpreview.draft_restore'] = 'Gendan';
$_lang['magicpreview.draft_discard'] = 'Kassér';

// Kladde-indstillinger
$_lang['setting_magicpreview.draft_ttl'] = 'Kladde-TTL';
$_lang['setting_magicpreview.draft_ttl_desc'] = 'Hvor længe gemte kladder opbevares, i sekunder. Sæt til 0 for at beholde kladder på ubestemt tid (indtil ressourcen gemmes, eller kladden kasseres manuelt). Standard: 0.';

// Ikon-indstillinger
$_lang['setting_magicpreview.icon_save_draft'] = 'Gem kladde-ikon';
$_lang['setting_magicpreview.icon_save_draft_desc'] = 'Ikon til Gem kladde-knappen i handlingslinjen. Indtast et FontAwesome-ikonnavn (f.eks. "icon-bookmark-o") eller lad det være tomt for standardikonet.';
$_lang['setting_magicpreview.icon_view'] = 'Vis-ikon';
$_lang['setting_magicpreview.icon_view_desc'] = 'Ikon til Vis-knappen i handlingslinjen. Indtast et FontAwesome-ikonnavn (f.eks. "icon-external-link") eller lad det være tomt for standardikonet.';

// Ressource-specifikke indstillinger
$_lang['magicpreview.resource_preview_mode'] = 'Forhåndsvisningstilstand';
$_lang['magicpreview.resource_preview_mode_desc'] = 'Tilsidesæt den systemdækkende forhåndsvisningstilstand for denne ressource. Efterlad som "Systemstandard" for at arve systemindstillingen.';
$_lang['magicpreview.resource_panel_layout'] = 'Panel-layout';
$_lang['magicpreview.resource_panel_layout_desc'] = 'Tilsidesæt det systemdækkende panel-layout for denne ressource. Efterlad som "Systemstandard" for at arve systemindstillingen.';
$_lang['magicpreview.resource_enabled'] = 'Brug Magic Preview?';
$_lang['magicpreview.resource_enabled_desc'] = 'Tilsidesæt skabelonfilteret for denne ressource. "Ja" tvinger Forhåndsvisnings-knappen til at blive vist, selvom skabelonen er udelukket af systemfilteret. "Nej" skjuler knappen, selvom skabelonen ellers ville tillade det.';
$_lang['magicpreview.system_default'] = 'Systemstandard';

// Delingslinks
$_lang['magicpreview.share_button_tooltip'] = 'Del et link til denne kladde';
$_lang['magicpreview.share_title'] = 'Del kladde';
$_lang['magicpreview.share_label'] = 'Etiket';
$_lang['magicpreview.share_label_emptytext'] = 'f.eks. Forside-redesign';
$_lang['magicpreview.share_expiry'] = 'Linket udløber';
$_lang['magicpreview.share_expiry_default'] = 'Standard (systemindstilling)';
$_lang['magicpreview.share_expiry_1day'] = '1 dag';
$_lang['magicpreview.share_expiry_1week'] = '1 uge';
$_lang['magicpreview.share_expiry_30days'] = '30 dage';
$_lang['magicpreview.share_expiry_never'] = 'Aldrig';
$_lang['magicpreview.share_live_label'] = 'Hold linket synkroniseret med min kladde (live)';
$_lang['magicpreview.share_create'] = 'Opret link';
$_lang['magicpreview.share_created'] = 'Delingslink oprettet';
$_lang['magicpreview.share_copy'] = 'Kopiér';
$_lang['magicpreview.share_copied'] = 'Link kopieret til udklipsholderen';
$_lang['magicpreview.share_link_note'] = 'Kopiér det nu — af sikkerhedshensyn kan linket ikke vises igen.';
$_lang['magicpreview.share_existing'] = 'Aktive links for denne ressource';
$_lang['magicpreview.share_none'] = 'Ingen aktive delingslinks.';
$_lang['magicpreview.share_col_label'] = 'Etiket';
$_lang['magicpreview.share_col_created'] = 'Oprettet';
$_lang['magicpreview.share_col_expires'] = 'Udløber';
$_lang['magicpreview.share_col_type'] = 'Type';
$_lang['magicpreview.share_col_views'] = 'Visninger';
$_lang['magicpreview.share_type_snapshot'] = 'Øjebliksbillede';
$_lang['magicpreview.share_type_live'] = 'Live';
$_lang['magicpreview.share_view'] = 'Vis';
$_lang['magicpreview.share_revoke'] = 'Tilbagekald';
$_lang['magicpreview.share_revoke_confirm'] = 'Tilbagekald dette delingslink? Alle, der bruger det, mister adgangen med det samme.';
$_lang['magicpreview.share_revoked'] = 'Delingslink tilbagekaldt';
$_lang['magicpreview.share_failed'] = 'Delingslinket kunne ikke oprettes.';
$_lang['magicpreview.share_unavailable'] = 'Dette forhåndsvisningslink er udløbet eller er ikke længere tilgængeligt.';

// Delingsindstillinger
$_lang['setting_magicpreview.share_link_ttl'] = 'Delingslink-TTL';
$_lang['setting_magicpreview.share_link_ttl_desc'] = 'Standardlevetid for kladde-delingslinks, i sekunder. Bruges, når der ikke vælges en udløbstid ved oprettelse af et link. Sæt til 0 for links, der aldrig udløber. Standard: 604800 (7 dage).';
$_lang['setting_magicpreview.icon_share'] = 'Del-ikon';
$_lang['setting_magicpreview.icon_share_desc'] = 'Ikon til Del-knappen i handlingslinjen. Indtast et FontAwesome-ikonnavn (f.eks. "icon-share-alt") eller lad det være tomt for standardikonet.';
