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
	}

	/* ------------------------------------------------------------------
	 * Settings (Atelier Irisee → Dog bandana)
	 * ------------------------------------------------------------------ */

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
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
	 * The bandana pattern product when it can be bought.
	 *
	 * @return WC_Product|null
	 */
	public static function product() {
		$product = self::opt( 'product' ) ? wc_get_product( self::opt( 'product' ) ) : null;
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
		if ( ! $product || ! $order || ! $order->is_paid() ) {
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
	 * The guide in parts, for the page and the PDF. Each part: [ id, title, public, blocks ]; a block is
	 * [ 'p', text ], [ 'list', items ], [ 'steps', [ [ title, text, figure ] ] ], [ 'figure', name, caption ],
	 * [ 'table', head, rows, note ], [ 'facts', [ [ label, value ] ] ], [ 'tip', text ] or [ 'terms', [ [ term, text ] ] ].
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
							array( __( 'Time', 'atelier-irisee-master-plugin' ), __( 'About 1 hour', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Sizes', 'atelier-irisee-master-plugin' ), 'XS – XL' ),
							array( __( 'Seam allowance', 'atelier-irisee-master-plugin' ), __( '1 cm, included', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
			),
			array(
				'styles',
				__( 'Three ways to wear it', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'figure', 'styles', '' ),
					array(
						'terms',
						array(
							array( $styles['tie'], __( 'The classic: long ends that you knot at the back of the neck. Fits a wide range of necks and needs no collar.', 'atelier-irisee-master-plugin' ) ),
							array( $styles['collar'], __( 'A channel along the top edge slides over your dog\'s own collar, so the bandana stays in place and never pulls tight.', 'atelier-irisee-master-plugin' ) ),
							array( $styles['snap'], __( 'Short ends that overlap and close with a plastic snap: quick to put on and take off.', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
			),
			array(
				'materials',
				__( 'What you need', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array(
						'list',
						array(
							__( 'Fabric: a woven cotton such as poplin, quilting cotton or cotton lawn (see the size chart for how much).', 'atelier-irisee-master-plugin' ),
							__( 'If you like, a second fabric for the reverse side (the same amount), or the same fabric twice.', 'atelier-irisee-master-plugin' ),
							__( 'Matching sewing thread.', 'atelier-irisee-master-plugin' ),
							__( 'With a snap: one plastic snap (size 20) and snap pliers or a snap press.', 'atelier-irisee-master-plugin' ),
							__( 'Ribbon ties (optional): 120 cm of ribbon or bias tape, 1 to 1.5 cm wide.', 'atelier-irisee-master-plugin' ),
						),
					),
				),
			),
			array(
				'tools',
				__( 'Tools', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array(
						'list',
						array(
							__( 'Sewing machine with a universal needle 80', 'atelier-irisee-master-plugin' ),
							__( 'Fabric scissors, or a rotary cutter and cutting mat', 'atelier-irisee-master-plugin' ),
							__( 'Pins or sewing clips', 'atelier-irisee-master-plugin' ),
							__( 'Iron and ironing board', 'atelier-irisee-master-plugin' ),
							__( 'Tailor\'s chalk or a fabric marker', 'atelier-irisee-master-plugin' ),
							__( 'Ruler or tape measure', 'atelier-irisee-master-plugin' ),
							__( 'Point turner (or a chopstick)', 'atelier-irisee-master-plugin' ),
							__( 'Seam ripper', 'atelier-irisee-master-plugin' ),
							__( 'Paper scissors and tape for the template', 'atelier-irisee-master-plugin' ),
						),
					),
				),
			),
			array(
				'fabric',
				__( 'Choosing your fabric', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'p', __( 'Choose a fabric that is soft, breathable and washable: woven cotton is ideal. It holds its shape, is easy to sew and gets softer with every wash. Avoid very thick fabrics (the points get bulky) and very slippery ones.', 'atelier-irisee-master-plugin' ) ),
					array( 'p', __( 'Wash and iron your fabric before you cut it. Cotton can shrink a little in the first wash; this way your bandana keeps its size.', 'atelier-irisee-master-plugin' ) ),
				),
			),
			array(
				'size',
				__( 'Measuring your dog', 'atelier-irisee-master-plugin' ),
				true,
				array(
					array( 'figure', 'measure', '' ),
					array( 'p', __( 'Measure around your dog\'s neck where the collar sits, with a soft tape measure. Leave room for two fingers between the tape and the neck. Choose the size whose neck range holds your measurement; between two sizes, take the larger one for the tie-on style and the smaller one with a snap.', 'atelier-irisee-master-plugin' ) ),
					array( 'table', $head, $rows, __( 'Finished sizes, width × depth. Fabric: the length of 140 cm wide fabric for both sides of the bandana.', 'atelier-irisee-master-plugin' ) ),
				),
			),
			array(
				'print',
				__( 'Printing the template', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'steps',
						array(
							array( '', __( 'Download the template of your size, or the template with all sizes.', 'atelier-irisee-master-plugin' ), '' ),
							array( '', __( 'Print it on A4 paper at 100 % ("Actual size"). Do not choose "Fit to page".', 'atelier-irisee-master-plugin' ), '' ),
							array( '', __( 'Measure the test square on the first page: it must be exactly 5 × 5 cm. If it is not, check your printer settings and print again.', 'atelier-irisee-master-plugin' ), 'square' ),
							array( '', __( 'Lay the sheets out as in the overview: A1, A2 … from left to right, then the next row.', 'atelier-irisee-master-plugin' ), '' ),
							array( '', __( 'Cut away the margin on the right and bottom edge of each sheet, up to the frame. Lay each sheet over its neighbour so the frame lines meet and the half diamonds form whole ones, and tape them together.', 'atelier-irisee-master-plugin' ), 'assembly' ),
							array( '', __( 'Cut out the pattern piece along the line of your size.', 'atelier-irisee-master-plugin' ), '' ),
						),
					),
				),
			),
			array(
				'cutting',
				__( 'Cutting', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array( 'figure', 'layout', '' ),
					array( 'p', __( 'Fold your fabric in half lengthwise, right sides together, with the selvedges on top of each other. Lay the template with its straight edge ("Place on fold") exactly on the fold, and the grain line parallel to the selvedge.', 'atelier-irisee-master-plugin' ) ),
					array( 'p', __( 'Pin it down and cut along the cutting line. Cut a second piece the same way, from the same fabric or from your reverse fabric.', 'atelier-irisee-master-plugin' ) ),
					array( 'p', __( 'Copy the marks with chalk: the turning gap, and the channel line or the snap marks of your style.', 'atelier-irisee-master-plugin' ) ),
					array( 'tip', __( 'A fabric with a motif? Check where the motif falls on the front of the bandana before you cut.', 'atelier-irisee-master-plugin' ) ),
				),
			),
			array(
				'sewing',
				__( 'Sewing', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'steps',
						array(
							array( __( 'Pin', 'atelier-irisee-master-plugin' ), __( 'Lay both pieces on top of each other, right sides together, and pin or clip all around.', 'atelier-irisee-master-plugin' ), 'pin' ),
							array( __( 'Sew', 'atelier-irisee-master-plugin' ), __( 'Sew all around with a 1 cm seam allowance. Leave a 6 cm turning gap in the middle of the top edge, between the marks, and backstitch at the start and the end. Over the collar: leave the short sides of the top band open instead; they become the ends of the channel, and you turn the bandana through one of them.', 'atelier-irisee-master-plugin' ), 'sew' ),
							array( __( 'Clip', 'atelier-irisee-master-plugin' ), __( 'Cut the seam allowance off across the points, close to the stitching, and clip into the inner corners up to the stitching without cutting it. This gives sharp points and flat corners.', 'atelier-irisee-master-plugin' ), 'clip' ),
							array( __( 'Turn and press', 'atelier-irisee-master-plugin' ), __( 'Turn the bandana right side out through the gap. Push the points out with a point turner and press flat; fold the seam allowance of the gap inwards and press it too.', 'atelier-irisee-master-plugin' ), 'turn' ),
							array( __( 'Topstitch', 'atelier-irisee-master-plugin' ), __( 'Topstitch all around, 3 mm from the edge. This closes the turning gap and keeps the edges flat.', 'atelier-irisee-master-plugin' ), 'topstitch' ),
						),
					),
				),
			),
			array(
				'finish',
				__( 'Finishing your style', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'steps',
						array(
							array( $styles['tie'], __( 'Done! Tie the ends in a knot or a bow at the back of the neck, with room for two fingers under the bandana.', 'atelier-irisee-master-plugin' ), 'tie' ),
							array( $styles['collar'], __( 'Press the seam allowances of the open sides inwards. Stitch a straight line across along the channel line, 4 cm below the top edge: front and back now form a channel. Topstitch the edges of the openings one layer at a time, so the channel stays open, and slide your dog\'s collar through.', 'atelier-irisee-master-plugin' ), 'collar' ),
							array( $styles['snap'], __( 'Mark the snap on both ends: the cap on the front of one end, the socket on the back of the other. Make a small hole with an awl and fix the snap with the pliers or press, as its instructions say. Close it at the back of the neck.', 'atelier-irisee-master-plugin' ), 'snap' ),
							array( __( 'Ribbon ties', 'atelier-irisee-master-plugin' ), __( 'Instead of fabric ties you can use ribbon: cut the over-the-collar piece, pin a 60 cm ribbon into each top corner between the layers, pointing inwards, and sew all around without the channel.', 'atelier-irisee-master-plugin' ), '' ),
						),
					),
				),
			),
			array(
				'care',
				__( 'Care and safety', 'atelier-irisee-master-plugin' ),
				false,
				array(
					array(
						'list',
						array(
							__( 'Wash at 30 °C, inside out, and iron on the reverse side.', 'atelier-irisee-master-plugin' ),
							__( 'Two fingers must always fit between the bandana and your dog\'s neck.', 'atelier-irisee-master-plugin' ),
							__( 'Never leave your dog alone or in its crate with a bandana on, and take it off for rough play and at night.', 'atelier-irisee-master-plugin' ),
							__( 'A bandana is not a toy: check it regularly for loose threads or a loose snap.', 'atelier-irisee-master-plugin' ),
						),
					),
				),
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
						),
					),
				),
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
							array( __( 'Seam allowance', 'atelier-irisee-master-plugin' ), __( 'The strip between the stitching and the cut edge: 1 cm in this pattern, included in the template.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Grain line', 'atelier-irisee-master-plugin' ), __( 'The arrow on the template: lay it parallel to the selvedge.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Selvedge', 'atelier-irisee-master-plugin' ), __( 'The finished long edges of the fabric.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Place on fold', 'atelier-irisee-master-plugin' ), __( 'The straight edge of the template lies on the folded edge of the fabric, so you cut one whole, symmetrical piece.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Topstitching', 'atelier-irisee-master-plugin' ), __( 'A visible line of stitching close to the edge, on the right side.', 'atelier-irisee-master-plugin' ) ),
							array( __( 'Backstitch', 'atelier-irisee-master-plugin' ), __( 'A few stitches back and forth that lock the seam.', 'atelier-irisee-master-plugin' ) ),
						),
					),
				),
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
