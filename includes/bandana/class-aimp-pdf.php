<?php
/**
 * A small PDF writer of our own (no library): A4 pages, lines, curves, rectangles and fills in millimetres from the
 * top left corner, text in Helvetica or an embedded font (the site's own font file, TrueType or OpenType), and
 * pictures with transparency. Texts are written in Windows-1252 (WinAnsiEncoding): é, ë, ç, ° and – all work.
 *
 * Used for the dog bandana guide and its pattern pieces, which must print at their real size.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Pdf {

	/** A4 in points. */
	const PAGE_W = 595.28;
	const PAGE_H = 841.89;

	/** Points per millimetre. */
	const K = 2.834645669;

	/** Character widths (1/1000 em) of codes 32–255 in WinAnsiEncoding. */
	const HELVETICA = '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500';
	const HELVETICA_BOLD = '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556';

	/** @var string[] Content of each page. */
	private $pages = array();

	/** @var array Fonts: key => [ name, widths (32–255), embedded file or '', type TrueType|OpenType|'', metrics ]. */
	private $fonts = array();

	/** @var array Pictures: key => [ w, h, rgb (deflated), alpha (deflated) or '' ]. */
	private $images = array();

	/** @var string Title of the document. */
	private $title = '';

	/** @var int The page being written. */
	private $current = 0;

	public function __construct( $title = '' ) {
		$this->title = $title;
		$this->fonts['sans']      = array( 'Helvetica', array_map( 'intval', explode( ',', self::HELVETICA ) ), '', '', array() );
		$this->fonts['sans-bold'] = array( 'Helvetica-Bold', array_map( 'intval', explode( ',', self::HELVETICA_BOLD ) ), '', '', array() );
	}

	/* ------------------------------------------------------------------
	 * Fonts and pictures
	 * ------------------------------------------------------------------ */

	/**
	 * Use a font file (TrueType or OpenType) under a key. False when it cannot be read or may not be embedded.
	 *
	 * @param string $key  Key (for example "site").
	 * @param string $file Path of the font file.
	 * @return bool
	 */
	public function add_font( $key, $file ) {
		$font = AIMP_Pdf_Font::read( $file );
		if ( ! $font ) {
			return false;
		}
		$this->fonts[ $key ] = array( $font['name'], $font['widths'], $font['data'], $font['type'], $font );
		return true;
	}

	/**
	 * Whether a font key can be used.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function has_font( $key ) {
		return isset( $this->fonts[ $key ] );
	}

	/**
	 * A picture (any format GD can read: PNG, JPEG, WebP, GIF), at most $max pixels wide. Returns its key or ''.
	 *
	 * @param string $file Path.
	 * @param int    $max  Largest width in pixels.
	 * @return string
	 */
	public function add_image( $file, $max = 900 ) {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $file ) ) {
			return '';
		}
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local upload.
		$src = $raw ? @imagecreatefromstring( $raw ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- formats GD cannot read: no picture.
		if ( ! $src ) {
			return '';
		}
		$w = imagesx( $src );
		$h = imagesy( $src );
		if ( $w > $max ) {
			$nh  = max( 1, (int) round( $h * $max / $w ) );
			$dst = imagecreatetruecolor( $max, $nh );
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
			imagecopyresampled( $dst, $src, 0, 0, 0, 0, $max, $nh, $w, $h );
			imagedestroy( $src );
			$src = $dst;
			$w   = $max;
			$h   = $nh;
		} elseif ( ! imageistruecolor( $src ) ) {
			imagepalettetotruecolor( $src );
		}
		$rgb     = '';
		$alpha   = '';
		$see     = false;
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$c      = imagecolorat( $src, $x, $y );
				$rgb   .= chr( ( $c >> 16 ) & 255 ) . chr( ( $c >> 8 ) & 255 ) . chr( $c & 255 );
				$a      = ( $c >> 24 ) & 127;
				$alpha .= chr( 255 - (int) round( $a * 255 / 127 ) );
				$see    = $see || $a > 0;
			}
		}
		imagedestroy( $src );
		$key                  = 'Im' . ( count( $this->images ) + 1 );
		$this->images[ $key ] = array( $w, $h, gzcompress( $rgb ), $see ? gzcompress( $alpha ) : '' );
		return $key;
	}

	/**
	 * Width / height of a picture.
	 *
	 * @param string $key Key.
	 * @return float
	 */
	public function image_ratio( $key ) {
		return isset( $this->images[ $key ] ) ? $this->images[ $key ][0] / max( 1, $this->images[ $key ][1] ) : 1;
	}

	/* ------------------------------------------------------------------
	 * Pages and drawing (millimetres, from the top left)
	 * ------------------------------------------------------------------ */

	public function add_page() {
		$this->pages[] = '';
		$this->current = count( $this->pages ) - 1;
	}

	/**
	 * @return int Number of pages.
	 */
	public function page_count() {
		return count( $this->pages );
	}

	/**
	 * Write on another page (0 = the first), for example the page numbers at the end.
	 *
	 * @param int $index Page.
	 */
	public function on_page( $index ) {
		$this->current = max( 0, min( count( $this->pages ) - 1, (int) $index ) );
	}

	private function out( $s ) {
		$this->pages[ $this->current ] .= $s . "\n";
	}

	private static function n( $v ) {
		$s = number_format( (float) $v, 3, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return '-0' === $s ? '0' : $s;
	}

	private function x( $mm ) {
		return self::n( $mm * self::K );
	}

	private function y( $mm ) {
		return self::n( self::PAGE_H - $mm * self::K );
	}

	/**
	 * A colour: "#rrggbb" or [ r, g, b ] (0–255).
	 *
	 * @param string|array $color Colour.
	 * @return string "r g b"
	 */
	private static function rgb( $color ) {
		if ( is_string( $color ) ) {
			$hex = ltrim( $color, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$color = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		}
		return self::n( $color[0] / 255 ) . ' ' . self::n( $color[1] / 255 ) . ' ' . self::n( $color[2] / 255 );
	}

	/**
	 * Line style: colour, width (mm) and dashes (mm, [] = solid).
	 *
	 * @param string|array $color  Colour.
	 * @param float        $width  Width.
	 * @param float[]      $dashes Dashes.
	 */
	public function stroke( $color, $width = 0.3, $dashes = array() ) {
		$this->out( self::rgb( $color ) . ' RG ' . self::n( $width * self::K ) . ' w 1 J 1 j [' . implode( ' ', array_map( array( $this, 'x' ), $dashes ) ) . '] 0 d' );
	}

	/**
	 * @param string|array $color Fill colour.
	 */
	public function fill( $color ) {
		$this->out( self::rgb( $color ) . ' rg' );
	}

	/**
	 * A path: segments [ 'M', x, y ], [ 'L', x, y ], [ 'C', x1, y1, x2, y2, x, y ], [ 'Z' ].
	 *
	 * @param array  $segments Segments.
	 * @param string $mode     S (stroke), f (fill), B (both).
	 */
	public function path( $segments, $mode = 'S' ) {
		$s = '';
		foreach ( $segments as $seg ) {
			switch ( $seg[0] ) {
				case 'M':
					$s .= $this->x( $seg[1] ) . ' ' . $this->y( $seg[2] ) . ' m ';
					break;
				case 'L':
					$s .= $this->x( $seg[1] ) . ' ' . $this->y( $seg[2] ) . ' l ';
					break;
				case 'C':
					$s .= $this->x( $seg[1] ) . ' ' . $this->y( $seg[2] ) . ' ' . $this->x( $seg[3] ) . ' ' . $this->y( $seg[4] ) . ' ' . $this->x( $seg[5] ) . ' ' . $this->y( $seg[6] ) . ' c ';
					break;
				case 'Z':
					$s .= 'h ';
					break;
			}
		}
		$this->out( $s . $mode );
	}

	public function line( $x1, $y1, $x2, $y2 ) {
		$this->path( array( array( 'M', $x1, $y1 ), array( 'L', $x2, $y2 ) ) );
	}

	public function rect( $x, $y, $w, $h, $mode = 'S' ) {
		$this->out( $this->x( $x ) . ' ' . $this->y( $y + $h ) . ' ' . self::n( $w * self::K ) . ' ' . self::n( $h * self::K ) . ' re ' . $mode );
	}

	/**
	 * A rectangle with rounded corners.
	 */
	public function round_rect( $x, $y, $w, $h, $r, $mode = 'S' ) {
		$r = min( $r, $w / 2, $h / 2 );
		$c = $r * 0.5523;
		$this->path(
			array(
				array( 'M', $x + $r, $y ),
				array( 'L', $x + $w - $r, $y ),
				array( 'C', $x + $w - $r + $c, $y, $x + $w, $y + $r - $c, $x + $w, $y + $r ),
				array( 'L', $x + $w, $y + $h - $r ),
				array( 'C', $x + $w, $y + $h - $r + $c, $x + $w - $r + $c, $y + $h, $x + $w - $r, $y + $h ),
				array( 'L', $x + $r, $y + $h ),
				array( 'C', $x + $r - $c, $y + $h, $x, $y + $h - $r + $c, $x, $y + $h - $r ),
				array( 'L', $x, $y + $r ),
				array( 'C', $x, $y + $r - $c, $x + $r - $c, $y, $x + $r, $y ),
				array( 'Z' ),
			),
			$mode
		);
	}

	public function circle( $cx, $cy, $r, $mode = 'S' ) {
		$c = $r * 0.5523;
		$this->path(
			array(
				array( 'M', $cx + $r, $cy ),
				array( 'C', $cx + $r, $cy + $c, $cx + $c, $cy + $r, $cx, $cy + $r ),
				array( 'C', $cx - $c, $cy + $r, $cx - $r, $cy + $c, $cx - $r, $cy ),
				array( 'C', $cx - $r, $cy - $c, $cx - $c, $cy - $r, $cx, $cy - $r ),
				array( 'C', $cx + $c, $cy - $r, $cx + $r, $cy - $c, $cx + $r, $cy ),
				array( 'Z' ),
			),
			$mode
		);
	}

	/**
	 * A picture at x, y (top left), w × h mm.
	 */
	public function image( $key, $x, $y, $w, $h ) {
		if ( ! isset( $this->images[ $key ] ) ) {
			return;
		}
		$this->out( 'q ' . self::n( $w * self::K ) . ' 0 0 ' . self::n( $h * self::K ) . ' ' . $this->x( $x ) . ' ' . $this->y( $y + $h ) . ' cm /' . $key . ' Do Q' );
	}

	/* ------------------------------------------------------------------
	 * Text
	 * ------------------------------------------------------------------ */

	/**
	 * UTF-8 to Windows-1252.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function encode( $s ) {
		$s = (string) $s;
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$out = @mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unknown characters become "?".
			if ( false !== $out ) {
				return $out;
			}
		}
		if ( function_exists( 'iconv' ) ) {
			$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $s ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $out ) {
				return $out;
			}
		}
		return preg_replace( '/[^\x20-\x7e]/', '?', $s );
	}

	/**
	 * Width of a text in mm.
	 *
	 * @param string $s       Text (UTF-8).
	 * @param string $font    Font key.
	 * @param float  $size    Size in pt.
	 * @param float  $spacing Letter spacing in em.
	 * @return float
	 */
	public function width( $s, $font, $size, $spacing = 0 ) {
		$font   = isset( $this->fonts[ $font ] ) ? $font : 'sans';
		$widths = $this->fonts[ $font ][1];
		$s      = self::encode( $s );
		$w      = 0;
		$len    = strlen( $s );
		for ( $i = 0; $i < $len; $i++ ) {
			$code = ord( $s[ $i ] );
			$w   += $code >= 32 ? $widths[ $code - 32 ] : 0;
		}
		return ( $w / 1000 * $size + $len * $spacing * $size ) / self::K;
	}

	/**
	 * A text split into lines that fit a width (mm). Line breaks in the text are kept.
	 *
	 * @return string[]
	 */
	public function wrap( $s, $font, $size, $width, $spacing = 0 ) {
		$lines = array();
		foreach ( preg_split( "/\r?\n/", (string) $s ) as $paragraph ) {
			$line = '';
			foreach ( preg_split( '/ +/', trim( $paragraph ) ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && $this->width( $try, $font, $size, $spacing ) > $width ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$line = $try;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * A line of text with its baseline at y (mm).
	 *
	 * @param float        $x       Left (or centre / right with $align).
	 * @param float        $y       Baseline.
	 * @param string       $s       Text (UTF-8).
	 * @param string       $font    Font key.
	 * @param float        $size    Size in pt.
	 * @param string|array $color   Colour.
	 * @param string       $align   left, center or right.
	 * @param float        $spacing Letter spacing in em.
	 * @param float        $rot     Turned anticlockwise, in degrees.
	 */
	public function text( $x, $y, $s, $font, $size, $color, $align = 'left', $spacing = 0, $rot = 0 ) {
		$font = isset( $this->fonts[ $font ] ) ? $font : 'sans';
		$a    = deg2rad( $rot );
		if ( 'left' !== $align ) {
			$w  = $this->width( $s, $font, $size, $spacing ) * ( 'center' === $align ? 0.5 : 1 );
			$x -= $w * cos( $a );
			$y += $w * sin( $a );
		}
		$enc = self::encode( $s );
		$enc = str_replace( array( '\\', '(', ')', "\r" ), array( '\\\\', '\\(', '\\)', '\\r' ), $enc );
		$at  = $rot ? self::n( cos( $a ) ) . ' ' . self::n( sin( $a ) ) . ' ' . self::n( -sin( $a ) ) . ' ' . self::n( cos( $a ) ) . ' ' . $this->x( $x ) . ' ' . $this->y( $y ) . ' Tm' : $this->x( $x ) . ' ' . $this->y( $y ) . ' Td';
		$this->out(
			'BT /F' . ( array_search( $font, array_keys( $this->fonts ), true ) + 1 ) . ' ' . self::n( $size ) . ' Tf ' . self::n( $spacing * $size ) . ' Tc ' .
			self::rgb( $color ) . ' rg ' . $at . ' (' . $enc . ') Tj ET'
		);
	}

	/**
	 * Draw only inside a rectangle until restore().
	 */
	public function clip_rect( $x, $y, $w, $h ) {
		$this->out( 'q ' . $this->x( $x ) . ' ' . $this->y( $y + $h ) . ' ' . self::n( $w * self::K ) . ' ' . self::n( $h * self::K ) . ' re W n' );
	}

	public function restore() {
		$this->out( 'Q' );
	}

	/* ------------------------------------------------------------------
	 * The file
	 * ------------------------------------------------------------------ */

	/**
	 * The whole PDF.
	 *
	 * @return string
	 */
	public function output() {
		$objects = array();
		$add     = function ( $body ) use ( &$objects ) {
			$objects[] = $body;
			return count( $objects );
		};
		$stream  = function ( $dict, $data ) {
			return '<< ' . $dict . ' /Length ' . strlen( $data ) . " >>\nstream\n" . $data . "\nendstream";
		};

		// 1: catalog, 2: pages (filled in at the end).
		$add( '' );
		$add( '' );

		$font_refs = array();
		$n         = 0;
		foreach ( $this->fonts as $key => $font ) {
			$n++;
			if ( '' === $font[2] ) {
				$font_refs[ $n ] = $add( '<< /Type /Font /Subtype /Type1 /BaseFont /' . $font[0] . ' /Encoding /WinAnsiEncoding >>' );
				continue;
			}
			$m    = $font[4];
			$open = 'OpenType' === $font[3];
			$file = $add( $stream( ( $open ? '/Subtype /OpenType ' : '/Length1 ' . strlen( $font[2] ) . ' ' ) . '/Filter /FlateDecode', gzcompress( $font[2] ) ) );
			$desc = $add(
				'<< /Type /FontDescriptor /FontName /' . $font[0] . ' /Flags 32 /FontBBox [' . implode( ' ', array_map( 'intval', $m['bbox'] ) ) . '] /ItalicAngle 0 /Ascent ' . (int) $m['ascent'] .
				' /Descent ' . (int) $m['descent'] . ' /CapHeight ' . (int) $m['cap'] . ' /StemV 80 /' . ( $open ? 'FontFile3' : 'FontFile2' ) . ' ' . $file . ' 0 R >>'
			);
			$font_refs[ $n ] = $add(
				'<< /Type /Font /Subtype /' . ( $open ? 'Type1' : 'TrueType' ) . ' /BaseFont /' . $font[0] . ' /FirstChar 32 /LastChar 255 /Widths [' . implode( ' ', $font[1] ) .
				'] /Encoding /WinAnsiEncoding /FontDescriptor ' . $desc . ' 0 R >>'
			);
		}

		$image_refs = array();
		foreach ( $this->images as $key => $image ) {
			$mask = '';
			if ( '' !== $image[3] ) {
				$mask = ' /SMask ' . $add( $stream( '/Type /XObject /Subtype /Image /Width ' . $image[0] . ' /Height ' . $image[1] . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode', $image[3] ) ) . ' 0 R';
			}
			$image_refs[ $key ] = $add( $stream( '/Type /XObject /Subtype /Image /Width ' . $image[0] . ' /Height ' . $image[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode' . $mask, $image[2] ) );
		}

		$resources = '<< /Font <<';
		foreach ( $font_refs as $i => $ref ) {
			$resources .= ' /F' . $i . ' ' . $ref . ' 0 R';
		}
		$resources .= ' >> /XObject <<';
		foreach ( $image_refs as $key => $ref ) {
			$resources .= ' /' . $key . ' ' . $ref . ' 0 R';
		}
		$resources .= ' >> >>';
		$res = $add( $resources );

		$kids = array();
		foreach ( $this->pages as $content ) {
			$c      = $add( $stream( '/Filter /FlateDecode', gzcompress( $content ) ) );
			$kids[] = $add( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] /Resources ' . $res . ' 0 R /Contents ' . $c . ' 0 R >>' ) . ' 0 R';
		}
		$objects[1] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
		// Print at the real size: no "fit to page" in the print dialog.
		$objects[0] = '<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /PrintScaling /None >> >>';
		$info       = $add( '<< /Title (' . str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), self::encode( $this->title ) ) . ') /Producer (Atelier Irisee) /CreationDate (D:' . gmdate( 'YmdHis' ) . 'Z) >>' );

		$pdf     = "%PDF-1.6\n%\xe2\xe3\xcf\xd3\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}
}
