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
3. **Fabrics and haberdashery** are *simple* products.
   - Fabric prices and stock are **per 10 cm**. Interfacing is per 10 cm; elastic, bias tape and ribbons & cords are per 10 cm or per roll (Sold per). A size that needs 85 cm adds 9 × 10 cm, or 1 roll of 1 m.
   - Zips have a length (Haberdashery details). The configurator only offers zips whose length matches what the size needs.
   - Buttons, zips, interfacing, elastic, ribbons & cords and bias tape get a **Haberdashery details** box, see [Haberdashery](#haberdashery).
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

## Haberdashery

Six kinds of haberdashery, described in one registry (`AIMP_Notions::kinds()`): buttons, zips, interfacing, elastic, ribbons & cords and bias tape. Each kind has its own category (Settings → Categories: `button_cat`, `zip_cat`, `interfacing_cat`, `elastic_cat`, `ribbon_cat`, `bias_cat`), Products → "All …" / "Add …", and the Atelier Irisee workspace.

**Haberdashery details** (`AIMP_Notion_Fields`), meta keys `AIMP_Notions::META_*`:
- `_aimp_hab_type`: the kind's types (buttons: button, jeans button, press stud; zips: the four zip types; ribbons & cords: ribbon, cord). Without a type a product counts as the kind's default type; a zip without a type fits every zip type.
- `_aimp_hab_colour`, `_aimp_hab_material`, `_aimp_hab_finish`, `_aimp_hab_mounting`: free texts.
- `_aimp_hab_diameter` (mm), `_aimp_hab_width` (mm; interfacing cm), `_aimp_hab_length` (cm: the zip length, or the length of one roll).
- `_aimp_hab_sold`: `piece` or `set` (buttons, zips; set size `_aimp_hab_set_size`), `10cm` or `roll` (the others; interfacing only `10cm`). `AIMP_Notions::pack_qty()` turns a need (pieces, or cm) into the cart quantity: whole sets, 10 cm units or whole rolls.
- `_aimp_hab_ideal_for`: up to four keys of `AIMP_Notions::ideal_for_options()` (icons: `AIMP_Product_Page::clothing_icon()`).
- `_aimp_order_info`: shown under the buy bar, as on fabrics.

3.7 copies the 3.6 button keys (`_aimp_btn_*`) and the zip length (`_aimp_zip_length`) once to these keys (`AIMP_Notions::migrate()`, option `aimp_db_version`); zips in the old zip type categories get that type.

**Patterns:** `_aimp_notion_rules` = `[ rule => [ on, min, max ] ]` for the rules of `AIMP_Notions::rules()` (button, jeans_button, press_stud, the four zip types, elastic, bias, ribbon, cord, interfacing). Off = no amount field on the sizes, amounts cleared, and `get_requirements()` sets the amount to 0. A rule that was never saved counts as on with any size (3.6 `_aimp_button_rules` is still read).

**Finish choice (3.57):** a rule can have `finish` = `a` or `b` (else always needed), and the rules hold `finish => [ a => name, b => name ]`. `AIMP_Notions::finish_options()` returns both options only when each has a switched-on rule. The configurator shows "Choose your finish" (`size.finish`), sends `finish`, and `AIMP_Cart::add_group()` requires it and sets `$req['finish']`, so `AIMP_Catalog::notion_count()` returns 0 for the other option. The chosen name is stored on the pattern line (`aimp_finish`) and in saved kits.

**Extra fabric for narrow fabrics (3.63):** the rules also hold `fabric_extra => [ [ wmin, wmax, from, to, cm ], … ]` (fabric width band in cm, size slugs of the size attribute, '' = no limit; a slug the pattern no longer has makes the row not count). `AIMP_Catalog::fabric_width()` reads the first number of the fabric's "Fabric width" text (`parse_width()`: "135 cm", "1,40 m", "1400 mm"; 0 = unknown, no extra). `get_requirements( $variation, $fabric )` / `with_fabric()` add the cm of the first matching row before the 10 cm units are counted, so the fabric list (`fabric_card()`: `fabric_units`, `need_text`), the summary and the cart line (`add_group()`) all use that fabric's own amount. `size_attribute()` gives the size order (also used to sort packs by design).

**Configurator:** one step per rule (`AIMP_Catalog::notion_types()`), in the kind's category, with `AIMP_Notions::meta_query()` (type and range); the cart checks `AIMP_Notions::fits()`. Zips also match the exact length. Cards carry `qty`, `qty_text`, `price_suffix` and the set/roll notice `set_note`.

**Shop filters:** `aimp_htype`, `aimp_hcol`, `aimp_hdia`, `aimp_hwid`, `aimp_hlen`, `aimp_hmat` in the address (`m_*` in the request), see `AIMP_Shop::notion_filters()`; 3.6 links (`aimp_b*`) still work.

**Kit steps bar:** `[atelier_irisee_kit_steps title="…" button="…" url="…"]` (`AIMP_Kit_Steps`): the four steps of the customizer on a gold thread, for the home page.

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
- **Shipping = PostNL for WooCommerce** (`includes/class-aimp-postnl.php`, `AIMP_PostNL`): there is no shipping method of our own anymore (3.3). Costs live in WooCommerce shipping zones (one per country) with PostNL's method (`postnl`, a flat rate with `minimum_for_free_shipping`, option `woocommerce_postnl_{instance}_settings`).
  - **Migration** (once, `aimp_postnl_migrated`): the old option `aimp_shipping` becomes zones (base country, listed countries, zone 0), costs converted to ex-VAT when prices include VAT; old `aimp_shipping` method instances are removed.
  - **Shipping and delivery tab:** `aimp_postnl_rates` (sanitize writes the zone instances, stores nothing). **PostNL tab:** section `aimp_postnl` prints PostNL's form (`generate_settings_html()`) and the Fill-in fields (`woocommerce_get_settings_shipping`, section `fill-in-with-postnl`); `aimp_postnl_proxy`'s sanitize calls PostNL's `process_admin_options()` and `WC_Admin_Settings::save_fields()`. PostNL's settings pages redirect to `#tab-aimp_postnl`.
  - **Front:** `amounts_for()` (delivery line), `free_hint()` under `postnl` rates, `pickup_point()` (thank-you, My orders), `tracking_url()` (fallback in `AIMP_Tracking::url()`).
  - **Checkout (3.3.1):** PostNL skips its classic script, house-number field and pickup fee when `has_block( 'woocommerce/checkout', checkout page )`. `checkout_script()` enqueues `fe-checkout.js` with `postnlParams` when PostNL didn't; `checkout.js` handles the tabs and points itself when `postnlParams` is missing; `checkout_block_notice()` / `fix_checkout_page()` remove a leftover Checkout block (parse_blocks / serialize_blocks, revision kept).
  - **Pickup list (3.3.2):** PostNL 5.9.12's V4 pickup locations have no `PartnerID`/`PickupTime`, so `FrontendDropoff_Points::get_content_data()` drops them all. `swap_pickup_renderer()` (init 20) replaces its `postnl_checkout_content` callback with `render_pickup()`, which builds the list from the V4 locations and prints PostNL's template; with complete locations it calls PostNL's own `display_content()`.
- **Patterns with designs (3.4):** the variation attribute whose name contains design / ontwerp / model(e) (`AIMP_Catalog::design_attribute()`, filter `aimp_pattern_design_attribute`) splits a pack into designs. Per-design settings live in `_aimp_designs` (slug => image_ids, fabric_cats, height, project_time). Configurator cards of a design use the design's first size (a variation ID) as their id; `get_sizes()` accepts a pattern or a size and returns one design's sizes and pictures. `size_label()` leaves the design out; `kit_label()` / `kit_image_id()` name and picture kits.
- **Materials per size (3.5):** `AIMP_Catalog::material_fields()` (key => meta, label, unit, group) drives the variation box, `get_requirements()['materials']`, the configurator `extras` and the pattern page. `notion_types()` now has the zip types, button types, bias and ribbon, each with `role`, `setting` (category), `fallback`, `kind` (zip|count|length) and `material`; `configurator.js` builds its steps from `aimpConfig.notions` and each size's `notions[type]`. Fabric is stored in cm (`_aimp_fabric_cm`, fallback `_aimp_fabric_units` × 10).
- **2.7 classes:** `AIMP_Search` (wc-ajax aimp_search), `AIMP_Stock_Alerts` (post type aimp_stock_alert, sent on woocommerce_product_set_stock_status), `AIMP_Saved_Kits` (user meta _aimp_saved_kits, wc-ajax aimp_save_kit / aimp_load_kit / aimp_delete_kit, configurator ?aimp_kit=), `AIMP_Orders` (kit items hidden with woocommerce_order_item_visible and shown as one row through woocommerce_order_details_after_order_table_items and woocommerce_email_order_items_table), `AIMP_Emails` (WooCommerce email colours and styles). Newsletter subscribers have _aimp_status and _aimp_token; the aimp_nl cookie lets the footer show the state.
- **Checkout codes:** `AIMP_Checkout::codes_open/close` wrap the gift card box (`woocommerce_review_order_before_payment`) with a discount code field; `checkout.js` passes the code to WooCommerce's hidden `form.checkout_coupon`.
- **3.1 trust and checkout** (`includes/class-aimp-trust.php`):
  - `delivery_items()` / `delivery_html()` builds the delivery line from the settings `delivery_time` (text, `NL | FR | EN`, read with `AIMP_Settings::localized()`), `return_days` and the Shipping tab's `home_free`. It is printed under the buy bar and, with `cart_html()`, under the cart totals.
  - `expected_date()` adds `delivery_days` working days to the order date, skipping weekends and Belgian holidays (Easter Monday, Ascension and Whit Monday calculated). It appears on the thank-you page and in the processing / on-hold emails (`woocommerce_email_order_details` 5).
  - `woocommerce_checkout_fields` (20) removes the company fields unless `checkout_company` is on, and marks address line 2 and the order note `aimp-behind-link`. `checkout.js` hides those behind a "+ Add …" button and shows messages under invalid fields.
  - Low stock: `AIMP_Product_Page::buy_data()` returns `low` when the stock is at or under `low_stock`.
- **Search** (`AIMP_Search`): the response has `categories` (`name__like`, linked with `AIMP_Shop::category_url()`) and, when nothing matches, `suggest` (Levenshtein against the cached product names and their words). `search.js` keeps the last five searches in `localStorage` (`aimp_recent_searches`).
- **Newsletter source** (`AIMP_Newsletter`): `_aimp_source` (`editor` or `page`), `_aimp_page` and `_aimp_preheader`. The web version of a written newsletter is `?aimp_nl_view=ID&key=…`; a page newsletter links to the page.
- **Products menu** (`AIMP_Product_Workspace::menu()`): All fabrics / All patterns (`edit.php?post_type=product&product_cat=<root slug>`) and Add a fabric / Add a pattern (`post-new.php?post_type=product&aimp_new=fabric|pattern`; `save_post_product` sets the category and type on the auto-draft). `submenu_file` highlights the right item.
- **Pattern-wide fields (3.2):** `_aimp_fabric_cats`, `_aimp_height` and `_aimp_project_time` live on the pattern. `AIMP_Catalog::pattern_fabric_cats()` and `pattern_height()` fall back to the old per-size values while the pattern has none.
- **Multi-select categories:** the shop (`aimp_cat=12,15`), the configurator fabric filter and the configurator pattern buttons send a comma list; `AIMP_Ajax::int_list_param()` reads it, and the queries keep only IDs inside the allowed tree (any of them).
- **PostNL checkout:** PostNL's block (`woocommerce_review_order_after_shipping`) is styled in `checkout.css`. For NL addresses PostNL adds `*_house_number` and uses address_2 as the extension: `checkout.js` then doesn't hide address_2 and splits "Street 12A"; `address.js` fills the three fields.
- **Low stock per category:** option key `low_stock_cats` (term ID => number), read with `AIMP_Settings::low_stock_categories()`; `AIMP_Trust::low_stock_threshold()` takes the nearest category with a number, else `low_stock`.
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
