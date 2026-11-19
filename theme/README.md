# Kbroo Hello theme

A near-blank WordPress theme to pair with the Kbroo Studio plugin, like Hello is for Elementor. It adds almost no styling, shows Kbroo Studio pages edge to edge with no theme title, and leaves clean spots for the plugin's upcoming header, footer and archive templates. It also works on its own as a plain minimal theme.

The theme lives in its own folder, `theme/kbroo-hello`, and is installed separately from the plugin.

## Install

1. Make a zip of the `kbroo-hello` folder (the zip should contain the `kbroo-hello` folder itself, not just its files).
2. In WordPress go to **Appearance > Themes > Add New Theme > Upload Theme**, choose the zip, then **Install Now** and **Activate**.

Or copy the `kbroo-hello` folder to `wp-content/themes/` and activate it under **Appearance > Themes**.

## What you get

- **Pages built with Kbroo Studio** show full width with no theme title, automatically.
- **Two page templates**, picked in the page editor under *Template*:
  - **Kbroo Canvas**: no header, footer or title. Just your design. Good for landing pages.
  - **Kbroo Full Width**: keeps the site header and footer, content edge to edge.
- **Appearance > Customize > Kbroo Hello**: switches to hide the theme's header, hide its footer, or turn off its stylesheet.
- A plain header (logo or site name, tagline, *Header menu*) and footer (*Footer menu*, copyright) until Kbroo Studio has its own header and footer builder.

## For the plugin: theme locations

The theme declares `add_theme_support( 'kbroo-theme-locations', [ 'header', 'footer', 'single', 'archive' ] )`. The plugin prints its own template in a location through the `kbroo_theme_do_location` filter and returns `true`, and the theme then skips its default markup:

```php
add_filter( 'kbroo_theme_do_location', function ( $done, $location ) {
	if ( 'header' === $location && $template_id = find_header_template() ) {
		echo render_template( $template_id );
		return true;
	}
	return $done;
}, 10, 2 );
```

- `single` covers pages, posts and the 404 page. `archive` covers the blog, categories, tags, dates, authors and search.
- Builder pages are detected from the plugin's `_kbroo_edit_mode = builder` post meta, so the theme does not need the plugin's classes.
- Other filters: `kbroo_hello_locations`, `kbroo_hello_is_builder_page`, `kbroo_hello_show_page_title`, `kbroo_hello_load_theme_css`. Actions: `kbroo_hello_before_location`, `kbroo_hello_location_done`.
- Body classes: `kbroo-hello`, plus `kbroo-hello--builder`, `kbroo-hello--canvas` or `kbroo-hello--full-width`.

## Files

| File | What it does |
| --- | --- |
| `style.css` | Theme header (name, version). No styles. |
| `functions.php` | Theme supports, menus, stylesheet, builder page detection, body classes |
| `inc/locations.php` | The theme location API |
| `inc/customizer.php` | The three Customizer switches |
| `inc/admin-notice.php` | A dismissible "install Kbroo Studio" reminder when the plugin is not active |
| `header.php`, `footer.php`, `index.php` | The page shell. `index.php` hands off to the `single` or `archive` location |
| `template-parts/*` | The theme's own header, footer, single, archive and 404 markup |
| `templates/canvas.php`, `templates/full-width.php` | The two page templates |
| `assets/css/theme.css` | The small stylesheet |
