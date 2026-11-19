# Kbroo Studio – Build Plan

Kbroo Studio is a WordPress visual page builder plugin in the style of Elementor:
drag-and-drop editing with a live preview, a settings panel for every element, and
responsive controls. It will run on several existing sites, so moving pages over
from Elementor is part of version 1.

- **Live plan card:** https://claude.ai/artifact/NvhHrkFGoGiC8B9peRNCxF
  (choices are saved on the card; `build-plan-card.html` in this folder is its source)
- **Choices below captured:** 19 November 2026

## Technical direction

- Editor UI in React, talking to WordPress through the REST API.
- Page layout stored as JSON in post meta, rendered to HTML and CSS on the front end.
- Code prefix `kbs_`, plugin slug `kbroo-studio`.
- Updates delivered from GitHub releases, like the Cheri Gallery plugin.
- Runs alongside Elementor during migration. Elementor's data is never deleted.

## Version 1 scope ("Need now")

### Phase 1 – Foundation
- Sections, columns and containers
- Drag-and-drop editor with live preview
- Settings panel with Content, Style and Advanced tabs
- Desktop, tablet and mobile editing
- Undo and redo
- Navigator (layer tree)
- Copy, paste and duplicate
- Revision history
- Auto-updates from GitHub releases

### Phase 2 – Core widgets and global styles
- Heading, text, image, button, spacer, divider
- Video, image gallery, image carousel
- Icon, icon list, icon box, image box
- HTML and shortcode widget
- Global colours and fonts
- Entrance animations and hover effects
- Background images, gradients, overlays and video backgrounds

### Phase 3 – Moving over from Elementor
- Convert Elementor pages (from `_elementor_data`)
- Import Elementor template `.json` files
- Import Site Kit colours and fonts
- Fallback blocks and a conversion report
- Run side by side and roll back

### Phase 4 – Templates
- Template library (save and insert pages and sections)

### Phase 5 – Pro features
- Header and footer builder (including sticky headers)

## Later
- Tabs, accordion, testimonials, counters, progress bars
- Google map, social icons, star rating
- Starter templates
- Share templates between sites
- Forms with email notifications
- Popups
- Blog post and archive templates
- Dynamic content
- WooCommerce widgets

## Still to decide
- Cheri Gallery widgets

## Next steps
1. Export one real Elementor template from an existing site and add it to
   `docs/samples/` so the importer is built against real data.
2. Set up the plugin skeleton and start Phase 1.
