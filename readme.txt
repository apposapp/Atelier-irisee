=== Atelier Irisee Master Plugin ===
Contributors: atelieririsee
Tags: woocommerce, configurator, sewing, patterns, fabric
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.3.0
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

= 2.3.0 =
* Settings page in tabs: General, Product categories, Shop pages, Discounts, Site look, Footer and Mosaic. Still one form, so saving one tab keeps the others; after saving you stay on the same tab.
* Checkout: "Your order" next to every step, with sewing project kits shown as one product (pictures, amounts, crossed-out kit prices, set total), like the cart. On phones it sits above the steps and folds open and closed.
* Checkout: step 3 is now "Overview" and starts with the delivery address from the details step (or the other delivery address when "Ship to a different address" is ticked).
* Configurator: the fabric filters are now the gold panel of the shop pages (search, sort, fabric categories, in stock). Both panels have the side menu's gold gradient.
* Details panels taller than the screen move along while scrolling and stay in view.
* Patterns: saving a product now also refreshes the page caches of common caching plugins (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, SiteGround, WP Fastest Cache, Breeze, Cloudflare, Hummingbird). The pattern's Atelier Irisee tab says whether it is for sale on the website, or why it shows "Out of stock".
* Button texts are brown and 15px everywhere; selected buttons stay white on gold.
* Cart: removing a discount code works again, with a notice.
* Favorites page: every product gets a "View product" button.

= 2.2.0 =
* New: step-by-step checkout [atelier_irisee_checkout] (Cart → Details → Delivery → Payment → Confirmation) on top of WooCommerce's own checkout, so payment plugins such as Mollie work unchanged. The "Checkout page" setting also sets WooCommerce's checkout page.
* New: sewing project kit discount (setting, 10% by default), shown with crossed-out prices in the configurator and on the cart page.
* New: discount codes follow the shop's rules: never combined, not on products with a sale price (sewing kits excepted), never on gift cards. Make them under Marketing → Coupons.
* New: welcome code for new customers (setting %, validity): a popup after the first login and listed in the account under "Gift cards and discount codes" until it is used.
* New: recommendation box on product pages, next to the picture: a recommended pattern (or a recommended fabric on patterns) and a button to the configurator. Chosen in the new "Atelier Irisee recommendation" box on every product.
* Language switcher moved into the header: the active flag next to the account icon, with a list of the other languages.
* Shop filters: a gold panel that slides and fades in and out, never covers the products and doesn't lock the page.
* Mosaic layout "Centre stage": ten tiles of different sizes, one screen high.
* Sticky panels, pictures and the cart totals stay below the header; the side menu's line lines up with the header line; the configurator scrolls to the top on every step.
* Smaller cards (8 per row) for suggested patterns, recommended fabrics and related products; 18px amounts with "cm" aligned; single borders on the cart totals and gift card box; smaller footer without the empty band; new composition icon.

= 2.1.0 =
* New: cart page [atelier_irisee_cart]. Configurator sets are shown as one product with thumbnails, prices and the set total; other products have ‹ › amounts; discount code, gift card field, totals with shipping and checkout. The "Cart page" setting also sets WooCommerce's cart page.
* New: footer (company details, social icons, My account and Customer service menus, newsletter, payment logos, legal links, copyright). It replaces Divi's footer, including the "Powered by" bar. Newsletter sign-ups are stored on the site, with a list and CSV download under WooCommerce > Newsletter.
* New: product mosaic [atelier_irisee_mosaic type="…"]: ten products, best sellers or your own choice.
* Fabric pages: "Inspiration" title, cards with a single line and no dots, a fourth card with the recommended pattern; specifications as icon cards above the washing instructions.
* Pattern pages: "Included sizes" under the skill level, and a "Recommended fabrics" row.
* Gift cards: the ‹ › arrows in the price bar choose the value in steps of €5, from €10.
* Header: Divi logo, Divi's menu bar always hidden, full width, the side menu fades out as it fades in, and sub-menus fade open and closed.
* Notices: readable brown text on Divi too, and they close after 4 seconds.
* The "Empty line" button is now in every text editor, also the small ones.
* No category label on product pages; "Fits these patterns" is now "Suggested patterns"; the overview card "Configurator" is now "Sewing project kits"; "Out of stock" sits at the bottom of the card.

= 2.0.0 =
* New: Atelier Irisee header (switch in WooCommerce > Atelier Irisee > Site look). Logo on the left, gold favourites, cart and account icons on the right, and a "Menu" bar that opens a gold side menu with the menu "Atelier Irisee side menu". It replaces the theme header in block and classic themes; [atelier_irisee_header] places it anywhere.
* New: Trajan Pro and 15px text on the whole site. The font files are uploaded in the settings and stay on your own site.
* New: notices get a single gold border, brown text and a × button; success and info messages close by themselves after 4 seconds.
* New: the All products page shows an overview of five cards (Fabrics, Patterns, Haberdashery, Gift cards, Configurator), with pictures chosen in the settings. Category links still show the product list.
* New: skill level for patterns (Beginner, Average, Advanced, Expert), shown under the price bar and as a filter on the patterns page.
* New: "Empty line" button in the text editor (Visual and Text tab).
* Fabric pages: order information under the price bar; inspiration as three cards with a title and text; washing instructions as a 2×2 grid with gold icons; washing instructions and specifications as dropdowns.
* Price bar: price on the left, quantity and button on the right. The gift card page gets the price bar too, and its picture follows the chosen design.
* Product pictures fill their box everywhere.

