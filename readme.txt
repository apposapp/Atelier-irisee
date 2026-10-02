=== Atelier Irisee Master Plugin ===
Contributors: atelieririsee
Tags: woocommerce, configurator, sewing, patterns, fabric
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
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
