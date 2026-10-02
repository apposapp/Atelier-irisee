=== Atelier Irisee Master Plugin ===
Contributors: atelieririsee
Tags: woocommerce, configurator, sewing, patterns, fabric
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pattern configurator for WooCommerce: pattern and size, matching fabric, buttons and zips, added to the cart as one linked set.

== Description ==

* Patterns are variable products with one variation per size.
* Each size stores how much fabric it needs (in units of 10 cm), which fabric categories are allowed, how many buttons and zips it needs, the zip length, and the bust, waist and height measurements.
* The `[atelier_irisee_configurator]` shortcode guides the customer through four steps: pattern and size, then fabric, then buttons and zips, then a summary.
* Everything is added to the cart in one go. Stock is checked first, quantities are locked to the chosen size, and the lines stay linked, so removing one removes the whole set.
* Compatible with HPOS (High-Performance Order Storage) and the Cart and Checkout blocks.
* Updates are delivered automatically from GitHub releases.

== Installation ==

1. Download `atelier-irisee-master-plugin.zip` from the latest GitHub release and upload it under Plugins > Add New > Upload Plugin.
2. Go to WooCommerce > Atelier Irisee and choose your Patterns, Fabrics, Buttons and Zips categories.
3. Fill in the material requirements on each pattern size. The Atelier Irisee box is shown on every variation.
4. Fill in "Zip length (cm)" on each zip product.
5. Add `[atelier_irisee_configurator]` to a page.

== Changelog ==

= 1.4.0 =
* Ribbons and bias tape: set the length in cm on each pattern size. Customers can pick one in step 3, just like buttons and zips. Products are sold per 10 cm; lengths are rounded up to whole 10 cm.
* Step 3 is now called "Choose your haberdashery".
* The details panel no longer has its own scrollbar. It follows you while scrolling when it fits on the screen.
* Favorites for the whole shop:
  * a heart on shop and category grids, on product pages and in the configurator,
  * guests' favorites are kept in their browser and moved into their account when they log in,
  * a favorites page with the [atelier_irisee_favorites] shortcode.

= 1.3.0 =
* New Atelier Irisee look: all text, titles and links in #b38f4f, and round gold double-border buttons, tiles and thumbnails. Selected items are filled gold.
* Step indicator shows only the circle number (no duplicate list number), in gold.
* New measurements on pattern sizes: hip and inside leg. The configurator only shows measurements that are filled in.
* The size chart is now a separate box under the patterns and details, so it stays in place when you choose a size. Click a row to choose that size.
* "How to measure your body measurements" button opens the measuring guide picture in a lightbox.
* Details panel, measurement boxes, recap and summary: transparent backgrounds with gold lines.

= 1.2.2 =
* Fix: fabric category names were hidden in the "Fabric categories shown first" table (WooCommerce panel label styling).

= 1.2.1 =
* The "Fabric categories shown first" table on a pattern now lists only the fabric categories ticked under "Fabric categories allowed" on its sizes, and updates as soon as you tick or untick one.

= 1.2.0 =
* French translation, with the French flag added to the language switcher.
* Fabric step:
  * filter by fabric category,
  * search,
  * show only fabrics in stock,
  * sort by recommended, name, price or newest.
* Fabric step: smaller fabric pictures, 16 per page (adjustable), numbered pagination and a "showing x–y of z" count.
* New "Atelier Irisee" tab on pattern products to choose which fabric categories are shown first.
* Pattern details show all pictures: one large picture with selectable thumbnails underneath. Choosing a size shows its own picture.
* Details of the selected pattern, fabric, buttons or zip now appear on the right next to the grid, and stay in view while scrolling.

= 1.1.0 =
* Complete Dutch translation: settings, product fields, configurator, cart, checkout and order texts.
* Language switcher with flags in the top right of the configurator (Nederlands / English). The customer's choice is remembered and also used in the cart.
* New setting "Plugin language" under WooCommerce > Atelier Irisee.

= 1.0.0 =
* Initial release.
