=== Atelier Irisee Master Plugin ===
Contributors: atelieririsee
Tags: woocommerce, configurator, sewing, patterns, fabric
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.3.2
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

= 3.3.2 =
* PostNL pickup points show again in the Atelier Irisee checkout. PostNL for WooCommerce 5.9.12 gets pickup points from its new V4 API without a partner ID and pickup time, and its classic checkout then drops every point (only the empty "Pick up" tab remained). The plugin now builds that list from the V4 points, with PostNL's own template, so choosing a point, saving it on the order and labels work as before. When PostNL sends complete points, its own list is used unchanged.

= 3.3.1 =
* PostNL pickup points: PostNL's checkout script is always loaded on the checkout, and the Atelier Irisee checkout shows and handles the "Pick up" choice itself if that script is missing, so the list of pickup points opens and a point can be chosen.
* When the checkout page still contains WooCommerce's Checkout block (PostNL then switches to its block mode: no pickup points, no Dutch house-number field, no pickup fee), a notice offers to remove that block in one click (a revision is kept).
* PostNL tab: a "Checkout check" with the checkout page, the block check and the number of PostNL shipping zones.

= 3.3.0 =
* Shipping now runs fully through PostNL for WooCommerce. The Atelier Irisee shipping method is removed; your shipping costs were moved (once, automatically) to WooCommerce shipping zones with the PostNL method: your own country, every country you had listed, and "all other countries", each with its cost and "free from".
* Shipping and delivery tab: the PostNL cost and "free from" of every zone, "Add country" and remove. New PostNL tab: all PostNL settings (API key, sender, checkout, labels, Fill in with PostNL), saved by PostNL itself. PostNL's own settings pages open this tab.
* "Free shipping from …" (delivery line) and "… to go" with the bar now use PostNL's amounts.
* Orders with a PostNL pickup point show "Pickup point" on the thank-you page and under the order in My orders.
* Track & trace: without a pasted link, the PostNL track & trace of the label is used in the Completed email and in My orders.
* Cart: WooCommerce's "Calculate shipping" and the shipping options (PostNL logo, local pickup) in the Atelier Irisee look.

= 3.2.1 =
* PostNL for WooCommerce: while the Atelier Irisee shipping costs are on, they count as a PostNL shipping method, so PostNL's pickup points (and delivery options where PostNL offers them), its fees and its labels work. PostNL's fees are added on top of our shipping cost.
* Dutch addresses with PostNL: the house number extension field is always shown (no "+ Add apartment" link), "Street 12A" from address autocomplete or a saved address is split into street, house number and extension, and the address box shows the house number.
* The PostNL choice at checkout in the Atelier Irisee look (gold tabs, readable text, aligned left).

= 3.2.0 =
* Products menu: All fabrics, Add a fabric, All patterns, Add a pattern (moved from the WooCommerce menu).
* Patterns: the height (body length) and the fitting fabrics are set once on the pattern (Atelier Irisee tab), for all sizes; the fitting fabrics table ticks the allowed categories and their position together. Existing patterns show the values of their sizes until saved.
* Patterns: new "Average project time". The pattern page shows skill level (stars), sizes and project time as three bordered tiles with a sewing machine, tape measure and clock icon.
* Filters: several categories can be ticked at once on the shop pages and in the configurator (fabrics and pattern categories); the Filters button has a single thin border.
* Settings: the info bar and Delivery and stock are on the "Shipping and delivery" tab; "Only … left" can be set per product category.
* Product pages: the delivery line is one line and a bit smaller; "Only … left" keeps its gold colour.
* Configurator: a roll of fabric icon for the fabric needed.

= 3.1.0 =
* New: "Add a fabric" and "Add a pattern" in the WooCommerce menu. The new product starts in Stoffen or Patronen (patterns as Variable), so the workspace opens straight away.
* Pattern edit screen: the short description is card 1 ("Description"); the long description is under "Show all other fields".
* Newsletters: the content can be a page on the website (for example designed with Divi). The email then shows your intro, the page's picture and a "Read the newsletter" button. Every newsletter has a "View in browser" link and an optional preview text.
* Delivery information (Site look → Delivery and stock): "Delivered in … · Free shipping from … · 14 days to return" under the buy bar and in the cart, plus the payment logos and "Continue shopping" in the cart.
* Product pages: "Only 3 left" when stock is low (setting, 0 = off), zoom on hover over the picture, swipe with dots on phones.
* Shop pages: number of results, the active filters as chips with ×, and the sort menu next to Filters.
* Search: matching categories first, "Did you mean …" for typos, and your last five searches.
* Checkout: no company field unless switched on; address line 2 and order note behind a "+ Add …" link; "No account needed" for guests; fields are checked as you leave them (also in the login and registration forms).
* Orders: expected delivery date (working days, Belgian holidays skipped) on the thank-you page and in the order emails, with a "Questions?" contact line.
* Emails: preview line in the inbox, mobile and dark mode styles, buttons that work in Outlook, sender name "Atelier Irisée", replies go to the shop email, one-click unsubscribe for newsletters.
* Phones: product grids as list rows; cart products as stacked cards; one payment method per row; favorites 2 per row; All products overview fits the screen on desktop.
* The side menu opens with "All products" unfolded; the configurator's "added to cart" notice has a single border.

