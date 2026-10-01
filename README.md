# Atelier Irisee Master Plugin

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

1. Go to **WooCommerce → Atelier Irisee** and choose four categories:
   - **Patterns**: the parent category of all pattern products. Its subcategories become the filter tabs.
   - **Fabrics**: the parent category of all fabrics. Its subcategories (e.g. Cotton, Linen) are the "fabric categories" you allow per size.
   - **Buttons**
   - **Zips**
2. **Patterns** are *variable* products with one variation per size. Each variation has an *Atelier Irisee* box with these fields:
   - fabric needed (units of 10 cm)
   - allowed fabric categories
   - button count
   - zip count
   - zip length (cm)
   - bust, waist and height (cm)

   The pattern's **Atelier Irisee** tab (in Product data) lets you choose which fabric categories customers see first: give a category position 1, 2, 3… and those fabrics are listed first under the "Recommended" sort.

   All pictures of the pattern are shown in the configurator: the main image, the product gallery, and the variation images. When a customer picks a size that has its own picture, that picture is shown.
3. **Fabrics, buttons and zips** are *simple* products.
   - A fabric's price and stock are **per 10 cm**.
   - Zips have a **Zip length (cm)** field in the General tab. The configurator only offers zips whose length matches what the size needs.
4. Put the shortcode `[atelier_irisee_configurator]` on a page.

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
| `assets/js/configurator.js` | Configurator UI (vanilla JS, no build step) |
