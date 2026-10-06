# Atelier Irisee Master Plugin

GUIDE WEBPAGE: https://claude.ai/artifact/HeQ9WZ2QD4wY8RbT1PTz7h

A WooCommerce pattern configurator for Atelier Irisee. Customers pick a pattern and size, then a fabric, then buttons and zips, and add the whole set to the cart as one linked set.

## Releasing an update

Every live site checks this repository's **latest GitHub release**. To ship an update:

1. Make your changes in VS Code.
2. Raise the version number in **three places**. They must match, or the release check fails.
   - `atelier-irisee-master-plugin.php`: the `Version:` header line
   - `atelier-irisee-master-plugin.php`: `define( 'AIMP_VERSION', '…' )`
   - `readme.txt`: the `Stable tag:` line, plus a new entry under `== Changelog ==`
3. Commit and push to `main`.

The **Release** GitHub Action ([.github/workflows/release.yml](.github/workflows/release.yml)) then:

- lints every PHP file (on PHP 7.4 and 8.3),
- creates the release `vX.Y.Z` with `atelier-irisee-master-plugin.zip` attached.

Sites show **"Update available"** on their Plugins page within a few hours. You can make it immediate by clicking **Dashboard → Updates → Check again**. Sites that turned on **Enable auto-updates** for this plugin install the update by themselves.

A push that doesn't change the version never reaches live sites. The Action sees that the release already exists and skips it.

Use version numbers like `1.0.1`, `1.1.0`, `2.0.0`. WordPress compares them with `version_compare`, so `1.10.0` counts as newer than `1.9.0`.

## First install on a site

