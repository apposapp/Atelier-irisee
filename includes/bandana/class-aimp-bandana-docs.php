<?php
/**
 * The bandana's two PDFs, in the site's look (its colours, its logo, its font when the font file can be embedded):
 *  - the guide: a cover, then every part of AIMP_Bandana::guide() with its drawings;
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

	/** @var float Where the next block starts (mm). */
	private $y = self::TOP;

	/** @var string Running title in the page head. */
	private $running = '';

	private function __construct( $title ) {
		$this->pdf = new AIMP_Pdf( $title );
		$this->c   = AIMP_Bandana::palette();
		$this->setup_fonts();
		$this->setup_logo();
	}

	/* ------------------------------------------------------------------
	 * The site's font and logo
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
	 * The site logo (the email logo of the Design studio, Divi's logo or the site logo).
	 */
	private function setup_logo() {
		$ids = array();
		if ( class_exists( 'AIMP_Design' ) ) {
			$ids[] = absint( AIMP_Design::opt( 'emails', 'em_logo', 0 ) );
		}
		$ids[] = (int) get_theme_mod( 'custom_logo' );
		$file  = '';
		foreach ( array_filter( $ids ) as $id ) {
			$file = (string) get_attached_file( $id );
			if ( '' !== $file ) {
				break;
			}
		}
		if ( '' === $file && function_exists( 'et_get_option' ) ) {
			$url  = (string) et_get_option( 'divi_logo' );
			$id   = $url ? attachment_url_to_postid( $url ) : 0;
			$file = $id ? (string) get_attached_file( $id ) : '';
			if ( '' === $file && $url ) {
				$uploads = wp_get_upload_dir();
				if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
					$file = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
				}
			}
		}
		$this->logo = '' !== $file ? $this->pdf->add_image( $file ) : '';
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
	 * The page head (logo, running title, gold double line) and foot (the site, page number added at the end).
	 */
	private function page_frame() {
		if ( '' !== $this->logo ) {
			$h = 10;
			$w = min( 46, $h * $this->pdf->image_ratio( $this->logo ) );
			$this->pdf->image( $this->logo, self::LEFT, 8, $w, $w / $this->pdf->image_ratio( $this->logo ) );
		} else {
			$this->pdf->text( self::LEFT, 15, get_bloginfo( 'name' ), $this->f['head'], 10, $this->c['gold_text'], 'left', 0.12 );
		}
		$this->pdf->text( self::RIGHT, 15, self::upper( $this->running ), $this->f['head'], 8, $this->c['gold_text'], 'right', 0.14 );
		$this->double_line( self::LEFT, self::RIGHT, 20.5 );
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
	 * A title between gold double lines, like the site's block titles.
	 */
	private function title( $s ) {
		$this->need( 45 );
		$size = 15;
		$text = self::upper( $s );
		$w    = $this->pdf->width( $text, $this->f['head'], $size, 0.06 );
		$mid  = ( self::LEFT + self::RIGHT ) / 2;
		$this->y += 6;
		$this->pdf->text( $mid, $this->y, $text, $this->f['head'], $size, $this->c['gold_text'], 'center', 0.06 );
		$this->double_line( self::LEFT, $mid - $w / 2 - 5, $this->y - 2.3, 0.35 );
		$this->double_line( $mid + $w / 2 + 5, self::RIGHT, $this->y - 2.3, 0.35 );
		$this->y += 9;
	}

	/**
	 * A paragraph (it may run onto the next page).
	 */
	private function paragraph( $s, $x = self::LEFT, $width = 0, $size = 10, $font = '', $color = '' ) {
		$width = $width ? $width : self::RIGHT - $x;
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
	 * Height of a wrapped text.
	 */
	private function text_height( $s, $width, $size = 10, $font = '' ) {
		return count( $this->pdf->wrap( $s, $font ? $font : $this->f['body'], $size, $width ) ) * $size * 0.52;
	}

	/**
	 * The little gold sparkle of the site's lists.
	 */
	private function sparkle( $x, $y, $r = 1.5 ) {
		$k = $r * 0.28;
		$this->pdf->fill( $this->c['gold'] );
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
	 * A drawing centred, at most $max mm wide.
	 */
	private function figure( $name, $max = 120 ) {
		$fig = AIMP_Bandana::figure( $name );
		if ( ! $fig ) {
			return;
		}
		list( $w, $h, $shapes ) = $fig;
		$scale                  = min( $max / ( $w + 4 ), 1.6 );
		$height                 = ( $h + 8 ) * $scale;
		$this->need( $height + 4 );
		$x = ( self::LEFT + self::RIGHT ) / 2 - ( $w + 4 ) * $scale / 2;
		AIMP_Bandana_Draw::pdf( $this->pdf, $shapes, $x + 2 * $scale, $this->y + 6 * $scale, $scale, $this->c, array( $this->f['body'], $this->f['bold'] ) );
		$this->y += $height + 4;
	}

	private function block( $b ) {
		switch ( $b[0] ) {
			case 'p':
				$this->paragraph( $b[1] );
				break;

			case 'list':
				foreach ( $b[1] as $item ) {
					$this->need( 6 );
					$this->sparkle( self::LEFT + 2, $this->y + 3.4 );
					$this->paragraph( $item, self::LEFT + 7 );
					$this->y -= 1.2;
				}
				$this->y += 2;
				break;

			case 'steps':
				$n = 0;
				foreach ( $b[1] as $step ) {
					$n++;
					$title = '' !== $step[0] ? self::upper( $step[0] ) : '';
					$this->need( 12 + $this->text_height( $step[1], self::RIGHT - self::LEFT - 12 ) * 0.4 );
					// A gold ring with the number.
					$this->pdf->stroke( $this->c['gold'], 0.35 );
					$this->pdf->circle( self::LEFT + 4, $this->y + 4.2, 4 );
					$this->pdf->circle( self::LEFT + 4, $this->y + 4.2, 3.2 );
					$this->pdf->text( self::LEFT + 4, $this->y + 5.6, (string) $n, $this->f['head'], 10, $this->c['gold_text'], 'center' );
					if ( '' !== $title ) {
						$this->pdf->text( self::LEFT + 12, $this->y + 5.4, $title, $this->f['bold'], 10, $this->c['gold_text'], 'left', 0.06 );
						$this->y += 6.5;
					} else {
						$this->y += 0.6;
					}
					$this->paragraph( $step[1], self::LEFT + 12 );
					if ( '' !== $step[2] ) {
						$this->figure( $step[2], 100 );
					}
					$this->y += 1;
				}
				break;

			case 'figure':
				$this->figure( $b[1] );
				break;

			case 'table':
				$head  = $b[1];
				$rows  = $b[2];
				$width = self::RIGHT - self::LEFT;
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
				$this->pdf->rect( self::LEFT, $this->y, $width, $row_h, 'B' );
				$x = self::LEFT;
				foreach ( $head as $i => $cell ) {
					$this->pdf->text( $x + 2, $this->y + 4.7, self::upper( $cell ), $this->f['bold'], 7.5, $this->c['gold_text'], 'left', 0.04 );
					$x += $ws[ $i ] * $width;
				}
				$this->y += $row_h;
				foreach ( $rows as $row ) {
					$x = self::LEFT;
					foreach ( $row as $i => $cell ) {
						$this->pdf->text( $x + 2, $this->y + 4.7, $cell, 0 === $i ? $this->f['bold'] : $this->f['body'], 8.5, $this->c['brown'] );
						$x += $ws[ $i ] * $width;
					}
					$this->y += $row_h;
					$this->pdf->stroke( $this->c['light'], 0.25 );
					$this->pdf->line( self::LEFT, $this->y, self::RIGHT, $this->y );
				}
				$this->y += 2;
				if ( ! empty( $b[3] ) ) {
					$this->paragraph( $b[3], self::LEFT, 0, 8, '', $this->c['gold_text'] );
				}
				break;

			case 'facts':
				$count = count( $b[1] );
				$gap   = 4;
				$w     = ( self::RIGHT - self::LEFT - $gap * ( $count - 1 ) ) / $count;
				$this->need( 20 );
				foreach ( $b[1] as $i => $fact ) {
					$x = self::LEFT + $i * ( $w + $gap );
					$this->pdf->stroke( $this->c['gold'], 0.3 );
					$this->pdf->round_rect( $x, $this->y, $w, 16, 4 );
					$this->pdf->round_rect( $x + 0.9, $this->y + 0.9, $w - 1.8, 14.2, 3.2 );
					$this->pdf->text( $x + $w / 2, $this->y + 6.2, self::upper( $fact[0] ), $this->f['bold'], 6.5, $this->c['gold_text'], 'center', 0.12 );
					$this->pdf->text( $x + $w / 2, $this->y + 11.6, $fact[1], $this->f['bold'], 9.5, $this->c['brown'], 'center' );
				}
				$this->y += 22;
				break;

			case 'tip':
				$h = $this->text_height( $b[1], self::RIGHT - self::LEFT - 12, 9.5 ) + 12;
				$this->need( $h );
				$this->pdf->fill( $this->c['tint'] );
				$this->pdf->stroke( $this->c['gold'], 0.3 );
				$this->pdf->round_rect( self::LEFT, $this->y, self::RIGHT - self::LEFT, $h, 5, 'B' );
				$this->sparkle( self::LEFT + 6, $this->y + 6, 2 );
				$this->pdf->text( self::LEFT + 10, $this->y + 7.2, self::upper( __( 'Tip', 'atelier-irisee-master-plugin' ) ), $this->f['bold'], 8, $this->c['gold_text'], 'left', 0.12 );
				$top     = $this->y;
				$this->y = $top + 8;
				$this->paragraph( $b[1], self::LEFT + 6, self::RIGHT - self::LEFT - 12, 9.5 );
				$this->y = $top + $h + 4;
				break;

			case 'terms':
				foreach ( $b[1] as $term ) {
					$this->need( 12 );
					$this->pdf->text( self::LEFT, $this->y + 4.6, self::upper( $term[0] ), $this->f['bold'], 9, $this->c['gold_text'], 'left', 0.06 );
					$this->y += 5.4;
					$this->paragraph( $term[1] );
				}
				break;
		}
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
			$doc->title( $part[1] );
			foreach ( $part[3] as $block ) {
				$doc->block( $block );
			}
			$doc->y += 4;
		}
		$doc->numbers();
		return $doc->pdf->output();
	}

	/**
	 * The cover: a double gold frame, the logo, the title, the three styles and the facts.
	 */
	private function cover() {
		$this->pdf->add_page();
		$this->pdf->stroke( $this->c['gold'], 0.5 );
		$this->pdf->round_rect( 10, 10, 190, 277, 8 );
		$this->pdf->round_rect( 11.6, 11.6, 186.8, 273.8, 6.8 );
		$mid = 105;
		$y   = 34;
		if ( '' !== $this->logo ) {
			$w = min( 80, 30 * $this->pdf->image_ratio( $this->logo ) );
			$h = $w / $this->pdf->image_ratio( $this->logo );
			$this->pdf->image( $this->logo, $mid - $w / 2, $y, $w, $h );
			$y += $h + 18;
		} else {
			$this->pdf->text( $mid, $y + 10, self::upper( get_bloginfo( 'name' ) ), $this->f['head'], 18, $this->c['gold_text'], 'center', 0.1 );
			$y += 30;
		}
		$this->pdf->text( $mid, $y, self::upper( __( 'Sewing pattern and guide', 'atelier-irisee-master-plugin' ) ), $this->f['bold'], 9, $this->c['gold_text'], 'center', 0.2 );
		$y += 14;
		$title = self::upper( __( 'Dog bandana', 'atelier-irisee-master-plugin' ) );
		$this->pdf->text( $mid, $y, $title, $this->f['head'], 30, $this->c['gold_text'], 'center', 0.05 );
		$w = $this->pdf->width( $title, $this->f['head'], 30, 0.05 );
		$this->double_line( 24, $mid - $w / 2 - 6, $y - 4, 0.4 );
		$this->double_line( $mid + $w / 2 + 6, 186, $y - 4, 0.4 );
		$this->y = $y + 12;
		$intro   = AIMP_Bandana::guide()[0][3];
		$this->paragraph( $intro[0][1], 30, 150, 11 );
		$this->y += 4;
		$this->figure( 'styles', 150 );
		$this->y = max( $this->y + 6, 236 );
		$this->block( $intro[1] );
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
