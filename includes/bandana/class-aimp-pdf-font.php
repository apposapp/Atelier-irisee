<?php
/**
 * Reads a TrueType or OpenType font file for AIMP_Pdf: its name, the widths of the Windows-1252 characters, its
 * measurements, and whether its licence allows embedding. The file itself is only read on the server (the site's
 * uploaded font); it is never part of the plugin.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Pdf_Font {

	/**
	 * @param string $file Path.
	 * @return array|null [ name, widths (codes 32–255, 1/1000 em), data, type TrueType|OpenType, bbox, ascent, descent, cap ]
	 */
	public static function read( $file ) {
		if ( ! $file || ! is_readable( $file ) ) {
			return null;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the site's own font file.
		if ( ! is_string( $data ) || strlen( $data ) < 12 ) {
			return null;
		}
		$version = substr( $data, 0, 4 );
		if ( "\x00\x01\x00\x00" !== $version && 'true' !== $version && 'OTTO' !== $version ) {
			return null;
		}
		$tables = array();
		$count  = self::u16( $data, 4 );
		for ( $i = 0; $i < $count; $i++ ) {
			$at                                 = 12 + $i * 16;
			$tables[ substr( $data, $at, 4 ) ] = array( self::u32( $data, $at + 8 ), self::u32( $data, $at + 12 ) );
		}
		foreach ( array( 'head', 'hhea', 'hmtx', 'cmap' ) as $need ) {
			if ( ! isset( $tables[ $need ] ) ) {
				return null;
			}
		}

		// Licence: "restricted" fonts may not be embedded.
		if ( isset( $tables['OS/2'] ) ) {
			$fs = self::u16( $data, $tables['OS/2'][0] + 8 );
			if ( 0x0002 === ( $fs & 0x000f ) ) {
				return null;
			}
		}

		$head  = $tables['head'][0];
		$units = max( 1, self::u16( $data, $head + 18 ) );
		$scale = 1000 / $units;
		$bbox  = array(
			self::i16( $data, $head + 36 ) * $scale,
			self::i16( $data, $head + 38 ) * $scale,
			self::i16( $data, $head + 40 ) * $scale,
			self::i16( $data, $head + 42 ) * $scale,
		);
		$hhea    = $tables['hhea'][0];
		$ascent  = self::i16( $data, $hhea + 4 ) * $scale;
		$descent = self::i16( $data, $hhea + 6 ) * $scale;
		$metrics = self::u16( $data, $hhea + 34 );
		$cap     = $ascent;
		if ( isset( $tables['OS/2'] ) && self::u16( $data, $tables['OS/2'][0] ) >= 2 && $tables['OS/2'][1] > 90 ) {
			$cap = self::i16( $data, $tables['OS/2'][0] + 88 ) * $scale;
		}

		$map = self::cmap( $data, $tables['cmap'][0] );
		if ( ! $map ) {
			return null;
		}
		$hmtx    = $tables['hmtx'][0];
		$advance = function ( $glyph ) use ( $data, $hmtx, $metrics ) {
			$glyph = min( $glyph, max( 0, $metrics - 1 ) );
			return self::u16( $data, $hmtx + $glyph * 4 );
		};
		$widths = array();
		for ( $code = 32; $code <= 255; $code++ ) {
			$uni      = self::unicode( $code );
			$glyph    = isset( $map[ $uni ] ) ? $map[ $uni ] : 0;
			$widths[] = (int) round( $advance( $glyph ) * $scale );
		}

		return array(
			'name'    => self::name( $data, isset( $tables['name'] ) ? $tables['name'][0] : 0 ),
			'widths'  => $widths,
			'data'    => $data,
			'type'    => 'OTTO' === $version ? 'OpenType' : 'TrueType',
			'bbox'    => $bbox,
			'ascent'  => $ascent,
			'descent' => $descent,
			'cap'     => $cap,
		);
	}

	/**
	 * The code point of a Windows-1252 character.
	 *
	 * @param int $code 0–255.
	 * @return int
	 */
	public static function unicode( $code ) {
		static $high = array(
			128 => 0x20ac, 130 => 0x201a, 131 => 0x0192, 132 => 0x201e, 133 => 0x2026, 134 => 0x2020, 135 => 0x2021, 136 => 0x02c6,
			137 => 0x2030, 138 => 0x0160, 139 => 0x2039, 140 => 0x0152, 142 => 0x017d, 145 => 0x2018, 146 => 0x2019, 147 => 0x201c,
			148 => 0x201d, 149 => 0x2022, 150 => 0x2013, 151 => 0x2014, 152 => 0x02dc, 153 => 0x2122, 154 => 0x0161, 155 => 0x203a,
			156 => 0x0153, 158 => 0x017e, 159 => 0x0178,
		);
		return isset( $high[ $code ] ) ? $high[ $code ] : $code;
	}

	/**
	 * Unicode => glyph from a format 4 subtable (Windows Unicode, or Unicode).
	 *
	 * @return array
	 */
	private static function cmap( $data, $at ) {
		$count = self::u16( $data, $at + 2 );
		$sub   = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$rec      = $at + 4 + $i * 8;
			$platform = self::u16( $data, $rec );
			$encoding = self::u16( $data, $rec + 2 );
			$offset   = $at + self::u32( $data, $rec + 4 );
			if ( 4 === self::u16( $data, $offset ) && ( ( 3 === $platform && 1 === $encoding ) || 0 === $platform ) ) {
				$sub = $offset;
				if ( 3 === $platform ) {
					break;
				}
			}
		}
		if ( ! $sub ) {
			return array();
		}
		$segs   = self::u16( $data, $sub + 6 ) / 2;
		$ends   = $sub + 14;
		$starts = $ends + $segs * 2 + 2;
		$deltas = $starts + $segs * 2;
		$ranges = $deltas + $segs * 2;
		$map    = array();
		for ( $s = 0; $s < $segs; $s++ ) {
			$end   = self::u16( $data, $ends + $s * 2 );
			$start = self::u16( $data, $starts + $s * 2 );
			$delta = self::i16( $data, $deltas + $s * 2 );
			$range = self::u16( $data, $ranges + $s * 2 );
			// Only what Windows-1252 can hold.
			for ( $c = $start; $c <= $end && $c <= 0x2200; $c++ ) {
				if ( 0xffff === $c ) {
					break;
				}
				if ( 0 === $range ) {
					$glyph = ( $c + $delta ) & 0xffff;
				} else {
					$pos   = $ranges + $s * 2 + $range + ( $c - $start ) * 2;
					$glyph = self::u16( $data, $pos );
					$glyph = $glyph ? ( $glyph + $delta ) & 0xffff : 0;
				}
				$map[ $c ] = $glyph;
			}
		}
		return $map;
	}

	/**
	 * The PostScript name, letters and digits only.
	 */
	private static function name( $data, $at ) {
		$name = '';
		if ( $at ) {
			$count = self::u16( $data, $at + 2 );
			$store = $at + self::u16( $data, $at + 4 );
			for ( $i = 0; $i < $count; $i++ ) {
				$rec = $at + 6 + $i * 12;
				if ( 6 !== self::u16( $data, $rec + 6 ) ) {
					continue;
				}
				$raw  = substr( $data, $store + self::u16( $data, $rec + 10 ), self::u16( $data, $rec + 8 ) );
				$name = 3 === self::u16( $data, $rec ) ? preg_replace( '/\x00/', '', $raw ) : $raw;
				break;
			}
		}
		$name = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $name );
		return '' !== $name ? $name : 'SiteFont';
	}

	private static function u16( $data, $at ) {
		return ( ord( $data[ $at ] ) << 8 ) | ord( $data[ $at + 1 ] );
	}

	private static function i16( $data, $at ) {
		$v = self::u16( $data, $at );
		return $v >= 0x8000 ? $v - 0x10000 : $v;
	}

	private static function u32( $data, $at ) {
		return ( self::u16( $data, $at ) << 16 ) | self::u16( $data, $at + 2 );
	}
}
