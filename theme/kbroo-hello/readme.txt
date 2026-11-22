=== Kbroo Hello ===
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A near-blank starter theme for the Kbroo Studio page builder.

== Description ==

Kbroo Hello stays out of the builder's way. It adds almost no styling, shows
pages built with Kbroo Studio edge to edge with no theme title (unless the
page turns Hide title off in Kbroo Studio's Page settings), with comments
underneath when the page allows them, and leaves clean spots (header, footer,
single, archive) for the builder's own templates.

It also works without the plugin, as a plain minimal theme.

= Page templates =

* Kbroo Canvas: no header, footer or title. Just the page.
* Kbroo Full Width: site header and footer, content edge to edge.

Choose one in the page editor under Template (block editor: Page settings >
Template; classic editor: Page Attributes > Template).

= Settings =

Appearance > Customize > Kbroo Hello has switches to hide the theme header,
hide the theme footer, or stop loading the theme stylesheet.

= For developers =

Builder plugins fill a location with the `kbroo_theme_do_location` filter:

    add_filter( 'kbroo_theme_do_location', function ( $done, $location ) {
        if ( 'header' === $location ) {
            echo '<header>...</header>';
            return true;
        }
        return $done;
    }, 10, 2 );

Locations: header, footer, single (pages, posts, 404), archive (blog lists,
categories, tags, search). The theme declares
`add_theme_support( 'kbroo-theme-locations', [...] )`.

Other filters: `kbroo_hello_locations`, `kbroo_hello_is_builder_page`,
`kbroo_hello_show_page_title`, `kbroo_hello_load_theme_css`.
Actions: `kbroo_hello_before_location`, `kbroo_hello_location_done`.

== Changelog ==

= 0.1.0 =
* First release.
