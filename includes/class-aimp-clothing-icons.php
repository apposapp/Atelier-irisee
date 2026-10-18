<?php
/**
 * "Ideal for" icons: per kind of clothing the standard icon (AIMP_Product_Page) and 5 alternatives, all 24×24 gold
 * line drawings. Which one the site shows is chosen per kind under Atelier Irisee → Ideal for icons.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Clothing_Icons {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 20 );
	}

	/**
	 * The alternatives per kind (variants 1–5; variant 0 is the standard icon): key => [ SVG content ].
	 *
	 * @return array
	 */
	public static function alternatives() {
		return array(
			'shirt'    => array(
				// Short sleeves.
				'<path d="M9 4 5.5 5.5 3 9.5l3 1.5 1-1.5V20.5h10V9.5l1 1.5 3-1.5-2.5-4L15 4"/><path d="M9 4l3 3.5L15 4"/><path d="M12 7.5v13"/><path d="M12 11h.01M12 14.5h.01M12 18h.01"/>',
				// With a chest pocket.
				'<path d="M9 3.5 5 5.5 2.5 10l3 1.5 1-2V20.5h11V9.5l1 2 3-1.5L19 5.5l-4-2"/><path d="M9 3.5 12 7l3-3.5"/><path d="M12 7v13.5"/><path d="M14 10.5h3V13h-3z"/>',
				// On a hanger.
				'<path d="M12 2.5a1.2 1.2 0 1 1 1.2 1.2c-.7 0-1.2.4-1.2 1v.8"/><path d="M12 5.5 6 8l-2 4 2.5 1V21h11v-8l2.5-1-2-4z"/><path d="M10 7l2 2.5L14 7"/><path d="M12 9.5V21"/>',
				// Folded.
				'<path d="M5 7.5 9 5h6l4 2.5V19H5z"/><path d="M9 5l3 3.5L15 5"/><path d="M12 8.5V19"/><path d="M12 12h.01M12 15.5h.01"/>',
				// With a bow tie.
				'<path d="M9 3.5 5 5.5 2.5 10l3 1.5 1-2V20.5h11V9.5l1 2 3-1.5L19 5.5l-4-2"/><path d="M12 6.5 9.5 5.2v2.6zM12 6.5l2.5-1.3v2.6z"/><path d="M12 7.8v12.7"/>',
			),
			'blouse'   => array(
				// A bow at the neck.
				'<path d="M8.5 4 4.5 6 3 11l2.5.8 1-2V20h11V9.8l1 2 2.5-.8L19.5 6l-4-2"/><path d="M8.5 4c.6 1.4 1.9 2.2 3.5 2.2s2.9-.8 3.5-2.2"/><path d="M12 6.2 9.8 5v2.6zM12 6.2 14.2 5v2.6z"/><path d="M11.5 7l-1 3M12.5 7l1 3"/>',
				// Sleeveless top.
				'<path d="M9 3v3.5L7 9v11.5h10V9l-2-2.5V3"/><path d="M9 6.5c1 1.2 2 1.8 3 1.8s2-.6 3-1.8"/>',
				// Peplum.
				'<path d="M9 4 5 5.5 3.5 10l2.5 1 1-2V14h10V9l1 2 2.5-1L19 5.5 15 4c-.6 1.4-1.6 2.2-3 2.2S9.6 5.4 9 4z"/><path d="M7 14l-2 6.5h14L17 14"/>',
				// Wrap blouse.
				'<path d="M9 4 5 5.5 3 10.5l2.5 1 1-2V20.5h11v-11l1 2 2.5-1L19 5.5 15 4"/><path d="M9 4l7.5 11M15 4l-4 6"/>',
				// Off the shoulder with a ruffle.
				'<path d="M4 8c2.5 1.2 5.2 1.8 8 1.8S17.5 9.2 20 8"/><path d="M4 8l1.5 3.5L7 10.5V20h10v-9.5l1.5 1L20 8"/><path d="M5 9.3c.8.8 1.6.8 2.4 0 .8.8 1.6.8 2.4 0 .8.8 1.6.8 2.4 0 .8.8 1.6.8 2.4 0 .8.8 1.6.8 2.4 0"/>',
			),
			'dress'    => array(
				// A-line, sleeveless.
				'<path d="M9.5 3v3L8 9.5 5 20.5h14L16 9.5 14.5 6V3"/><path d="M9.5 6c.8.9 1.6 1.3 2.5 1.3s1.7-.4 2.5-1.3"/><path d="M8 10h8"/>',
				// Short sleeves and a belt.
				'<path d="M9 3.5 5.5 5 4 8.5l2.5 1 1.3-1.5L8.5 11 5.5 20.5h13L15.5 11l.7-3 1.3 1.5 2.5-1L18.5 5 15 3.5c-.6 1.2-1.6 1.8-3 1.8S9.6 4.7 9 3.5z"/><path d="M8.3 11h7.4"/>',
				// Maxi dress.
				'<path d="M9.5 2.5v3l-1.5 3 1 2L6.5 21.5h11L15 10.5l1-2-1.5-3v-3"/><path d="M9.5 5.5c.8.9 1.6 1.3 2.5 1.3s1.7-.4 2.5-1.3"/><path d="M9 10.5h6"/>',
				// Shirt dress with buttons.
				'<path d="M9 3.5 5 5.5 3 10l2.5 1 1.5-2-1 11.5h12L17 9l1.5 2 2.5-1L19 5.5l-4-2"/><path d="M9 3.5 12 7l3-3.5"/><path d="M12 7v13.5"/><path d="M12 10h.01M12 15h.01M12 18h.01"/><path d="M6.8 12.5h10.4"/>',
				// Wrap dress with a V-neck.
				'<path d="M9 3.5 6 5 5 9l2 .5L5 20.5h14L17 9.5l2-.5-1-4-3-1.5"/><path d="M9 3.5l3 5 3-5"/><path d="M12 8.5l4.5 5"/>',
			),
			'skirt'    => array(
				// Pencil skirt with a slit.
				'<path d="M7.5 4.5h9V7h-9z"/><path d="M7.5 7 7 20h10l-.5-13"/><path d="M12 20v-3"/>',
				// Pleated.
				'<path d="M7 4.5h10V7H7z"/><path d="M7 7 4 19.5h16L17 7"/><path d="M9 7l-1.5 12.5M11 7l-.5 12.5M13 7l.5 12.5M15 7l1.5 12.5"/>',
				// Wrap skirt with a tie.
				'<path d="M7.5 4.5h9V7h-9z"/><path d="M7.5 7 5 20h14L16.5 7"/><path d="M14.5 7l-3 13"/><path d="M16.5 7l1.5 2.5M16.5 7l2.5.5"/>',
				// Tiered.
				'<path d="M8 4.5h8v2H8z"/><path d="M8 6.5 7 11h10l-1-4.5"/><path d="M7 11l-1.5 4.5h13L17 11"/><path d="M5.5 15.5 4 20h16l-1.5-4.5"/>',
				// Mini skirt with buttons.
				'<path d="M7.5 6h9v2.5h-9z"/><path d="M7.5 8.5 5.5 16h13l-2-7.5"/><path d="M12 8.5V16"/><path d="M13.3 11h.01M13.3 13.5h.01"/>',
			),
			'trousers' => array(
				// Wide legs.
				'<path d="M7 3.5h10l3 17h-6L12 9l-2 11.5H4z"/><path d="M7 6h10"/>',
				// Shorts.
				'<path d="M6.5 5h11l1.5 9h-5L12 9.5 10 14H5z"/><path d="M6.5 7.5h11"/>',
				// Pressed, with belt loops.
				'<path d="M6.5 3.5h11l1.5 17h-5L12 9l-2 11.5H5z"/><path d="M6.5 6h11"/><path d="M9 3.5V6M15 3.5V6"/><path d="M8 9l-1 11M16 9l1 11"/>',
				// Cropped.
				'<path d="M6.5 3.5h11l2 13h-6L12 9l-1.5 7.5h-6z"/><path d="M6.5 6h11"/>',
				// Joggers with a drawstring.
				'<path d="M7 4h10l1.5 14.5h-4.5L12 9l-2 9.5H5.5z"/><path d="M7 6.5h10"/><path d="M5.5 17h4.5M14 17h4.5"/><path d="M12 6.5l-1 2M12 6.5l1 2"/>',
			),
			'jeans'    => array(
				// Back pockets.
				'<path d="M6.5 3.5h11l1.5 17h-5L12 9l-2 11.5H5z"/><path d="M6.5 6h11"/><path d="M7.5 7.5H11v3l-1.75 1-1.75-1zM13 7.5h3.5v3l-1.75 1L13 10.5z"/>',
				// Flared.
				'<path d="M7 3.5h10l1 9 2 8h-6L12 9l-2 11.5H4l2-8z"/><path d="M7 6h10"/><path d="M8.5 6c0 1.4.7 2.2 1.8 2.5M15.5 6c0 1.4-.7 2.2-1.8 2.5"/>',
				// Denim shorts with a frayed hem.
				'<path d="M6.5 5h11l1.5 9h-5L12 9.5 10 14H5z"/><path d="M6.5 7.5h11"/><path d="M8 7.5c0 1.2.6 2 1.6 2.3M16 7.5c0 1.2-.6 2-1.6 2.3"/><path d="M5.3 15.2v.8M7 15.2v.8M8.7 15.2v.8M15.3 15.2v.8M17 15.2v.8M18.7 15.2v.8"/>',
				// The front: waistband, button and fly.
				'<path d="M5 6h14v3H5z"/><path d="M12 7.5h.01"/><path d="M12 9v6c0 1-1 1.5-2 1.5"/><path d="M5 9c1.5 0 3 1 3.5 3M19 9c-1.5 0-3 1-3.5 3"/><path d="M5 9v11M19 9v11"/>',
				// A folded stack.
				'<path d="M5 8h14v3.5H5zM5 11.5h14V15H5zM5 15h14v3.5H5z"/><path d="M12 8v3.5"/><path d="M8 9.8h.01M16 9.8h.01"/>',
			),
			'jacket'   => array(
				// Bomber.
				'<path d="M8.5 4 4.5 5.5 3 12l2.5.5V18.5h13V12.5l2.5-.5-1.5-6.5-4-1.5"/><path d="M5.5 18.5v2h13v-2"/><path d="M8.5 4c.8 1 2 1.5 3.5 1.5S14.7 5 15.5 4"/><path d="M12 5.5v13"/>',
				// Denim jacket with chest pockets.
				'<path d="M8.5 3.5 4.5 5.5 3 12l2 .5V20.5h5.5L12 9l1.5 11.5H19V12.5l2-.5-1.5-6.5-4-2"/><path d="M8.5 3.5 12 9l3.5-5.5"/><path d="M6.5 10h3v2.5h-3zM14.5 10h3v2.5h-3z"/>',
				// Blazer with one button.
				'<path d="M8.5 3.5 4.5 5.5 3 12l2 .5V20.5h14V12.5l2-.5-1.5-6.5-4-2"/><path d="M8.5 3.5 12 13l3.5-9.5"/><path d="M8.5 3.5 10 8 9 9M15.5 3.5 14 8l1 1"/><path d="M12 15h.01"/><path d="M6 16.5h2.5M15.5 16.5h2.5"/>',
				// Cropped.
				'<path d="M8.5 4 4.5 6 3 13l2.5.5 1-3V16h11v-5.5l1 3 2.5-.5L19.5 6l-4-2"/><path d="M8.5 4 12 8.5 15.5 4"/><path d="M12 8.5V16"/>',
				// With a hood.
				'<path d="M8.5 6c0-2.5 1.6-4 3.5-4s3.5 1.5 3.5 4"/><path d="M8.5 6 4.5 7.5 3 13l2.5.5V21h13v-7.5l2.5-.5-1.5-5.5-4-1.5"/><path d="M12 7v14"/><path d="M6.5 15.5h3M14.5 15.5h3"/>',
			),
			'coat'     => array(
				// Trench coat with a belt.
				'<path d="M8.5 3 4.5 5 3 13l2 .3V21h14v-7.7l2-.3-1.5-8-4-2"/><path d="M8.5 3 12 7.5 15.5 3"/><path d="M5 12h14"/><path d="M12 7.5V21"/><path d="M10.8 11.2h2.4v1.6h-2.4z"/>',
				// Long A-line coat.
				'<path d="M9 3 5 5 4 13l1.5.3L4 21.5h16L18.5 13.3 20 13l-1-8-4-2"/><path d="M9 3l1 3 2-1 2 1 1-3"/><path d="M12 5v16.5"/><path d="M10.5 9h.01M10.5 12h.01M10.5 15h.01"/>',
				// Duffle coat with toggles.
				'<path d="M8.5 3 4.5 5 3 13l2 .3V21h14v-7.7l2-.3-1.5-8-4-2"/><path d="M8.5 3c.5 1.5 1.8 2.4 3.5 2.4S15 4.5 15.5 3"/><path d="M12 5.4V21"/><path d="M10 9.5h4M10 13h4M10 16.5h4"/>',
				// Cape.
				'<path d="M12 3 4 9l1.5 12h13L20 9z"/><path d="M10 4.5l2 3 2-3"/><path d="M12 7.5V21"/><path d="M7.5 13v2M16.5 13v2"/>',
				// On a hanger.
				'<path d="M12 2.5a1.2 1.2 0 1 1 1.2 1.2c-.7 0-1.2.4-1.2 1v.8"/><path d="M12 5.5 6.5 8 5 20.5h14L17.5 8z"/><path d="M10 7l2 3 2-3"/><path d="M12 10v10.5"/><path d="M10.5 13h.01M10.5 16h.01"/>',
			),
			'cardigan' => array(
				// Long, open.
				'<path d="M9 3.5 5 5.5 3.5 19l2.5.5 1-8V21H10.5V8M15 3.5l4 2L20.5 19l-2.5.5-1-8V21h-3.5V8"/><path d="M9 3.5 10.5 8M15 3.5 13.5 8"/><path d="M7.5 15h2M14.5 15h2"/>',
				// Cropped.
				'<path d="M9 4 5 5.5 3.5 13l2.5.5 1-4V15h10V9.5l1 4 2.5-.5L19 5.5 15 4"/><path d="M9 4l3 5 3-5"/><path d="M12 9v6"/><path d="M12 11h.01M12 13h.01"/><path d="M7 15h10"/>',
				// Cable knit.
				'<path d="M9 3.5 5 5.5 3.5 19l2.5.5 1-8V20.5h10v-9l1 8 2.5-.5L19 5.5l-4-2"/><path d="M9 3.5l3 7 3-7"/><path d="M12 10.5v10"/><path d="M8.5 11c1 1 1 2 0 3s-1 2 0 3M15.5 11c-1 1-1 2 0 3s1 2 0 3"/>',
				// Round neck, buttoned all the way.
				'<path d="M9 3.5 5 5.5 3.5 19l2.5.5 1-8V20.5h10v-9l1 8 2.5-.5L19 5.5l-4-2c-.6 1.2-1.6 1.8-3 1.8S9.6 4.7 9 3.5z"/><path d="M12 5.3v15.2"/><path d="M12 8h.01M12 11h.01M12 14h.01M12 17h.01"/>',
				// Folded.
				'<path d="M5 7h14v12H5z"/><path d="M9 7l3 4 3-4"/><path d="M12 11v8"/><path d="M12 13.5h.01M12 16h.01"/><path d="M5 17h14"/>',
			),
			'knitwear' => array(
				// Turtleneck.
				'<path d="M10 2.5h4V5h-4z"/><path d="M10 5 5.5 6.5 4 19l2.5.5 1-8V20.5h9v-9l1 8 2.5-.5L18.5 6.5 14 5"/><path d="M7.5 18.5h9"/>',
				// Knitted vest.
				'<path d="M9 3.5v3L7.5 8.5V20h9V8.5L15 6.5v-3"/><path d="M9 3.5l3 4 3-4"/><path d="M7.5 18h9"/>',
				// Striped jumper.
				'<path d="M9 3.5c.7 1.2 1.7 1.8 3 1.8s2.3-.6 3-1.8l4 2L20.5 19l-2.5.5-1-8V20.5H7v-9l-1 8-2.5-.5L5 5.5z"/><path d="M7 10h10M7 13h10"/>',
				// Knitted hat.
				'<path d="M5.5 16c0-5 2.9-9 6.5-9s6.5 4 6.5 9"/><path d="M5 16h14v3H5z"/><path d="M12 7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/><path d="M8 16v3M11 16v3M14 16v3M17 16v3"/>',
				// A ball of yarn with needles.
				'<path d="M11 21a7 7 0 1 0 0-14 7 7 0 0 0 0 14z"/><path d="M6 10c3 1 6 4 7 9M8.5 7.5c3 2 5 6 5.5 10M5 14c2 0 5 2 6 6"/><path d="M15 2.5l6 6M19 2l-3.5 6"/>',
			),
			'kids'     => array(
				// Baby bodysuit.
				'<path d="M9 4 6 5.5 4.5 9l2.5 1 1-1.5V15l-1 3h3l1.5-2 1.5 2h3l-1-3V8.5l1 1.5 2.5-1L18 5.5 15 4c-.6 1.4-1.6 2-3 2S9.6 5.4 9 4z"/><path d="M10.5 17h.01M13.5 17h.01"/>',
				// A little dress with a collar.
				'<path d="M9 4 7 5.5 6 9l1.5.5L5.5 20h13l-2-10.5L18 9l-1-3.5L15 4"/><path d="M9 4c.4 1.5 1.5 2.3 3 2.3S14.6 5.5 15 4"/><path d="M9.5 4.5 11 7M14.5 4.5 13 7"/>',
				// Bib with a heart.
				'<path d="M8 5.5a4 4 0 0 0 8 0"/><path d="M6 6.5c-1.5 2-2 4.5-1.5 7 .7 3.5 3.8 6 7.5 6s6.8-2.5 7.5-6c.5-2.5 0-5-1.5-7"/><path d="M6 6.5 8 5.5M18 6.5l-2-1"/><path d="M12 14.5s-2-1.2-2-2.5a1 1 0 0 1 2-.5 1 1 0 0 1 2 .5c0 1.3-2 2.5-2 2.5z"/>',
				// T-shirt with a star.
				'<path d="M9 5 5.5 6.5 4 10l2.5 1 1-1.5V19h9V9.5l1 1.5 2.5-1L18.5 6.5 15 5c-.6 1.2-1.6 1.8-3 1.8S9.6 6.2 9 5z"/><path d="M12 10.5l.9 1.9 2 .3-1.5 1.4.4 2-1.8-1-1.8 1 .4-2-1.5-1.4 2-.3z"/>',
				// Baby bootie.
				'<path d="M7 4h4v8c0 2 1 3 3 3.5 2 .5 2.5 2 2.5 3S16 20 14.5 20H8c-1 0-1-1-1-2z"/><path d="M7 7h4"/><path d="M9 9.5h.01"/>',
			),
			'pyjamas'  => array(
				// Top and trousers.
				'<path d="M8 3 5 4.5 3.5 8l2 .8 1-1.3V12h11V7.5l1 1.3 2-.8L19 4.5 16 3l-4 3z"/><path d="M7 13h10l1 8h-4l-2-5-2 5H6z"/>',
				// Nightgown with a lace hem.
				'<path d="M9.5 3v3L8 9 6 20h12L16 9l-1.5-3V3"/><path d="M9.5 6c.8.8 1.6 1.2 2.5 1.2s1.7-.4 2.5-1.2"/><path d="M6 20c.7.7 1.3.7 2 0 .7.7 1.3.7 2 0 .7.7 1.3.7 2 0 .7.7 1.3.7 2 0 .7.7 1.3.7 2 0 .7.7 1.3.7 2 0"/>',
				// Sleep mask.
				'<path d="M3 10c0-1.5 1.5-2.5 3-2.5h12c1.5 0 3 1 3 2.5v2c0 2-1.5 3.5-3.5 3.5-2 0-3-1.5-4.5-1.5s-2.5 1.5-4.5 1.5C4.5 15.5 3 14 3 12z"/><path d="M3 10.5H1.5M21 10.5h1.5"/><path d="M7 12c.8.6 1.7.6 2.5 0M14.5 12c.8.6 1.7.6 2.5 0"/>',
				// Slippers.
				'<path d="M3.5 15c0-2 1.5-3.5 3.5-3.5h2l2 3h7c1.5 0 2.5 1 2.5 2.5S19.5 19.5 18 19.5H5.5c-1.2 0-2-1-2-2z"/><path d="M3.5 17.5h17"/>',
				// Pyjama trousers with dots.
				'<path d="M7 4h10l1.5 16.5h-4.5L12 10l-2 10.5H5.5z"/><path d="M7 6.5h10"/><path d="M11 6.5l-.5 2M13 6.5l.5 2"/><path d="M9 13h.01M15 15h.01M8 17h.01M16 11h.01"/>',
			),
			'workwear' => array(
				// Apron.
				'<path d="M9 3c0-1 1.3-1.5 3-1.5S15 2 15 3"/><path d="M9 3h6v4H9z"/><path d="M9 7 6 9v12h12V9l-3-2"/><path d="M9 14h6v3H9z"/><path d="M6 9.5 3.5 11M18 9.5l2.5 1.5"/>',
				// Work jacket with a pen in the pocket.
				'<path d="M8.5 3.5 4.5 5.5 3 12l2 .5V20.5h14V12.5l2-.5-1.5-6.5-4-2"/><path d="M8.5 3.5 12 7l3.5-3.5"/><path d="M12 7v13.5"/><path d="M7 10h3.5v3H7zM13.5 10H17v3h-3.5z"/><path d="M8.5 10V8"/>',
				// Boiler suit.
				'<path d="M9 3 6 4.5 5 8l2 .5v4L6 21h4.5l1.5-6 1.5 6H18l-1-8.5v-4l2-.5-1-3.5L15 3"/><path d="M12 4v11"/><path d="M7 12.5h10"/>',
				// Tool belt.
				'<path d="M3 9h18v3H3z"/><path d="M5 12h4l-.5 5h-3zM15 12h4l-.5 5h-3z"/><path d="M11 12l-.5 7M13 12l.5 7"/>',
				// Safety vest.
				'<path d="M8 3v4L6 9v12h4v-9h4v9h4V9l-2-2V3"/><path d="M6 15h4M14 15h4M6 18h4M14 18h4"/>',
			),
			'bag'      => array(
				// Tote bag.
				'<path d="M5 8h14l-1 13H6z"/><path d="M8 8c0-3 1.5-5 4-5s4 2 4 5"/>',
				// Backpack.
				'<path d="M6.5 8a5.5 5.5 0 0 1 11 0v12.5h-11z"/><path d="M10 3.5a2 2 0 0 1 4 0"/><path d="M9 14h6v4H9z"/><path d="M6.5 11h11"/>',
				// Clutch.
				'<path d="M3.5 9h17v9h-17z"/><path d="M3.5 9 12 14l8.5-5"/><path d="M12 14v1.5"/>',
				// Crossbody bag.
				'<path d="M5 3l7 6 7-6"/><path d="M6 9h12v10H6z"/><path d="M6 9v4c0 1 1 1.5 2 1.5h8c1 0 2-.5 2-1.5V9"/><path d="M12 14.5V16"/>',
				// Drawstring bag.
				'<path d="M7 7c-1 4-1.5 9-1 14h12c.5-5 0-10-1-14"/><path d="M7 7c1.5 1 3.2 1.5 5 1.5S15.5 8 17 7"/><path d="M10 8.3 8.5 3.5M14 8.3l1.5-4.8"/>',
			),
			'home'     => array(
				// Table with a tablecloth.
				'<path d="M3 9h18l-2 4H5z"/><path d="M6 13v8M18 13v8"/><path d="M5 13c.5 2 1 2.5 1 2.5M19 13c-.5 2-1 2.5-1 2.5"/>',
				// Curtains.
				'<path d="M3 3.5h18"/><path d="M5 3.5c0 6 .5 12 2.5 17h3c-1.5-5-1.5-11-1.5-17M19 3.5c0 6-.5 12-2.5 17h-3c1.5-5 1.5-11 1.5-17"/><path d="M6.5 13h3M14.5 13h3"/>',
				// Bed with a duvet.
				'<path d="M3 10h18v9H3z"/><path d="M5 7h6c.8 0 1.5.7 1.5 1.5V10h-9V8.5C3.5 7.7 4.2 7 5 7z"/><path d="M3 19v2M21 19v2"/><path d="M9 10v9M15 10v9"/>',
				// Tea towel on a rail.
				'<path d="M5 4h14"/><path d="M7 4v15h10V4"/><path d="M7 7.5h10M7 9h10"/><path d="M10 4V2.5h4V4"/>',
				// Round cushion.
				'<path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/><path d="M12 12h.01"/><path d="M12 4v4M12 16v4M4 12h4M16 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M17.7 6.3l-2.8 2.8M9.1 14.9l-2.8 2.8"/>',
			),
		);
	}

	/**
	 * The chosen variant of a kind (0 = the standard icon).
	 *
	 * @param string $key Kind of clothing.
	 * @return int
	 */
	public static function chosen( $key ) {
		return min( 5, absint( AIMP_Settings::get_text( 'ideal_icon_' . $key, '0' ) ) );
	}

	/**
	 * The SVG content of a variant, or '' for the standard icon.
	 *
	 * @param string $key     Kind of clothing.
	 * @param int    $variant 1–5.
	 * @return string
	 */
	public static function variant( $key, $variant ) {
		$all = self::alternatives();
		return $variant >= 1 && isset( $all[ $key ][ $variant - 1 ] ) ? $all[ $key ][ $variant - 1 ] : '';
	}

	public static function register_settings() {
		add_settings_section(
			'aimp_ideal_icons',
			__( 'Ideal for icons', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_section' ),
			AIMP_Settings::PAGE
		);
	}

	public static function render_section() {
		echo '<p>' . esc_html__( 'Choose per kind of clothing which icon the "Ideal for" cards show on the product pages.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<table class="form-table aimp-icon-choice" role="presentation"><tbody>';
		foreach ( AIMP_Notions::ideal_for_options() as $key => $label ) {
			$chosen = self::chosen( $key );
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><div class="aimp-icon-choice-row">';
			for ( $n = 0; $n <= 5; $n++ ) {
				printf(
					'<label class="aimp-icon-choice-tile"><input type="radio" name="%1$s[ideal_icon_%2$s]" value="%3$d"%4$s><span>%5$s</span></label>',
					esc_attr( AIMP_Settings::OPTION ),
					esc_attr( $key ),
					(int) $n,
					checked( $chosen, $n, false ),
					AIMP_Product_Page::clothing_icon( $key, $n ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG.
				);
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The choices from the settings form (called from AIMP_Settings::sanitize()).
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array();
		foreach ( array_keys( AIMP_Notions::ideal_for_options() ) as $key ) {
			$clean[ 'ideal_icon_' . $key ] = isset( $input[ 'ideal_icon_' . $key ] ) ? (string) min( 5, absint( $input[ 'ideal_icon_' . $key ] ) ) : '0';
		}
		return $clean;
	}
}
