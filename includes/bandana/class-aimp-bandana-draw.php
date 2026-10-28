<?php
/**
 * Draws the bandana's shapes (AIMP_Bandana::figure() and ::template()) as inline SVG for the page, in the site's
 * colours (its CSS variables, so a change in the Design studio follows), or into a PDF.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bandana_Draw {

	/**
	 * A palette key as CSS (the site's own colour variables).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function css_color( $key ) {
		$colors = array(
			'brown'     => 'var(--aimp-site-brown,#613907)',
			'gold'      => 'var(--aimp-site-gold,#b38f4f)',
			'gold_text' => 'var(--aimp-site-gold-text,#8a6a33)',
			'tint'      => 'color-mix(in srgb,var(--aimp-site-gold,#b38f4f) 9%,#fff)',
			'light'     => 'color-mix(in srgb,var(--aimp-site-gold,#b38f4f) 45%,#fff)',
			'pink'      => 'var(--bd-pink,#e2bfc9)',
			'purple'    => 'var(--bd-purple,#79416d)',
			'blush'     => 'color-mix(in srgb,var(--bd-pink,#e2bfc9) 30%,#fff)',
		);
		return isset( $colors[ $key ] ) ? $colors[ $key ] : 'none';
	}

	private static function n( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * A drawing as SVG.
	 *
	 * @param array  $figure [ width, height, shapes ] (AIMP_Bandana::figure()).
	 * @param string $label  Text for screen readers ('' = decorative).
	 * @return string
	 */
	public static function svg( $figure, $label = '' ) {
		if ( ! $figure ) {
			return '';
		}
		list( $w, $h, $shapes ) = $figure;
		$out = '<svg class="aimp-bandana-figure" viewBox="-2 -6 ' . self::n( $w + 4 ) . ' ' . self::n( $h + 8 ) . '" xmlns="http://www.w3.org/2000/svg"' .
			( '' !== $label ? ' role="img" aria-label="' . esc_attr( $label ) . '"' : ' aria-hidden="true" focusable="false"' ) . '>';
		foreach ( $shapes as $s ) {
			$out .= self::svg_shape( $s );
		}
		return $out . '</svg>';
	}

	/**
	 * A small icon (AIMP_Bandana::icon()) as SVG; its lines keep their width at every size (bandana.css).
	 *
	 * @param string $name Icon.
	 * @return string
	 */
	public static function icon_svg( $name ) {
		$out = '<svg class="aimp-bandana-icon" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';
		foreach ( AIMP_Bandana::icon( $name )[2] as $s ) {
			$out .= self::svg_shape( $s );
		}
		return $out . '</svg>';
	}

	/**
	 * The points of the logo's ribbon circle: a ring that waves in and out (unit circle, $n points).
	 *
	 * @param float $phase Where the waves start.
	 * @param int   $n     Points.
	 * @return array
	 */
	public static function ribbon_points( $phase, $n = 120 ) {
		$points = array();
		for ( $i = 0; $i <= $n; $i++ ) {
			$a        = 2 * M_PI * $i / $n;
			$r        = 1 + 0.045 * sin( 5 * $a + $phase ) + 0.02 * sin( 3 * $a - $phase );
			$points[] = array( cos( $a ) * $r, sin( $a ) * $r );
		}
		return $points;
	}

	/**
	 * The logo's ribbon circle as SVG: a wide ribbon from gold to pink and purple, and a thin gold line crossing it.
	 *
	 * @param string $class Class.
	 * @return string
	 */
	public static function ribbon_svg( $class = 'aimp-bandana-ribbon' ) {
		$id   = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'aimp-bd-ribbon-' ) : 'aimp-bd-ribbon-' . wp_rand();
		$path = function ( $phase ) {
			$d = '';
			foreach ( self::ribbon_points( $phase ) as $i => $p ) {
				$d .= ( $i ? 'L' : 'M' ) . self::n( 50 + $p[0] * 44 ) . ' ' . self::n( 50 + $p[1] * 44 );
			}
			return $d . 'Z';
		};
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' .
			'<defs><linearGradient id="' . esc_attr( $id ) . '" x1="0" y1="0" x2="1" y2="1">' .
			'<stop offset="0" stop-color="var(--hb-gold,#b38f4f)"/><stop offset=".45" stop-color="var(--bd-pink,#e2bfc9)"/>' .
			'<stop offset=".8" stop-color="var(--bd-purple,#79416d)" stop-opacity=".55"/><stop offset="1" stop-color="var(--hb-gold,#b38f4f)"/></linearGradient></defs>' .
			'<path class="aimp-bandana-ribbon-band" d="' . $path( 0 ) . '" fill="none" stroke="url(#' . esc_attr( $id ) . ')" stroke-width="3.2" stroke-linejoin="round"/>' .
			'<path class="aimp-bandana-ribbon-line" d="' . $path( 2.2 ) . '" fill="none" stroke="var(--hb-gold,#b38f4f)" stroke-width=".5"/>' .
			'</svg>';
	}

	/**
	 * The logo's ribbon circle in a PDF: short pieces whose colour runs from gold to pink, purple and back.
	 *
	 * @param AIMP_Pdf $pdf     PDF.
	 * @param float    $cx      Centre (mm).
	 * @param float    $cy      Centre (mm).
	 * @param float    $rx      Radius across (mm).
	 * @param float    $ry      Radius down (mm).
	 * @param array    $palette Colours (AIMP_Bandana::palette()).
	 * @param float    $width   Width of the ribbon (mm).
	 */
	public static function ribbon_pdf( $pdf, $cx, $cy, $rx, $ry, $palette, $width = 1.4 ) {
		$stops  = array( $palette['gold'], $palette['pink'], $palette['blush'], $palette['purple'], $palette['pink'], $palette['gold'] );
		$rgb    = function ( $hex ) {
			$hex = ltrim( $hex, '#' );
			return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		};
		$points = self::ribbon_points( 0 );
		$n      = count( $points ) - 1;
		for ( $i = 0; $i < $n; $i++ ) {
			$t     = $i / $n * ( count( $stops ) - 1 );
			$k     = min( count( $stops ) - 2, (int) floor( $t ) );
			$a     = $rgb( $stops[ $k ] );
			$b     = $rgb( $stops[ $k + 1 ] );
			$f     = $t - $k;
			$color = array( $a[0] + ( $b[0] - $a[0] ) * $f, $a[1] + ( $b[1] - $a[1] ) * $f, $a[2] + ( $b[2] - $a[2] ) * $f );
			$pdf->stroke( $color, $width );
			$pdf->line( $cx + $points[ $i ][0] * $rx, $cy + $points[ $i ][1] * $ry, $cx + $points[ $i + 1 ][0] * $rx, $cy + $points[ $i + 1 ][1] * $ry );
		}
		$segments = array();
		foreach ( self::ribbon_points( 2.2 ) as $i => $p ) {
			$segments[] = array( $i ? 'L' : 'M', $cx + $p[0] * $rx, $cy + $p[1] * $ry );
		}
		$pdf->stroke( $palette['gold'], 0.25 );
		$pdf->path( $segments );
	}

	private static function stroke_css( $stroke, $width, $dashes, $fill ) {
		return 'fill:' . self::css_color( $fill ) . ';stroke:' . self::css_color( $stroke ) . ';stroke-width:' . self::n( $width ) . ';stroke-linecap:round;stroke-linejoin:round' .
			( $dashes ? ';stroke-dasharray:' . implode( ' ', array_map( array( __CLASS__, 'n' ), $dashes ) ) : '' );
	}

	private static function svg_shape( $s ) {
		switch ( $s[0] ) {
			case 'poly':
				$points = implode(
					' ',
					array_map(
						function ( $p ) {
							return self::n( $p[0] ) . ',' . self::n( $p[1] );
						},
						$s[1]
					)
				);
				return '<' . ( $s[2] ? 'polygon' : 'polyline' ) . ' points="' . $points . '" style="' . self::stroke_css( $s[3], $s[4], $s[5], $s[6] ) . '"/>';
			case 'path':
				$d = '';
				foreach ( $s[1] as $seg ) {
					$d .= $seg[0] . ( count( $seg ) > 1 ? implode( ' ', array_map( array( __CLASS__, 'n' ), array_slice( $seg, 1 ) ) ) : '' ) . ' ';
				}
				return '<path d="' . trim( $d ) . '" style="' . self::stroke_css( $s[2], $s[3], $s[4], $s[5] ) . '"/>';
			case 'circle':
				return '<circle cx="' . self::n( $s[1] ) . '" cy="' . self::n( $s[2] ) . '" r="' . self::n( $s[3] ) . '" style="' . self::stroke_css( $s[4], $s[5], array(), $s[6] ) . '"/>';
			case 'text':
				$size   = $s[4] * 0.3528 * 0.9;
				$anchor = array(
					'left'   => 'start',
					'center' => 'middle',
					'right'  => 'end',
				);
				$turn   = $s[8] ? ' transform="rotate(' . self::n( -$s[8] ) . ' ' . self::n( $s[1] ) . ' ' . self::n( $s[2] ) . ')"' : '';
				return '<text x="' . self::n( $s[1] ) . '" y="' . self::n( $s[2] ) . '"' . $turn . ' text-anchor="' . $anchor[ $s[6] ] . '" style="fill:' . self::css_color( $s[5] ) . ';font-size:' . self::n( $size ) . 'px;font-weight:' . ( $s[7] ? 700 : 400 ) . ';letter-spacing:.04em">' . esc_html( $s[3] ) . '</text>';
		}
		return '';
	}

	/**
	 * Shapes into a PDF.
	 *
	 * @param AIMP_Pdf $pdf     PDF.
	 * @param array    $shapes  Shapes.
	 * @param float    $x       Left of the drawing's 0 (mm).
	 * @param float    $y       Top of the drawing's 0 (mm).
	 * @param float    $scale   Millimetres per unit.
	 * @param array    $palette Colours (AIMP_Bandana::palette()).
	 * @param array    $fonts   [ regular, bold ] font keys.
	 */
	public static function pdf( $pdf, $shapes, $x, $y, $scale, $palette, $fonts ) {
		$at    = function ( $p ) use ( $x, $y, $scale ) {
			return array( $x + $p[0] * $scale, $y + $p[1] * $scale );
		};
		$color = function ( $key ) use ( $palette ) {
			return isset( $palette[ $key ] ) ? $palette[ $key ] : $palette['brown'];
		};
		$mode  = function ( $stroke, $fill ) {
			return '' !== $fill && '' !== $stroke ? 'B' : ( '' !== $fill ? 'f' : 'S' );
		};
		foreach ( $shapes as $s ) {
			switch ( $s[0] ) {
				case 'poly':
					$pdf->stroke( $color( $s[3] ), $s[4], $s[5] );
					if ( '' !== $s[6] ) {
						$pdf->fill( $color( $s[6] ) );
					}
					$segments = array();
					foreach ( $s[1] as $i => $p ) {
						$q          = $at( $p );
						$segments[] = array( $i ? 'L' : 'M', $q[0], $q[1] );
					}
					if ( $s[2] ) {
						$segments[] = array( 'Z' );
					}
					$pdf->path( $segments, $mode( $s[3], $s[6] ) );
					break;
				case 'path':
					$pdf->stroke( $color( $s[2] ), $s[3], $s[4] );
					if ( '' !== $s[5] ) {
						$pdf->fill( $color( $s[5] ) );
					}
					$segments = array();
					foreach ( $s[1] as $seg ) {
						$out = array( $seg[0] );
						for ( $i = 1; $i + 1 < count( $seg ); $i += 2 ) {
							$q     = $at( array( $seg[ $i ], $seg[ $i + 1 ] ) );
							$out[] = $q[0];
							$out[] = $q[1];
						}
						$segments[] = $out;
					}
					$pdf->path( $segments, $mode( $s[2], $s[5] ) );
					break;
				case 'circle':
					$pdf->stroke( $color( $s[4] ), $s[5] );
					if ( '' !== $s[6] ) {
						$pdf->fill( $color( $s[6] ) );
					}
					$q = $at( array( $s[1], $s[2] ) );
					$pdf->circle( $q[0], $q[1], $s[3] * $scale, $mode( $s[4], $s[6] ) );
					break;
				case 'text':
					$q = $at( array( $s[1], $s[2] ) );
					$pdf->text( $q[0], $q[1], $s[3], $s[7] ? $fonts[1] : $fonts[0], $s[4], $color( $s[5] ), $s[6], 0.02, $s[8] );
					break;
			}
		}
	}
}