1. Download **`atelier-irisee-master-plugin.zip`** from the [latest release](https://github.com/apposapp/Atelier-irisee/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, then upload and activate it.

Don't use GitHub's green **Code → Download ZIP** button. That zip has the wrong folder name, so automatic updates would not line up with the installed copy.

## Setup in WooCommerce

1. Go to **WooCommerce → Atelier Irisee** and choose the categories:
   - **Patterns**: the parent category of all pattern products. Its subcategories become the filter tabs.
   - **Fabrics**: the parent category of all fabrics. Its subcategories (e.g. Cotton, Linen) are the "fabric categories" you allow per size.
   - **Buttons**
   - **Zips**
   - **Ribbons**
   - **Bias tape**
2. **Patterns** are *variable* products with one variation per size. Each variation has an *Atelier Irisee* box with these fields:
   - fabric needed (units of 10 cm)
   - allowed fabric categories
   - button count
   - zip count
   - zip length (cm)
   - ribbon length (cm) and bias tape length (cm)
   - bust, waist, hip, inside leg and height (cm). Only the ones you fill in are shown to customers.

   The pattern's **Atelier Irisee** tab (in Product data) lets you choose which fabric categories customers see first. It lists the fabric categories ticked under "Fabric categories allowed" on the sizes. Give a category position 1, 2, 3… and those fabrics are listed first under the "Recommended" sort.

   All pictures of the pattern are shown in the configurator: the main image, the product gallery, and the variation images. When a customer picks a size that has its own picture, that picture is shown.
3. **Fabrics, buttons, zips, ribbons and bias tape** are *simple* products.
   - Fabric, ribbon and bias tape prices and stock are **per 10 cm**. A size that needs 85 cm of bias tape adds 9 × 10 cm to the cart, because lengths are rounded up.
   - Zips have a **Zip length (cm)** field in the General tab. The configurator only offers zips whose length matches what the size needs.
4. Put the shortcode `[atelier_irisee_configurator]` on a page.

## Favorites

Customers can save any product as a favorite with the heart button. The heart appears:

- on shop, category and search grids, including product blocks,
- on product pages,
- in the configurator details.

Logged-in customers' favorites are saved in their account. Guests' favorites are kept in their browser and moved into their account when they log in.

To set up the favorites page:

1. Create a page, e.g. "My favorites", with the shortcode `[atelier_irisee_favorites]`.
2. Choose that page under WooCommerce → Atelier Irisee → **Favorites page**. Customers then get a link to it after adding a favorite.

## Login & registration

Settings are under **WooCommerce → Login & registration**. The full step-by-step setup is in the separate setup guide.

- **Shortcodes**
  - `[aimp_login_popup type="login|register|lostpw" display="link|button" text="…" change_to="logout|myaccount|none" redirect_to="same|<url>"]`
  - `[aimp_login_form active="login|register" login_redirect="…" register_redirect="…"]`
  - `[aimp_profile]`
- **Trigger classes:** `aimp-login-tgr`, `aimp-reg-tgr` and `aimp-lostpw-tgr`, on any link, button or menu item.
- **Menu items:** in Appearance → Menus, use the box "Atelier Irisee login". It offers Log in, Register, Log in / Log out, and Log in / My account.
- **Redirect parameter:** `?aimp_redirect=<url>` on any page decides where the visitor goes after logging in.
- **Social login callback URLs:** `https://your-site/aimp-social/{google|facebook|apple|linkedin|microsoft|line|x}/`. They are also shown in the settings.
- **Templates:** copy `templates/forms/*.php` to `yourtheme/atelier-irisee/forms/` to change the markup.
- **Hooks**
  - Actions: `aimp_el_before_form`, `aimp_el_after_form`, `aimp_el_register_user`, `aimp_el_login_success`.
  - Filters: `aimp_el_redirect`, `aimp_el_fields`.

## Account page

Put `[atelier_irisee_account]` on a page, for example "My account". Logged-out visitors see the login and registration forms. Logged-in customers get four tabs (`?tab=personal|orders|refund|giftcards`):

- **Personal data:** the profile form and the saved billing and shipping addresses.
- **Orders:** WooCommerce's own order list and order view.
- **Refund or remove account:**
  - Refund requests are possible up to N days after an order (setting in WooCommerce → Atelier Irisee).
  - You approve or reject a request in the "Atelier Irisee refund request" box on the order. Approving refunds through the payment method when it supports refunds.
  - Customers can remove their account. They confirm with their password, and orders stay as guest orders.
- **Gift cards:** the cards the customer bought or received, with a copy button, "Use in my cart" and a balance check.

## Gift cards

**Admin:** WooCommerce → Gift cards (the list), Add gift card (manual cards) and Gift card settings.

**Settings:**
- designs;
- preset amounts, plus a custom amount with min and max;
- validity;
- delivery options and the printing fee;
- the code prefix;
- the **Create gift card product** button.

**How it works:**
- **The product:** a simple product with "This is a gift card" ticked. Customers choose the amount, a design and the delivery: email to me, email to someone else (optionally on a date), or by post.
- **Codes:** created when the order is processing or completed. Email cards are sent by WP-Cron. For post cards you get an email with a print link.
- **Paying with a card:** the gift card field in the cart and at checkout lowers the order total after VAT, like a payment. The order gets a VAT-free negative line per card. The balance is deducted when the order is created and comes back when it is cancelled, fails or is refunded.
- **Not allowed:** gift cards cannot be used to buy other gift cards.

## Shop pages

`[atelier_irisee_shop type="all|patterns|fabrics|haberdashery"]` shows a filter sidebar, the product grid and a details panel.

- **Filter sidebar:** opens and closes with the Filters button, and the choice is remembered per browser. On phones it is a drawer.
- **Filters:** search, sort, category, price, every product attribute used on that page (automatically), and in stock.
- **Address bar:** the filters are kept as `aimp_*` parameters, so a filtered page can be shared.
- **Products per page:** a setting in WooCommerce → Atelier Irisee.
- **Speed:** the filter options are cached and refreshed automatically when a product or category is saved.
- **Category routing:** in WooCommerce → Atelier Irisee → Shop pages, choose the All products, Patterns, Fabrics and Haberdashery pages.
  - Product category links (`term_link`) then point to the right page with `?aimp_cat=ID`.
  - The shop link points to the All products page.
  - `/product-category/…` and the shop page redirect there, unless the "Category links" setting is switched off.

## Product pages

With **Use the Atelier Irisee design on product pages** on (WooCommerce → Atelier Irisee), WooCommerce's product pages use the plugin's design:

- **Classic themes:** through the `content-single-product` template part.
- **Block themes:** through the Single Product template, which keeps the theme's header and footer.

`[atelier_irisee_product id="123"]` shows one product on any page. To change the markup, copy `templates/product/single.php` to `yourtheme/atelier-irisee/product/single.php`.

The **Complete it in the configurator** button on patterns uses the **Configurator page** setting and opens `?aimp_pattern=ID`.

**Fabric texts:** fabric products get a "Fabric texts" box on the edit screen. It holds:
- **Inspiration** and **Order information**, two free texts;
- **Specifications:** composition, type, colour, width and weight;
- **Washing instructions:** washing, drying, ironing, and optional tips.

Each subject is stored in its own meta field (`_aimp_inspiration`, `_aimp_order_info`, `_aimp_spec_*`, `_aimp_wash_*`, see `AIMP_Catalog::fabric_text_fields()`).

**Buy bar:** simple products and patterns get the plugin's own buy bar (amount with ‹ › arrows, add to cart, live total price). Gift cards and other product types keep WooCommerce's form.
- **Products sold per 10 cm:** the amount is sent in cm as `aimp_length_cm` and turned into units of 10 cm before WooCommerce adds it to the cart.
- **Patterns:** they post `aimp_add_pattern`. The first available size is added with the cart item flag `aimp_all_sizes`, and the cart and order show "Sizes: All sizes" instead of the size.

**Styling:** the product and shop pages share one style block at the end of `configurator.css`, so WooCommerce and theme styles can't change them.

## Header, site look and editor

- **Header** (`includes/class-aimp-header.php`, `templates/header.php`):
  - Switched on with "Use the Atelier Irisee header".
  - Block themes: the `core/template-part` block whose slug, tag or area contains "header" is replaced (`render_block`).
  - Classic themes: the header is printed at `wp_body_open`, and common theme headers are hidden through the body class `aimp-replace-theme-header`.
  - The side menu uses the menu location `aimp_side_menu`. The cart badge counts cart lines and refreshes through `woocommerce_add_to_cart_fragments`.
- **Site look** (`includes/class-aimp-site.php`):
  - Trajan Pro `@font-face` from the font files uploaded in the settings. Font uploads are allowed for shop managers.
  - 15px body text.
  - Notices (`assets/js/notices.js`): a × button; success and info messages close after 4 s.
- **Editor** (`includes/class-aimp-editor.php`): an "Empty line" TinyMCE and Quicktags button that inserts `<p class="aimp-space">&nbsp;</p>`.
- **Overview:** `[atelier_irisee_shop type="all"]` shows five section cards unless the address has `aimp_*` filters.
- **Pattern skill level:** stored as `_aimp_skill`. It's a filter on the shop pages (`aimp_skill`).

## Footer, cart page and mosaic

- **Footer** (`includes/class-aimp-footer.php`, `templates/footer.php`):
  - Settings in their own option `aimp_footer` (same settings page).
  - Menu locations `aimp_footer_account`, `aimp_footer_service` and `aimp_footer_legal`.
  - Classic themes (Divi): printed at `wp_footer` (priority 1); `#main-footer` / `.et-l--footer` are hidden.
  - Block themes: the footer template part is replaced.
  - Newsletter: `?wc-ajax=aimp_subscribe` stores private `aimp_subscriber` posts. WooCommerce → Newsletter lists them and offers a CSV download.
- **Cart page** (`includes/class-aimp-cart-page.php`, `templates/cart.php`): `[atelier_irisee_cart]`.
  - Sets are grouped by the `aimp` cart item data. Updating, coupons and removing go through WooCommerce's form handler (`aimp_cart_cm[key]` is turned into units on `wp_loaded` 19).
  - The totals are `woocommerce_cart_totals()`, which also brings the gift card field.
  - The "Cart page" setting writes `woocommerce_cart_page_id`.
- **Mosaic** (`includes/class-aimp-mosaic.php`): `[atelier_irisee_mosaic type="…"]`. Option `aimp_mosaic`: source `bestsellers` (by `total_sales`, ties random, cached 1 h) or `manual` (product pickers).

## Discounts and checkout

- **Sewing kit discount** (`AIMP_Cart::apply_kit_discount`): set items get the "kit_discount" percentage in `woocommerce_before_calculate_totals`, calculated from a fresh copy of the product.
- **Coupons** (`includes/class-aimp-coupons.php`):
  - every coupon is individual use;
  - `woocommerce_coupon_is_valid_for_product` skips products on sale (except kit items) and gift cards.
- **Welcome code:** created on the first login after registering (`_aimp_welcome_pending`), as a personal WooCommerce coupon. It is shown once in a popup and listed in the account's gift card tab. `?aimp_coupon=CODE` applies a code to the cart.
- **Checkout** (`includes/class-aimp-checkout.php`, `assets/js/checkout.js`): `[atelier_irisee_checkout]` prints WooCommerce's checkout. The JS adds `is-stepped` and `data-step`, and `checkout.css` shows the parts of one step.
  - "Your order" (`AIMP_Cart_Page::summary_html()`, read-only, kits grouped like the cart) sits next to the steps; WooCommerce's review table then only shows the totals.
  - The Overview step (key `delivery`) gets a delivery address box built by `checkout.js` from the billing or shipping fields.
- **Patterns** (`AIMP_Product_Fields::sync_pattern`): the pattern price (`_aimp_pattern_price`, `_aimp_pattern_sale_price`) is written to every size, and sizes get `manage_stock = false`, so WooCommerce uses the pattern's stock ("managed by parent") for configurator sales and "All sizes" sales alike. Runs on `woocommerce_update_product` and `woocommerce_ajax_save_product_variations`.
- **Shipping** (`includes/class-aimp-shipping.php`, option `aimp_shipping`): `woocommerce_package_rates` (priority 50) replaces the rates with one `aimp_shipping` rate (local pickup kept). Amounts include VAT when prices do (`WC_Tax::calc_inclusive_tax`). `woocommerce_shipping_cost_requires_address` is forced to "no" while on.
- **Checkout codes:** `AIMP_Checkout::codes_open/close` wrap the gift card box (`woocommerce_review_order_before_payment`) with a discount code field; `checkout.js` passes the code to WooCommerce's hidden `form.checkout_coupon`.
- **Page caches** (`AIMP_Product_Page::purge_caches`): after a product or variation is saved, WooCommerce's product transients are cleared and common caching plugins are asked to refresh that page (action `aimp_purge_product_pages` for others).

## Languages

The plugin speaks **Dutch**, **French** and **English**, independent of the WordPress site language.

- **Admin**: the settings page and product fields use the **Plugin language** setting under WooCommerce → Atelier Irisee. The default is Dutch.
- **Customers**: the configurator starts in that same language. Customers can switch at any time with the flags in the top right: 🇧🇪 Nederlands / 🇫🇷 Français / 🇬🇧 English.
  - Their choice is remembered in a cookie (`aimp_lang`).
  - The cart and checkout texts from this plugin use the same language.
- Product names, descriptions and category names are your own shop content, so they are shown as you wrote them.

### Adding or changing a text

All strings in the code are written in English with `__( '…', 'atelier-irisee-master-plugin' )`. The translations live in:

- [includes/languages/nl.php](includes/languages/nl.php) (Dutch)
- [includes/languages/fr.php](includes/languages/fr.php) (French)

Each line has the form `'English text' => 'translation'`. When you add a new string to the code, add a line for it in both files. Any string without a translation is shown in English.

To add another language:

1. Add it to `AIMP_I18n::languages()` in [includes/class-aimp-i18n.php](includes/class-aimp-i18n.php).
2. Create `includes/languages/{code}.php`.
3. Add a flag SVG in `assets/images/flags/`.

## How the cart behaves

- Stock is checked for every item, including what is already in the cart, **before** anything is added. If one item fails, nothing is added.
- Quantities are locked to the size in both the classic cart and the Cart block.
- The items of a set are linked:
  - removing any item removes the whole set,
  - "Undo" brings the whole set back.
- Order line items store the set ID and role as order item meta. This is compatible with HPOS.

## Structure

| Path | Purpose |
|---|---|
| `atelier-irisee-master-plugin.php` | Header, constants, declares HPOS and blocks compatibility |
| `includes/class-aimp-catalog.php` | All eligibility rules: allowed fabrics, zip length, stock |
| `includes/class-aimp-cart.php` | Adding a set, locking quantities, linking items, order meta |
| `includes/class-aimp-ajax.php` | `?wc-ajax=aimp_*` endpoints used by the configurator |
| `includes/class-aimp-product-fields.php` | Admin fields on variations and zip products |
| `includes/class-aimp-settings.php` | WooCommerce → Atelier Irisee settings page |
| `includes/class-aimp-shortcode.php` | Shortcode and front-end assets |
| `includes/class-aimp-updater.php` | Checks GitHub releases for updates |
| `includes/class-aimp-i18n.php` | Picks the active language (Dutch/English) and translates the plugin's texts |
| `includes/languages/nl.php`, `fr.php` | Dutch and French translations |
| `includes/class-aimp-shop.php` | Shop page shortcode, the `aimp_shop` endpoint and its cached filter options |
| `includes/class-aimp-fabric-fields.php` | "Fabric texts" box on fabric products |
| `includes/class-aimp-product-page.php` | Product page design, the product shortcode and the block template swap |
| `templates/product/` | Product page markup |
| `assets/js/ui.js` | Shared front-end pieces: cards, gallery, pagination, lightbox, language flags |
| `includes/class-aimp-account.php` | Account page shortcode, refund requests, account removal |
| `includes/giftcards/` | Gift cards: storage and emails, product form, redeeming, admin screens |
| `templates/giftcards/` | Gift card product form and the card used in emails and print |
| `assets/js/configurator.js` | Configurator UI (vanilla JS, no build step) |
