# MagicPreview for MODX

MagicPreview adds a **Preview** button to the MODX Revolution resource editor that renders a live preview of your unsaved changes — without saving the resource. Preview in a popup window or an inline side panel with responsive breakpoints, auto-refresh, and draft support.

Compatible with **MODX 2.x** and **MODX 3.x**.

## Features

- **Preview without saving** — see your changes rendered on the actual front-end template, without persisting anything to the database
- **Popup window or inline panel** — choose between a new browser window or a side panel (overlay or on-page column)
- **Responsive breakpoints** — preview at desktop, tablet, and mobile widths (configurable via system settings)
- **Auto-refresh** — the panel automatically re-renders when form data changes (configurable interval)
- **Draft system** — save a draft of unsaved form data that persists across sessions and can be restored later
- **Custom event** — `OnResourceMagicPreview` allows other extras (e.g. ContentBlocks) to hook into the preview process
- **Customisable** — override the preview HTML template and CSS via system settings
- **i18n** — English, German, and Danish translations included

## Installation

Install MagicPreview from the [modmore package provider](https://modmore.com/about/package-provider/).

## System Settings

All settings use the `magicpreview.` prefix and can be configured in the MODX manager under System Settings.

| Setting                 | Default      | Description                                                         |
| ----------------------- | ------------ | ------------------------------------------------------------------- |
| `preview_mode`          | `New Window` | `New Window` or `Panel`                                             |
| `panel_layout`          | `Overlay`    | `Overlay` (floats over content) or `On Page` (pushes content aside) |
| `auto_refresh_interval` | `5`          | Seconds between auto-refresh checks (0 to disable)                  |
| `breakpoint_desktop`    | `1280px`     | Desktop breakpoint width                                            |
| `breakpoint_tablet`     | `768px`      | Tablet breakpoint width                                             |
| `breakpoint_mobile`     | `320px`      | Mobile breakpoint width                                             |
| `custom_preview_tpl`    | _(empty)_    | Custom Smarty template chunk for preview page                       |
| `custom_preview_css`    | _(empty)_    | Custom CSS file URL for preview page                                |
| `draft_ttl`             | `0`          | Draft expiry in seconds (0 = no expiry)                             |
| `icon_save_draft`       | _(empty)_    | FontAwesome class for the Save Draft button icon                    |
| `icon_view`             | _(empty)_    | FontAwesome class for the View button icon                          |

## Per-Resource Settings

You can override `preview_mode` and `panel_layout` on individual resources. The override fields appear in the resource editor's **Settings** tab under a "Magic Preview" fieldset. Select "System Default" (the default) to inherit the system setting, or choose a specific value to override it for that resource.

Per-resource settings are stored in the resource's `properties` column under the `magicpreview` namespace.

## Panel State

The preview panel remembers whether it was open or closed (and its drag-resized width) per user. This state is persisted automatically via MODX's built-in `Ext.state.Manager` — the same mechanism MODX uses for the left tree panel's collapsed/expanded state. When you reopen a resource, the panel restores to its previous state. The panel defaults to hidden.

## Development

### Requirements

- PHP 7.0+
- MODX Revolution 2.6.5+ or 3.x
- A local MODX installation for development and building

### Local Setup

1. Clone this repository
2. Copy `config.core.sample.php` to `config.core.php` and set the path to your MODX installation's `core/` directory
3. Run the bootstrap script to register the namespace, settings, plugin, and events:

```bash
php _bootstrap/index.php
```

### Building a Transport Package

```bash
php _build/build.transport.php
```

This outputs a `.transport.zip` file to `core/packages/` (or `_packages/` depending on your MODX setup).

### Project Structure

```
_bootstrap/                  Dev bootstrap script
_build/                      Transport package builder
assets/components/magicpreview/
  connector.php              AJAX connector (auto-detects MODX 2/3)
  preview.css                Preview window page styles
  css/mgr.css                Manager panel + button styles
  js/                        Client-side JS modules (window, panel, preview, combo)
core/components/magicpreview/
  controllers/               Manager controller for preview page
  elements/plugins/          Main plugin (3 system events)
  lexicon/                   i18n strings (en, de, da)
  model/magicpreview/        Service class + VERSION constant
  processors/resource/       Preview, draft, and restore processors (v2 + v3)
  templates/                 Smarty template for preview window
```

## Integration with Other Extras

MagicPreview exposes a public API that other MODX extras can use as a preview engine. The `OnResourceMagicPreview` event fires during preview processing, allowing other extras to modify the in-memory resource before it is cached and rendered.

**VersionX** uses this integration to let users preview what a resource would look like after reverting a delta — without actually applying the revert. VersionX feature-detects MagicPreview at runtime, calls MagicPreview's `resource/preview` processor with the delta's field changes, and opens MagicPreview's preview window to display the result.

For implementation details, see the integration pattern in the `OnResourceMagicPreview` event handler and the `resource/preview` processor.

## Contributing

Contributions are welcome. Please open an issue or pull request on this repository.

## License

MIT — see [LICENSE](core/components/magicpreview/docs/license.txt).

Copyright 2018 Mark Hamstra / [modmore](https://modmore.com/).