= 3.0.0 =
* New: write and send newsletters under WooCommerce → Newsletter: subject, text with pictures, optional button and attachment; preview, test email, then send to all confirmed subscribers in the background, each with a personal unsubscribe link (WooCommerce email "Newsletter").
* New: product workspace for fabrics and patterns: only the fields they use, as numbered cards (price and stock, texts, specifications, washing, sizes, recommendation); all other fields grouped under "Show all other fields".
* New: gold info bar under the header (Site look → Info bar), with sparkles; it folds away while scrolling.
* New: Shop icon in the header (All products); the All products overview shows the sewing project kits as a large hero tile.
* New: "Certification" fabric specification (shown when filled in).
* Configurator: the size needs as cards with icons; body measurements and account gift cards with a single line; "Make another sewing project kit".
* Registration: the cat/dog question answers Cat, Dog, Both or No, in the visitor's language.
* Mobile: compact header that slims down while scrolling, pinch zoom allowed, 44px tap areas and 16px fields; shop details as a bottom sheet; fixed Next bar in the configurator and checkout; account menu as swipeable buttons; foldable footer menus; size chart and overview as cards.

= 2.9.1 =
* Admin menu: choose a role or a person at the top ("Menu for:") and set up their whole menu at once: order and visibility of the main items and of the submenu items (for example inside WooCommerce). People without their own set-up use their role's, or else Everyone's. Settings from 2.9.0 are converted.

= 2.9.0 =
* New: Admin menu tab in the settings. Drag the dashboard menu items into your own order and hide items for chosen roles or people (WooCommerce stays visible for shop managers).
* Shop pages: the details panel has the buy bar (amount, price, Add to cart) under the price; products are added in the background.
* Gift cards show the chosen design as their picture in the cart, checkout, mini cart and orders.
* Search: now in the top header row, centred between logo and icons.

= 2.8.0 =
* New: track & trace link on orders. Paste it in the "Track & trace" box on the order screen; when the order is set to Completed, the Completed order email has a "Track my parcel" button. Customers also see it with the order under My orders.
* Fix: the newsletter confirmation email was sent to an invalid address. It is now a WooCommerce email ("Newsletter confirmation" under WooCommerce → Settings → Emails, with your own subject and heading). WooCommerce → Newsletter can send it again or confirm a subscriber.
* Footer: when subscribed, the newsletter invitation and privacy line are hidden too.
* Search: centred on the menu bar and fades in and out.

= 2.7.0 =
* Newsletter: double opt-in (a confirmation email; only confirmed subscribers are in the CSV). The footer shows "You are subscribed" with an Unsubscribe button, or "Check your inbox" with a resend button, also on cached pages.
* New: search in the header with live results (pictures, prices), arrow keys and "See all results".
* New: back-in-stock alerts on sold-out products ("Email me when it's back"), sent by themselves when the product is in stock again; list under WooCommerce → Stock alerts.
* New: "Save this kit" in the configurator; saved kits are on the favorites page and "Continue" opens the configurator with every choice.
* Orders: sewing project kits as one block on the thank-you page, under My orders and in the order emails.
* Thank-you page in the Atelier Irisee style: order at a glance, delivery address, next steps, and for guests an offer to create an account.
* WooCommerce emails in the Atelier Irisee style (gold and brown, logo, company details); setting under Site look.
* Cart: a progress bar towards free shipping; an empty cart shows popular products and the sewing project kit configurator.
* Phones: a small buy bar at the bottom of product pages once the price bar has scrolled away.
* Gold texts are a little deeper so they are easy to read; the favorites page styles load only on that page and the scripts load deferred.

= 2.6.2 =
* Cart: no "Shipping to …" line under the shipping cost; the "Free shipping from …: … to go." line sits there instead (also in the checkout totals).
* Checkout: logged-in customers get empty fields filled from their account: the saved address, and for the details step the saved shipping address, email and name when no billing address is saved.

= 2.6.1 =
* Fix: addresses from the account page's form are now saved to the account (they only reached the session before), so they show on the account page with an "Edit" button and fill in the checkout.
* Fix: the totals and payment methods no longer turn white while the checkout updates (for example after choosing another payment method).

= 2.6.0 =
* Account page: addresses are added and changed in a lightbox form; they are saved to the account and fill in the checkout. The menu buttons are all the same size, and the tab is now "Refunds": customers can no longer remove their own account there (the setting is gone too).
* Shipping costs now show in the cart and checkout: the plugin adds its own shipping method ("Atelier Irisee shipping") to WooCommerce's "Locations not covered by your other zones" zone by itself. Without a known address no shipping row is shown. A warning appears when shipping is switched off in WooCommerce.
* Payment step: no empty cell in the payment methods (WooCommerce's clearfix), and an opened payment box (extra choice, text, button) is a full-width panel under the row, so the cards stay two per row.
* Favorites page: smaller cards, six per row.

= 2.5.0 =
* New: shipping costs in the settings (new Shipping tab): a cost for your own country, a cost per country, a cost for all other countries, each free from an order amount. Shown in the cart and checkout totals with a "Free shipping from …: … to go" line. No WooCommerce shipping zones needed; local pickup stays available.
* Checkout: payment methods (Mollie and others) as cards like the category pages, two per row, logo on top; the chosen one is gold, and a method with its own fields spans the row. No "Do you have a gift card?" text in the Payment step.
* Favorites page: the category pages' cards, with a "View product" button.
* Product pages: a gold "Description" title above the short description.
* Account page: the menu items are buttons with a fine white border; the open tab is white.

= 2.4.0 =
* Patterns are one product for both ways of selling: with a size in the configurator (for the fabric amounts) or on their own with all sizes. New "Pattern price" and "Sale price" on the pattern's Atelier Irisee tab, copied to every size on save. The stock is the pattern's (Inventory tab, "Manage stock?"); sizes no longer keep their own stock, so every sale lowers the one stock. The price and stock fields of the sizes are read-only.
* Favorites page: smaller cards, six per row.
* Checkout: no empty "shipping fields" box. Discount code and gift card together in one block in the Payment step only (and in the cart); WooCommerce's coupon bar at the top is gone.
* Account page: the tabs are a gold side panel on the left, like the shop filters, with "Log out" at the bottom.

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
