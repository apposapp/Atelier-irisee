<?php
/**
 * The bandana's two PDFs, in the site's look (its colours, its logo, its font when the font file can be embedded,
 * the logo's ribbon circle in gold, pink and purple):
 *  - the guide: a cover with the hero photo, then every part of AIMP_Bandana::guide() with its photos, icons and
 *    drawings;
 *  - the template: an overview (with the 5 × 5 cm test square and the order of the sheets), then the pattern piece at
 *    its real size over A4 sheets that are taped together.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bandana_Docs {

	/** Page layout (mm). */
	const LEFT   = 18;
	const RIGHT  = 192;
	const TOP    = 30;
	const BOTTOM = 276;

	/** Template sheets: the frame of each sheet (mm). */
	const TILE_X = 15;
	const TILE_Y = 22;
	const TILE_W = 180;
	const TILE_H = 262;

	/** @var AIMP_Pdf */
	private $pdf;

	/** @var array Colours. */
	private $c;

	/** @var array Font keys: head, body, bold. */
	private $f;

	/** @var string Logo picture key, or ''. */
	private $logo = '';

	/** @var array Picture slot => key ('' = none). */
	private $photos = array();

	/** @var float Where the next block starts (mm). */
	private $y = self::TOP;

	/** @var float Left and right of the column the blocks are written in (mm). */
	private $l = self::LEFT;
	private $r = self::RIGHT;

	/** @var string Running title in the page head. */
	private $running = '';

	private function __construct( $title ) {
		$this->pdf = new AIMP_Pdf( $title );
		$this->c   = AIMP_Bandana::palette();
		$this->setup_fonts();
		$this->setup_logo();
	}

	/* ------------------------------------------------------------------
	 * The site's font and logo, the photos
	 * ------------------------------------------------------------------ */

	/**
	 * The site's own font (Settings → Site look) when its file can be embedded; titles and texts follow the Design
	 * studio's choice (that font, or a plain one).
	 */
	private function setup_fonts() {
		$site = false;
		if ( AIMP_Settings::get( 'site_font' ) && AIMP_Settings::get( 'font_regular' ) ) {
			$site = $this->pdf->add_font( 'site', (string) get_attached_file( AIMP_Settings::get( 'font_regular' ) ) );
			if ( $site && AIMP_Settings::get( 'font_bold' ) ) {
				$this->pdf->add_font( 'site-bold', (string) get_attached_file( AIMP_Settings::get( 'font_bold' ) ) );
			}
		}
		$choice = array(
			'head' => '',
			'body' => '',
		);
		if ( class_exists( 'AIMP_Design' ) ) {
			$elements = AIMP_Design::elements();
			foreach ( AIMP_Design::live() as $id => $values ) {
				if ( isset( $elements[ $id ] ) && '' === $elements[ $id ]['selector'] && is_array( $values ) ) {
					foreach ( array_keys( $choice ) as $which ) {
						if ( isset( $values[ $which . '_font' ] ) ) {
							$choice[ $which ] = (string) $values[ $which . '_font' ];
						}
					}
				}
			}
		}
		$uses = function ( $which ) use ( $site, $choice ) {
			return $site && in_array( $choice[ $which ], array( '', 'trajan' ), true );
		};
		$bold    = $this->pdf->has_font( 'site-bold' ) ? 'site-bold' : 'site';
		$this->f = array(
			'head' => $uses( 'head' ) ? $bold : 'sans-bold',
			'body' => $uses( 'body' ) ? 'site' : 'sans',
			'bold' => $uses( 'body' ) ? $bold : 'sans-bold',
		);
	}

	/**
	 * The site logo: the same as the site header (AIMP_Bandana_Pictures::logo_file()).
	 */
	private function setup_logo() {
		$file       = AIMP_Bandana_Pictures::logo_file();
		$this->logo = '' !== $file ? $this->pdf->add_image( $file ) : '';
	}

	/**
	 * A guide picture's key in the PDF ('' = none), added once.
	 *
	 * @param string $slot Slot.
	 * @return string
	 */
	private function photo( $slot ) {
		if ( '' === $slot ) {
			return '';
		}
		if ( ! isset( $this->photos[ $slot ] ) ) {
			$jpeg                  = AIMP_Bandana_Pictures::jpeg( $slot );
			$this->photos[ $slot ] = '' !== $jpeg ? $this->pdf->add_jpeg( $jpeg ) : '';
		}
		return $this->photos[ $slot ];
	}

	/**
	 * A picture filling a box with rounded corners (cut to fit, centred).
	 */
	private function cover_image( $key, $x, $y, $w, $h, $r = 4 ) {
		$ratio = $this->pdf->image_ratio( $key );
		$dw    = $w;
		$dh    = $w / $ratio;
		if ( $dh < $h ) {
			$dh = $h;
			$dw = $h * $ratio;
		}
		$this->pdf->clip_round( $x, $y, $w, $h, $r );
		$this->pdf->image( $key, $x + ( $w - $dw ) / 2, $y + ( $h - $dh ) / 2, $dw, $dh );
		$this->pdf->restore();
	}

	/* ------------------------------------------------------------------
	 * Pieces of a page
	 * ------------------------------------------------------------------ */

	private function double_line( $x1, $x2, $y, $width = 0.3 ) {
		$this->pdf->stroke( $this->c['gold'], $width );
		$this->pdf->line( $x1, $y, $x2, $y );
		$this->pdf->line( $x1, $y + 0.9, $x2, $y + 0.9 );
	}

	/**
	 * A thin line from pink to purple (the logo's ribbon).
	 */
	private function iris_line( $x1, $x2, $y, $width = 0.35 ) {
		$a    = $this->rgb( $this->c['pink'] );
		$b    = $this->rgb( $this->c['purple'] );
		$step = ( $x2 - $x1 ) / 40;
		for ( $i = 0; $i < 40; $i++ ) {
			$f = $i / 39;
			$this->pdf->stroke( array( $a[0] + ( $b[0] - $a[0] ) * $f, $a[1] + ( $b[1] - $a[1] ) * $f, $a[2] + ( $b[2] - $a[2] ) * $f ), $width );
			$this->pdf->line( $x1 + $i * $step, $y, $x1 + ( $i + 1 ) * $step, $y );
		}
	}

	private function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * An icon of AIMP_Bandana::icon(), $size mm wide, top left at x, y.
	 */
	private function icon( $name, $x, $y, $size ) {
		AIMP_Bandana_Draw::pdf( $this->pdf, AIMP_Bandana::icon( $name )[2], $x, $y, $size / 12, $this->c, array( $this->f['body'], $this->f['bold'] ) );
	}

	/**
	 * An icon in a gold ring.
	 */
	private function ring_icon( $name, $cx, $cy, $r, $double = false ) {
		$this->pdf->fill( '#ffffff' );
		$this->pdf->stroke( $this->c['gold'], 0.3 );
		$this->pdf->circle( $cx, $cy, $r, 'B' );
		if ( $double ) {
			$this->pdf->circle( $cx, $cy, $r - 0.8 );
		}
		$this->icon( $name, $cx - $r * 0.62, $cy - $r * 0.62, $r * 1.24 );
	}

	/**
	 * The page head (logo, running title, gold double line, the logo's pink and purple) and foot (the site, page
	 * number added at the end).
	 */
	private function page_frame() {
		if ( '' !== $this->logo ) {
			$h = 12;
			$w = $h * $this->pdf->image_ratio( $this->logo );
			$this->pdf->image( $this->logo, self::LEFT, 6.5, $w, $h );
		} else {
			$this->pdf->text( self::LEFT, 15, get_bloginfo( 'name' ), $this->f['head'], 10, $this->c['gold_text'], 'left', 0.12 );
		}
		$this->pdf->text( self::RIGHT, 15, self::upper( $this->running ), $this->f['head'], 8, $this->c['gold_text'], 'right', 0.14 );
		$this->double_line( self::LEFT + 16, self::RIGHT, 20.5 );
		$this->iris_line( self::LEFT + 16, self::RIGHT, 22.6, 0.3 );
		$this->pdf->stroke( $this->c['gold'], 0.3 );
		$this->pdf->line( self::LEFT, 283, self::RIGHT, 283 );
		$this->pdf->text( self::LEFT, 288, wp_parse_url( home_url(), PHP_URL_HOST ), $this->f['body'], 7.5, $this->c['gold_text'] );
	}

	private function new_page() {
		$this->pdf->add_page();
		$this->page_frame();
		$this->y = self::TOP;
	}

	/**
	 * Room for $h mm, or a new page.
	 */
	private function need( $h ) {
		if ( $this->y + $h > self::BOTTOM ) {
			$this->new_page();
		}
	}

	private static function upper( $s ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $s, 'UTF-8' ) : strtoupper( (string) $s );
	}

	/**
	 * Page numbers "3 / 12" in every foot (the cover has none).
	 */
	private function numbers( $from = 1 ) {
		$total = $this->pdf->page_count();
		for ( $i = $from; $i < $total; $i++ ) {
			$this->pdf->on_page( $i );
			$this->pdf->text( self::RIGHT, 288, ( $i + 1 ) . ' / ' . $total, $this->f['body'], 7.5, $this->c['gold_text'], 'right' );
		}
	}

	/**
	 * A title between gold double lines, like the site's block titles; with an icon, in a small ribbon circle above.
	 */
	private function title( $s, $icon = '' ) {
		// The title never stays alone at the bottom of a page: room for the first block after it too.
		$this->need( '' !== $icon ? 105 : 45 );
		$mid = ( self::LEFT + self::RIGHT ) / 2;
		if ( '' !== $icon ) {
			$this->y += 9;
			AIMP_Bandana_Draw::ribbon_pdf( $this->pdf, $mid, $this->y, 8.6, 8.6, $this->c, 0.9 );
			$this->ring_icon( $icon, $mid, $this->y, 6, true );
			$this->y += 11;
		}
		$size = 15;
		$text = self::upper( $s );
		$w    = $this->pdf->width( $text, $this->f['head'], $size, 0.06 );
		$this->y += 6;
		$this->pdf->text( $mid, $this->y, $text, $this->f['head'], $size, $this->c['gold_text'], 'center', 0.06 );
		$this->double_line( self::LEFT, $mid - $w / 2 - 5, $this->y - 2.3, 0.35 );
		$this->double_line( $mid + $w / 2 + 5, self::RIGHT, $this->y - 2.3, 0.35 );
		$this->y += 9;
	}

	/**
	 * A paragraph in the current column (it may run onto the next page).
	 */
	private function paragraph( $s, $x = null, $width = 0, $size = 10, $font = '', $color = '' ) {
		$x     = null === $x ? $this->l : $x;
		$width = $width ? $width : $this->r - $x;
		$font  = $font ? $font : $this->f['body'];
		$color = $color ? $color : $this->c['brown'];
		$lead  = $size * 0.52;
		foreach ( $this->pdf->wrap( $s, $font, $size, $width ) as $line ) {
			$this->need( $lead );
			$this->y += $lead;
			$this->pdf->text( $x, $this->y - 1.2, $line, $font, $size, $color );
		}
		$this->y += 2.6;
	}

	/**
	 * Lines of a text at a fixed place (no page break), returns the height used.
	 */
	private function lines( $s, $x, $y, $width, $size = 10, $font = '', $color = '' ) {
		$font  = $font ? $font : $this->f['body'];
		$color = $color ? $color : $this->c['brown'];
		$lead  = $size * 0.52;
		$h     = 0;
		foreach ( $this->pdf->wrap( $s, $font, $size, $width ) as $line ) {
			$h += $lead;
			$this->pdf->text( $x, $y + $h - 1.2, $line, $font, $size, $color );
		}
		return $h;
	}

	/**
	 * Height of a wrapped text.
	 */
	private function text_height( $s, $width, $size = 10, $font = '' ) {
		return count( $this->pdf->wrap( $s, $font ? $font : $this->f['body'], $size, $width ) ) * $size * 0.52;
	}

	/**
	 * The little gold sparkle of the site's lists.
	 */
	private function sparkle( $x, $y, $r = 1.5, $color = '' ) {
		$k = $r * 0.28;
		$this->pdf->fill( $color ? $color : $this->c['gold'] );
		$this->pdf->path(
			array(
				array( 'M', $x, $y - $r ),
				array( 'C', $x + $k, $y - $k, $x + $k, $y - $k, $x + $r, $y ),
				array( 'C', $x + $k, $y + $k, $x + $k, $y + $k, $x, $y + $r ),
				array( 'C', $x - $k, $y + $k, $x - $k, $y + $k, $x - $r, $y ),
				array( 'C', $x - $k, $y - $k, $x - $k, $y - $k, $x, $y - $r ),
				array( 'Z' ),
			),
			'f'
		);
	}

	/**
	 * A drawing centred in the column, at most $max mm wide.
	 */
	private function figure( $name, $max = 120 ) {
		$fig = AIMP_Bandana::figure( $name );
		if ( ! $fig ) {
			return;
		}
		list( $w, $h, $shapes ) = $fig;
		$scale                  = min( $max / ( $w + 4 ), 1.6, ( $this->r - $this->l ) / ( $w + 4 ) );
		$height                 = ( $h + 8 ) * $scale;
		$this->need( $height + 4 );
		$x = ( $this->l + $this->r ) / 2 - ( $w + 4 ) * $scale / 2;
		AIMP_Bandana_Draw::pdf( $this->pdf, $shapes, $x + 2 * $scale, $this->y + 6 * $scale, $scale, $this->c, array( $this->f['body'], $this->f['bold'] ) );
		$this->y += $height + 4;
	}

	/**
	 * A drawing fitted in a box (top left x, y; w × h), in a gold-tinted frame.
	 */
	private function figure_box( $name, $x, $y, $w, $h ) {
		$this->pdf->fill( $this->c['tint'] );
		$this->pdf->stroke( $this->c['gold'], 0.3 );
		$this->pdf->round_rect( $x, $y, $w, $h, 4, 'B' );
		$fig = AIMP_Bandana::figure( $name );
		if ( ! $fig ) {
			return;
		}
		list( $fw, $fh, $shapes ) = $fig;
		$scale                    = min( ( $w - 6 ) / ( $fw + 4 ), ( $h - 6 ) / ( $fh + 8 ) );
		$dx                       = $x + ( $w - ( $fw + 4 ) * $scale ) / 2 + 2 * $scale;
		$dy                       = $y + ( $h - ( $fh + 8 ) * $scale ) / 2 + 6 * $scale;
		AIMP_Bandana_Draw::pdf( $this->pdf, $shapes, $dx, $dy, $scale, $this->c, array( $this->f['body'], $this->f['bold'] ) );
	}

	/**
	 * A part's blocks; a photo stands next to the list or text after it, as on the page.
	 */
	private function blocks( $blocks ) {
		$count = count( $blocks );
		for ( $i = 0; $i < $count; $i++ ) {
			$b = $blocks[ $i ];
			if ( 'photo' === $b[0] && $i + 1 < $count && in_array( $blocks[ $i + 1 ][0], array( 'items', 'dodont', 'p' ), true ) && '' !== $this->photo( $b[1] ) ) {
				$key = $this->photo( $b[1] );
				$w   = 76;
				$h   = min( 95, $w / $this->pdf->image_ratio( $key ) );
				$this->need( $h + 4 );
				$top = $this->y;
				$this->cover_image( $key, self::LEFT, $top, $w, $h, 5 );
				AIMP_Bandana_Draw::ribbon_pdf( $this->pdf, self::LEFT + $w - 4, $top + 4, 9, 9, $this->c, 0.8 );
				$this->l = self::LEFT + $w + 8;
				$this->block( $blocks[ $i + 1 ] );
				$this->l = self::LEFT;
				$this->y = max( $this->y, $top + $h + 6 );
				$i++;
				continue;
			}
			$this->block( $b );
		}
	}

	private function block( $b ) {
		$width = $this->r - $this->l;
		switch ( $b[0] ) {
			case 'p':
				$this->paragraph( $b[1] );
				break;

			case 'list':
				foreach ( $b[1] as $item ) {
					$this->need( 6 );
					$this->sparkle( $this->l + 2, $this->y + 3.4 );
					$this->paragraph( $item, $this->l + 7 );
					$this->y -= 1.2;
				}
				$this->y += 2;
				break;

			case 'items':
				foreach ( $b[1] as $item ) {
					$h = max( 10, $this->text_height( $item[1], $width - 13, 9.5 ) + 2 );
					$this->need( $h + 2 );
					$this->ring_icon( $item[0], $this->l + 4.5, $this->y + 4.5, 4.5 );
					$this->lines( $item[1], $this->l + 13, $this->y + max( 0.6, ( 9 - $this->text_height( $item[1], $width - 13, 9.5 ) ) / 2 ), $width - 13, 9.5 );
					$this->y += $h + 1.5;
				}
				$this->y += 2;
				break;

			case 'photo':
				$key = $this->photo( $b[1] );
				if ( '' !== $key ) {
					// A banner over the column's width.
					$h = min( 72, $width / $this->pdf->image_ratio( $key ) );
					$this->need( $h + 6 );
					$this->cover_image( $key, $this->l, $this->y, $width, $h, 5 );
					AIMP_Bandana_Draw::ribbon_pdf( $this->pdf, $this->r - 12, $this->y + 12, 8, 8, $this->c, 0.9 );
					$this->y += $h + 6;
				} elseif ( '' !== $b[3] ) {
					$this->figure( $b[3] );
				}
				break;

			case 'journey':
				$count = count( $b[1] );
				$step  = $width / $count;
				$this->need( 34 );
				$cy = $this->y + 10;
				$this->pdf->stroke( $this->c['gold'], 0.5, array( 1.4, 1.2 ) );
				$segments = array( array( 'M', $this->l + $step / 2, $cy ) );
				for ( $i = 1; $i < $count; $i++ ) {
					$x0         = $this->l + $step * ( $i - 0.5 );
					$segments[] = array( 'C', $x0 + $step * 0.35, $cy + ( $i % 2 ? -6 : 6 ), $x0 + $step * 0.65, $cy + ( $i % 2 ? -6 : 6 ), $x0 + $step, $cy );
				}
				$this->pdf->path( $segments );
				foreach ( $b[1] as $i => $item ) {
					$cx = $this->l + $step * ( $i + 0.5 );
					$this->ring_icon( $item[0], $cx, $cy, 8, true );
					$this->pdf->fill( $this->c['gold'] );
					$this->pdf->circle( $cx + 6.6, $cy - 6.6, 2.6, 'f' );
					$this->pdf->text( $cx + 6.6, $cy - 5.5, (string) ( $i + 1 ), $this->f['bold'], 8, '#ffffff', 'center' );
					$this->lines( self::upper( $item[1] ), $cx - $step / 2 + 1, $cy + 10, $step - 2, 7.5, $this->f['bold'], $this->c['gold_text'] );
				}
				foreach ( array( array( $this->r - 2, $this->y + 1, 2.2 ), array( $this->l + 2, $this->y + 1.5, 1.6 ) ) as $spark ) {
					$this->sparkle( $spark[0], $spark[1], $spark[2], $this->c['purple'] );
				}
				$this->y += 34;
				break;

			case 'cards':
				$count = count( $b[1] );
				$gap   = 6;
				$w     = ( $width - $gap * ( $count - 1 ) ) / $count;
				$ph    = $w * 1.1;
				$th    = 0;
				foreach ( $b[1] as $card ) {
					$th = max( $th, $this->text_height( $card[3], $w - 6, 8.5 ) );
				}
				$h = $ph + 14 + $th;
				$this->need( $h + 4 );
				foreach ( $b[1] as $i => $card ) {
					$x = $this->l + $i * ( $w + $gap );
					$this->pdf->stroke( $this->c['gold'], 0.3 );
					$this->pdf->round_rect( $x, $this->y, $w, $h, 5 );
					$key = $this->photo( $card[0] );
					if ( '' !== $key ) {
						$this->cover_image( $key, $x, $this->y, $w, $ph, 5 );
					} else {
						$this->figure_box( $card[1], $x, $this->y, $w, $ph );
					}
					$this->pdf->text( $x + $w / 2, $this->y + $ph + 6.5, self::upper( $card[2] ), $this->f['bold'], 9, $this->c['gold_text'], 'center', 0.06 );
					$this->lines( $card[3], $x + 3, $this->y + $ph + 9, $w - 6, 8.5 );
				}
				$this->y += $h + 6;
				break;

			case 'dodont':
				$cols = array(
					array( $b[1], 'tick', __( 'Choose', 'atelier-irisee-master-plugin' ), $this->c['tint'], $this->c['gold_text'] ),
					array( $b[2], 'warn', __( 'Avoid', 'atelier-irisee-master-plugin' ), $this->c['blush'], $this->c['purple'] ),
				);
				foreach ( $cols as $col ) {
					$h = 11;
					foreach ( $col[0] as $item ) {
						$h += $this->text_height( $item, $width - 12, 9 ) + 1.2;
					}
					$this->need( $h + 3 );
					$this->pdf->fill( $col[3] );
					$this->pdf->round_rect( $this->l, $this->y, $width, $h, 4, 'f' );
					$this->icon( $col[1], $this->l + 3, $this->y + 2.5, 5 );
					$this->pdf->text( $this->l + 10, $this->y + 6.4, self::upper( $col[2] ), $this->f['bold'], 8.5, $col[4], 'left', 0.1 );
					$y = $this->y + 9.5;
					foreach ( $col[0] as $item ) {
						$this->sparkle( $this->l + 6, $y + 2.3, 1.1, $col[4] );
						$y += $this->lines( $item, $this->l + 10, $y, $width - 12, 9 ) + 1.2;
					}
					$this->y += $h + 3;
				}
				$this->y += 2;
				break;

			case 'sizes':
				$sizes = AIMP_Bandana::sizes();
				$gap   = 4;
				$w     = ( $width - $gap * 4 ) / 5;
				$this->need( 44 );
				$n = 0;
				foreach ( $sizes as $key => $s ) {
					$x = $this->l + $n * ( $w + $gap );
					$this->pdf->stroke( $this->c['gold'], 0.3 );
					$this->pdf->round_rect( $x, $this->y, $w, 42, 4 );
					$d = 9 + 2.4 * $n++;
					$this->icon( 'dog', $x + ( $w - $d ) / 2, $this->y + 20 - $d, $d );
					$this->pdf->text( $x + $w / 2, $this->y + 27, $s[0], $this->f['head'], 12, $this->c['gold_text'], 'center' );
					/* translators: 1: from, 2: to (cm) */
					$this->pdf->text( $x + $w / 2, $this->y + 32.5, sprintf( __( '%1$s–%2$s cm', 'atelier-irisee-master-plugin' ), $s[1], $s[2] ), $this->f['body'], 8, $this->c['brown'], 'center' );
					/* translators: %s: centimetres */
					$this->pdf->text( $x + $w / 2, $this->y + 37, sprintf( __( 'Fabric: %s cm', 'atelier-irisee-master-plugin' ), AIMP_Bandana::fabric_need( $key ) ), $this->f['body'], 7, $this->c['gold_text'], 'center' );
				}
				$this->y += 48;
				break;

			case 'steps':
				$n = 0;
				foreach ( $b[1] as $step ) {
					$n++;
					$this->step( $step, $n );
				}
				break;

			case 'figure':
				$this->figure( $b[1] );
				break;

			case 'table':
				$head  = $b[1];
				$rows  = $b[2];
				$cols  = count( $head );
				// The first columns narrow, the measurements wider.
				$ws = array( 0.09, 0.15 );
				for ( $i = 2; $i < $cols - 1; $i++ ) {
					$ws[] = 0.66 / ( $cols - 3 );
				}
				$ws[]  = 0.10;
				$row_h = 7;
				$this->need( $row_h * ( count( $rows ) + 1 ) + 10 );
				$this->pdf->fill( $this->c['tint'] );
				$this->pdf->stroke( $this->c['gold'], 0.3 );
				$this->pdf->rect( $this->l, $this->y, $width, $row_h, 'B' );
				$x = $this->l;
				foreach ( $head as $i => $cell ) {
					$this->pdf->text( $x + 2, $this->y + 4.7, self::upper( $cell ), $this->f['bold'], 7.5, $this->c['gold_text'], 'left', 0.04 );
					$x += $ws[ $i ] * $width;
				}
				$this->y += $row_h;
				foreach ( $rows as $row ) {
					$x = $this->l;
					foreach ( $row as $i => $cell ) {
						$this->pdf->text( $x + 2, $this->y + 4.7, $cell, 0 === $i ? $this->f['bold'] : $this->f['body'], 8.5, $this->c['brown'] );
						$x += $ws[ $i ] * $width;
					}
					$this->y += $row_h;
					$this->pdf->stroke( $this->c['light'], 0.25 );
					$this->pdf->line( $this->l, $this->y, $this->r, $this->y );
				}
				$this->y += 2;
				if ( ! empty( $b[3] ) ) {
					$this->paragraph( $b[3], $this->l, 0, 8, '', $this->c['gold_text'] );
				}
				break;

			case 'facts':
				$count = count( $b[1] );
				$gap   = 4;
				$w     = ( $width - $gap * ( $count - 1 ) ) / $count;
				$this->need( 20 );
				foreach ( $b[1] as $i => $fact ) {
					$x = $this->l + $i * ( $w + $gap );
					$this->pdf->fill( '#ffffff' );
					$this->pdf->stroke( $this->c['gold'], 0.3 );
					$this->pdf->round_rect( $x, $this->y, $w, 16, 8, 'B' );
					$this->pdf->round_rect( $x + 0.9, $this->y + 0.9, $w - 1.8, 14.2, 7.2 );
					$this->pdf->text( $x + $w / 2, $this->y + 6.2, self::upper( $fact[0] ), $this->f['bold'], 6.5, $this->c['gold_text'], 'center', 0.12 );
					$this->pdf->text( $x + $w / 2, $this->y + 11.6, $fact[1], $this->f['bold'], 9.5, $this->c['brown'], 'center' );
				}
				$this->y += 22;
				break;

			case 'tip':
				$h = $this->text_height( $b[1], $width - 18, 9.5 ) + 12;
				$this->need( $h );
				$this->pdf->fill( $this->c['tint'] );
				$this->pdf->stroke( $this->c['gold'], 0.3 );
				$this->pdf->round_rect( $this->l, $this->y, $width, $h, 5, 'B' );
				$this->icon( 'bulb', $this->l + 3.5, $this->y + 3.2, 6.5 );
				$this->pdf->text( $this->l + 12, $this->y + 7.2, self::upper( __( 'Tip', 'atelier-irisee-master-plugin' ) ), $this->f['bold'], 8, $this->c['gold_text'], 'left', 0.12 );
				$this->lines( $b[1], $this->l + 12, $this->y + 8.5, $width - 18, 9.5 );
				$this->y += $h + 4;
				break;

			case 'terms':
				foreach ( $b[1] as $term ) {
					$this->need( 12 );
					$this->sparkle( $this->l + 1.5, $this->y + 3.4, 1.3 );
					$this->pdf->text( $this->l + 5, $this->y + 4.6, self::upper( $term[0] ), $this->f['bold'], 9, $this->c['gold_text'], 'left', 0.06 );
					$this->y += 5.4;
					$this->paragraph( $term[1], $this->l + 5 );
				}
				break;
		}
	}

	/**
	 * One step: its photo or drawing on the left, the number, title, why, sub-steps and notes on the right. Without a
	 * picture or drawing, the text uses the whole width.
	 *
	 * @param array $step [ title, text, drawing, extra ].
	 * @param int   $n    Number.
	 */
	private function step( $step, $n ) {
		$extra = isset( $step[3] ) ? $step[3] : array();
		$key   = $this->photo( isset( $extra['slot'] ) ? $extra['slot'] : '' );
		$media = '' !== $key || '' !== $step[2];
		$mw    = $media ? 62 : 0;
		$x     = $this->l + ( $media ? $mw + 8 : 0 );
		$w     = $this->r - $x;
		$mh    = '' !== $key ? min( 70, $mw / $this->pdf->image_ratio( $key ) ) : ( $media ? 46 : 0 );

		$body = $this->step_body( $step, $extra, $n, $x, 0, $w, false );
		$this->need( max( $mh, $body ) + 6 );
		$top = $this->y;
		if ( '' !== $key ) {
			$this->cover_image( $key, $this->l, $top, $mw, $mh, 5 );
			if ( '' !== $step[2] ) {
				$this->figure_box( $step[2], $this->l + $mw - 30, $top + $mh - 20, 28, 18 );
			}
		} elseif ( $media ) {
			$this->figure_box( $step[2], $this->l, $top, $mw, $mh );
		}
		$this->step_body( $step, $extra, $n, $x, $top, $w, true );
		$this->y = $top + max( $mh, $body ) + 7;
	}

	/**
	 * Draws (or only measures) a step's text block; returns its height.
	 */
	private function step_body( $step, $extra, $n, $x, $top, $w, $draw ) {
		$y  = $top;
		$tx = $x + 11;
		$tw = $w - 11;
		if ( $draw ) {
			$this->pdf->fill( '#ffffff' );
			$this->pdf->stroke( $this->c['gold'], 0.35 );
			$this->pdf->circle( $x + 4.2, $y + 4.2, 4.2, 'B' );
			$this->pdf->circle( $x + 4.2, $y + 4.2, 3.4 );
			$this->pdf->text( $x + 4.2, $y + 5.6, (string) $n, $this->f['head'], 10, $this->c['gold_text'], 'center' );
		}
		if ( '' !== $step[0] ) {
			if ( $draw ) {
				$this->pdf->text( $tx, $y + 5.6, self::upper( $step[0] ), $this->f['bold'], 10, $this->c['gold_text'], 'left', 0.06 );
				if ( ! empty( $extra['time'] ) ) {
					$tw0 = $this->pdf->width( self::upper( $step[0] ), $this->f['bold'], 10, 0.06 );
					$this->icon( 'clock', $tx + $tw0 + 3, $y + 2, 4.4 );
					$this->pdf->text( $tx + $tw0 + 8.6, $y + 5.4, $extra['time'], $this->f['body'], 8, $this->c['gold_text'] );
				}
			}
			$y += 8.5;
		} else {
			$y += 1;
		}
		$measure = function ( $s, $x0, $width, $size = 9.5, $font = '', $color = '' ) use ( &$y, $draw ) {
			$y += $draw ? $this->lines( $s, $x0, $y, $width, $size, $font, $color ) : $this->text_height( $s, $width, $size, $font );
		};
		if ( ! empty( $extra['why'] ) ) {
			$measure( $extra['why'], $tx, $tw, 9.5, '', $this->c['gold_text'] );
			$y += 1.8;
		}
		if ( ! empty( $extra['do'] ) ) {
			foreach ( $extra['do'] as $i => $line ) {
				if ( $draw ) {
					$this->pdf->text( $tx, $y + 3.7, ( $i + 1 ) . '.', $this->f['bold'], 9, $this->c['gold_text'] );
				}
				$measure( $line, $tx + 5, $tw - 5 );
				$y += 1.4;
			}
		} else {
			$measure( $step[1], $tx, $tw );
		}
		$y += 1.2;
		$chips = array(
			'note'    => array( 'bulb', __( 'Note', 'atelier-irisee-master-plugin' ), $this->c['tint'], $this->c['gold_text'] ),
			'check'   => array( 'tick', __( 'Check', 'atelier-irisee-master-plugin' ), $this->c['tint'], $this->c['gold_text'] ),
			'mistake' => array( 'warn', __( 'Common mistake', 'atelier-irisee-master-plugin' ), $this->c['blush'], $this->c['purple'] ),
		);
		foreach ( $chips as $k => $chip ) {
			if ( empty( $extra[ $k ] ) ) {
				continue;
			}
			$label = self::upper( $chip[1] );
			$h     = $this->text_height( $extra[ $k ], $tw - 10, 8.5 ) + 7.4;
			if ( $draw ) {
				$this->pdf->fill( $chip[2] );
				$this->pdf->round_rect( $tx, $y, $tw, $h, 3, 'f' );
				$this->icon( $chip[0], $tx + 2, $y + 1.8, 5 );
				$this->pdf->text( $tx + 8.5, $y + 5, $label, $this->f['bold'], 7, $chip[3], 'left', 0.1 );
				$this->lines( $extra[ $k ], $tx + 8.5, $y + 5.6, $tw - 10, 8.5 );
			}
			$y += $h + 1.8;
		}
		return $y - $top;
	}

	/* ------------------------------------------------------------------
	 * The guide
	 * ------------------------------------------------------------------ */

	/**
	 * @return string The PDF.
	 */
	public static function guide() {
		$doc          = new self( __( 'Dog bandana', 'atelier-irisee-master-plugin' ) . ' – ' . __( 'Sewing guide', 'atelier-irisee-master-plugin' ) );
		$doc->running = __( 'Dog bandana', 'atelier-irisee-master-plugin' ) . ' · ' . __( 'Sewing guide', 'atelier-irisee-master-plugin' );
		$doc->cover();
		$doc->new_page();
		foreach ( AIMP_Bandana::guide() as $part ) {
			if ( 'intro' === $part[0] ) {
				continue;
			}
			$doc->title( $part[1], $part[4] );
			$doc->blocks( $part[3] );
			$doc->y += 4;
		}
		$doc->numbers();
		return $doc->pdf->output();
	}

	/**
	 * The cover: the hero photo (or the three styles), the logo in the ribbon circle, the title and the facts.
	 */
	private function cover() {
		$this->pdf->add_page();
		$mid  = 105;
		$hero = $this->photo( 'hero' );
		$this->pdf->stroke( $this->c['gold'], 0.5 );
		$this->pdf->round_rect( 10, 10, 190, 277, 8 );
		$this->pdf->round_rect( 11.6, 11.6, 186.8, 273.8, 6.8 );
		if ( '' !== $hero ) {
			$this->cover_image( $hero, 15, 15, 180, 128, 6 );
			$y = 143;
		} else {
			$y = 62;
		}
		// The logo in a white disc with the ribbon circle around it.
		$r = 24;
		$this->pdf->fill( '#ffffff' );
		$this->pdf->stroke( $this->c['gold'], 0.4 );
		$this->pdf->circle( $mid, $y, $r, 'B' );
		AIMP_Bandana_Draw::ribbon_pdf( $this->pdf, $mid, $y, $r + 3, $r + 3, $this->c, 1.8 );
		if ( '' !== $this->logo ) {
			$lw = 2 * $r * 0.86;
			$lh = $lw / $this->pdf->image_ratio( $this->logo );
			$this->pdf->image( $this->logo, $mid - $lw / 2, $y - $lh / 2, $lw, $lh );
		} else {
			$this->pdf->text( $mid, $y + 2, self::upper( get_bloginfo( 'name' ) ), $this->f['head'], 11, $this->c['gold_text'], 'center', 0.1 );
		}
		$y += $r + 14;
		$this->pdf->text( $mid, $y, self::upper( __( 'Sewing pattern and guide', 'atelier-irisee-master-plugin' ) ), $this->f['bold'], 9, $this->c['gold_text'], 'center', 0.2 );
		$y += 14;
		$title = self::upper( __( 'Dog bandana', 'atelier-irisee-master-plugin' ) );
		$this->pdf->text( $mid, $y, $title, $this->f['head'], 30, $this->c['gold_text'], 'center', 0.05 );
		$w = $this->pdf->width( $title, $this->f['head'], 30, 0.05 );
		$this->double_line( 24, $mid - $w / 2 - 6, $y - 4, 0.4 );
		$this->double_line( $mid + $w / 2 + 6, 186, $y - 4, 0.4 );
		$this->iris_line( 24, $mid - $w / 2 - 6, $y - 1.6 );
		$this->iris_line( $mid + $w / 2 + 6, 186, $y - 1.6 );
		$this->y = $y + 8;
		$intro   = AIMP_Bandana::guide()[0][3];
		$this->l = 30;
		$this->r = 180;
		$this->paragraph( $intro[0][1], 30, 150, 10.5 );
		if ( '' === $hero ) {
			$this->y += 4;
			$this->figure( 'styles', 150 );
		}
		$this->y = max( $this->y + 4, 248 );
		$this->l = 24;
		$this->r = 186;
		$this->block( $intro[1] );
		$this->l = self::LEFT;
		$this->r = self::RIGHT;
	}

	/* ------------------------------------------------------------------
	 * The template
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $style Style.
	 * @param string $size  Size, or 'all'.
	 * @return string The PDF.
	 */
	public static function template( $style, $size ) {
		$styles = AIMP_Bandana::styles();
		$sizes  = 'all' === $size ? array_keys( AIMP_Bandana::sizes() ) : array( $size );
		/* translators: %s: size */
		$what         = 'all' === $size ? __( 'All sizes', 'atelier-irisee-master-plugin' ) : sprintf( __( 'Size %s', 'atelier-irisee-master-plugin' ), AIMP_Bandana::sizes()[ $size ][0] );
		$doc          = new self( __( 'Dog bandana', 'atelier-irisee-master-plugin' ) . ' – ' . $styles[ $style ] . ' – ' . $what );
		$doc->running = __( 'Dog bandana', 'atelier-irisee-master-plugin' ) . ' · ' . $styles[ $style ] . ' · ' . $what;

		list( $x0, $y0, $x1, $y1, $shapes ) = AIMP_Bandana::template( $style, $sizes );
		$width                             = ( $x1 - $x0 ) * 10;
		$height                            = ( $y1 - $y0 ) * 10;
		$cols                              = max( 1, (int) ceil( $width / self::TILE_W ) );
		$rows                              = max( 1, (int) ceil( $height / self::TILE_H ) );

		// Sheets with nothing of the pattern on them are not printed.
		$used = array();
		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				$used[ $r ][ $c ] = self::has_lines( $shapes, $x0 + $c * self::TILE_W / 10, $y0 + $r * self::TILE_H / 10, self::TILE_W / 10, self::TILE_H / 10 );
			}
		}

		$doc->overview( $style, $what, $sizes, $shapes, $x0, $y0, $used, $cols, $rows );

		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				if ( $used[ $r ][ $c ] ) {
					$doc->sheet( $shapes, $x0, $y0, $r, $c, $used );
				}
			}
		}
		$doc->numbers( 0 );
		return $doc->pdf->output();
	}

	/**
	 * Whether a line of the pattern passes through a rectangle (cm).
	 */
	private static function has_lines( $shapes, $x, $y, $w, $h ) {
		foreach ( $shapes as $s ) {
			if ( 'poly' !== $s[0] ) {
				continue;
			}
			$points = $s[1];
			if ( $s[2] ) {
				$points[] = $points[0];
			}
			for ( $i = 0; $i + 1 < count( $points ); $i++ ) {
				// Sample the segment: precise enough for sheets of 18 × 26 cm.
				for ( $k = 0; $k <= 20; $k++ ) {
					$px = $points[ $i ][0] + ( $points[ $i + 1 ][0] - $points[ $i ][0] ) * $k / 20;
					$py = $points[ $i ][1] + ( $points[ $i + 1 ][1] - $points[ $i ][1] ) * $k / 20;
					if ( $px >= $x && $px <= $x + $w && $py >= $y && $py <= $y + $h ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	private static function sheet_name( $r, $c ) {
		return chr( 65 + $r ) . ( $c + 1 );
	}

	/**
	 * The first page: how to print and tape, the test square, the order of the sheets and (all sizes) the lines.
	 */
	private function overview( $style, $what, $sizes, $shapes, $x0, $y0, $used, $cols, $rows ) {
		$this->new_page();
		$this->title( __( 'Template', 'atelier-irisee-master-plugin' ) );
		$this->paragraph( AIMP_Bandana::styles()[ $style ] . ' · ' . $what, self::LEFT, 0, 11, $this->f['bold'], $this->c['gold_text'] );
		$this->block(
			array(
				'list',
				array(
					__( 'Print every page at 100 % ("Actual size"), never "Fit to page".', 'atelier-irisee-master-plugin' ),
					__( 'Check the test square: it must measure exactly 5 × 5 cm.', 'atelier-irisee-master-plugin' ),
					__( 'Cut away the right and bottom margin of each sheet up to the frame, lay it over the next sheet so the half diamonds form whole ones, and tape.', 'atelier-irisee-master-plugin' ),
					__( 'Seam allowance of 1 cm is included. Cut 2 on the fold.', 'atelier-irisee-master-plugin' ),
				),
			)
		);

		// The test square, at its real size.
		$top = $this->y + 2;
		$this->pdf->stroke( $this->c['brown'], 0.4 );
		$this->pdf->rect( self::LEFT, $top, 50, 50 );
		$this->pdf->stroke( $this->c['gold'], 0.25 );
		for ( $i = 1; $i < 5; $i++ ) {
			$this->pdf->line( self::LEFT + $i * 10, $top, self::LEFT + $i * 10, $top + 2.5 );
			$this->pdf->line( self::LEFT, $top + $i * 10, self::LEFT + 2.5, $top + $i * 10 );
		}
		$this->pdf->text( self::LEFT + 25, $top + 24, '5 × 5 cm', $this->f['bold'], 11, $this->c['gold_text'], 'center' );
		$this->pdf->text( self::LEFT + 25, $top + 30, __( 'Test square', 'atelier-irisee-master-plugin' ), $this->f['body'], 8, $this->c['brown'], 'center' );

		// The sheets in their order, over a small drawing of the piece.
		$box_x = self::LEFT + 62;
		$box_w = self::RIGHT - $box_x;
		$scale = min( $box_w / ( $cols * self::TILE_W ), 95 / ( $rows * self::TILE_H ) );
		AIMP_Bandana_Draw::pdf( $this->pdf, $this->thin( $shapes ), $box_x - $x0 * 10 * $scale, $top - $y0 * 10 * $scale, 10 * $scale, $this->c, array( $this->f['body'], $this->f['bold'] ) );
		$this->pdf->stroke( $this->c['gold'], 0.3, array( 1, 0.8 ) );
		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				$cx = $box_x + $c * self::TILE_W * $scale;
				$cy = $top + $r * self::TILE_H * $scale;
				$this->pdf->rect( $cx, $cy, self::TILE_W * $scale, self::TILE_H * $scale );
				// A sheet without the pattern on it is not in the file.
				$this->pdf->text( $cx + 2, $cy + 5, $used[ $r ][ $c ] ? self::sheet_name( $r, $c ) : '–', $this->f['bold'], 9, $this->c['gold_text'] );
			}
		}
		$this->y = $top + max( 55, $rows * self::TILE_H * $scale ) + 8;

		if ( count( $sizes ) > 1 ) {
			$this->paragraph( __( 'Every size has its own line:', 'atelier-irisee-master-plugin' ), self::LEFT, 0, 9.5 );
			$dashes = array( 'xs' => array( 0.6, 1.2 ), 's' => array( 3, 1.2 ), 'm' => array(), 'l' => array( 5, 1.2, 0.6, 1.2 ), 'xl' => array( 8, 1.6 ) );
			$x      = self::LEFT;
			foreach ( $sizes as $size ) {
				$this->pdf->stroke( $this->c['brown'], 0.45, $dashes[ $size ] );
				$this->pdf->line( $x, $this->y + 2, $x + 16, $this->y + 2 );
				$this->pdf->text( $x + 18, $this->y + 3.2, AIMP_Bandana::sizes()[ $size ][0], $this->f['bold'], 9, $this->c['brown'] );
				$x += 32;
			}
			$this->y += 8;
		}
	}

	/**
	 * The overview's drawing without texts (too small to read).
	 */
	private function thin( $shapes ) {
		return array_values(
			array_filter(
				$shapes,
				function ( $s ) {
					return 'text' !== $s[0];
				}
			)
		);
	}

	/**
	 * One sheet of the pattern at its real size, with its frame, name and half diamonds on the edges it shares.
	 */
	private function sheet( $shapes, $x0, $y0, $r, $c, $used ) {
		$this->pdf->add_page();
		$name = self::sheet_name( $r, $c );
		$this->pdf->text( self::TILE_X, 15, self::upper( $this->running ), $this->f['head'], 8, $this->c['gold_text'], 'left', 0.12 );
		$this->pdf->text( self::TILE_X + self::TILE_W, 17, $name, $this->f['head'], 18, $this->c['gold_text'], 'right' );
		$this->pdf->text( self::TILE_X, 20, __( 'Print at 100 % (actual size)', 'atelier-irisee-master-plugin' ), $this->f['body'], 7, $this->c['brown'] );

		$this->pdf->clip_rect( self::TILE_X, self::TILE_Y, self::TILE_W, self::TILE_H );
		AIMP_Bandana_Draw::pdf( $this->pdf, $shapes, self::TILE_X - $x0 * 10 - $c * self::TILE_W, self::TILE_Y - $y0 * 10 - $r * self::TILE_H, 10, $this->c, array( $this->f['body'], $this->f['bold'] ) );
		$this->pdf->restore();

		$this->pdf->stroke( $this->c['gold'], 0.3 );
		$this->pdf->rect( self::TILE_X, self::TILE_Y, self::TILE_W, self::TILE_H );
		// Half diamonds, each with the name of the sheet next to it.
		$d     = 6;
		$edges = array(
			'left'   => ! empty( $used[ $r ][ $c - 1 ] ) ? self::sheet_name( $r, $c - 1 ) : '',
			'right'  => ! empty( $used[ $r ][ $c + 1 ] ) ? self::sheet_name( $r, $c + 1 ) : '',
			'top'    => ! empty( $used[ $r - 1 ][ $c ] ) ? self::sheet_name( $r - 1, $c ) : '',
			'bottom' => ! empty( $used[ $r + 1 ][ $c ] ) ? self::sheet_name( $r + 1, $c ) : '',
		);
		$this->pdf->fill( $this->c['gold'] );
		foreach ( $edges as $edge => $next ) {
			if ( '' === $next ) {
				continue;
			}
			$mx = self::TILE_X + self::TILE_W / 2;
			$my = self::TILE_Y + self::TILE_H / 2;
			switch ( $edge ) {
				case 'left':
					$tri = array( array( self::TILE_X, $my - $d ), array( self::TILE_X + $d, $my ), array( self::TILE_X, $my + $d ) );
					$at  = array( self::TILE_X + $d + 2, $my + 1.2, 'left' );
					break;
				case 'right':
					$tri = array( array( self::TILE_X + self::TILE_W, $my - $d ), array( self::TILE_X + self::TILE_W - $d, $my ), array( self::TILE_X + self::TILE_W, $my + $d ) );
					$at  = array( self::TILE_X + self::TILE_W - $d - 2, $my + 1.2, 'right' );
					break;
				case 'top':
					$tri = array( array( $mx - $d, self::TILE_Y ), array( $mx, self::TILE_Y + $d ), array( $mx + $d, self::TILE_Y ) );
					$at  = array( $mx, self::TILE_Y + $d + 4, 'center' );
					break;
				default:
					$tri = array( array( $mx - $d, self::TILE_Y + self::TILE_H ), array( $mx, self::TILE_Y + self::TILE_H - $d ), array( $mx + $d, self::TILE_Y + self::TILE_H ) );
					$at  = array( $mx, self::TILE_Y + self::TILE_H - $d - 2, 'center' );
			}
			$this->pdf->path( array( array( 'M', $tri[0][0], $tri[0][1] ), array( 'L', $tri[1][0], $tri[1][1] ), array( 'L', $tri[2][0], $tri[2][1] ), array( 'Z' ) ), 'f' );
			$this->pdf->text( $at[0], $at[1], $name . ' | ' . $next, $this->f['bold'], 7, $this->c['gold_text'], $at[2] );
		}
		if ( $edges['right'] || $edges['bottom'] ) {
			$this->pdf->text( self::TILE_X, self::TILE_Y + self::TILE_H + 5, __( 'Cut away the right and bottom margin up to the frame.', 'atelier-irisee-master-plugin' ), $this->f['body'], 7, $this->c['brown'] );
		}
	}
}
