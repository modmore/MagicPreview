MagicPreview for MODX
=====================

MagicPreview adds a _Magical_ Preview button to the MODX resource editor that lets you see
exactly how your page will look -- without saving. Edit your content, click Preview,
and instantly see a live, fully rendered preview of your changes.

It works with MODX 2.x and 3.x, supports custom resource types (Collections,
Articles, etc.), and integrates with rich text editors and ContentBlocks
automatically.


Preview Modes
-------------

Choose how you want to preview your work:

- New Window: Opens the preview in a separate browser window (default).
- Panel: Shows the preview in an inline side panel right next to the editor, so you
  can edit and preview simultaneously without switching windows.

The panel has two layout options:

- Overlay: The panel slides in from the right and floats on top of the editor.
- On Page: The editor shrinks to make room for a permanent preview column alongside
  it.

Both the preview mode and panel layout can be overridden per resource from the
resource's Settings tab, allowing you to use different configurations for different
parts of your site.


Responsive Breakpoints
----------------------

Preview your page at different screen widths with one click. Four breakpoint buttons
are available in both the preview window and the panel toolbar:

- Full (100% width)
- Desktop (default: 1280px)
- Tablet (default: 768px)
- Mobile (default: 320px)

Each breakpoint width is configurable via system settings. Width transitions are
smoothly animated.


Auto-Refresh
------------

When using the panel, MagicPreview can automatically refresh the preview at a
configurable interval (default: every 5 seconds). The preview only reloads when your
form data has actually changed, so it is efficient and unobtrusive. Set the interval
to 0 to disable auto-refresh.


Draft Save & Restore
--------------------

Working on a page but not ready to publish? Use the Save Draft button (or press
Ctrl+Shift+S / Cmd+Shift+S) to save your current edits as a draft. Drafts are stored
per resource and per user, so your work is private and won't interfere with other
editors.

When you return to a resource that has a saved draft, a notification banner offers to
restore or discard it. Restoring a draft reloads the page with all your previous
edits filled in -- including template variables and extras data.

Draft expiry is configurable; by default drafts are kept until the resource is saved
or the draft is manually discarded.


Drag-to-Resize
---------------

In panel mode, drag the left edge of the panel to resize it to your preferred width.
Your chosen width is remembered across page loads.


Flicker-Free Updates
--------------------

The panel uses a double-buffered iframe technique: new content loads in a hidden
iframe behind the scenes, and only swaps in once it is fully rendered. Your scroll
position is preserved across refreshes, so you never lose your place.


Keyboard Shortcuts
------------------

- Ctrl+P / Cmd+P: Trigger a preview
- Ctrl+Shift+S / Cmd+Shift+S: Save a draft


Customisation
-------------

MagicPreview is designed to work out of the box, but offers several customisation
options via system settings:

- Custom preview template: Override the Smarty template used for the preview window.
- Custom preview CSS: Load an additional CSS file in the preview window.
- Configurable breakpoint widths for desktop, tablet, and mobile.
- Configurable button icons: Replace the default Save Draft and View button icons
  with any FontAwesome icon class.
- Auto-refresh interval: Adjust or disable the automatic refresh timer.
- Draft TTL: Control how long drafts are kept before expiring.


Third-Party Integration
-----------------------

MagicPreview fires a custom system event (OnResourceMagicPreview) during the preview
process, allowing other extras to modify the resource data before it is rendered. This
is used by extras like VersionX to preview version history without saving.

A public JavaScript API is also available for extras that need to open, close, or
interact with the preview programmatically.


Support & Documentation
-----------------------

Full documentation is available at: https://www.modmore.com/extras/magicpreview/
