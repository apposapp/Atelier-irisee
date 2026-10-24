=== Atelier Irisee Master Plugin ===
Contributors: atelieririsee
Tags: woocommerce, configurator, sewing, patterns, fabric
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.44.2
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

= 3.44.2 =
* Design studio: something clicked in a page header (a <header> of its own page) is now kept to that page, like everything else on a page. Before, a picture chosen there for the header was used in the page header of every page.

= 3.44.1 =
* Newsletter pop-up: repaired. The hidden spam-trap field no longer shows as a second box, and the field and button have the shop's look (they only had it inside the footer).
* Never two pop-ups at once: the login/registration form that opens by itself waits until the newsletter pop-up is closed.
* Page header: placed automatically it no longer makes the page a few pixels too wide (the customizer could scroll sideways); the full-width blocks measure the scrollbar again when the page grows.
* Newsletter block: the footer newsletter's settings in the Design studio no longer move its form.
* "Page not found": one main heading.

= 3.44.0 =
* Design studio: every part of a page gets a height (Design → Size), on computers, tablets and phones; empty = its own height. E.g. a lower page header.
* Page header: never a margin under it any more.

= 3.43.0 =
* Page header on every page: automatically at the top of all pages (Divi), product pages (title: the product's name), the cart, checkout and my account, shop categories, blog and search results; never on the home page and pages with the hero. No shortcode needed (it still works, without a second header).
  * Design studio → Page header: a block per page (with "No page header on this page"), one for all product pages and one for the other pages; the panel shows the block of the page in the preview.
  * Where: automatically or only where the shortcode is; not on product pages, the cart/checkout/account or the other pages (each can be switched off); "Hide the header where no picture or video is chosen" (in the studio's preview it stays as a slim dashed band, to click).
  * On product pages and pages with a title of their own (About, Contact) the header's title is not a second main heading.

= 3.42.0 =
* Page header: the shortcode [atelier_irisee_page_header], a full-width band at the top of a page. Per page in the Design studio (Page header): a picture with the title over it, or a picture with the title already in it (shown whole; the title stays on the page for search engines), a phone picture, a video instead of the picture (MP4 without sound, the picture shows until it plays; not with "reduced motion" or the data saver), a phone video, the focal point, another title and a line under it. The look of all headers: height (also on phones), tint, place, colour and size of the title, a breadcrumb. Without a picture: a gold band with the title.
* Newsletter block (home page): repaired. The form stays in one row (the footer's newsletter style no longer reaches it), the label for screen readers is hidden, the form no longer shows next to "you are subscribed", and the block hides itself for subscribers (can be switched off).

= 3.41.0 =
* Design studio:
  * Layers: the parts of the page in the list as a tree, in page order and inside each other (fold open and shut); pointing at a layer, a "Change instead" button or the breadcrumb outlines that part in the preview.
  * Above the settings, staying in view: back to the previous part, the breadcrumb, the name, the tabs, "Find a setting…" (the matching settings of every tab) and "Only what I changed". The tab and block that were open are remembered per part.
  * A ↺ "Back to standard" on every setting and every text.
  * "Translate to the empty languages" (🌐) next to every text in NL/FR/EN: fills the empty languages from the one typed, with the Google Gemini or OpenAI key of the AI pictures.
  * Picture position: move the pictures of a part left/right and up/down inside their frame, and zoom in or out (computer and phone); also for one clicked picture.
* About page: the dotted line around the pictures is gone (it can be switched on: Pictures of the chapters).
* Brands block: a picture per brand in the studio (before the brand's own picture); no list bullets in the home page blocks any more.
* Meet the maker: a focal point for the small round picture (the mascot).
* Hero arrow: a calm slide down, its time to set in the studio (standard 1.2 s).

= 3.40.0 =
* About page: the shortcode [atelier_irisee_about], full width. A head, the story in up to four chapters (picture and text in turns left and right; an arch, round or rounded picture with its focal point; an optional button per chapter, e.g. to the dog bandanas), four tiles of what the shop stands for, and a closing with the quote, the signature (with a small round picture of Klaus) and buttons to the shop and the customizer. The standard texts are the story of the current About page (NL as written, translated to FR and EN).
* Design studio: the About page and its parts (head, chapters, tiles, closing), with every text in NL/FR/EN and the pictures per chapter.

= 3.39.1 =
* Home page blocks: they use the full width of the screen, also inside a Divi row (like the hero). "Meet the maker" without a picture keeps its text in a readable column. A background picture of the newsletter block fills its gold frame.

= 3.39.0 =
* Contact page: the shortcode [atelier_irisee_contact]. Contact cards (email with a copy button, phone with a WhatsApp link, the address with "Plan your route", the answer time, social media; from the footer settings), a contact form sent in the background (name, email, subject, order number — a list of their orders for logged-in customers —, message, privacy consent; spam stopped by a hidden field, a time check and a limit per visitor, no cookies), an email to the shop with Reply-To the customer and a copy to the customer, and the questions and answers below.
* Home page blocks, each a shortcode:
  * [atelier_irisee_categories]: shop by category, the sewing project kits as the large tile (the overview cards of the All products page).
  * [atelier_irisee_reviews]: product reviews (from 4 stars) and your own testimonials in a slider, with the average score; hidden while there is nothing to show.
  * [atelier_irisee_maker]: meet the maker, with a picture, a small round picture (the mascot), the story and a button.
  * [atelier_irisee_new]: the newest products in a row, with "View all".
  * [atelier_irisee_newsletter]: the newsletter sign-up, with the welcome discount for new accounts next to it.
  * [atelier_irisee_faq]: the first questions and answers, with a button to the contact page.
  * [atelier_irisee_brands]: the brands with products (logo or name), each linking to its products.
* Design studio: the contact page and every block are parts with their own settings, and their texts (NL/FR/EN) in their Texts block: lists of questions and answers and of testimonials can be added to and removed there.
* Shop: a brand filter (aimp_brand); brand links (the brand tiles, the brand on a product page) lead to the All products page with that brand's products.

= 3.38.0 =
* Lining: a fabric can also be used as lining (Fabric data → Specifications: "This fabric can also be used as lining"). It is the same product: the same price and the same stock.
  * Shop: on the fabrics page a "Lining" filter at the top of the categories, and "Lining" in the side menu under the fabrics. The product page says "Can also be used as lining".
  * Patterns: a lining amount per size (cm), next to the fabric. The size chart on the pattern page gets a Lining column.
  * Customizer: when the chosen size needs lining, a step "Lining" after the fabric, with every lining fabric (filters, search, stock), optional ("Continue without lining"). The lining is added to the cart as its own line of the set (used as: lining), with the kit discount; fabric and lining of the same product are checked together against its stock. Saved kits keep the lining.
  * Design studio: a line at the top of the lining step (Customizer → Steps).

= 3.37.1 =
* Haberdashery: the diameter (and the width of ribbons, elastic, bias tape) can have one decimal, e.g. 12,8 mm (the field took steps of 0.5 mm). The same for the diameter range a pattern asks for.

= 3.37.0 =
* Design studio: spacing like Divi 4. The block "Spacing" (Design tab) has the margin (outside) and the padding (inside) of a part, each with a field per side, "the same on all sides" and "Standard". Empty = standard; 0 is a value of its own.
  * Tablets have their own spacing: the Tablet button at the top shows it (empty = as on computers, shown in grey).
  * In the preview, the chosen part shows its margin (orange) and padding (green) as bands, with a handle on each side: drag a handle to change that side, with the value shown while dragging. One Undo step per drag.
* Fix: the names under the mouse, the "where it was / how far it moved" overlay and the new spacing bands were hidden in some browsers (e.g. by an extension); they are now shown everywhere.

= 3.36.1 =
* Design studio: the focal-point picture of the hero (Pictures, video and slides) shows the hero photo instead of the round seal on it.

= 3.36.0 =
* Design studio: changes show without the page refreshing.
  * Only settings that change what the server builds (pictures, video, buttons, texts, …) still load the page again, and then without a white flash and at the same place: the new page is made behind the old one and swapped in.
  * Everything else shows at once: the focal point of the hero picture, colours, badge colours, card options, hidden parts, star colour, cards per row, kit steps thread and many more.
* Moving something: the preview shows where the part was (dashed), where it is now, an arrow and the distance ("→ 24 px  ↓ 10 px"); size and space get a label too, the focal point a cross.
  * Drag a chosen part in the preview to move it, or use the arrow keys (Shift: 10 px). One Undo step per drag.
  * Focal point: click or drag on the picture in the panel.
* Fix: changes for computers only (position, size, space) did not show in the preview until the page was loaded again.
* Fix: the Product page part showed the settings of the customizer, checkout, account, footer and more; it shows its own four blocks again. Those other settings now load the preview again when they need to.

= 3.35.0 =
* Design studio: the texts of the Atelier Irisee settings can be changed in the studio, with their part, on the Content tab ("Texts from the settings"):
  * the gold bar under the header (each text in Dutch, French and English; texts can be added and removed), the delivery time (buy bar, cart), the hero's tagline and text, every text of the kit steps bar, and the footer's company name, address, phone, email, newsletter text and copyright line.
  * They are part of the draft (only the preview shows them) and Publish saves them in the settings; Undo works for them too.
* More room: the WordPress menu is hidden in the studio (the button "WordPress menu" brings it back), the panel is wider, and the computer preview uses the full width of the screen.
* Nothing in the panel is too wide any more (e.g. the hero's pictures, video and slides); long button texts wrap. Fix: the first slider of Position, Space and Title sizes was squeezed out of view.

= 3.34.0 =
* Design studio: click and change (almost) everything.
  * Any element of a page can be clicked, also the page's own content (Divi sections, titles, paragraphs, pictures): it becomes one of "Your own picks", with text style, colours, picture, size, space, corners, position, show or hide and animations. Header and footer elements count on every page, the rest on that page.
  * "Or change:" under the name shows the clicked element and the ones around it (e.g. Section › Block › Paragraph): one click switches.
  * The preview names what is under the mouse.
  * New for every part: Text style (size, thickness, alignment, capitals), on the Design tab.
  * Undo and Redo (also Ctrl+Z / Ctrl+Shift+Z), for settings and texts.
  * Words from the page's own content: next to "Edit this in WordPress" also "Open this page in the Divi builder".
  * The page list has every published page (e.g. the start page) and the latest blog posts, in groups; a page reached by browsing in the preview shows as "This page".
  * After a change, the open block and tab stay as they are.
* Fix: clicking a customizer text in the preview could open the English text list instead of the page language.

= 3.33.0 =
* Design studio, organised like Divi:
  * Click a text in the preview to change only that text: its words in Dutch, French and English, nothing else. Words that come from your own content (a product name, a category, a page) say so, with a button to edit that product or page in WordPress.
  * A chosen part has a coloured head with its name and three tabs: Content (the clicked text and the part's own settings), Design (colours, picture, size, space, corners) and Advanced (position, show or hide, animations). Each tab shows its blocks folded, the first one open.
  * The note "computer or phone settings" only shows when the open tab has such settings.
  * Texts tab: at first only the texts of the page in the preview (search to find any other text).

= 3.32.0 =
* Design studio, easier to work with:
  * The list shows the parts of the page in the preview first; "Whole site" and "Parts of other pages" are folded away.
  * A chosen part shows only its own settings, as blocks that fold open one at a time (its own settings first, the general ones under "Fine-tuning"), with a dot on blocks you changed.
  * Only the settings of the screen chosen at the top (Computer/Tablet or Phone); a line says which.
  * A wider panel, no WordPress notices above the studio, opening a block keeps the top bar in view.
  * The page menu has Checkout and Favourites, and "My account" opens the account page (was the home page).
* Fixes: an empty box above the products on shop pages (the category banner without a category); the shop dropped the preview in the studio after a filter or "Show more"; with "Show more" a reload no longer starts at the last page; product page tabs and dropdowns got clean names and the size chart is a tab too.

= 3.31.0 =
* Own haberdashery types (Atelier Irisee → Settings → Haberdashery options): up to 8 new types within buttons, zips, ribbons & cords or interfacing, named in Dutch | French | English and sold like their kind, per piece or per length. They can be chosen in the product editor, patterns ask for them in their Haberdashery block with an amount per size, and the customizer gets a step for each.
* Design studio, phase 10, new part "SEO, speed and dashboard" (Whole site):
  * Search engines: filtered shop pages and search results stay out of search engines; Open Graph tags (picture and text when a page is shared) when no SEO plugin adds them; the brand in the product data for Google.
  * Speed: the next page loads while the mouse is on a link (WordPress speculative loading, a little earlier); no emoji script; no embed script.
  * Dashboard: "Design studio" in the admin bar on the site opens the studio with that page in the preview (can be switched off).

= 3.30.0 =
* Design studio, phase 9: footer, newsletter, gift cards, messages and emails.
  * Footer: columns per row on computers, centred text, a "Back to top" button, each column hidden or moved.
  * Newsletter: the button next to the field; a sign-up pop-up after a number of seconds with its own title (NL | FR | EN), once per visitor and never to subscribers.
  * Gift card choices: designs per row, the colours of the chosen amount.
  * Messages (Whole site): how long success messages stay, floating at the top or bottom of the screen, their colours and the error colour.
  * Emails (Whole site): gold and text colours, the background, an own logo, no gold frame, an extra line at the bottom (NL | FR | EN).

= 3.29.0 =
* Design studio, phase 8: new parts for the cart, checkout, my account, log in and favourites.
  * Cart: no discount code field, no "Update cart", no delivery line, payment logos or "Continue shopping", no "You may also like" or "Popular right now"; the totals on the left or scrolling with the page; the size of the pictures.
  * Checkout: no steps, "Your order" on the left or folded at first, no discount code / gift card box, no order notes field, no "Create an account" after ordering, an extra line on the thank-you page (NL | FR | EN).
  * My account: the tab that opens first, tabs hidden, the tabs above the page, no "Hello …".
  * Log in and register: no picture or logo, the width of the form, the colour behind the pop-up.
  * Favourites: cards per row on computers and phones, the colour of the hearts everywhere, no message after adding one.

= 3.28.0 =
* Design studio, phase 7 (customizer), new part "Customizer (whole)":
  * Steps: numbered steps, with a gold progress bar, or no step indicator; no numbers; no "How it works" button; a line at the top of each step (NL | FR | EN).
  * Product cards: cards per row on computers and phones, no price, no line under the name, no favourite heart, the picture shape.
  * Haberdashery: drag the haberdashery types in order, or leave the haberdashery step out; no size chart, no "How to measure" button; details on phones and tablets as a sheet or the whole screen.
  * The gold bar on phones: its colours, and the next button without sparkle.
  * Summary: the kit discount on every line, only at the total, or with a gold badge; no "Save this kit" button; a line under the table.

= 3.27.0 =
* Design studio, phase 6 (product page), new part "Product page (whole)":
  * Blocks under the pictures (description, inspiration, ideal for, details, fitting patterns, size chart, recommended fabrics, reviews, you may also like): drag them in order, separately for computers and phones, hide them per screen, and show them one under the other, as dropdowns or as tabs.
  * Pictures: small pictures under or left of the picture, or dots; the picture shape; no zoom; the picture wider or narrower than the information and on the left or right.
  * Buy bar: the slim bar at the bottom on phones, on every screen or never; no delivery line, no "Only … left", no "Email me when it's back"; share buttons (WhatsApp, Facebook, Pinterest, email, copy link).
  * Information: the brand as a link to its products, the size of the "Ideal for" icons, specification and washing lines left out, how many fitting patterns, recommended fabrics and "You may also like" products, cards per row, no "How to measure" button, the colour of the review stars.

= 3.26.0 =
* Design studio, phase 5 (shop pages), new part "Shop (product list)":
  * Products per row on computers, tablets and phones; what a card shows (price, unit, the line under the name, the brand, the favourite heart), the picture shape and a zoom under the mouse.
  * Sorting: the first sort order, and sort orders left out. Filters: search, category, price, colour/material and "in stock" each on/off, every filter folded at first, the filter column closed at first on computers.
  * Page numbers, a "Show more" button or loading by itself while scrolling; a click on a product can open its page instead of the quick view.
  * Badges: "New" (younger than a number of days), "Sale", "Almost sold out", with their colours (texts in the Texts tab).
  * Category pages: a banner with the picture, name and description of the chosen category, or only its description (both from Products → Categories).
* Overview cards (All products page): which section is the large card (or none), cards per row per screen, the order of the cards and a line under each name.
* Shop caches start fresh after every plugin update.

= 3.25.0 =
* Design studio, phase 4 (kit steps bar and mosaic):
  * Kit steps bar: leave out step 2 or 3, add a fifth step ("Start sewing", with a new spool-and-needle drawing), another drawing or your own picture per step, thread colour and thickness, needle speed, a smooth thread without loops, another address for the button, no sparkle, and the steps pop-up on the first visit of the customizer on/off. New part: each kit steps card.
  * Mosaic: the newest products, those on sale or a random choice (besides the settings), an even grid or one row to scroll sideways, space between the pictures, names and prices on/off, and the mouse-over effect.

= 3.24.0 =
* Design studio, phase 3 (header, menu, tab bar, search, hero):
  * Header: scrolls away or stays on top, stays the same size or shrinks while scrolling, words under the icons on/off, colours of the numbers on the icons. Header icons: hide each one on computers or phones, and choose their order.
  * "Menu" bar left, centre or right. Side menu: width, background, text colour and size, dropdowns all closed / all open / "All products" open, automatic sub-items for haberdashery (on/off), fabric and pattern categories, and a picture, contact details and social media icons under the menu.
  * Gold info bar: seconds per text, only on the home page or not on cart and checkout, a link per text. Phone tab bar: which tabs (also Search, Favourites, Cart, Account), icons only.
  * Language switch: flag, flag and code, or flag and name. Search: number of results, and fabrics, patterns, haberdashery or other products left out.
  * Hero: own pictures for computers and phones with a focal point, a video, or slides that take turns; the filter colour and strength; full, three-quarter or half height; place of the text (computer and phone apart); buttons on/off, where they go, and a third button; where the arrow goes. New part: the hero seal (round logo).
* Design studio: the list of parts is hidden while a part is open (it stayed visible).

= 3.23.0 =
* Design studio: clicking a part shows only the settings that part has (a title has no picture or corners, a picture part gets the picture and focal point), without the list; "‹ All parts" goes back.
* Design studio, new "Whole site" group (phase 2, the overall look): brand colours (gold, gold text, brown, success and error), fonts for titles and text (Trajan Pro, classic, clean or any Google Font), text and title sizes for computer and phone, capitals on/off and letter spacing, button shape/border/fill/inner space and mouse-over effect, card corners/border/shadow, page background (colour, gradient, picture), shop width and phone side margin, stitched or dotted gold lines, sparkles on/off, icon line thickness, a loading ring or dots, the keyboard focus outline, and the browser tab icon.
* The colours in all front-end stylesheets now follow the brand colours (nothing changes until you set one).

= 3.22.0 =
* Design studio, Texts tab: every text of the shop (±1,600) can get your own wording in Dutch, French and English. Search in any language; "Only my own texts" shows what you changed. Texts with codes like %s must keep them (the field turns red otherwise). Own texts are part of the draft and go live with Publish.
* Design studio, Parts: per part another picture from the media library and a focal point (the spot that stays in view when a picture is cut off), and animations off or slower/faster. New part: Hero picture.
* Design studio, Looks tab: save the draft as a named look ("Christmas", "Sale"), load it into the draft later, or plan it between two dates — visitors then see that look, and afterwards the published look again (cached pages are refreshed at the start and the end).
* The history and the backup file now include the texts.

= 3.21.1 =
* Design studio: the page opens (it said "not allowed": its menu item was added before the Atelier Irisee menu existed).

= 3.21.0 =
* New: Atelier Irisee → Design studio. Your site in a live preview (computer, tablet or phone size, on any of the main pages). Click a part of the page — or pick it from the list — and move it left/right and up/down, make it bigger or smaller, hide it, change the space above and below (computer and phone separately), or its text, background and border colour and rounded corners.
* Changes are a draft, only visible in the studio, until you press Publish (cached pages are refreshed). Discard draft goes back to the published look.
* History: the last 20 published looks can be put back into the draft. Backup: download the draft as a file and load it again later. Reset one part or every part.
* The Atelier Irisee menu now has two items: Settings and Design studio.

= 3.20.1 =
* Customizer on phones: the sparkle of the gold bar no longer makes the size sheet scroll sideways.

= 3.20.0 =
* Refund request: the quantity of an item that is not ticked is greyed out and no longer checked (no more "value must be at least 1" message). After an error the form keeps what was filled in.
* Free delivery from €75 counts the amount after discount codes: a code that takes the cart under €75 brings back the shipping cost (and the "… to go" text uses the same amount). Gift cards don't count as a discount.
* Phones: the cart icon is in the top bar; the tab bar has Home, Shop, Customizer and Menu.
* Customizer on phones: the size chart only appears once a size is chosen, and shows only that size (it changes with the size).
* Customizer on phones: the bar with the next step's button is gold, its button is white and sparkles when it appears.
* Forms keep their entries after an error: refund request, gift card form, extra account fields.
* Dutch: "Vlieseline" is now "Verstevigingen" (the Vlieseline brand stays). New type in that category: shoulder pads, sold per pair; a pattern can ask for shoulder pads (pairs) and the customizer offers them.
* "Knopen" is now "Knopen en sluitingen" (FR "Boutons et fermetures", EN "Buttons & fasteners").
* Product pages show the brand under the title.

= 3.19.0 =
* AI pictures, new "Per pattern design" block (one box per design): its own model photo to dress, and several pictures of the pattern for the AI (tick up to 4 of the pattern's pictures and add up to 4 other pictures from the media library, e.g. front, back and technical drawing).
* The general model photos are used for designs without their own model photo. The face close-ups stay the same for all designs.
* Leonardo (max. 6 reference pictures): face close-ups and extra pattern pictures are left out first when there are too many.

= 3.18.0 =
* AI pictures keep the person exactly the same: instead of drawing her again, the AI now dresses her in one of her own full-body photos (only the clothes change; face, hair, body, pose and background stay her real photo). Each combination always uses the same photo; different combinations rotate through the photos.
* New: "Face close-ups (for likeness)" (up to 3) next to "Model photos to dress (full body)" (up to 4), with advice on which photos work best.
* New per service: "Model for step 2 (the person)", e.g. Nano Banana Pro (gemini-3-pro-image-preview) for the person and a cheaper model for the garment.

= 3.17.0 =
* AI pictures use the chosen fabric much better:
  * "Two steps" (on by default): first the garment is made in the fabric, then that garment is put on the person. Each picture then costs 2 generations (the count and the confirmation say so).
  * Per pattern design you choose which of its pictures the AI gets (e.g. the technical drawing), so it has no other fabric to copy.
  * The AI gets a swatch cut from the middle of the fabric photo, and every picture is labelled (person, garment shape only, fabric to use); Leonardo weighs the fabric high and the garment picture low.
* The pictures grid shows the fabric in the corner of each picture.

= 3.16.2 =
* AI pictures: only the combinations the customizer shows: each pattern design with the fabrics allowed for its sizes (in the Fabrics category, visible). The single-picture choice lists only these, and a design without allowed fabrics is left out.
* Side menu: "All products" starts open each time the menu opens; the other dropdowns start closed.

= 3.16.1 =
* AI pictures: the "Generate missing pictures" start button and the Stop button only show when they can be used (before, an empty button showed).

= 3.16.0 =
* New settings tab "AI pictures": an image AI (Leonardo AI, Google Gemini or OpenAI – your choice, with your own API key, kept on the server) makes a picture of a pattern design sewn from a fabric, worn by your reference person. "Generate one combination" to try it, "Generate missing pictures" for every design with every fitting fabric (with a count and a confirmation first, and a maximum per month), and a grid to approve, reject or generate again.
* Approved pictures come first among the fabric's pictures in the customizer, for that pattern design.

= 3.15.0 =
* "Atelier Irisee" is now its own item in the dashboard menu (under Products) instead of under WooCommerce. Existing links keep working.
* New settings tab "Haberdashery options": the button materials and surface finishes, one per line as Dutch | French | English. In the product editor these are now choice lists; the shop and product pages show them in the visitor's language. A value that is not in the list stays on the product.
* New settings tab "Ideal for icons": per kind of clothing a choice of 6 icons for the "Ideal for" cards on the product pages.
* Haberdashery page: without filters it now shows an overview, like All products: a large "All haberdashery" card and a card per kind (Buttons, Zips, …). The pictures are set under Shop pages.
* Side menu: the Haberdashery item gets "All haberdashery" and the kinds as sub-items by itself; every dropdown starts closed when the menu opens; the whole row next to the text opens or closes a dropdown (the text itself follows the link).

= 3.14.0 =
* New settings tab "Images" with one button: "Optimize all images" makes a light WebP copy of every picture in all its sizes (with a progress bar) and the site shows those copies. The originals stay as they are; new uploads are optimized automatically; deleting a picture removes its copies. This replaces Imagify, which can be deactivated.

= 3.13.0 =
* Speed: shop pages bring their first products along (no wait for a second request); visitors without an account, cart or newsletter subscription start without any extra request (favourites from their cookie); logged-in customers and visitors with a cart get cart number, favourites and newsletter state in one request instead of three; WooCommerce's cart fragments request is no longer loaded on every page.
* Speed: the customizer remembers pattern lists and pattern details (refreshed on every product, stock or settings change) and loads the fabrics and haberdashery of the next step in the background.
* Page cache (LiteSpeed): adding a kit to the cart, saving a kit and adding a pattern keep working on pages from the cache (a fresh security code is fetched when needed); the cart number is always up to date.
* The plugin's scripts load with "defer".

= 3.12.2 =
* My orders: in the order details the product name sits beside its picture, and the order number, date and status are gold instead of marked in yellow.

= 3.12.1 =
* Customizer on phones: "Back" in the step bar shows its text with an arrow; the heart in the product sheets sits top left (away from the ×) and slides under the sheet's top bar; after adding a kit to the cart, the bar offers "Go to shopping cart" and "Make another".
* Kit steps bar on phones: bigger loops and a slightly slower needle.
* My orders on phones: the status as a small pill and only the price (bold); the order details slide in more smoothly and show a picture of every product.
* Product pages on phones: opening Specifications or Washing instructions no longer scrolls the page up.

= 3.12.0 =
* Haberdashery per design: a pattern with designs gets a Haberdashery block per design (until saved, a design uses the pattern's settings).
* Button groups: up to two extra button groups per pattern or design, each with its own name (e.g. "Cuffs"), button type and diameter; each is its own choice in the customizer.
* Number or length for all sizes in the Haberdashery block (zips: the zip length, only zips of exactly that length are offered; elastic and bias tape: the length that is bought, the width stays the filter). A size can still have its own value.
* Ribbons and cords: no width filter any more; the customizer offers all of them.

= 3.11.0 =
* Product pages on phones: the new order now really applies (description and specifications, recommended pattern, inspiration, the rest).
* Cart: a × in the corner of a sewing kit set ("Remove set" stays).
* Customizer on phones: the button bars are full width on white with single-line buttons (smaller on the overview); in the pattern sheet "Confirm" appears at the bottom once a size is chosen; the haberdashery sheet has a "Choose this" button.
* All pop-up sheets: a bigger round × with a double gold line.
* Customizer: on the first visit a pop-up shows the 4 steps (a sheet on phones); the "How it works" button opens it again.
* Kit steps bar: tailor's shears on the first card, a faster needle, and all texts editable per language (new settings tab "Kit steps bar").
* Tab bar: a dress form as the Customizer icon.
* My orders on phones: every order is a card; "View" opens the details in a sheet.

= 3.10.2 =
* Customizer on phones and tablets: a chosen pattern opens in a sheet from the bottom (like the fabrics) with its picture, sizes, needs, price, size chart and "Confirm"; tapping the pattern again reopens it.
* Size chart: the chosen size is a gold row with white text; on phones it moves to the top of the chart.

= 3.10.1 =
* Kit steps bar on phones: the seam runs around the cards (with small loops in the gaps) instead of over their texts. The seam is measured again once the fonts have loaded.
* Home page hero: the arrow scrolls until the title of the 4 steps is at the top, just under the (slimmer) header.

= 3.10.0 =
* Phones: the "Show results" button in the filters is white on a solid gold strip, so the filter list no longer shows through it.
* Tab bar: the open page is a gold tile instead of a small dot.
* Product pages on phones: description and specifications / washing first, then the recommended pattern, then the (smaller) inspiration cards, then the rest.
* My account on phones: the tabs open as a menu from the left (like the shop filters) instead of a swipeable row.
* Home page hero: "Atelier Irisée" in the arch under the logo; the arrow is white.
* Kit steps bar: bigger sparkles that fade out more slowly; on phones the seam winds through the cards; NL "naaipakket".

= 3.9.1 =
* Home page hero: the arch with the tagline and buttons is bigger and scales with the screen (width and height); no more white band between the header and the picture.
* Kit steps bar: more space above it, and bigger on laptops and desktops.

= 3.9.0 =
* New shortcode [atelier_irisee_hero]: a full-screen opening for the home page. Your picture under a gold filter, a cream arch with the logo as a seal, the tagline "Sew something truly yours" and buttons to the customizer and the shop, and a bouncing gold arrow that slides down to the kit steps bar.
* New settings tab "Home page": the picture (plus an optional phone picture and logo), where the subject is, the strength of the gold filter, and the tagline and text per language.

= 3.8.5 =
* Kit steps bar: a new fabric drawing, a roll of floral fabric that unrolls, with a sewing tape measure.

= 3.8.4 =
* Shop, phones: the page no longer scrolls sideways (the sort menu stays within the screen).

= 3.8.3 =
* Customizer, phones: after choosing a pattern the sizes sit right under the picture; the pictures show as dots (swipe to browse) and the description is folded with "Read more".
* Customizer, phones and tablets: a chosen fabric or haberdashery product opens in a sheet from the bottom, with "Confirm fabric" always in view.
* Shop, phones: products show as a grid of two photos per row.
* Tablets held upright get the tab bar and the slim header too.
* Kit steps bar: a new fabric drawing (pinked swatch, folded fabrics, tape measure), a zip with a common pull tab, loops of different sizes and heights, and the needle now sews all the way to the button, which sparkles. The button reads "Start making your project sewing kit".

= 3.8.2 =
* Shop pages: "Add to cart" in the product details (the panel or sheet) works again. It shared its address with the customizer and was refused as an expired session.
* Phones: product pages no longer scroll sideways (a long breadcrumb now wraps); "Ideal for" shows two per row; the filter drawer's "Show results" button is no longer hidden behind the tab bar; the cart tab reads "Mandje".
* Phones: in the cart and checkout every field is 16 px (no zooming in on iPhones) and long field labels wrap.
* Customizer: when the page scrolls to the chosen fabric or size, it stops below the header instead of under it.
* Kit steps bar: the seam runs through the cards again, with a playful loop in each gap; the needle is smaller and fine gold.

= 3.8.1 =
* Customizer on phones and tablets: the chosen fabric's details and Continue are shown again (3.8.0 hid them), stacked under the fabrics.
* Customizer on phones: "‹ Back" sits with the steps at the top instead of in the bar at the bottom.
* Kit steps bar: a bit bigger and wider; a pointed silver needle sews a gold seam under the steps from left to right, in a loop (above the cards on phones), and the same needle stands next to the button.

= 3.8.0 =
* Phones: an app-like tab bar at the bottom (Home, Shop, Customizer, Cart with count, Menu). The header gets slimmer: logo, search, favourites, account and language.
* Phones: on product pages a slim bar with the price and "Add to cart" appears at the bottom once the buy bar has scrolled away.
* Phones: in the customizer, Back and Continue stay at the bottom of the screen; form fields are 16 px, so phones no longer zoom in.
* Shop pages on phones and tablets: the products use the full width, and the details open as a sheet from the bottom (on tablets a centred card). Before, an empty details column took a third of the width.
* Tablets: product pages show the picture and the information side by side.
* Pattern page on phones: the size chart cards name every value (Bust, Waist, …).
* "Ideal for" shows two per row on phones.
* Info bar: the texts no longer overlap while they take turns.
* The automatic login/register popup never opens on the cart, the checkout or the customizer.

= 3.7.0 =
* Haberdashery like buttons: zips, interfacing, elastic, ribbons & cords and bias tape each get Products → "All …" and "Add …", the Atelier Irisee workspace, a Haberdashery details box (order information, specifications, sold per, Ideal for), a product page with the buy bar, Ideal for, specification tiles and "Fits these patterns", and the Type and size columns.
* Specifications: zips type, length and colour; interfacing width; elastic colour, length and width; ribbons & cords type (ribbon or cord), length, width and colour; bias tape colour, width and length.
* Sold per: zips and buttons per piece or per set; elastic, bias tape, ribbons & cords and interfacing per 10 cm or per piece/roll of a fixed length. The buy bar says "per roll of 3 m", "per set of 6", "per 10 cm" or "per piece".
* Patterns: the Buttons block becomes "Haberdashery": per kind and type a switch, with the diameter or width that fits. Only switched-on kinds can be filled in on the sizes. The configurator offers only fitting products; elastic, cord and interfacing are now in the kit. Rolls and sets are added whole, with a notice.
* Settings → Categories: new Interfacing and Elastic categories; "Ribbons & cords category"; the four zip type categories are replaced by the zip Type field (zips in them get that type automatically).
* Shop pages: haberdashery filters for type, colour, diameter, width, length and material.
* Recommendation box: a pattern with designs can be recommended per design ("Lison – Jurk"); the configurator button then opens that design.
* Designs: a separate Main picture per design next to the other pictures, and the fitting fabrics with their positions per design.
* New shortcode [atelier_irisee_kit_steps]: a playful bar for the home page with the four steps of the customizer on a gold thread, and a button to the configurator.

= 3.6.2 =
* Header: no more flickering at the top of the page or when scrolling up (the header is now fixed, with a spacer of its full height, so getting slimmer no longer moves the page).
* Logged in as admin: no gap under the WordPress admin bar while scrolling on phones; the header follows the bar.
* Checkout: no list dot before the PostNL choices (Home delivery, pickup points).
* Customizer: choosing a size on a phone or tablet no longer makes the page jump up.
* Pattern sizes: embroidery is filled in in cm.
* Dashboard: the plugin's screens follow your own profile language (Users → Profile → Language), so Dutch users see everything in Dutch.

= 3.6.1 =
* Pattern sizes: every field in "Needed materials & sizes" shows its name again (Cord, Elastic, Fabric, …), and the group titles Zips, Buttons and Body measurements are visible.
* Pattern sizes: only the button types switched on in the pattern's Buttons block can be filled in; the others are disabled, and switching a type off removes its amounts from all sizes. Under the buttons a note says what is allowed, e.g. "Allowed: Buttons 14–18 mm".
* Button page: the buy bar always shows "€0,60 per piece" or "€0,60 per set of 6"; buttons get an "Order information" text under the buy bar, as on fabrics.
* Configurator: choosing a button sold per set shows that it is only sold per set and that the amount can't be changed, e.g. "Added: 2 × set of 6 = 12 buttons, 3 more than needed."

= 3.6.0 =
* Buttons as their own kind of product: Products → "All buttons" and "Add a button" (opens with the Buttons category ticked and the button workspace: price and stock, description, specifications, "Ideal for", recommendation).
* Button details: type (button, jeans button, press stud), colour, diameter (mm), material, surface finish, mounting method, sold per piece or per set of a number of buttons, and up to four kinds of clothes for "Ideal for".
* Button page (the same shortcode and design as fabrics): the buy bar shows "€x per set of 6" for sets, "Ideal for" cards with an icon in the place of the inspiration cards, the specifications as tiles with icons next to the description, and "Fits these patterns".
* Patterns: on the Atelier Irisee tab a "Buttons" block with per type a switch and the diameter range that fits. Only switched-on types have an amount field on the sizes. The separate "Snap fasteners" and "Jeans buttons" categories are gone: all buttons sit in the Buttons category and the type links them.
* Configurator: the button steps only offer buttons of the type with a diameter in the pattern's range. Buttons sold per set are added in whole sets ("2 × set of 6"); one set lowers the stock by one.
* Shop pages: filters for button type, diameter, colour and material; cards show the diameter and "per set of 6". The cart, checkout and orders show "Sold per: Set of 6".
* Products → All buttons: Button type and Diameter columns.

= 3.5.0 =
* Pattern sizes: a new "Needed materials & sizes" box in your order: fabric (cm), zips (divisible, invisible, non divisible, double divisible, in cm), buttons (buttons, snap fasteners, jeans buttons), elastic, bias tape, ribbon, cord, embroidery, bag strap, fusible interfacing, cord lock, then the body measurements. Existing fabric (as cm), buttons, ribbon, bias tape and measurements are kept; old zip count and length are no longer used.
* Variations tab on patterns: only that box per size; the variations are ordered per design, then per size.
* Configurator: every zip and button type is its own step with its own product category (new under Settings → Categories; a zip type without one uses the Zips category). Elastic, cord and the other new materials show as information ("not in the kit"), only when filled in.
* Pattern page: "Accessories" next to the size chart with measurements and fabric, with the measuring guide, per design.
* Designs block: "Pictures of this design: Jurk" and "Fitting fabrics: Jurk".

= 3.4.0 =
* Patterns with two designs in one pack: add a "Design" attribute (e.g. Jurk | Blouse) next to the sizes and generate the variations. The pattern page stays one product (one price, one stock) with a Designs tile, the project time per design and a configurator button per design. In the configurator every design is its own card with only its sizes, its pictures and its fitting fabrics.
* Atelier Irisee tab: a Designs block with per design the pictures (ticked from the product gallery; the first is the card picture), the fitting fabrics, the height and the project time.
* Cart, saved kits and orders name the design: "Sewing project kit: Lison – Jurk – M".

= 3.3.6 =
* PostNL checkout: the price on the "Home delivery" tab shows as €5,00 (it showed &euro;5,00).

= 3.3.5 =
* PostNL checkout: a "Home delivery" choice (with its price, the delivery time and the expected date) next to "Choose a pickup point". PostNL offers no delivery days to Belgian shops, so before this every order became a pickup order. Choosing home delivery clears the pickup point and the pickup fee. PostNL's line "Make a selection from the Delivery Days" is no longer shown when there are no delivery days.
* The PostNL pickup tab now reads "Choose a pickup point".

= 3.3.4 =
* PostNL pickup points: when PostNL returns pickup groups without locations, the page source note shows what PostNL received, to find the cause.

= 3.3.3 =
* PostNL pickup points: also points without a distance (PostNL drops those too) are now listed, and a point without a location code still gets a unique choice. The page source has a short note (aimp-postnl) with what PostNL delivered, for support.

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
