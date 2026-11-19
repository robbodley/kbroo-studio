# Kbroo Studio

A visual, drag-and-drop page builder plugin for WordPress.

## Status

Foundation (v0.1.0): full-screen editor, sections and columns, Heading and Text widgets, JSON layout storage and front-end rendering.

## Install

Copy this folder to `wp-content/plugins/kbroo-studio` and activate **Kbroo Studio**. There is no build step: the editor is plain JavaScript and CSS.

Requires WordPress 6.2+ and PHP 7.4+.

## Using it

Open a page or post with any of these:

- **Edit with Kbroo Studio** in the block editor sidebar (Summary panel) or under the title in the classic editor
- the **Edit with Kbroo Studio** row link in the Pages / Posts lists
- the **Edit with Kbroo Studio** admin bar link on the front end

In the editor:

- Drag a widget from the left panel into a column, or between sections to start a new one. Clicking a widget adds it after the selected element.
- **Add section** offers column structures (100, 50/50, 33/33/33, 66/33, 33/66, 25x4).
- Click any section, column or widget to edit its settings. Hover toolbars duplicate, delete, add columns, and drag sections (move handle).
- `Ctrl/Cmd+S` saves, `Delete` removes the selected element, `Esc` deselects.

## How it works

| Piece | Where |
| --- | --- |
| Bootstrap, autoloader | `kbroo-studio.php`, `src/Plugin.php` |
| Editor route `post.php?post=ID&action=kbroo_edit` and launchers | `src/Editor.php`, `templates/editor.php`, `assets/js/launcher.js` |
| Editor app (canvas, drag and drop, settings panel, saving) | `assets/js/editor.js`, `assets/css/editor.css` |
| Layout schema and sanitizing | `src/Layout.php`, `src/Controls.php` |
| Save endpoint `POST /wp-json/kbroo/v1/documents/<id>` | `src/Rest.php` |
| Widgets (PHP render + JS preview) | `src/Widgets/*`, `assets/js/widgets.js` |
| Front-end rendering | `src/Renderer.php`, `src/Frontend.php`, `assets/css/frontend.css` |

The layout is stored as JSON in the `_kbroo_data` post meta, and `_kbroo_edit_mode = builder` marks a page as built with Kbroo Studio. The tree is `sections > columns > widgets`:

```json
{
  "version": 1,
  "elements": [
    { "id": "a1b2c3d", "elType": "section", "settings": { "padding": 40 },
      "elements": [
        { "id": "e4f5a6b", "elType": "column", "settings": { "width": 100 },
          "elements": [
            { "id": "c7d8e9f", "elType": "widget", "widgetType": "heading",
              "settings": { "text": "Hello", "tag": "h1", "align": "center" } }
          ] } ] } ]
}
```

Every save is sanitized server side against each element's control definitions (unknown widgets and settings are dropped, HTML goes through `wp_kses_post`). A rendered HTML copy is also written to `post_content`, so the content survives if the plugin is deactivated. On the front end, `the_content` is replaced with the rendered layout for builder pages.

### Adding a widget

1. Subclass `KbrooStudio\Widgets\Widget` (name, title, icon, `get_controls()`, `render()`), and register it on the `kbroo_studio_register_widgets` action.
2. Register a matching preview with `KbrooStudio.registerWidget( name, settings => html )` in a script that loads after `kbroo-widgets` in the editor.

Hooks: `kbroo_studio_post_types` (filter), `kbroo_studio_register_widgets` (action), `kbroo_studio_render` (filter).
