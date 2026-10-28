<?php
/**
 * The dog bandana: a reversible triangle in five sizes with three ways to finish it (tie-on, over the collar, with a
 * snap). One description of the pieces and the guide feeds the page ([atelier_irisee_bandana]), the PDF guide, the
 * PDF template (real size, tiled over A4 sheets) and the customizer's "matching dog bandana" question.
 *
 * Measurements are in cm, y going down from the top edge; x = 0 is the middle (the fold).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bandana {

	const OPTION = 'aimp_bandana';

	/** Seam allowance (included in the template). */
	const SA = 1;

	/** Height of the ends of the tie-on and snap styles, and of the collar band. */
	const END     = 4;
	const CHANNEL = 4;

	/** Tie-on: the neck plus this for the knot. Snaps: the neck plus this overlap. */
	const TIE_EXTRA    = 30;
	const SNAP_OVERLAP = 4;

	/** Turning gap in the top edge. */
	const GAP = 6;

	public static function init() {
		AIMP_Bandana_Page::init();
		AIMP_Bandana_Pictures::init();
	}

	/* ------------------------------------------------------------------
	 * Settings (Atelier Irisee → Dog bandana)
	 * ------------------------------------------------------------------ */

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			// The whole dog bandana feature: the guide page, its PDFs, the order links and the customizer question.
			'enabled'    => 1,
			// The bandana pattern product (the PDF is sold through it) and the page with the guide.
			'product'    => 0,
			'page'       => 0,
			// The customizer asks at the end whether to add it, with this much extra of the kit's fabric.
			'addon'      => 1,
			'fabric_cm'  => 50,
			'thread'     => 1,
			'thread_qty' => 1,
			'band'       => 1,
			'band_cm'    => 120,
		);
	}

	/**
	 * @param string $key Setting.
	 * @return int
	 */
	public static function opt( $key ) {
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $options[ $key ] ) ? absint( $options[ $key ] ) : 0;
	}

	/**
	 * Whether the dog bandana is switched on (Settings → Dog bandana).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) self::opt( 'enabled' );
	}

	/**
	 * The bandana pattern product when it can be bought (and the bandana is switched on).
	 *
	 * @return WC_Product|null
	 */
	public static function product() {
		$product = self::enabled() && self::opt( 'product' ) ? wc_get_product( self::opt( 'product' ) ) : null;
		return $product && 'publish' === $product->get_status() && $product->is_purchasable() && $product->is_type( 'simple' ) ? $product : null;
	}

	/**
	 * The guide page's address, or ''.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page = self::opt( 'page' );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';
	}

	/* ------------------------------------------------------------------
	 * Who may see the whole guide
	 * ------------------------------------------------------------------ */

	/**
	 * An order that bought the bandana pattern, from the link in its email (?aimp_order=…&aimp_key=…).
	 *
	 * @return WC_Order|null
	 */
	public static function order_from_link() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the order key is the proof.
		$id  = isset( $_GET['aimp_order'] ) ? absint( $_GET['aimp_order'] ) : 0;
		$key = isset( $_GET['aimp_key'] ) ? sanitize_text_field( wp_unslash( $_GET['aimp_key'] ) ) : '';
		// phpcs:enable
		if ( ! $id || '' === $key ) {
			return null;
		}
		$order = wc_get_order( $id );
		return $order && hash_equals( (string) $order->get_order_key(), $key ) && self::order_has_pattern( $order ) ? $order : null;
	}

	/**
	 * Whether a paid order contains the bandana pattern.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function order_has_pattern( $order ) {
		$product = self::opt( 'product' );
		if ( ! self::enabled() || ! $product || ! $order || ! $order->is_paid() ) {
			return false;
		}
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === $product ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether this visitor may see the whole guide and download the PDFs: shop managers, customers who bought the
	 * pattern, and the link of a paid order with it.
	 *
	 * @return bool
	 */
	public static function can_view() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		$product = self::opt( 'product' );
		if ( ! $product ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( wc_customer_bought_product( $user->user_email, $user->ID, $product ) ) {
				return true;
			}
		}
		return null !== self::order_from_link();
	}

	/**
	 * The guide with the access of an order (for its email and the order page).
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function order_link( $order ) {
		$url = self::page_url();
		return '' === $url ? '' : add_query_arg(
			array(
				'aimp_order' => $order->get_id(),
				'aimp_key'   => $order->get_order_key(),
			),
			$url
		);
	}

	/* ------------------------------------------------------------------
	 * Sizes and pieces
	 * ------------------------------------------------------------------ */

	/**
	 * Size => [ label, neck from, neck to, body width, depth ] (finished, cm).
	 *
	 * @return array
	 */
	public static function sizes() {
		return array(
			'xs' => array( 'XS', 20, 28, 22, 15 ),
			's'  => array( 'S', 28, 36, 28, 19 ),
			'm'  => array( 'M', 36, 44, 34, 23 ),
			'l'  => array( 'L', 44, 52, 40, 27 ),
			'xl' => array( 'XL', 52, 62, 46, 31 ),
		);
	}

	/**
	 * Style => name.
	 *
	 * @return array
	 */
	public static function styles() {
		return array(
			'tie'    => __( 'Tie-on', 'atelier-irisee-master-plugin' ),
			'collar' => __( 'Over the collar', 'atelier-irisee-master-plugin' ),
			'snap'   => __( 'With a snap', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The finished outline of a whole piece (clockwise, starting in the middle of the top edge), and the index of its
	 * bottom point.
	 *
	 * @param string $size  Size.
	 * @param string $style Style.
	 * @return array [ points, bottom ]
	 */
	public static function outline( $size, $style ) {
		$s = self::sizes()[ $size ];
		$b = $s[3] / 2;
		$h = $s[4];
		if ( 'collar' === $style ) {
			$c = self::CHANNEL;
			return array( array( array( 0, 0 ), array( $b, 0 ), array( $b, $c ), array( 0, $c + $h ), array( -$b, $c ), array( -$b, 0 ) ), 3 );
		}
		$w = ( $s[2] + ( 'tie' === $style ? self::TIE_EXTRA : self::SNAP_OVERLAP ) ) / 2;
		$e = self::END;
		return array( array( array( 0, 0 ), array( $w, 0 ), array( $w, $e ), array( $b, $e ), array( 0, $h ), array( -$b, $e ), array( -$w, $e ), array( -$w, 0 ) ), 4 );
	}

	/**
	 * Finished width × depth (cm).
	 *
	 * @return float[]
	 */
	public static function dimensions( $size, $style ) {
		$o = self::outline( $size, $style );
		return array( $o[0][1][0] * 2, $o[0][ $o[1] ][1] );
	}

	/**
	 * A closed outline moved outwards by $d (a seam allowance), with mitred corners.
	 *
	 * @param array $points Points.
	 * @param float $d      Distance.
	 * @return array
	 */
	public static function offset( $points, $d ) {
		$n    = count( $points );
		$area = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$a     = $points[ $i ];
			$b     = $points[ ( $i + 1 ) % $n ];
			$area += $a[0] * $b[1] - $b[0] * $a[1];
		}
		$sign    = $area > 0 ? 1 : -1;
		$normals = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$a          = $points[ $i ];
			$b          = $points[ ( $i + 1 ) % $n ];
			$dx         = $b[0] - $a[0];
			$dy         = $b[1] - $a[1];
			$len        = max( 1e-9, sqrt( $dx * $dx + $dy * $dy ) );
			$normals[] = array( $sign * $dy / $len, -$sign * $dx / $len );
		}
		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$n1  = $normals[ ( $i - 1 + $n ) % $n ];
			$n2  = $normals[ $i ];
			$dot = $n1[0] * $n2[0] + $n1[1] * $n2[1];
			$k   = $d / max( 0.2, 1 + $dot );
			$out[] = array( $points[ $i ][0] + ( $n1[0] + $n2[0] ) * $k, $points[ $i ][1] + ( $n1[1] + $n2[1] ) * $k );
		}
		return $out;
	}

	/**
	 * The half piece for the template (from the fold to the right): finished line and cutting line.
	 *
	 * @return array [ finished points, cutting points ]
	 */
	public static function half( $size, $style ) {
		list( $points, $bottom ) = self::outline( $size, $style );
		$cut = self::offset( $points, self::SA );
		return array( array_slice( $points, 0, $bottom + 1 ), array_slice( $cut, 0, $bottom + 1 ) );
	}

	/**
	 * Fabric needed for one bandana (both sides from the same fabric, 140 cm wide, folded): cm, rounded up to 5.
	 *
	 * @param string $size Size.
	 * @return int
	 */
	public static function fabric_need( $size ) {
		$need = 0;
		foreach ( array_keys( self::styles() ) as $style ) {
			list( $width, $depth ) = self::dimensions( $size, $style );
			// Two half pieces on the folded fabric (68 cm usable): side by side when they fit, otherwise under each
			// other; plus a little to straighten the edge.
			$rows = 68 >= 2 * ( $width / 2 + self::SA ) ? 1 : 2;
			$need = max( $need, $rows * ( $depth + 2 * self::SA ) + 3 );
		}
		return (int) ( ceil( $need / 5 ) * 5 );
	}

	/* ------------------------------------------------------------------
	 * Colours of the site
	 * ------------------------------------------------------------------ */

	/**
	 * The site's colours (Design studio → Whole site), as hex: brown (text), gold (lines), gold_text (titles), tint.
	 *
	 * @return array
	 */
	public static function palette() {
		$colors = array(
			'brown'     => '#613907',
			'gold'      => '#b38f4f',
			'gold_text' => '#8a6a33',
		);
		if ( class_exists( 'AIMP_Design' ) ) {
			$elements = AIMP_Design::elements();
			foreach ( AIMP_Design::live() as $id => $values ) {
				if ( ! isset( $elements[ $id ] ) || '' !== $elements[ $id ]['selector'] || ! is_array( $values ) ) {
					continue;
				}
				foreach ( array_keys( $colors ) as $key ) {
					// A hex value, or a link to a Divi colour with the hex after the comma.
					if ( isset( $values[ $key ] ) && preg_match( '/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\)?\s*$/', (string) $values[ $key ], $m ) ) {
						$colors[ $key ] = '#' . $m[1];
					}
				}
			}
		}
		$colors['tint']  = self::mix( $colors['gold'], 0.09 );
		$colors['light'] = self::mix( $colors['gold'], 0.45 );
		// The logo's ribbon: pink and purple, only as accents.
		list( $colors['pink'], $colors['purple'] ) = class_exists( 'AIMP_Bandana_Pictures' ) ? AIMP_Bandana_Pictures::colors() : array( '#e2bfc9', '#79416d' );
		$colors['blush']                           = self::mix( $colors['pink'], 0.3 );
		return $colors;
	}

	/**
	 * A colour mixed with white: $share of the colour.
	 */
	private static function mix( $hex, $share ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$c    = hexdec( substr( $hex, $i * 2, 2 ) );
			$out .= sprintf( '%02x', (int) round( 255 - ( 255 - $c ) * $share ) );
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * The guide
	 * ------------------------------------------------------------------ */

	/**
	 * The size chart: header and rows.
	 *
	 * @return array [ head, rows ]
	 */
	public static function size_chart() {
		$styles = self::styles();
		$head   = array( __( 'Size', 'atelier-irisee-master-plugin' ), __( 'Neck', 'atelier-irisee-master-plugin' ) );
		foreach ( $styles as $name ) {
			$head[] = $name;
		}
		$head[] = __( 'Fabric', 'atelier-irisee-master-plugin' );
		$rows   = array();
		foreach ( self::sizes() as $key => $s ) {
			/* translators: 1: from, 2: to (cm) */
			$row = array( $s[0], sprintf( __( '%1$s–%2$s cm', 'atelier-irisee-master-plugin' ), $s[1], $s[2] ) );
			foreach ( array_keys( $styles ) as $style ) {
				$d     = self::dimensions( $key, $style );
				$row[] = AIMP_I18n::number( $d[0] ) . ' × ' . AIMP_I18n::number( $d[1] ) . ' cm';
			}
			/* translators: %s: centimetres */
			$row[]  = sprintf( __( '%s cm', 'atelier-irisee-master-plugin' ), self::fabric_need( $key ) );
			$rows[] = $row;
		}
		return array( $head, $rows );
	}

	/**
	 * The guide in parts, for the page and the PDF. Each part: [ id, title, public, blocks, icon ]. A block is one of:
	 *  [ 'p', text ], [ 'list', items ], [ 'items', [ [ icon, text ] ] ] (a list with a small drawing per item),
	 *  [ 'photo', picture slot, caption, drawing without the picture ], [ 'figure', name, caption ],
	 *  [ 'steps', [ [ title, text, drawing, extra ] ] ] with extra: slot (picture), why, do (sub-steps), check,
	 *  mistake, note, time; [ 'cards', [ [ slot, drawing, title, text ] ] ], [ 'journey', [ [ icon, title, part ] ] ],
	 *  [ 'dodont', do items, avoid items ], [ 'sizes' ], [ 'table', head, rows, note ], [ 'facts', [ [ label, value ] ] ],
	 *  [ 'tip', text ] or [ 'terms', [ [ term, text ] ] ].
	 *
	 * @return array
	 */
	public static function guide() {
		list( $head, $rows ) = self::size_chart();
		$styles              = self::styles();
		return array(
			array(
				'intro',
				'',
				true,
				array(
					array( 'p', __( 'A reversible dog bandana in five sizes, with three ways to wear it: tied on, slid over the collar or closed with a snap. A quick project for beginners, and a lovely way to use the rest of your fabric: your dog wears the same fabric as you.', 'atelier-irisee-master-plugin' ) ),
					array(
						'facts',
						array(
							array( __( 'Level', 'atelier-irisee-master-plugin' ), __( 'Beginner', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Time', 'atelier-irisee-master-plugin' ), __( '1 to 1.5 hours', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Sizes', 'atelier-irisee-master-plugin' ), 'XS – XL' ),
							array( __( 'Seam allowance', 'atelier-irisee-master-plugin' ), __( '1 cm, included', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
				'heart',
			),
			array(
				'overview',
				__( 'Your bandana in six steps', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array(
						'journey',
						array(
							array( 'tape', __( 'Measure', 'atelier-irisee-master-plugin' ), 'size' ),
							array( 'paper', __( 'Print', 'atelier-irisee-master-plugin' ), 'print' ),
							array( 'scissors', __( 'Cut', 'atelier-irisee-master-plugin' ), 'cutting' ),
							array( 'machine', __( 'Sew', 'atelier-irisee-master-plugin' ), 'sewing' ),
							array( 'iron', __( 'Turn and press', 'atelier-irisee-master-plugin' ), 'sewing' ),
							array( 'bandana', __( 'Finish', 'atelier-irisee-master-plugin' ), 'finish' ),
						),
					),
					array( 'p', __( 'Never sewn before? No problem. This guide explains every step, every word and every tool. Read a step completely before you start it, look at its picture and drawing, and take your time: there is no hurry.', 'atelier-irisee-master-plugin' ) ),
				),
				'heart',
			),
			array(
				'styles',
				__( 'Three ways to wear it', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array(
						'cards',
						array(
							array( 'style_tie', 'tie', $styles['tie'], __( 'The classic: long ends that you knot at the back of the neck. Fits a wide range of necks and needs no collar.', 'atelier-irisee-master-plugin' ) ),
							array( 'style_collar', 'collar', $styles['collar'], __( 'A channel along the top edge slides over your dog\'s own collar, so the bandana stays in place and never pulls tight.', 'atelier-irisee-master-plugin' ) ),
							array( 'style_snap', 'snap', $styles['snap'], __( 'Short ends that overlap and close with a plastic snap: quick to put on and take off.', 'atelier-irisee-master-plugin' ) ),
						),
					),
					array( 'tip', __( 'Not sure which one to choose? The tie-on style fits the widest range of necks and is the easiest to make.', 'atelier-irisee-master-plugin' ) ),
				),
				'bandana',
			),
			array(
				'start',
				__( 'Before you start', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'p', __( 'Sewing has its own words and habits. These are the few you need for this bandana; everything else is explained in the step where you need it.', 'atelier-irisee-master-plugin' ) ),
					array(
						'terms',
						array(
							array( __( 'Right side and wrong side', 'atelier-irisee-master-plugin' ), __( 'The right side is the printed or nicest side of the fabric: the side you see on the finished bandana. The wrong side is the back. "Right sides together" means: lay the two pieces on top of each other with their nice sides facing each other, so you see the backs.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Seam allowance', 'atelier-irisee-master-plugin' ), __( 'The strip of fabric between the stitching and the cut edge. In this pattern it is 1 cm and it is already in the template: you cut on the outer line and sew 1 cm from the edge.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Pressing, not ironing', 'atelier-irisee-master-plugin' ), __( 'Ironing slides the iron back and forth. Pressing puts it down, lifts it and puts it down again a little further. Pressing keeps your pieces from stretching out of shape, so in sewing you press.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Backstitching', 'atelier-irisee-master-plugin' ), __( 'Two or three stitches backwards and forwards again at the start and the end of every seam, so it cannot come undone. Your machine has a button or lever for it.', 'atelier-irisee-master-plugin' ) ),
						),
					),
					array( 'p', __( 'How long does it take? Preparing the fabric and the template: about 20 minutes (plus drying time after prewashing). Cutting: 10 minutes. Sewing, turning and pressing: 30 minutes. Finishing: 5 to 15 minutes, depending on the style.', 'atelier-irisee-master-plugin' ) ),
					array( 'tip', __( 'Read each step completely before you start it. Something unclear? Look at the picture and the drawing next to it, and at the sewing terms at the end of the guide.', 'atelier-irisee-master-plugin' ) ),
				),
				'book',
			),
			array(
				'materials',
				__( 'What you need', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'photo', 'materials', '', '' ),
					array(
						'items',
						array(
							array( 'fabric', __( 'Fabric: a woven cotton such as poplin, quilting cotton or cotton lawn (see the size chart for how much).', 'atelier-irisee-master-plugin' ) ),
							array( 'layers', __( 'If you like, a second fabric for the reverse side (the same amount), or the same fabric twice.', 'atelier-irisee-master-plugin' ) ),
							array( 'thread', __( 'Sewing thread in a colour that matches your fabric (all-purpose polyester or cotton thread).', 'atelier-irisee-master-plugin' ) ),
							array( 'snap', __( 'With a snap: one plastic snap (size 20) and snap pliers or a snap press.', 'atelier-irisee-master-plugin' ) ),
							array( 'ribbon', __( 'Ribbon ties (optional): 120 cm of ribbon or bias tape, 1 to 1.5 cm wide.', 'atelier-irisee-master-plugin' ) ),
						),
					),
					array( 'tip', __( 'Ordering the bandana with your sewing kit? The customizer adds the right length of the same fabric and, if you like, a matching thread and ribbon.', 'atelier-irisee-master-plugin' ) ),
				),
				'fabric',
			),
			array(
				'tools',
				__( 'Tools', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'photo', 'tools', '', '' ),
					array(
						'items',
						array(
							array( 'machine', __( 'Sewing machine with a universal needle size 80: the standard needle for woven cotton.', 'atelier-irisee-master-plugin' ) ),
							array( 'scissors', __( 'Sharp fabric scissors (only for fabric: paper makes them blunt), or a rotary cutter and cutting mat.', 'atelier-irisee-master-plugin' ) ),
							array( 'pins', __( 'Pins or sewing clips, to hold the layers together while you sew.', 'atelier-irisee-master-plugin' ) ),
							array( 'iron', __( 'Iron and ironing board', 'atelier-irisee-master-plugin' ) ),
							array( 'chalk', __( 'Tailor\'s chalk or a fabric marker that washes out, to copy the marks.', 'atelier-irisee-master-plugin' ) ),
							array( 'tape', __( 'Ruler and a soft tape measure (also for measuring your dog).', 'atelier-irisee-master-plugin' ) ),
							array( 'turner', __( 'Point turner (or a chopstick): to push the points out neatly.', 'atelier-irisee-master-plugin' ) ),
							array( 'ripper', __( 'Seam ripper: to undo a seam that went wrong. Every sewer uses one, even the most experienced.', 'atelier-irisee-master-plugin' ) ),
							array( 'paper', __( 'Paper scissors and tape for the template', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
				'scissors',
			),
			array(
				'fabric',
				__( 'Choosing your fabric', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'photo', 'fabric', '', '' ),
					array(
						'dodont',
						array(
							__( 'Woven cotton: poplin, quilting cotton, lawn or a light linen', 'atelier-irisee-master-plugin' ),
							__( 'Soft, breathable fabrics that can be washed at 30 °C', 'atelier-irisee-master-plugin' ),
							__( 'Small prints, which look good on a small piece', 'atelier-irisee-master-plugin' ),
						),
						array(
							__( 'Stretchy knits: the points lose their shape', 'atelier-irisee-master-plugin' ),
							__( 'Very thick fabrics such as denim or canvas: the points get bulky', 'atelier-irisee-master-plugin' ),
							__( 'Slippery fabrics such as satin: hard to cut and sew', 'atelier-irisee-master-plugin' ),
						),
					),
					array( 'p', __( 'Wash and iron your fabric before you cut it. Cotton can shrink a little in the first wash; this way your bandana keeps its size.', 'atelier-irisee-master-plugin' ) ),
					array( 'p', __( 'How to prewash: wash the fabric the way you will wash the bandana later (30 °C), let it dry and press it flat, with a little steam if it creased.', 'atelier-irisee-master-plugin' ) ),
				),
				'fabric',
			),
			array(
				'size',
				__( 'Measuring your dog', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'photo', 'measure', '', 'measure' ),
					array( 'p', __( 'Measure around your dog\'s neck where the collar sits, with a soft tape measure. Leave room for two fingers between the tape and the neck. Choose the size whose neck range holds your measurement; between two sizes, take the larger one for the tie-on style and the smaller one with a snap.', 'atelier-irisee-master-plugin' ) ),
					array( 'tip', __( 'A wriggling dog? Give a treat while you measure, or measure the collar instead: lay it flat and measure from the buckle to the hole your dog uses.', 'atelier-irisee-master-plugin' ) ),
					array( 'sizes' ),
					array( 'table', $head, $rows, __( 'Finished sizes, width × depth. Fabric: the length of 140 cm wide fabric for both sides of the bandana.', 'atelier-irisee-master-plugin' ) ),
				),
				'tape',
			),
			array(
				'machine',
				__( 'Getting your machine ready', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array( 'photo', 'machine', '', '' ),
					array(
						'steps',
						array(
							array(
								__( 'Put in a new needle', 'atelier-irisee-master-plugin' ),
								__( 'Use a universal needle size 80. Loosen the needle screw, take out the old needle, push the new one up as far as it goes (on most machines with its flat side to the back) and tighten the screw.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'mistake' => __( 'A blunt needle, or one that is the wrong way round, skips stitches. A new needle costs little and solves a lot.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Thread the machine', 'atelier-irisee-master-plugin' ),
								__( 'Follow the numbers on your machine (or its manual) to thread the top thread, and wind a bobbin with the same thread. Always thread with the presser foot up, so the thread slips between the tension discs.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'check' => __( 'Lower the presser foot and pull the top thread gently: you feel a little resistance.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Choose the stitch', 'atelier-irisee-master-plugin' ),
								__( 'Choose a straight stitch with a stitch length of 2.5 mm (often the machine\'s standard). For the topstitching later you set it to 3 mm.', 'atelier-irisee-master-plugin' ),
								'',
								array(),
							),
							array(
								__( 'Find the 1 cm line', 'atelier-irisee-master-plugin' ),
								__( 'Look at the metal plate under the presser foot: its lines show the distance to the needle. Find the 1 cm line (sometimes marked 10). While you sew, the edge of the fabric follows that line.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'note' => __( 'No line on your plate? Stick a strip of tape on it exactly 1 cm to the right of the needle.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Sew a test seam', 'atelier-irisee-master-plugin' ),
								__( 'Fold a scrap of your fabric double and sew a seam of 10 cm. Look at both sides: the stitches look the same on both, without loops.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'mistake' => __( 'Loops underneath usually mean the top thread is not between the tension discs: lift the presser foot and thread it again.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Practise backstitching and pivoting', 'atelier-irisee-master-plugin' ),
								__( 'On the scrap, sew a few stitches, backstitch and sew on. Then practise a corner: stop with the needle down in the fabric, lift the presser foot, turn the fabric, lower the foot and sew on. This is pivoting, and you need it at every point of the bandana.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'slot' => 'pivot' ),
							),
						),
					),
				),
				'machine',
			),
			array(
				'print',
				__( 'Printing the template', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array( 'photo', 'print', '', '' ),
					array(
						'steps',
						array(
							array( '', __( 'Download the template of your size, or the template with all sizes.', 'atelier-irisee-master-plugin' ), '', array() ),
							array( '', __( 'Print it on A4 paper at 100 % ("Actual size"). Do not choose "Fit to page".', 'atelier-irisee-master-plugin' ), '', array() ),
							array( '', __( 'Measure the test square on the first page: it must be exactly 5 × 5 cm. If it is not, check your printer settings and print again.', 'atelier-irisee-master-plugin' ), 'square', array( 'check' => __( 'Both sides of the square measure exactly 5 cm.', 'atelier-irisee-master-plugin' ) ) ),
							array( '', __( 'Lay the sheets out as in the overview: A1, A2 … from left to right, then the next row.', 'atelier-irisee-master-plugin' ), '', array() ),
							array( '', __( 'Cut away the margin on the right and bottom edge of each sheet, up to the frame. Lay each sheet over its neighbour so the frame lines meet and the half diamonds form whole ones, and tape them together.', 'atelier-irisee-master-plugin' ), 'assembly', array() ),
							array( '', __( 'Cut out the pattern piece along the line of your size.', 'atelier-irisee-master-plugin' ), '', array() ),
						),
					),
				),
				'paper',
			),
			array(
				'cutting',
				__( 'Cutting', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array( 'photo', 'cutting', '', 'layout' ),
					array(
						'steps',
						array(
							array(
								__( 'Fold the fabric', 'atelier-irisee-master-plugin' ),
								__( 'Fold your fabric in half lengthwise, right sides together, so the two selvedges (the finished long edges) lie exactly on top of each other. Smooth it flat with your hands.', 'atelier-irisee-master-plugin' ),
								'layout',
								array( 'why' => __( 'Folded, you cut both halves of the bandana at once, perfectly the same.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Place the template', 'atelier-irisee-master-plugin' ),
								__( 'Lay the straight edge of the template marked "Place on fold" exactly on the folded edge. The grain line arrow runs parallel to the selvedge: measure from both ends of the arrow to the selvedge, the two distances must be the same.', 'atelier-irisee-master-plugin' ),
								'',
								array(),
							),
							array(
								__( 'Pin the template', 'atelier-irisee-master-plugin' ),
								__( 'Pin the template to the fabric every 10 cm, close to the edge, through both layers.', 'atelier-irisee-master-plugin' ),
								'',
								array(),
							),
							array(
								__( 'Cut', 'atelier-irisee-master-plugin' ),
								__( 'Cut along the cutting line of your size with long, calm cuts, using the whole length of the blades. Keep the fabric flat on the table and walk around it instead of lifting it.', 'atelier-irisee-master-plugin' ),
								'',
								array( 'mistake' => __( 'Lifting the fabric while you cut makes the piece crooked.', 'atelier-irisee-master-plugin' ) ),
							),
							array(
								__( 'Cut the second piece', 'atelier-irisee-master-plugin' ),
								__( 'Pin it down and cut along the cutting line. Cut a second piece the same way, from the same fabric or from your reverse fabric.', 'atelier-irisee-master-plugin' ),
								'',
								array(),
							),
							array(
								__( 'Copy the marks', 'atelier-irisee-master-plugin' ),
								__( 'Before you take the template off, copy the marks onto the wrong side with chalk: the turning gap, and the channel line or the snap marks of your style. Snip 3 mm into the seam allowance at the gap marks, so you can also feel them.', 'atelier-irisee-master-plugin' ),
								'',
								array(),
							),
						),
					),
					array( 'tip', __( 'A fabric with a motif? Check where the motif falls on the front of the bandana before you cut.', 'atelier-irisee-master-plugin' ) ),
				),
				'scissors',
			),
			array(
				'sewing',
				__( 'Sewing', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'steps',
						array(
							array(
								__( 'Pin', 'atelier-irisee-master-plugin' ),
								__( 'Lay both pieces on top of each other, right sides together, and pin or clip all around.', 'atelier-irisee-master-plugin' ),
								'pin',
								array(
									'slot'    => 'pin',
									'why'     => __( 'Pins keep both layers exactly on top of each other, so nothing shifts while you sew.', 'atelier-irisee-master-plugin' ),
									'do'      => array(
										__( 'Lay the outer piece on the table, right side up.', 'atelier-irisee-master-plugin' ),
										__( 'Lay the second piece on top, right side down, with all the edges exactly on top of each other.', 'atelier-irisee-master-plugin' ),
										__( 'Pin all around every 5 to 8 cm, with the pins at a right angle to the edge and the heads pointing outwards.', 'atelier-irisee-master-plugin' ),
										__( 'Put an extra pin on both sides of the turning gap marks.', 'atelier-irisee-master-plugin' ),
									),
									'check'   => __( 'Turn the work over: the edges of both layers meet everywhere.', 'atelier-irisee-master-plugin' ),
									'mistake' => __( 'Sewing over pins can break the needle: pull each pin out just before the needle reaches it.', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Sew', 'atelier-irisee-master-plugin' ),
								__( 'Sew all around with a 1 cm seam allowance. Leave a 6 cm turning gap in the middle of the top edge, between the marks, and backstitch at the start and the end. Over the collar: leave the short sides of the top band open instead; they become the ends of the channel, and you turn the bandana through one of them.', 'atelier-irisee-master-plugin' ),
								'sew',
								array(
									'slot'    => 'sew',
									'why'     => __( 'This seam joins both pieces; the gap lets you turn the bandana right side out later.', 'atelier-irisee-master-plugin' ),
									'do'      => array(
										__( 'Start at one gap mark: put the fabric under the presser foot with its edge on the 1 cm line, turn the needle down into the fabric and lower the foot.', 'atelier-irisee-master-plugin' ),
										__( 'Sew three stitches, backstitch three, and sew on.', 'atelier-irisee-master-plugin' ),
										__( 'Sew slowly and guide the fabric with your hands flat in front of the foot. Let the machine move the fabric; do not push or pull it.', 'atelier-irisee-master-plugin' ),
										__( 'At every corner and point: stop with the needle down in the fabric, lift the presser foot, turn the fabric, lower the foot and sew on.', 'atelier-irisee-master-plugin' ),
										__( 'Stop at the other gap mark and backstitch. The 6 cm between the marks stays open.', 'atelier-irisee-master-plugin' ),
									),
									'check'   => __( 'The stitching runs 1 cm from the edge all around, and the gap is open.', 'atelier-irisee-master-plugin' ),
									'mistake' => __( 'The seam wanders? Watch the edge of the fabric on the 1 cm line, not the needle.', 'atelier-irisee-master-plugin' ),
									'note'    => __( 'Over the collar: leave the short sides of the top band open instead of the gap; they become the ends of the channel, and you turn the bandana through one of them.', 'atelier-irisee-master-plugin' ),
									'time'    => __( '15 minutes', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Clip', 'atelier-irisee-master-plugin' ),
								__( 'Cut the seam allowance off across the points, close to the stitching, and clip into the inner corners up to the stitching without cutting it. This gives sharp points and flat corners.', 'atelier-irisee-master-plugin' ),
								'clip',
								array(
									'slot'    => 'clip',
									'why'     => __( 'Without clipping, the seam allowance bunches up inside the points and corners, and they turn out round and bulky.', 'atelier-irisee-master-plugin' ),
									'do'      => array(
										__( 'Cut the seam allowance off straight across each point, 2 to 3 mm from the stitching.', 'atelier-irisee-master-plugin' ),
										__( 'At the inner corners (where the ends meet the triangle), cut into the seam allowance straight towards the corner, up to 2 mm from the stitching.', 'atelier-irisee-master-plugin' ),
										__( 'Trim the rest of the seam allowance to about 5 mm, except at the turning gap.', 'atelier-irisee-master-plugin' ),
									),
									'mistake' => __( 'Never cut through the stitching: that makes a hole. Did it happen? Sew over that spot again with a few stitches.', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Turn', 'atelier-irisee-master-plugin' ),
								__( 'Turn the bandana right side out through the gap. Push the points out with a point turner and press flat; fold the seam allowance of the gap inwards and press it too.', 'atelier-irisee-master-plugin' ),
								'turn',
								array(
									'slot'  => 'turn',
									'why'   => __( 'Now the right side comes outside and the seams disappear inside.', 'atelier-irisee-master-plugin' ),
									'do'    => array(
										__( 'Reach into the gap and pull the far end of the bandana through, little by little.', 'atelier-irisee-master-plugin' ),
										__( 'Push the ends through from their tips with a chopstick or the point turner.', 'atelier-irisee-master-plugin' ),
										__( 'Push every point out gently from the inside with the point turner. Never use scissors: they go straight through the fabric.', 'atelier-irisee-master-plugin' ),
										__( 'Roll the seams between your fingers so they sit exactly on the edge.', 'atelier-irisee-master-plugin' ),
									),
									'check' => __( 'All the points are sharp and the edges lie flat.', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Press', 'atelier-irisee-master-plugin' ),
								__( 'Press the bandana flat with the iron on the cotton setting.', 'atelier-irisee-master-plugin' ),
								'',
								array(
									'slot'  => 'press',
									'why'   => __( 'Pressing makes the bandana flat and crisp, and the topstitching easy.', 'atelier-irisee-master-plugin' ),
									'do'    => array(
										__( 'Press the edges, rolling the seam exactly to the edge so neither fabric peeks out on the other side.', 'atelier-irisee-master-plugin' ),
										__( 'At the turning gap, fold both seam allowances 1 cm inwards, in line with the rest of the edge, and press.', 'atelier-irisee-master-plugin' ),
										__( 'Pin the gap closed.', 'atelier-irisee-master-plugin' ),
									),
									'check' => __( 'The gap is folded in neatly and closed with a pin.', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Topstitch', 'atelier-irisee-master-plugin' ),
								__( 'Topstitch all around, 3 mm from the edge. This closes the turning gap and keeps the edges flat.', 'atelier-irisee-master-plugin' ),
								'topstitch',
								array(
									'slot'    => 'topstitch',
									'why'     => __( 'The topstitching closes the turning gap and keeps the edges flat in the wash.', 'atelier-irisee-master-plugin' ),
									'do'      => array(
										__( 'Set the stitch length to 3 mm.', 'atelier-irisee-master-plugin' ),
										__( 'Start on a straight edge, right side up, and sew all around 3 mm from the edge. The edge of the presser foot is a good guide.', 'atelier-irisee-master-plugin' ),
										__( 'Go slowly at the points and pivot at each one.', 'atelier-irisee-master-plugin' ),
										__( 'Sew over the turning gap: both folded edges are caught in the stitching and the gap is closed.', 'atelier-irisee-master-plugin' ),
										__( 'Where you started, sew 1 cm over the first stitches and backstitch.', 'atelier-irisee-master-plugin' ),
									),
									'check'   => __( 'The gap is closed and the stitching runs at the same distance from the edge everywhere.', 'atelier-irisee-master-plugin' ),
									'mistake' => __( 'The layers shift and wrinkle? Pin before you topstitch, and let the machine move the fabric.', 'atelier-irisee-master-plugin' ),
									'time'    => __( '10 minutes', 'atelier-irisee-master-plugin' ),
								),
							),
						),
					),
				),
				'machine',
			),
			array(
				'finish',
				__( 'Finishing your style', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'steps',
						array(
							array(
								$styles['tie'],
								__( 'Done! Tie the ends in a knot or a bow at the back of the neck, with room for two fingers under the bandana.', 'atelier-irisee-master-plugin' ),
								'tie',
								array( 'slot' => 'style_tie' ),
							),
							array(
								$styles['collar'],
								__( 'Press the seam allowances of the open sides inwards. Stitch a straight line across along the channel line, 4 cm below the top edge: front and back now form a channel. Topstitch the edges of the openings one layer at a time, so the channel stays open, and slide your dog\'s collar through.', 'atelier-irisee-master-plugin' ),
								'collar',
								array(
									'slot' => 'style_collar',
									'do'   => array(
										__( 'Measure the width of your dog\'s collar: the channel holds collars up to 3 cm wide. For a wider collar, draw the channel line a little lower.', 'atelier-irisee-master-plugin' ),
										__( 'Press the seam allowances of the open short sides inwards.', 'atelier-irisee-master-plugin' ),
										__( 'Sew a straight line along the channel line, from side to side, through both layers.', 'atelier-irisee-master-plugin' ),
										__( 'Topstitch the edges of the openings one layer at a time, so the channel stays open.', 'atelier-irisee-master-plugin' ),
										__( 'Slide the collar through the channel.', 'atelier-irisee-master-plugin' ),
									),
								),
							),
							array(
								$styles['snap'],
								__( 'Mark the snap on both ends: the cap on the front of one end, the socket on the back of the other. Make a small hole with an awl and fix the snap with the pliers or press, as its instructions say. Close it at the back of the neck.', 'atelier-irisee-master-plugin' ),
								'snap',
								array(
									'slot'  => 'snap',
									'do'    => array(
										__( 'Try the bandana on your dog and mark where the ends overlap comfortably, with room for two fingers.', 'atelier-irisee-master-plugin' ),
										__( 'A snap has four parts: two caps, a socket and a stud. Mark the middle of each end.', 'atelier-irisee-master-plugin' ),
										__( 'Make a small hole through both layers at each mark, with an awl or the point of the pliers.', 'atelier-irisee-master-plugin' ),
										__( 'End on top: push a cap through from the right side and press the socket onto it on the wrong side.', 'atelier-irisee-master-plugin' ),
										__( 'End underneath: push a cap through from the wrong side and press the stud onto it on the right side.', 'atelier-irisee-master-plugin' ),
										__( 'Press each snap with the pliers or press until it is flat and no longer turns.', 'atelier-irisee-master-plugin' ),
									),
									'check' => __( 'The snap closes with a click and stays closed when you pull gently.', 'atelier-irisee-master-plugin' ),
								),
							),
							array(
								__( 'Ribbon ties', 'atelier-irisee-master-plugin' ),
								__( 'Instead of fabric ties you can use ribbon: cut the over-the-collar piece, pin a 60 cm ribbon into each top corner between the layers, pointing inwards, and sew all around without the channel.', 'atelier-irisee-master-plugin' ),
								'',
								array(
									'slot' => 'ribbon',
									'do'   => array(
										__( 'Cut both pieces with the over-the-collar template; you leave out the channel.', 'atelier-irisee-master-plugin' ),
										__( 'Cut two ribbons of 60 cm and cut their ends at an angle, or seal them with a drop of fray stop.', 'atelier-irisee-master-plugin' ),
										__( 'Pin one ribbon into each top corner of the right side of one piece, pointing inwards, with its end 1 cm over the edge.', 'atelier-irisee-master-plugin' ),
										__( 'Roll up the rest of each ribbon and pin it in the middle, so it stays out of the seam.', 'atelier-irisee-master-plugin' ),
										__( 'Lay the second piece on top and sew as in the sewing steps; backstitch two or three times over each ribbon so it holds firmly.', 'atelier-irisee-master-plugin' ),
									),
								),
							),
						),
					),
				),
				'bandana',
			),
			array(
				'care',
				__( 'Care and safety', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'photo', 'care', '', '' ),
					array(
						'items',
						array(
							array( 'wash', __( 'Wash at 30 °C, inside out, and iron on the reverse side.', 'atelier-irisee-master-plugin' ) ),
							array( 'fingers', __( 'Two fingers must always fit between the bandana and your dog\'s neck.', 'atelier-irisee-master-plugin' ) ),
							array( 'eye', __( 'Never leave your dog alone or in its crate with a bandana on, and take it off for rough play and at night.', 'atelier-irisee-master-plugin' ) ),
							array( 'check', __( 'A bandana is not a toy: check it regularly for loose threads or a loose snap.', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
				'heart',
			),
			array(
				'help',
				__( 'Good to know', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'terms',
						array(
							array( __( 'The points are round', 'atelier-irisee-master-plugin' ), __( 'Cut the seam allowance closer to the point and push the point out gently from the inside.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The template is too small or too large', 'atelier-irisee-master-plugin' ), __( 'Your printer scaled it. Print again at 100 % and check the test square.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The bandana slides around', 'atelier-irisee-master-plugin' ), __( 'Choose the over-the-collar style, or a size smaller.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The edges roll', 'atelier-irisee-master-plugin' ), __( 'Press well before you topstitch, and topstitch with a slightly longer stitch (3 mm).', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The machine skips stitches', 'atelier-irisee-master-plugin' ), __( 'The needle is blunt, bent or in the wrong way round. Put in a new needle size 80.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Loops of thread under the fabric', 'atelier-irisee-master-plugin' ), __( 'The top thread is not between the tension discs. Lift the presser foot, thread again and try on a scrap.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The fabric puckers along the seam', 'atelier-irisee-master-plugin' ), __( 'Use a stitch length of 2.5 mm and let the machine move the fabric, without pulling it.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The thread keeps breaking', 'atelier-irisee-master-plugin' ), __( 'Thread the top thread and the bobbin again, check that the spool turns freely, and use a good quality thread.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The seam is not straight', 'atelier-irisee-master-plugin' ), __( 'Watch the edge of the fabric on the 1 cm line, not the needle, and sew more slowly.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'The turning gap shows after topstitching', 'atelier-irisee-master-plugin' ), __( 'Fold the seam allowances in a little further, press well and pin the gap closed before you topstitch.', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
				'check',
			),
			array(
				'terms',
				__( 'Sewing terms', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'terms',
						array(
							array( __( 'Right side', 'atelier-irisee-master-plugin' ), __( 'The printed or nicest side of the fabric.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Wrong side', 'atelier-irisee-master-plugin' ), __( 'The back of the fabric.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Right sides together', 'atelier-irisee-master-plugin' ), __( 'Two pieces on top of each other with their right sides facing each other.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Seam allowance', 'atelier-irisee-master-plugin' ), __( 'The strip between the stitching and the cut edge: 1 cm in this pattern, included in the template.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Grain line', 'atelier-irisee-master-plugin' ), __( 'The arrow on the template: lay it parallel to the selvedge.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Selvedge', 'atelier-irisee-master-plugin' ), __( 'The finished long edges of the fabric.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Place on fold', 'atelier-irisee-master-plugin' ), __( 'The straight edge of the template lies on the folded edge of the fabric, so you cut one whole, symmetrical piece.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Backstitch', 'atelier-irisee-master-plugin' ), __( 'A few stitches back and forth that lock the seam.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Pivot', 'atelier-irisee-master-plugin' ), __( 'Turning the fabric at a corner with the needle down in it, so the stitching makes a sharp corner.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Clip', 'atelier-irisee-master-plugin' ), __( 'Small cuts into the seam allowance at points and corners, so they lie flat after turning.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Turning gap', 'atelier-irisee-master-plugin' ), __( 'An opening left in a seam to turn the work right side out.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Press', 'atelier-irisee-master-plugin' ), __( 'Putting the iron down and lifting it again, instead of sliding it.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Topstitching', 'atelier-irisee-master-plugin' ), __( 'A visible line of stitching close to the edge, on the right side.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Presser foot', 'atelier-irisee-master-plugin' ), __( 'The part of the machine that holds the fabric down under the needle.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Bobbin', 'atelier-irisee-master-plugin' ), __( 'The small spool under the needle plate that gives the lower thread of every stitch.', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
				'book',
			),
		);
	}

	/* ------------------------------------------------------------------
	 * Drawings
	 * ------------------------------------------------------------------ */

	/**
	 * A drawing of the guide: [ width, height, shapes ] in its own units. A shape:
	 * [ 'poly', points, closed, stroke, width (mm), dashes (mm), fill ],
	 * [ 'path', segments, stroke, width, dashes, fill ] (segments as in AIMP_Pdf::path()),
	 * [ 'circle', cx, cy, r, stroke, width, fill ],
	 * [ 'text', x, y, text, size (pt), colour, align, bold, rotation ].
	 * Colours are keys of palette() ('' = none).
	 *
	 * @param string $name Drawing.
	 * @return array|null
	 */
	public static function figure( $name ) {
		$shapes = array();
		$poly   = function ( $points, $stroke = 'gold', $width = 0.5, $dashes = array(), $fill = '', $closed = true ) use ( &$shapes ) {
			$shapes[] = array( 'poly', $points, $closed, $stroke, $width, $dashes, $fill );
		};
		$text   = function ( $x, $y, $s, $size = 9, $color = 'brown', $align = 'center', $bold = false, $rot = 0 ) use ( &$shapes ) {
			$shapes[] = array( 'text', $x, $y, $s, $size, $color, $align, $bold, $rot );
		};
		$move   = function ( $points, $scale, $dx, $dy ) {
			return array_map(
				function ( $p ) use ( $scale, $dx, $dy ) {
					return array( $p[0] * $scale + $dx, $p[1] * $scale + $dy );
				},
				$points
			);
		};
		$arrow  = function ( $x1, $y1, $x2, $y2, $color = 'gold_text', $both = false ) use ( &$shapes ) {
			$shapes[] = array( 'poly', array( array( $x1, $y1 ), array( $x2, $y2 ) ), false, $color, 0.35, array(), '' );
			$heads    = $both ? array( array( $x2, $y2, $x1, $y1 ), array( $x1, $y1, $x2, $y2 ) ) : array( array( $x1, $y1, $x2, $y2 ) );
			foreach ( $heads as $h ) {
				$a        = atan2( $h[3] - $h[1], $h[2] - $h[0] );
				$l        = 2.2;
				$shapes[] = array( 'poly', array( array( $h[2] - $l * cos( $a - 0.45 ), $h[3] - $l * sin( $a - 0.45 ) ), array( $h[2], $h[3] ), array( $h[2] - $l * cos( $a + 0.45 ), $h[3] - $l * sin( $a + 0.45 ) ) ), false, $color, 0.35, array(), '' );
			}
		};
		$styles = self::styles();
		$m      = function ( $style ) {
			return self::outline( 'm', $style )[0];
		};

		switch ( $name ) {
			case 'styles':
				$scale = 0.62;
				$x     = 4;
				foreach ( array( 'tie', 'collar', 'snap' ) as $style ) {
					$pts   = $m( $style );
					$half  = $pts[1][0] * $scale;
					$cx    = $x + $half;
					$piece = $move( $pts, $scale, $cx, 4 );
					$poly( $piece, 'gold', 0.6, array(), 'tint' );
					$poly( $move( self::offset( $pts, -0.6 ), $scale, $cx, 4 ), 'gold', 0.25, array( 0.8, 0.6 ) );
					if ( 'collar' === $style ) {
						$poly( array( array( $cx - $half, 4 + self::CHANNEL * $scale ), array( $cx + $half, 4 + self::CHANNEL * $scale ) ), 'gold', 0.3, array( 0.8, 0.6 ), '', false );
						$shapes[] = array( 'poly', array( array( $cx - $half - 5, 4 + 1.2 ), array( $cx + $half + 5, 4 + 1.2 ), array( $cx + $half + 5, 4 + 2.4 ), array( $cx - $half - 5, 4 + 2.4 ) ), true, 'brown', 0.3, array(), '' );
					}
					if ( 'snap' === $style ) {
						$shapes[] = array( 'circle', $cx + $half - 2 * $scale, 4 + self::END / 2 * $scale, 0.9, 'brown', 0.3, '' );
						$shapes[] = array( 'circle', $cx - $half + 2 * $scale, 4 + self::END / 2 * $scale, 0.9, 'brown', 0.3, '' );
					}
					$depth = $pts[ count( $pts ) > 6 ? 4 : 3 ][1] * $scale;
					$text( $cx, 4 + $depth + 6, $styles[ $style ], 9, 'gold_text', 'center', true );
					$x += $half * 2 + 12;
				}
				return array( $x - 4, 4 + 23 * $scale + self::CHANNEL * $scale + 9, $shapes );

			case 'measure':
				// A dog's head from the front, the tape around the neck and two fingers of room.
				$shapes[] = array( 'circle', 30, 18, 12, 'gold', 0.6, 'tint' );
				$shapes[] = array( 'path', array( array( 'M', 20, 10 ), array( 'C', 12, 8, 9, 20, 13, 30 ), array( 'C', 16, 28, 19, 22, 21, 17 ) ), 'gold', 0.6, array(), 'tint' );
				$shapes[] = array( 'path', array( array( 'M', 40, 10 ), array( 'C', 48, 8, 51, 20, 47, 30 ), array( 'C', 44, 28, 41, 22, 39, 17 ) ), 'gold', 0.6, array(), 'tint' );
				$shapes[] = array( 'circle', 25.5, 16, 1, 'brown', 0.3, 'brown' );
				$shapes[] = array( 'circle', 34.5, 16, 1, 'brown', 0.3, 'brown' );
				$shapes[] = array( 'path', array( array( 'M', 27.6, 21 ), array( 'C', 28.5, 20, 31.5, 20, 32.4, 21 ), array( 'C', 32, 23, 28, 23, 27.6, 21 ), array( 'Z' ) ), 'brown', 0.3, array(), 'brown' );
				$poly( array( array( 21, 28 ), array( 19, 44 ), array( 41, 44 ), array( 39, 28 ) ), 'gold', 0.6, array(), 'tint', false );
				$shapes[] = array( 'path', array( array( 'M', 19.6, 36 ), array( 'C', 22, 40, 38, 40, 40.4, 36 ) ), 'gold_text', 1.4, array( 1.2, 1.2 ), '' );
				$arrow( 44, 37, 56, 37 );
				$text( 57, 36, __( 'Neck where the collar sits', 'atelier-irisee-master-plugin' ), 8, 'brown', 'left' );
				$text( 57, 40, __( '+ room for two fingers', 'atelier-irisee-master-plugin' ), 8, 'gold_text', 'left' );
				return array( 110, 47, $shapes );

			case 'square':
				$poly( array( array( 6, 4 ), array( 36, 4 ), array( 36, 34 ), array( 6, 34 ) ), 'brown', 0.5 );
				$arrow( 6, 38, 36, 38, 'gold_text', true );
				$arrow( 40, 4, 40, 34, 'gold_text', true );
				$text( 21, 42.5, '5 cm', 8, 'gold_text' );
				$text( 44, 20, '5 cm', 8, 'gold_text', 'left' );
				$text( 21, 20, '100 %', 10, 'brown', 'center', true );
				return array( 60, 45, $shapes );

			case 'assembly':
				$labels = array( 'A1', 'A2', 'A3', 'B1', 'B2', 'B3' );
				foreach ( $labels as $i => $label ) {
					$x = 4 + ( $i % 3 ) * 22;
					$y = 4 + intdiv( $i, 3 ) * 30;
					$poly( array( array( $x, $y ), array( $x + 22, $y ), array( $x + 22, $y + 30 ), array( $x, $y + 30 ) ), 'gold', 0.4, array(), 0 === $i ? 'tint' : '' );
					$text( $x + 11, $y + 16, $label, 9, 'gold_text', 'center', true );
				}
				foreach ( array( array( 26, 19 ), array( 48, 19 ), array( 26, 49 ), array( 48, 49 ), array( 15, 34 ), array( 37, 34 ), array( 59, 34 ) ) as $d ) {
					$poly( array( array( $d[0], $d[1] - 1.6 ), array( $d[0] + 1.6, $d[1] ), array( $d[0], $d[1] + 1.6 ), array( $d[0] - 1.6, $d[1] ) ), 'brown', 0.3, array(), 'brown' );
				}
				return array( 74, 68, $shapes );

			case 'layout':
				// Folded fabric: the fold on the left, the selvedges on the right; two pieces on the fold.
				$poly( array( array( 8, 6 ), array( 78, 6 ), array( 78, 66 ), array( 8, 66 ) ), 'gold', 0.4, array(), 'tint' );
				$poly( array( array( 8, 6 ), array( 8, 66 ) ), 'brown', 0.9, array( 2, 1.2 ), '', false );
				$text( 5, 36, __( 'Fold', 'atelier-irisee-master-plugin' ), 8, 'brown', 'center', true, 90 );
				$text( 82, 36, __( 'Selvedges', 'atelier-irisee-master-plugin' ), 8, 'brown', 'center', false, 90 );
				list( , $cut ) = self::half( 'm', 'tie' );
				$scale         = 0.7;
				foreach ( array( 8, 37 ) as $top ) {
					$piece = $move( $cut, $scale, 8, $top + 1 );
					$poly( array_merge( $piece, array( array( 8, $top + 1 ) ) ), 'brown', 0.5, array(), '' );
				}
				$arrow( 60, 46, 60, 60, 'gold_text', true );
				$text( 62.5, 54, __( 'Grain line', 'atelier-irisee-master-plugin' ), 7, 'gold_text', 'left' );
				return array( 88, 70, $shapes );

			case 'pin':
			case 'sew':
			case 'clip':
			case 'turn':
			case 'topstitch':
				$pts   = $m( 'tie' );
				$scale = 1.15;
				$piece = $move( $pts, $scale, 46, 5 );
				if ( 'pin' === $name ) {
					$poly( $move( $pts, $scale, 47.2, 6.2 ), 'gold', 0.35, array( 1, 0.8 ) );
					$poly( $piece, 'gold', 0.6, array(), 'tint' );
					foreach ( array( array( 20, 6 ), array( 46, 6 ), array( 72, 6 ), array( 34, 17 ), array( 58, 17 ), array( 46, 26 ) ) as $p ) {
						$poly( array( array( $p[0] - 2, $p[1] - 1.5 ), array( $p[0] + 2, $p[1] + 1.5 ) ), 'brown', 0.35, array(), '', false );
						$shapes[] = array( 'circle', $p[0] - 2.2, $p[1] - 1.7, 0.7, 'brown', 0.3, 'gold' );
					}
				} elseif ( 'sew' === $name ) {
					$poly( $move( self::offset( $pts, self::SA ), $scale, 46, 5 ), 'gold', 0.6, array(), 'tint' );
					$stitch = $piece;
					$poly( $stitch, 'brown', 0.45, array( 1.1, 0.7 ) );
					// The gap: no stitching between the marks.
					$poly( array( array( 46 - 3 * $scale, 5 - 0.2 ), array( 46 + 3 * $scale, 5 - 0.2 ) ), 'tint', 1.2, array(), '', false );
					foreach ( array( -3, 3 ) as $g ) {
						$poly( array( array( 46 + $g * $scale, 3 ), array( 46 + $g * $scale, 7 ) ), 'gold_text', 0.5, array(), '', false );
					}
					$text( 46, 1.5, __( 'Turning gap', 'atelier-irisee-master-plugin' ), 7, 'gold_text' );
				} elseif ( 'clip' === $name ) {
					$poly( $move( self::offset( $pts, self::SA ), $scale, 46, 5 ), 'gold', 0.6, array(), 'tint' );
					$poly( $piece, 'brown', 0.35, array( 1.1, 0.7 ) );
					foreach ( array( 1, 2, 4, 6, 7 ) as $i ) {
						$c = $piece[ $i ];
						$o = $move( array( self::offset( $pts, self::SA )[ $i ] ), $scale, 46, 5 )[0];
						$d = array( $o[0] - $c[0], $o[1] - $c[1] );
						$l = max( 0.01, sqrt( $d[0] * $d[0] + $d[1] * $d[1] ) );
						// Across the points.
						$poly( array( array( $o[0] - $d[1] / $l * 1.6, $o[1] + $d[0] / $l * 1.6 ), array( $o[0] + $d[1] / $l * 1.6, $o[1] - $d[0] / $l * 1.6 ) ), 'brown', 0.5, array(), '', false );
					}
					// Into the inner corners.
					foreach ( array( 3, 5 ) as $i ) {
						$c = $piece[ $i ];
						$o = $move( array( self::offset( $pts, self::SA )[ $i ] ), $scale, 46, 5 )[0];
						$poly( array( $o, array( $c[0] + ( $o[0] - $c[0] ) * 0.15, $c[1] + ( $o[1] - $c[1] ) * 0.15 ) ), 'brown', 0.5, array(), '', false );
					}
				} elseif ( 'turn' === $name ) {
					$poly( $piece, 'gold', 0.6, array(), 'tint' );
					$shapes[] = array( 'path', array( array( 'M', 40, 2 ), array( 'C', 36, -4, 56, -4, 52, 2 ) ), 'gold_text', 0.5, array(), '' );
					$arrow( 53.5, -0.5, 52, 2 );
				} else {
					$poly( $piece, 'gold', 0.6, array(), 'tint' );
					$poly( $move( self::offset( $pts, -0.3 ), $scale, 46, 5 ), 'brown', 0.35, array( 0.9, 0.6 ) );
				}
				return array( 92, 34, $shapes );

			case 'tie':
				$piece = $move( array( array( -17, 4 ), array( 17, 4 ), array( 0, 27 ) ), 1, 40, 6 );
				$poly( $piece, 'gold', 0.6, array(), 'tint' );
				$shapes[] = array( 'path', array( array( 'M', 57, 10 ), array( 'C', 64, 6, 62, -2, 48, 2 ) ), 'gold', 2.6, array(), '' );
				$shapes[] = array( 'path', array( array( 'M', 23, 10 ), array( 'C', 16, 6, 18, -2, 32, 2 ) ), 'gold', 2.6, array(), '' );
				$shapes[] = array( 'circle', 40, 2.4, 3, 'gold', 0.6, 'tint' );
				$text( 40, 38, __( 'Knot at the back of the neck', 'atelier-irisee-master-plugin' ), 8, 'gold_text' );
				return array( 80, 40, $shapes );

			case 'collar':
				$pts   = $m( 'collar' );
				$scale = 1;
				$piece = $move( $pts, $scale, 40, 6 );
				$poly( $piece, 'gold', 0.6, array(), 'tint' );
				$poly( array( array( 40 - 17, 6 + self::CHANNEL ), array( 40 + 17, 6 + self::CHANNEL ) ), 'brown', 0.45, array( 1.1, 0.7 ), '', false );
				$poly( array( array( 10, 7.2 ), array( 70, 7.2 ), array( 70, 9.0 ), array( 10, 9.0 ) ), 'brown', 0.4, array(), '' );
				$text( 40, 6 + self::CHANNEL + 4, __( 'Channel stitching line', 'atelier-irisee-master-plugin' ), 7, 'gold_text' );
				$text( 72, 8.6, __( 'Collar', 'atelier-irisee-master-plugin' ), 7, 'brown', 'left' );
				return array( 84, 36, $shapes );

			case 'snap':
				$pts   = $m( 'snap' );
				$scale = 1.3;
				$piece = $move( $pts, $scale, 44, 6 );
				$poly( $piece, 'gold', 0.6, array(), 'tint' );
				$w = $pts[1][0];
				foreach ( array( -1, 1 ) as $side ) {
					$shapes[] = array( 'circle', 44 + $side * ( $w - 2 ) * $scale, 6 + self::END / 2 * $scale, 1.3, 'brown', 0.4, -1 === $side ? '' : 'gold' );
				}
				$text( 44 + ( $w - 2 ) * $scale, 2.5, __( 'Cap (front)', 'atelier-irisee-master-plugin' ), 7, 'gold_text' );
				$text( 44 - ( $w - 2 ) * $scale, 2.5, __( 'Socket (back)', 'atelier-irisee-master-plugin' ), 7, 'gold_text' );
				return array( 88, 36, $shapes );
		}
		return null;
	}

	/**
	 * A small line icon (12 × 12 units), in the same shapes as figure(): for the lists of materials and tools, the
	 * contents, the steps bar and the care tiles.
	 *
	 * @param string $name Icon.
	 * @return array [ 12, 12, shapes ]
	 */
	public static function icon( $name ) {
		$shapes = array();
		$w      = 0.35;
		$line   = function ( $points, $closed = false, $color = 'gold', $fill = '', $dashes = array() ) use ( &$shapes, $w ) {
			$shapes[] = array( 'poly', $points, $closed, $color, $w, $dashes, $fill );
		};
		$path   = function ( $d, $color = 'gold', $fill = '' ) use ( &$shapes, $w ) {
			$segments = array();
			foreach ( preg_split( '/(?=[MLCZ])/', $d, -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
				$numbers    = preg_split( '/[\s,]+/', trim( substr( $part, 1 ) ), -1, PREG_SPLIT_NO_EMPTY );
				$segments[] = array_merge( array( $part[0] ), array_map( 'floatval', $numbers ) );
			}
			$shapes[] = array( 'path', $segments, $color, $w, array(), $fill );
		};
		$circle = function ( $x, $y, $r, $color = 'gold', $fill = '' ) use ( &$shapes, $w ) {
			$shapes[] = array( 'circle', $x, $y, $r, $color, $w, $fill );
		};
		switch ( $name ) {
			case 'tape':
				$circle( 5, 5.6, 4 );
				$circle( 5, 5.6, 1.4 );
				$line( array( array( 5, 9.6 ), array( 11.4, 9.6 ), array( 11.4, 7.6 ), array( 7.6, 7.6 ) ) );
				$line( array( array( 7.4, 9.6 ), array( 7.4, 8.8 ) ) );
				$line( array( array( 9, 9.6 ), array( 9, 8.6 ) ) );
				$line( array( array( 10.5, 9.6 ), array( 10.5, 8.8 ) ) );
				break;
			case 'paper':
				$line( array( array( 2.5, 1 ), array( 7.5, 1 ), array( 9.5, 3 ), array( 9.5, 11 ), array( 2.5, 11 ) ), true );
				$line( array( array( 7.5, 1 ), array( 7.5, 3 ), array( 9.5, 3 ) ) );
				$line( array( array( 4, 5 ), array( 7, 5 ), array( 7, 8 ), array( 4, 8 ) ), true, 'purple', '', array( 0.5, 0.4 ) );
				break;
			case 'scissors':
				$circle( 3.4, 9, 1.8 );
				$circle( 8.6, 9, 1.8 );
				$line( array( array( 4.6, 7.6 ), array( 9.6, 0.8 ) ) );
				$line( array( array( 7.4, 7.6 ), array( 2.4, 0.8 ) ) );
				$circle( 6, 5.7, 0.35, 'gold', 'gold' );
				break;
			case 'machine':
				$line( array( array( 1, 9 ), array( 11, 9 ), array( 11, 10.8 ), array( 1, 10.8 ) ), true );
				$line( array( array( 2.2, 9 ), array( 2.2, 3 ), array( 10.4, 3 ), array( 10.4, 6.2 ), array( 7.6, 6.2 ) ) );
				$line( array( array( 8.8, 6.2 ), array( 8.8, 8.4 ) ), false, 'purple' );
				$circle( 4.6, 1.8, 0.9 );
				$circle( 4.6, 5.6, 1.3 );
				break;
			case 'iron':
				$path( 'M1.4 9.2 L10.6 9.2 C10.6 6 9 4.2 6.2 4.2 L3 4.2 C2 4.2 1.4 6 1.4 9.2 Z' );
				$line( array( array( 4, 4.2 ), array( 4.2, 2.2 ), array( 9, 2.2 ), array( 9.6, 4.6 ) ) );
				$circle( 4, 7, 0.35, 'gold', 'gold' );
				$circle( 6, 7, 0.35, 'gold', 'gold' );
				$circle( 8, 7, 0.35, 'gold', 'gold' );
				break;
			case 'bandana':
				$line( array( array( 0.8, 2.6 ), array( 11.2, 2.6 ), array( 11.2, 4.2 ), array( 8.6, 4.2 ), array( 6, 10.8 ), array( 3.4, 4.2 ), array( 0.8, 4.2 ) ), true, 'gold', 'blush' );
				$line( array( array( 4.6, 4.2 ), array( 7.4, 4.2 ), array( 6, 7.8 ) ), true, 'purple', '', array( 0.4, 0.4 ) );
				break;
			case 'fabric':
				$line( array( array( 1.4, 3 ), array( 9, 3 ), array( 10.6, 4.6 ), array( 10.6, 10 ), array( 3, 10 ), array( 1.4, 8.4 ) ), true );
				$line( array( array( 1.4, 3 ), array( 3, 4.6 ), array( 3, 10 ) ) );
				$line( array( array( 3, 4.6 ), array( 10.6, 4.6 ) ) );
				$circle( 5.6, 6.8, 0.6, 'purple', 'blush' );
				$circle( 8.4, 8.2, 0.6, 'purple', 'blush' );
				$circle( 8, 6.2, 0.35, 'gold', 'gold' );
				break;
			case 'layers':
				$line( array( array( 1.6, 4.4 ), array( 7.6, 4.4 ), array( 7.6, 10.4 ), array( 1.6, 10.4 ) ), true, 'gold', 'blush' );
				$line( array( array( 4.4, 4.4 ), array( 4.4, 1.6 ), array( 10.4, 1.6 ), array( 10.4, 7.6 ), array( 7.6, 7.6 ) ) );
				break;
			case 'thread':
				$line( array( array( 3, 1.6 ), array( 9, 1.6 ) ) );
				$line( array( array( 3, 10.4 ), array( 9, 10.4 ) ) );
				$line( array( array( 4, 1.6 ), array( 4, 10.4 ) ) );
				$line( array( array( 8, 1.6 ), array( 8, 10.4 ) ) );
				foreach ( array( 3.8, 5.6, 7.4, 9.2 ) as $y ) {
					$line( array( array( 4, $y ), array( 8, $y - 0.8 ) ), false, 'purple' );
				}
				$path( 'M8 7 C10 7 10.4 9.6 11.6 11.2' );
				break;
			case 'snap':
				$circle( 6, 6, 4.4 );
				$circle( 6, 6, 1.8, 'purple', 'blush' );
				$circle( 6, 6, 0.4, 'purple', 'purple' );
				break;
			case 'ribbon':
				$path( 'M6 6 C3 2 0.8 3.8 1.6 6 C0.8 8.2 3 10 6 6 Z', 'gold', 'blush' );
				$path( 'M6 6 C9 2 11.2 3.8 10.4 6 C11.2 8.2 9 10 6 6 Z', 'gold', 'blush' );
				$line( array( array( 6, 6 ), array( 4.4, 11.2 ) ), false, 'purple' );
				$line( array( array( 6, 6 ), array( 7.6, 11.2 ) ), false, 'purple' );
				$circle( 6, 6, 0.9, 'gold', 'gold' );
				break;
			case 'pins':
				$line( array( array( 2.4, 10 ), array( 9, 3.4 ) ) );
				$circle( 9.6, 2.8, 1.1, 'gold', 'blush' );
				$line( array( array( 2.4, 2.4 ), array( 9, 9 ) ) );
				$circle( 9.6, 9.6, 1.1, 'gold', 'blush' );
				break;
			case 'chalk':
				$line( array( array( 1.8, 7.8 ), array( 7.4, 2.2 ), array( 10, 4.8 ), array( 4.4, 10.4 ) ), true, 'gold', 'tint' );
				$line( array( array( 1.4, 11.4 ), array( 9, 11.4 ) ), false, 'purple', '', array( 0.6, 0.5 ) );
				break;
			case 'turner':
				$line( array( array( 1.2, 9.8 ), array( 2.2, 10.8 ), array( 10.8, 1.2 ) ), true, 'gold', 'tint' );
				$line( array( array( 3.4, 8.8 ), array( 5, 7.2 ) ) );
				break;
			case 'ripper':
				$line( array( array( 1, 9.8 ), array( 2.2, 11 ), array( 6, 7.2 ), array( 4.8, 6 ) ), true, 'gold', 'blush' );
				$line( array( array( 5.4, 6.6 ), array( 9.4, 2.6 ) ) );
				$path( 'M9.4 2.6 C10.4 1.4 11.6 2.4 10.6 3.6' );
				$circle( 8.7, 3.3, 0.45, 'purple', 'purple' );
				break;
			case 'wash':
				$path( 'M1.4 3.6 L2.8 10.4 L9.2 10.4 L10.6 3.6' );
				$path( 'M1.7 5.2 C3 4.2 4 6.2 5.4 5.2 C6.8 4.2 8 6.2 10.3 5.2', 'purple' );
				$shapes[] = array( 'text', 6, 9.2, '30', 4.2, 'gold_text', 'center', true, 0 );
				break;
			case 'fingers':
				$path( 'M3.4 11 L3.4 4 C3.4 2.4 5.4 2.4 5.4 4 L5.4 11' );
				$path( 'M6.6 11 L6.6 3 C6.6 1.4 8.6 1.4 8.6 3 L8.6 11' );
				$line( array( array( 1.2, 8 ), array( 10.8, 8 ) ), false, 'purple', '', array( 0.5, 0.4 ) );
				break;
			case 'eye':
				$path( 'M1 6 C3 2.4 9 2.4 11 6 C9 9.6 3 9.6 1 6 Z' );
				$circle( 6, 6, 1.8, 'purple', 'blush' );
				break;
			case 'check':
				$circle( 5, 5, 3.6 );
				$line( array( array( 7.6, 7.6 ), array( 10.8, 10.8 ) ) );
				$line( array( array( 3.4, 5 ), array( 4.6, 6.2 ), array( 6.8, 3.8 ) ), false, 'purple' );
				break;
			case 'tick':
				$circle( 6, 6, 4.6 );
				$line( array( array( 3.8, 6.2 ), array( 5.4, 7.8 ), array( 8.4, 4.4 ) ) );
				break;
			case 'warn':
				$line( array( array( 6, 1.4 ), array( 11, 10.4 ), array( 1, 10.4 ) ), true, 'purple' );
				$line( array( array( 6, 4.6 ), array( 6, 7.4 ) ), false, 'purple' );
				$circle( 6, 8.9, 0.35, 'purple', 'purple' );
				break;
			case 'bulb':
				$path( 'M4.2 8 C2.2 6.6 2.4 1.8 6 1.8 C9.6 1.8 9.8 6.6 7.8 8 L7.8 9.4 L4.2 9.4 Z' );
				$line( array( array( 4.6, 10.8 ), array( 7.4, 10.8 ) ) );
				$line( array( array( 5.2, 7 ), array( 6, 5.4 ), array( 6.8, 7 ) ), false, 'purple' );
				break;
			case 'clock':
				$circle( 6, 6, 4.6 );
				$line( array( array( 6, 3.2 ), array( 6, 6 ), array( 8, 7.4 ) ), false, 'purple' );
				break;
			case 'book':
				$path( 'M6 3 C4.5 2 2.5 2 1 2.6 L1 10 C2.5 9.4 4.5 9.4 6 10.4 C7.5 9.4 9.5 9.4 11 10 L11 2.6 C9.5 2 7.5 2 6 3 Z' );
				$line( array( array( 6, 3 ), array( 6, 10.4 ) ), false, 'purple' );
				break;
			case 'dog':
				$path( 'M3.4 3 C1.4 2.8 0.6 6 1.4 8 C2.4 7.4 3 6.2 3.4 5.2' );
				$path( 'M8.6 3 C10.6 2.8 11.4 6 10.6 8 C9.6 7.4 9 6.2 8.6 5.2' );
				$circle( 6, 5.4, 3.3 );
				$circle( 4.9, 4.9, 0.35, 'brown', 'brown' );
				$circle( 7.1, 4.9, 0.35, 'brown', 'brown' );
				$circle( 6, 6.4, 0.5, 'brown', 'brown' );
				$line( array( array( 3, 8.2 ), array( 9, 8.2 ), array( 6, 11.6 ) ), true, 'purple', 'blush' );
				break;
			default: // A heart.
				$path( 'M6 10.6 C2 7.6 1 5.6 1.4 4 C2 2 5 1.8 6 4 C7 1.8 10 2 10.6 4 C11 5.6 10 7.6 6 10.6 Z', 'gold', 'blush' );
		}
		return array( 12, 12, $shapes );
	}

	/**
	 * The template of a style as shapes in cm: one size, or every size nested (from the fold to the right).
	 *
	 * @param string   $style Style.
	 * @param string[] $sizes Sizes.
	 * @return array [ min x, min y, max x, max y, shapes ]
	 */
	public static function template( $style, $sizes ) {
		$shapes  = array();
		$all     = count( $sizes ) > 1;
		$dashes  = array( 'xs' => array( 0.6, 1.2 ), 's' => array( 3, 1.2 ), 'm' => array(), 'l' => array( 5, 1.2, 0.6, 1.2 ), 'xl' => array( 8, 1.6 ) );
		$max_x   = 0;
		$max_y   = 0;
		$largest = end( $sizes );
		foreach ( $sizes as $size ) {
			list( $finished, $cut ) = self::half( $size, $style );
			$label                  = self::sizes()[ $size ][0];
			// The cutting line (closed along the fold), and the stitching line for one size.
			$shapes[] = array( 'poly', array_merge( array( array( 0, $cut[0][1] ) ), $cut, array( array( 0, end( $cut )[1] ) ) ), false, 'brown', $all ? 0.45 : 0.6, $all ? $dashes[ $size ] : array(), '' );
			if ( ! $all ) {
				$shapes[] = array( 'poly', $finished, false, 'gold', 0.35, array( 2, 1.2 ), '' );
			}
			$bottom   = end( $cut );
			$corner   = $cut[1];
			$max_x    = max( $max_x, $corner[0], $cut[2][0] );
			$max_y    = max( $max_y, $bottom[1] );
			$shapes[] = array( 'text', 1.2, $bottom[1] - 0.6, $label, $all ? 11 : 14, 'brown', 'left', true, 0 );
			if ( $all ) {
				$shapes[] = array( 'text', $corner[0] - 0.4, $corner[1] + 1.4, $label, 9, 'brown', 'right', true, 0 );
			}
			if ( 'snap' === $style ) {
				$x        = $finished[1][0] - self::SNAP_OVERLAP / 2;
				$shapes[] = array( 'circle', $x, self::END / 2, 0.6, 'brown', 0.35, '' );
				$shapes[] = array( 'poly', array( array( $x - 0.9, self::END / 2 ), array( $x + 0.9, self::END / 2 ) ), false, 'brown', 0.25, array(), '' );
				$shapes[] = array( 'poly', array( array( $x, self::END / 2 - 0.9 ), array( $x, self::END / 2 + 0.9 ) ), false, 'brown', 0.25, array(), '' );
			}
		}
		$top = -self::SA;

		// The fold: a bracket with arrows to the edge and its name along it.
		$fold_to  = min( $max_y - 3, 16 );
		$shapes[] = array( 'poly', array( array( 0.9, 2 ), array( 0.9, $fold_to ) ), false, 'gold_text', 0.4, array(), '' );
		foreach ( array( 2, $fold_to ) as $y ) {
			$shapes[] = array( 'poly', array( array( 0.9, $y ), array( 0.1, $y ) ), false, 'gold_text', 0.4, array(), '' );
			$shapes[] = array( 'poly', array( array( 0.45, $y - 0.3 ), array( 0.1, $y ), array( 0.45, $y + 0.3 ) ), false, 'gold_text', 0.4, array(), '' );
		}
		$shapes[] = array( 'text', 1.6, ( 2 + $fold_to ) / 2, __( 'Place on fold', 'atelier-irisee-master-plugin' ), 9, 'gold_text', 'center', true, 90 );

		// The turning gap (tie-on, snap) or the open ends and the channel (over the collar).
		list( $finished_l ) = self::half( $largest, $style );
		if ( 'collar' === $style ) {
			$shapes[] = array( 'poly', array( array( 0, self::CHANNEL ), array( $finished_l[1][0], self::CHANNEL ) ), false, 'gold_text', 0.4, array( 1.2, 0.6, 0.3, 0.6 ), '' );
			$shapes[] = array( 'text', 3.4, self::CHANNEL - 0.5, __( 'Channel stitching line', 'atelier-irisee-master-plugin' ), 8, 'gold_text', 'left', false, 0 );
			foreach ( $sizes as $size ) {
				$side     = self::half( $size, $style )[0][1][0];
				$shapes[] = array( 'poly', array( array( $side - 0.4, self::CHANNEL ), array( $side + self::SA + 0.4, self::CHANNEL ) ), false, 'brown', 0.45, array(), '' );
			}
			$shapes[] = array( 'text', self::half( $sizes[0], $style )[0][1][0] - 0.5, self::CHANNEL / 2 + 0.4, __( 'Leave open', 'atelier-irisee-master-plugin' ), 8, 'gold_text', 'right', false, 0 );
		} else {
			$g        = self::GAP / 2;
			$shapes[] = array( 'poly', array( array( $g, $top - 0.5 ), array( $g, $top + 1.4 ) ), false, 'brown', 0.45, array(), '' );
			$shapes[] = array( 'text', $g + 0.4, 1.4, __( 'Turning gap', 'atelier-irisee-master-plugin' ), 8, 'gold_text', 'left', false, 0 );
		}

		// Grain line.
		$gx       = 2.6;
		$gy1      = ( 'collar' === $style ? self::CHANNEL : self::END ) + 1.5;
		$gy2      = $gy1 + min( 9, ( $max_y - $gy1 ) * 0.5 );
		$shapes[] = array( 'poly', array( array( $gx, $gy1 ), array( $gx, $gy2 ) ), false, 'brown', 0.35, array(), '' );
		foreach ( array( array( $gy1, 1 ), array( $gy2, -1 ) ) as $end ) {
			$shapes[] = array( 'poly', array( array( $gx - 0.35, $end[0] + 0.6 * $end[1] ), array( $gx, $end[0] ), array( $gx + 0.35, $end[0] + 0.6 * $end[1] ) ), false, 'brown', 0.35, array(), '' );
		}
		$shapes[] = array( 'text', $gx + 0.5, ( $gy1 + $gy2 ) / 2, __( 'Grain line', 'atelier-irisee-master-plugin' ), 8, 'brown', 'center', false, 90 );

		// The label of the piece.
		$styles = self::styles();
		$lx     = $gx + 1.3;
		$ly     = $gy1 + 0.6;
		$lines  = array(
			array( __( 'Dog bandana', 'atelier-irisee-master-plugin' ), 11, 'gold_text', true ),
			array( $styles[ $style ], 9, 'brown', true ),
			/* translators: %s: size, or sizes */
			array( $all ? sprintf( __( 'Sizes %s', 'atelier-irisee-master-plugin' ), 'XS – XL' ) : sprintf( __( 'Size %s', 'atelier-irisee-master-plugin' ), self::sizes()[ $sizes[0] ][0] ), 9, 'brown', false ),
			array( __( 'Cut 2 on the fold', 'atelier-irisee-master-plugin' ), 8, 'brown', false ),
			array( __( 'Seam allowance 1 cm included', 'atelier-irisee-master-plugin' ), 7, 'brown', false ),
		);
		foreach ( $lines as $line ) {
			$ly      += $line[1] * 0.045;
			$shapes[] = array( 'text', $lx, $ly, $line[0], $line[1], $line[2], 'left', $line[3], 0 );
			$ly      += 0.15;
		}
		return array( -1, $top - 0.3, $max_x + 1, $max_y + 0.3, $shapes );
	}
}