= 1.9.0 =
* Product pages: a buy bar right under the title, with the amount and "Add to cart" on the left and the price on the right. The amount has simple gold arrows (‹ ›), in steps of 10 cm for fabric, ribbon and bias tape and steps of 1 for other products, and the price shows the total.
* Patterns are bought as one item with all sizes: no size choice, size chart or size pictures on the pattern page, and the cart and order show "Sizes: All sizes". The description and details sit next to the picture.
* Gold titles for Inspiration and Order information. Slightly larger product picture.
* Stock levels are no longer shown anywhere; sold-out items still show "Out of stock".
* The language flags and the add-to-cart button keep the configurator look, whatever the theme does with buttons and links.
* New settings: shop pages for all products, patterns, fabrics and haberdashery. Category links and the shop link open these pages with the category selected, and WooCommerce's category and shop pages redirect there.

= 1.8.0 =
* New: "Fabric texts" box on fabric products. Inspiration and order information are free texts. Specifications (composition, type, colour, width, weight) and washing instructions (washing, drying, ironing, optional tips) each have one field per subject.
* Fabric product pages: inspiration and order information next to the picture under the price, specifications under add to cart, then a row with the description and the washing instructions side by side.
* Products sold per 10 cm (fabric, ribbon, bias tape): the quantity is chosen in cm, in steps of 10, and the price shows the total for the chosen length.
* Pattern product pages no longer show the size chart and measuring guide; these stay in the configurator.
* Smaller main picture. Product and shop pages now use exactly the configurator's text colours, sizes, buttons and fields, also where WooCommerce or the theme styled them differently.
* The separate "Add to favorites" button is gone; the heart sits in the corner of the product picture everywhere, also in the configurator.

= 1.7.0 =
* New: shop pages with [atelier_irisee_shop type="all|patterns|fabrics|haberdashery"]. A filter sidebar that opens and closes (search, sort, category, price, product attributes, in stock), the product grid in the configurator design, and a details panel with pictures, the favorite heart, short description, price and a View product button. Filters are kept in the page address.
* New: product pages in the Atelier Irisee design, for WooCommerce's own product pages (classic and block themes; can be switched off) and with [atelier_irisee_product id="123"]. Gallery with lightbox, add to cart, the size chart and measuring guide for patterns, "Complete it in the configurator", "Fits these patterns" for fabrics, description, details, reviews and related products.
* New settings: configurator page, products per page on shop pages, and the product page design switch.
* Configurator: the recap at the top only shows the pictures, names and size; the configurator can be opened with a pattern already chosen (?aimp_pattern=ID).

= 1.6.0 =
* New: account page shortcode [atelier_irisee_account] with tabs for personal data, orders, refund or remove account, and gift cards.
* New: refund requests. The customer picks items and a reason; you approve (refunded through the payment method when it supports refunds) or reject from the order screen. Emails go out at every step.
* New: customers can remove their own account (can be switched off in the settings).
* New: custom gift cards (WooCommerce > Gift cards). The customer chooses the amount and a design, and gets the card by email (to themselves or someone else, optionally on a chosen date) or printed by post (shipping plus an optional printing fee).
* Every gift card has a unique code. It appears in the account, the emails and the admin list, with a balance history, printing, resending and manual cards.
* The gift card code field in the cart and at checkout (classic and blocks) works like a payment after VAT. Unused balance stays on the card, and the balance comes back when an order is cancelled or refunded.
* Configurator: thumbnails of the chosen products at the top, normal text in brown (#613907, 17px, semi-bold), and fabric category buttons with a single line.

= 1.5.0 =
* New: Login & registration module (WooCommerce > Login & registration).
* AJAX login, registration, lost and reset password forms as a popup, side slider or inline form, with tabs or links, logo, side image, animations and auto-open.
* Triggers: menu items, shortcodes ([aimp_login_popup], [aimp_login_form], [aimp_profile]) and the classes aimp-login-tgr, aimp-reg-tgr, aimp-lostpw-tgr.
* Redirects after login, registration and logout. The ?aimp_redirect= parameter is supported.
* Custom registration fields: text, text area, email, number, date, phone, dropdown, radio, checkbox, file upload, profile picture and user role. Fields can be saved as WooCommerce billing or shipping fields.
* Profile form with a profile picture, email change confirmation and password change.
* Security: reCAPTCHA v2/v3, Cloudflare Turnstile, Friendly Captcha, a password strength meter, limits on login attempts, rate limits and a honeypot.
* Email verification (code or one-click link) and admin approval, with emails in the customer's language.
* Social login: Google, Facebook, Apple, LinkedIn, Microsoft, LINE and X.
* WooCommerce: the checkout login (classic and blocks) opens the popup, and the plugin's forms appear on the My Account login page.
* Address autocomplete (Google Places API New) in the forms and at checkout.
* Import and export of the settings.

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
