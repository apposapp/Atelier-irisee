<?php
/**
 * Dutch (Belgium) translations: English source text => Dutch.
 *
 * When you add a new string to the plugin, add its Dutch translation here.
 * Strings without an entry are shown in English.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Plugin header / plugins page.
	'Pattern configurator for WooCommerce: pick a pattern and size, a matching fabric, buttons and zips, and add the whole set to the cart.' => 'Patroonconfigurator voor WooCommerce: kies een patroon en maat, een passende stof, knopen en ritsen, en voeg de volledige set toe aan het winkelmandje.',
	'See the GitHub releases page for details.' => 'Bekijk de releasepagina op GitHub voor meer details.',
	'Atelier Irisee Master Plugin requires WooCommerce to be installed and active.' => 'Atelier Irisee Master Plugin vereist dat WooCommerce geïnstalleerd en actief is.',

	// Settings page.
	'Atelier Irisee' => 'Atelier Irisee',
	'Settings' => 'Instellingen',
	'General' => 'Algemeen',
	'Plugin language' => 'Taal van de plugin',
	'Used for these settings, the product fields and as the starting language of the configurator. Customers can switch language at any time with the flags in the configurator.' => 'Wordt gebruikt voor deze instellingen, de productvelden en als starttaal van de configurator. Klanten kunnen op elk moment van taal wisselen met de vlaggen in de configurator.',
	'Product categories' => 'Productcategorieën',
	'Patterns category' => 'Categorie patronen',
	'Fabrics category' => 'Categorie stoffen',
	'Buttons category' => 'Categorie knopen',
	'Zips category' => 'Categorie ritsen',
	'Items per page' => 'Items per pagina',
	'Choose which product categories hold your patterns and materials. The subcategories of the Fabrics category are the fabric categories you can allow per pattern size. The subcategories of the Patterns category become the filter tabs in the configurator.' => 'Kies in welke productcategorieën je patronen en materialen staan. De subcategorieën van de categorie stoffen zijn de stofcategorieën die je per patroonmaat kunt toestaan. De subcategorieën van de categorie patronen worden de filtertabs in de configurator.',
	'Place the configurator on any page with the shortcode %s.' => 'Plaats de configurator op een pagina met de shortcode %s.',
	'— Select —' => '— Kies —',
	'Products per page in the configurator grids. 9 fills a 3x3 grid.' => 'Producten per pagina in de rasters van de configurator. Met 9 vul je een raster van 3x3.',
	'Save settings' => 'Instellingen opslaan',

	// Product fields.
	'Atelier Irisee – material requirements & size measurements' => 'Atelier Irisee – benodigde materialen & maten',
	'Fabric needed (units of 10 cm)' => 'Benodigde stof (eenheden van 10 cm)',
	'Button count' => 'Aantal knopen',
	'Zip count' => 'Aantal ritsen',
	'Zip length (cm)' => 'Lengte rits (cm)',
	'Bust (cm)' => 'Borstomtrek (cm)',
	'Waist (cm)' => 'Taille (cm)',
	'Height (cm)' => 'Lichaamslengte (cm)',
	'Fabric categories allowed' => 'Toegestane stofcategorieën',
	'No fabric subcategories found. Set the Fabrics category under WooCommerce > Atelier Irisee and give it subcategories.' => 'Geen stofsubcategorieën gevonden. Stel de categorie stoffen in onder WooCommerce > Atelier Irisee en geef ze subcategorieën.',
	'Only used for products in the Zips category. The configurator only offers zips whose length equals the length a pattern size needs.' => 'Wordt enkel gebruikt voor producten in de categorie ritsen. De configurator toont enkel ritsen waarvan de lengte gelijk is aan de lengte die een patroonmaat nodig heeft.',

	// Catalog.
	'%1$d × %2$d cm (%3$s m)' => '%1$d × %2$d cm (%3$s m)',
	'%1$d × 10 cm in stock (%2$s m)' => '%1$d × 10 cm op voorraad (%2$s m)',
	'%d in stock' => '%d op voorraad',
	'In stock' => 'Op voorraad',

	// Configurator.
	'Language' => 'Taal',
	'Pattern & size' => 'Patroon & maat',
	'Fabric' => 'Stof',
	'Buttons & zips' => 'Knopen & ritsen',
	'Summary' => 'Overzicht',
	'All' => 'Alle',
	'Loading…' => 'Laden…',
	'Something went wrong. Please try again.' => 'Er ging iets mis. Probeer het opnieuw.',
	'No patterns found.' => 'Geen patronen gevonden.',
	'No fabrics are available for this pattern size at the moment.' => 'Er zijn momenteel geen stoffen beschikbaar voor deze patroonmaat.',
	'Nothing available at the moment. You can continue without.' => 'Momenteel niets beschikbaar. Je kunt zonder verdergaan.',
	'Previous' => 'Vorige',
	'Next' => 'Volgende',
	'Page %1$d of %2$d' => 'Pagina %1$d van %2$d',
	'Back' => 'Terug',
	'Choose your size' => 'Kies je maat',
	'Compare your own measurements with the measurements below to pick the right size for this pattern.' => 'Vergelijk je eigen maten met de maten hieronder om de juiste maat voor dit patroon te kiezen.',
	'Size chart for this pattern' => 'Maattabel voor dit patroon',
	'Size' => 'Maat',
	'Bust' => 'Borstomtrek',
	'Waist' => 'Taille',
	'Height' => 'Lichaamslengte',
	'cm' => 'cm',
	'This size needs' => 'Deze maat heeft nodig',
	'Buttons' => 'Knopen',
	'Zips' => 'Ritsen',
	'%1$d × zip of %2$s cm' => '%1$d × rits van %2$s cm',
	'None' => 'Geen',
	'Not available in this size' => 'Niet beschikbaar in deze maat',
	'Confirm pattern & size' => 'Patroon & maat bevestigen',
	'Choose your fabric' => 'Kies je stof',
	'Not enough stock' => 'Onvoldoende voorraad',
	'Price per 10 cm' => 'Prijs per 10 cm',
	'Price per piece' => 'Prijs per stuk',
	'You need' => 'Je hebt nodig',
	'Total for this size' => 'Totaal voor deze maat',
	'Stock' => 'Voorraad',
	'Confirm fabric' => 'Stof bevestigen',
	'Choose your buttons (optional)' => 'Kies je knopen (optioneel)',
	'Choose your zip (optional)' => 'Kies je rits (optioneel)',
	'%d buttons of the chosen design will be added.' => 'Er worden %d knopen van het gekozen model toegevoegd.',
	'%1$d zip(s) of %2$s cm will be added.' => 'Er worden %1$d rits(en) van %2$s cm toegevoegd.',
	'Click a selected item again to remove it.' => 'Klik opnieuw op een gekozen item om het te verwijderen.',
	'Continue' => 'Verder',
	'Your pattern set' => 'Jouw patroonset',
	'Product' => 'Product',
	'Quantity' => 'Aantal',
	'Price' => 'Prijs',
	'Total' => 'Totaal',
	'Quantities are fixed by your size. The items stay linked in your cart: removing one removes the whole set.' => 'De aantallen liggen vast volgens je maat. De items blijven gekoppeld in je winkelmandje: als je er één verwijdert, verdwijnt de volledige set.',
	'Add to cart' => 'In winkelmandje',
	'Adding…' => 'Toevoegen…',
	'View cart' => 'Bekijk winkelmandje',
	'Configure another pattern' => 'Nog een patroon samenstellen',
	'Selected' => 'Gekozen',
	'Please enable JavaScript to use the pattern configurator.' => 'Schakel JavaScript in om de patroonconfigurator te gebruiken.',

	// Cart, checkout and orders.
	'This pattern size is not available.' => 'Deze patroonmaat is niet beschikbaar.',
	'Please choose one of the fabrics allowed for this pattern.' => 'Kies een van de stoffen die voor dit patroon zijn toegestaan.',
	'This zip cannot be used with this pattern size.' => 'Deze rits kan niet gebruikt worden voor deze patroonmaat.',
	'These buttons cannot be used with this pattern size.' => 'Deze knopen kunnen niet gebruikt worden voor deze patroonmaat.',
	'"%s" could not be added to the cart.' => '"%s" kon niet aan het winkelmandje worden toegevoegd.',
	'"%s" is out of stock.' => '"%s" is niet op voorraad.',
	'Not enough stock for "%1$s" (%2$d available, including what is already in your cart).' => 'Onvoldoende voorraad voor "%1$s" (%2$d beschikbaar, rekening houdend met wat al in je winkelmandje zit).',
	'The quantities of a configured pattern set are fixed by the chosen size.' => 'De aantallen van een samengestelde patroonset liggen vast volgens de gekozen maat.',
	'A quantity in your pattern set was reset to the amount required by the chosen size.' => 'Een aantal in je patroonset werd teruggezet naar wat de gekozen maat nodig heeft.',
	'Configured set' => 'Samengestelde set',
	'Pattern only' => 'Enkel patroon',
	'For' => 'Voor',
	'Length' => 'Lengte',
	'Note' => 'Opmerking',
	'Removing any item of this set removes the whole set.' => 'Als je een item van deze set verwijdert, verdwijnt de volledige set.',
	'Configuration' => 'Samenstelling',
	'This item is not available.' => 'Dit item is niet beschikbaar.',
	'Your session has expired. Please reload the page and try again.' => 'Je sessie is verlopen. Herlaad de pagina en probeer het opnieuw.',
	'Your pattern set has been added to the cart.' => 'Je patroonset is toegevoegd aan je winkelmandje.',
);
