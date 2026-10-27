<?php
/**
 * The texts of the contact page and the home page blocks: kept in their own option (aimp_blocks), changed in the
 * Design studio's Texts block (draft in the preview, written on Publish). Not in aimp_settings: the settings page
 * rebuilds that option on every save and would drop them.
 *
 * Per language: key_nl, key_fr, key_en. Lists (subjects) are lines of "NL | FR | EN"; pairs (questions and answers,
 * quotes and names) are lines of "NL | FR | EN || NL | FR | EN".
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Blocks {

	const OPTION = 'aimp_blocks';

	/**
	 * A stored text: the studio's draft in its preview, else the published one; null when never saved.
	 *
	 * @param string $key Key.
	 * @return string|null
	 */
	public static function raw( $key ) {
		$draft = class_exists( 'AIMP_Design', false ) ? AIMP_Design::setting_draft( self::OPTION, $key ) : null;
		if ( null !== $draft ) {
			return $draft;
		}
		$values = (array) get_option( self::OPTION, array() );
		return isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : null;
	}

	/**
	 * A text in the visitor's language, or the standard text.
	 *
	 * @param string $key Key (without the language).
	 * @return string
	 */
	public static function text( $key ) {
		$text = trim( (string) self::raw( $key . '_' . AIMP_I18n::current() ) );
		if ( '' !== $text ) {
			return $text;
		}
		$defaults = self::default_texts();
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * A list: one text per line, in the visitor's language (the standard list until it is changed).
	 *
	 * @param string $key Key.
	 * @return string[]
	 */
	public static function lines( $key ) {
		$raw   = self::raw( $key );
		$raw   = null === $raw ? self::stored_default( $key ) : $raw;
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( AIMP_Settings::localized( $line ) );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * Pairs (question and answer, quote and name) in the visitor's language: [ [ a, b ], … ].
	 *
	 * @param string $key Key.
	 * @return array[]
	 */
	public static function pairs( $key ) {
		$raw   = self::raw( $key );
		$raw   = null === $raw ? self::stored_default( $key ) : $raw;
		$pairs = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$parts = explode( '||', $line, 2 );
			$a     = trim( AIMP_Settings::localized( $parts[0] ) );
			$b     = isset( $parts[1] ) ? trim( AIMP_Settings::localized( $parts[1] ) ) : '';
			if ( '' !== $a ) {
				$pairs[] = array( $a, $b );
			}
		}
		return $pairs;
	}

	/**
	 * The standard texts (in the current language): key => text.
	 *
	 * @return array
	 */
	public static function default_texts() {
		return array(
			// Contact page.
			'ct_title'        => __( 'Contact us', 'atelier-irisee-master-plugin' ),
			'ct_intro'        => __( 'A question about a pattern, a fabric or your order? Send us a message, we are happy to help.', 'atelier-irisee-master-plugin' ),
			'ct_hours'        => __( 'We usually answer within one working day.', 'atelier-irisee-master-plugin' ),
			'ct_form_title'   => __( 'Send us a message', 'atelier-irisee-master-plugin' ),
			'ct_success'      => __( 'Thank you! Your message is on its way. You will receive a copy by email.', 'atelier-irisee-master-plugin' ),
			'ct_faq_title'    => __( 'Frequently asked questions', 'atelier-irisee-master-plugin' ),
			// Home page blocks.
			'hc_title'        => __( 'Shop by category', 'atelier-irisee-master-plugin' ),
			'rv_title'        => __( 'What our customers say', 'atelier-irisee-master-plugin' ),
			'mk_kicker'       => __( 'Meet the maker', 'atelier-irisee-master-plugin' ),
			'mk_title'        => __( 'The face behind Atelier Irisée', 'atelier-irisee-master-plugin' ),
			'mk_text'         => __( 'Atelier Irisée started from a love of sewing: beautiful patterns, beautiful fabrics and everything that goes with them, chosen with care so you can start sewing right away.', 'atelier-irisee-master-plugin' ),
			'mk_button'       => __( 'Read our story', 'atelier-irisee-master-plugin' ),
			'nw_title'        => __( 'New in', 'atelier-irisee-master-plugin' ),
			'nw_button'       => __( 'View all', 'atelier-irisee-master-plugin' ),
			'hn_title'        => __( 'Stay in the loop', 'atelier-irisee-master-plugin' ),
			'hn_text'         => __( 'New fabrics, patterns and sewing tips in your mailbox.', 'atelier-irisee-master-plugin' ),
			/* translators: {percent} stays as it is: it becomes the welcome discount, e.g. 10 */
			'hn_offer'        => __( '{percent}% off your first order', 'atelier-irisee-master-plugin' ),
			'hn_offer_text'   => __( 'Create an account and receive your personal welcome code.', 'atelier-irisee-master-plugin' ),
			'hn_offer_button' => __( 'Create an account', 'atelier-irisee-master-plugin' ),
			'fq_title'        => __( 'Frequently asked questions', 'atelier-irisee-master-plugin' ),
			'fq_button'       => __( 'More questions? Contact us', 'atelier-irisee-master-plugin' ),
			'br_title'        => __( 'Our brands', 'atelier-irisee-master-plugin' ),
			// Gift card promo.
			'gp_kicker'       => __( 'Gift card', 'atelier-irisee-master-plugin' ),
			'gp_title'        => __( 'Give creativity as a gift', 'atelier-irisee-master-plugin' ),
			'gp_text'         => __( 'Fabrics, patterns, haberdashery or a whole sewing kit: they choose what they make. By email, on the day you choose, or printed by post.', 'atelier-irisee-master-plugin' ),
			'gp_button'       => __( 'Choose a gift card', 'atelier-irisee-master-plugin' ),
			'gp_other'        => __( 'Another amount', 'atelier-irisee-master-plugin' ),
			// Instagram.
			'ig_title'        => __( 'Follow us on Instagram', 'atelier-irisee-master-plugin' ),
			/* translators: {account} stays as it is: it becomes the Instagram account, e.g. @atelier.irisee */
			'ig_button'       => __( 'Follow {account}', 'atelier-irisee-master-plugin' ),
			// About page (the owner's own story; an empty line between paragraphs).
			'ab_kicker'       => __( 'About us', 'atelier-irisee-master-plugin' ),
			'ab_title'        => __( 'The story behind Atelier Irisée', 'atelier-irisee-master-plugin' ),
			'ab_intro'        => __( 'Fabrics, patterns and everything you need to sew clothes with a story.', 'atelier-irisee-master-plugin' ),
			'ab_c1_title'     => __( 'It all starts with the right light…', 'atelier-irisee-master-plugin' ),
			'ab_c1_text'      => implode(
				"\n\n",
				array(
					__( 'Do you know that magical moment when sunlight falls on a fabric and a whole new palette of colours suddenly appears? That is irisée: iridescent, pearlescent and surprising from every angle. Exactly that magic led me to found Atelier Irisée. A place where we turn every piece of fabric into a unique work of art and make your dream wardrobe come true.', 'atelier-irisee-master-plugin' ),
					__( 'My love for textiles and fashion started early, during my fashion studies. The real spark, though, only came a few years later, when I started making clothes myself. The thrill of a perfectly fitting garment, made from a quality fabric with a print you will not see anywhere else? Priceless! That intense sewing itch and my love for colour are what I want to share with you through this web shop.', 'atelier-irisee-master-plugin' ),
				)
			),
			'ab_c1_button'    => '',
			'ab_c2_title'     => __( 'Fashion as a reflection of your character', 'atelier-irisee-master-plugin' ),
			'ab_c2_text'      => implode(
				"\n\n",
				array(
					__( 'To me, fashion is a pure reflection of your personality. Boring? Uninspired? Words we do not know here. I do not believe in one specific style. One day it is romantic, the next day tough, and very often an eclectic mix of different styles. That is exactly what makes an outfit exuberant, striking and unique.', 'atelier-irisee-master-plugin' ),
					__( 'I love colour and prints, and yes, I even like to combine different prints in one single outfit! As soon as I see a pattern or a piece of fabric, a bomb of creativity explodes in my head and I immediately have a whole lot of ideas about what to make with it. I use that creative eye to select the most inspiring materials for you.', 'atelier-irisee-master-plugin' ),
				)
			),
			'ab_c2_button'    => '',
			'ab_c3_title'     => __( 'Meet Klaus, our furry mascot!', 'atelier-irisee-master-plugin' ),
			'ab_c3_text'      => implode(
				"\n\n",
				array(
					__( 'Say Atelier Irisée and you say Klaus in the same breath. This enthusiastic Australian Shepherd is not only my loyal four-legged friend, but also the official mascot of the web shop.', 'atelier-irisee-master-plugin' ),
					__( 'Ever since Klaus came into my life, he always wears a handmade bandana that perfectly matches my own outfits. Because that matching look gets so many lovely reactions, you will find a special collection of trendy dog bandanas in the web shop. So the two of you will soon shine together in style!', 'atelier-irisee-master-plugin' ),
					__( 'Whether you are just learning to sew a straight line or have years of experience: I am happy to help you, with lots of love, to add colour to your wardrobe (and your dog\'s!).', 'atelier-irisee-master-plugin' ),
				)
			),
			'ab_c3_button'    => __( 'Discover the dog bandanas', 'atelier-irisee-master-plugin' ),
			'ab_c4_title'     => '',
			'ab_c4_text'      => '',
			'ab_c4_button'    => '',
			'ab_quote'        => __( 'Get inspired and make clothes with a story. Your story.', 'atelier-irisee-master-plugin' ),
			'ab_sign'         => __( 'Happy sewing,', 'atelier-irisee-master-plugin' ) . "\n" . __( 'Dorien & a well-deserved paw from Klaus 🐾', 'atelier-irisee-master-plugin' ),
			'ab_shop_button'  => __( 'Shop', 'atelier-irisee-master-plugin' ),
			'ab_kit_button'   => __( 'Create your sewing kit', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * What the shop stands for (About page), standard tiles (current language).
	 *
	 * @return array[]
	 */
	public static function default_values() {
		return array(
			array( __( 'Prints you will not find anywhere else', 'atelier-irisee-master-plugin' ), __( 'Quality fabrics with prints, chosen with a creative eye.', 'atelier-irisee-master-plugin' ) ),
			array( __( 'Everything in one sewing kit', 'atelier-irisee-master-plugin' ), __( 'Pattern, fabric and haberdashery that match, in one package.', 'atelier-irisee-master-plugin' ) ),
			array( __( 'Colour and character', 'atelier-irisee-master-plugin' ), __( 'Romantic, tough or an eclectic mix: your clothes, your style.', 'atelier-irisee-master-plugin' ) ),
			array( __( 'Help with lots of love', 'atelier-irisee-master-plugin' ), __( 'Whether you are just starting or have been sewing for years, we are happy to help.', 'atelier-irisee-master-plugin' ) ),
		);
	}

	/**
	 * The standard subjects of the contact form (current language).
	 *
	 * @return string[]
	 */
	public static function default_subjects() {
		return array(
			__( 'A question about my order', 'atelier-irisee-master-plugin' ),
			__( 'A question about a product', 'atelier-irisee-master-plugin' ),
			__( 'Help with a sewing project kit', 'atelier-irisee-master-plugin' ),
			__( 'Returns and exchanges', 'atelier-irisee-master-plugin' ),
			__( 'Collaboration', 'atelier-irisee-master-plugin' ),
			__( 'Something else', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The standard questions and answers (current language).
	 *
	 * @return array[]
	 */
	public static function default_faq() {
		return array(
			array(
				__( 'How does a sewing project kit work?', 'atelier-irisee-master-plugin' ),
				__( 'Choose a pattern and your size, then a fabric that suits the pattern and the haberdashery that fits. We calculate the amounts and send everything together in one package.', 'atelier-irisee-master-plugin' ),
			),
			array(
				__( 'How much fabric do I need?', 'atelier-irisee-master-plugin' ),
				__( 'In the customizer you do not have to calculate anything: the amount follows from the pattern and your size. Loose fabric is sold per 10 cm.', 'atelier-irisee-master-plugin' ),
			),
			array(
				__( 'When will my order arrive?', 'atelier-irisee-master-plugin' ),
				__( 'The expected delivery time is shown on every product page under "Add to cart" and in your cart. You receive an email with the track and trace code as soon as your package leaves.', 'atelier-irisee-master-plugin' ),
			),
			array(
				__( 'Can I return something?', 'atelier-irisee-master-plugin' ),
				__( 'Contact us first with your order number; we will tell you how to return it. Fabric that was cut to length for you cannot always be returned.', 'atelier-irisee-master-plugin' ),
			),
			array(
				__( 'Do you have gift cards?', 'atelier-irisee-master-plugin' ),
				__( 'Yes. Choose an amount and a design, and the gift card is sent by email.', 'atelier-irisee-master-plugin' ),
			),
		);
	}

	/**
	 * The standard value of a list or pairs setting, in all languages ("NL | FR | EN" per text), as it is stored.
	 *
	 * @param string $key ct_subjects or faq ('' for others).
	 * @return string
	 */
	public static function stored_default( $key ) {
		$source = array(
			'ct_subjects' => array( __CLASS__, 'default_subjects' ),
			'faq'         => array( __CLASS__, 'default_faq' ),
			'ab_values'   => array( __CLASS__, 'default_values' ),
		);
		if ( ! isset( $source[ $key ] ) ) {
			return '';
		}
		$per_language = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$per_language[] = AIMP_I18n::with_language( $code, $source[ $key ] );
		}
		$lines = array();
		foreach ( $per_language[0] as $i => $item ) {
			$side = function ( $n ) use ( $per_language, $i ) {
				$texts = array();
				foreach ( $per_language as $list ) {
					$texts[] = is_array( $list[ $i ] ) ? $list[ $i ][ $n ] : $list[ $i ];
				}
				return implode( ' | ', $texts );
			};
			$lines[] = is_array( $item ) ? $side( 0 ) . ' || ' . $side( 1 ) : $side( 0 );
		}
		return implode( "\n", $lines );
	}

	/**
	 * A Design studio setting of a part.
	 *
	 * @param string $part    Part.
	 * @param string $key     Setting.
	 * @param mixed  $default When it is not set.
	 * @return mixed
	 */
	public static function design( $part, $key, $default = null ) {
		return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( $part, $key, $default ) : $default;
	}

	/**
	 * Whether the page being shown has one of these shortcodes (to load the stylesheet in the head).
	 *
	 * @param string[] $tags Shortcodes.
	 * @return bool
	 */
	public static function page_has( $tags ) {
		$post = get_post();
		if ( ! is_singular() || ! $post ) {
			return false;
		}
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The FAQ as an accordion (the contact page and the FAQ block).
	 *
	 * @param array[] $pairs Questions and answers.
	 * @return string
	 */
	public static function faq_html( $pairs ) {
		if ( ! $pairs ) {
			return '';
		}
		// Design studio (FAQ): the first question open, and only one open at a time (details with one name).
		$first  = (bool) self::design( 'faq', 'faq_open' );
		$single = self::design( 'faq', 'faq_single' ) ? ' name="' . esc_attr( wp_unique_id( 'aimp-faq-' ) ) . '"' : '';
		$html   = '<div class="aimp-faq">';
		foreach ( $pairs as $i => $pair ) {
			$html .= '<details class="aimp-faq-item"' . $single . ( $first && 0 === $i ? ' open' : '' ) . '>' .
				'<summary class="aimp-faq-question"><span>' . esc_html( $pair[0] ) . '</span><span class="aimp-faq-icon" aria-hidden="true"></span></summary>' .
				'<div class="aimp-faq-answer">' . wpautop( esc_html( $pair[1] ) ) . '</div></details>';
		}
		return $html . '</div>';
	}

	/**
	 * A gold line icon (24 × 24).
	 *
	 * @param string $name mail, phone, pin, clock, chat, copy, star, arrow-left, arrow-right, check.
	 * @return string SVG.
	 */
	public static function icon( $name ) {
		$paths = array(
			'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 6.5 12 13l8.5-6.5"/>',
			'phone'       => '<path d="M6.6 3.5h2.6l1.4 4.2-2 1.4a12 12 0 0 0 6.3 6.3l1.4-2 4.2 1.4v2.6a2 2 0 0 1-2.2 2A16.6 16.6 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
			'pin'         => '<path d="M12 21s-6.5-5.8-6.5-11a6.5 6.5 0 0 1 13 0c0 5.2-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
			'clock'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'chat'        => '<path d="M20 11.5a7.5 7.5 0 0 1-11 6.6L4.5 19.5l1.4-4.2A7.5 7.5 0 1 1 20 11.5z"/><path d="M9.2 9.5c.3 2 1.8 3.6 3.9 4.3"/>',
			'copy'        => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5"/>',
			'star'        => '<path d="M12 3.6l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.2-4.1 5.8-.8z"/>',
			'arrow-left'  => '<path d="M14.5 6 8.5 12l6 6"/>',
			'arrow-right' => '<path d="M9.5 6l6 6-6 6"/>',
			'check'       => '<circle cx="12" cy="12" r="8.5"/><path d="M8.3 12.3l2.5 2.5 5-5.3"/>',
			'sparkle'     => '<path d="M11 3.5l1.7 4.6 4.6 1.7-4.6 1.7L11 16.1l-1.7-4.6-4.6-1.7 4.6-1.7z"/><path d="M18 14.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>',
			'kit'         => '<path d="M4 10h16v10H4z"/><path d="M3 7h18v3H3zM12 7v13"/><path d="M12 7c-2-3.5-6-3.5-5-1s5 1 5 1zm0 0c2-3.5 6-3.5 5-1s-5 1-5 1z"/>',
			'palette'     => '<path d="M12 3.5a8.5 8.5 0 1 0 0 17c1.4 0 1.9-1 1.4-2.1-.6-1.2.1-2.4 1.5-2.4H17a3.5 3.5 0 0 0 3.5-3.5c0-5-3.8-9-8.5-9z"/><circle cx="7.8" cy="11" r="1"/><circle cx="10.5" cy="7.3" r="1"/><circle cx="15" cy="7.8" r="1"/>',
			'heart'       => '<path d="M12 20s-7.5-4.5-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.5-7.5 10-7.5 10z"/>',
			'paw'         => '<ellipse cx="12" cy="16" rx="4" ry="3.3"/><circle cx="6.3" cy="11" r="1.7"/><circle cx="9.5" cy="7" r="1.7"/><circle cx="14.5" cy="7" r="1.7"/><circle cx="17.7" cy="11" r="1.7"/>',
		);
		return '<svg class="aimp-icon aimp-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' ) . '</svg>';
	}
}
