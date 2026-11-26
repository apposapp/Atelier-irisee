<?php
/**
 * A newsletter built with rows and blocks (the blog builder) as email HTML: tables and inline styles that mail
 * programs show, columns side by side that stack on a phone. Two ways:
 * - full(): the whole newsletter, each section with an anchor (s-1, s-2, …);
 * - summary(): what comes before the first section in full, then per section its title, first picture, a short
 *   text and a "Read more" button to that section in the web version.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Newsletter_Render {

	// The width of the email's content (600 minus WooCommerce's padding).
	const WIDTH = 504;
	const PAD   = 10;

	private static function gold() {
		return class_exists( 'AIMP_Emails' ) ? AIMP_Emails::gold() : '#b38f4f';
	}

	private static function brown() {
		return class_exists( 'AIMP_Emails' ) ? AIMP_Emails::brown() : '#613907';
	}

	/**
	 * The rows in sections: [ intro rows ], then per section [ 'row' => first row, 'rows' => rows ].
	 *
	 * @param array $rows Rows.
	 * @return array [ array $intro, array $sections ]
	 */
	public static function sections( $rows ) {
		$intro    = array();
		$sections = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['sec'] ) ) {
				$sections[] = array(
					'row'  => $row,
					'rows' => array( $row ),
				);
			} elseif ( $sections ) {
				$sections[ count( $sections ) - 1 ]['rows'][] = $row;
			} else {
				$intro[] = $row;
			}
		}
		return array( $intro, $sections );
	}

	/**
	 * The email HTML.
	 *
	 * @param array  $rows     Rows.
	 * @param string $layout   full or summary.
	 * @param string $view_url The web version ('' = no "Read more" links).
	 * @return string
	 */
	public static function html( $rows, $layout = 'full', $view_url = '' ) {
		list( $intro, $sections ) = self::sections( $rows );
		if ( 'summary' !== $layout || ! $sections ) {
			return self::full( $rows );
		}
		return self::summary( $intro, $sections, $view_url );
	}

	/**
	 * The whole newsletter.
	 *
	 * @param array $rows Rows.
	 * @return string
	 */
	public static function full( $rows ) {
		$html  = '';
		$first = true;
		$sec   = 0;
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$anchor = '';
			if ( ! empty( $row['sec'] ) ) {
				++$sec;
				$anchor = '<a id="s-' . $sec . '" name="s-' . $sec . '"></a>';
			}
			$out = self::row( $row, $first );
			if ( '' === $out ) {
				$html .= $anchor;
				continue;
			}
			$html .= $anchor . $out;
			$first = false;
		}
		return '<div class="aimp-nl-rows">' . $html . '</div>';
	}

	/**
	 * Intro in full, then a card per section.
	 *
	 * @param array  $intro    Rows before the first section.
	 * @param array  $sections Sections.
	 * @param string $view_url Web version.
	 * @return string
	 */
	private static function summary( $intro, $sections, $view_url ) {
		$html = $intro ? self::full( $intro ) : '';
		foreach ( $sections as $i => $section ) {
			if ( '' !== $html ) {
				$html .= self::lijn_img( '', true );
			}
			$html .= self::card( $section, $i + 1, $view_url );
		}
		return '<div class="aimp-nl-rows is-summary">' . $html . '</div>';
	}

	/**
	 * What a section is about: its title, first picture and short text.
	 *
	 * @param array $section Section.
	 * @return array title, image (attachment id), text
	 */
	public static function digest( $section ) {
		$row   = $section['row'];
		$title = isset( $row['sec_name'] ) ? trim( (string) $row['sec_name'] ) : '';
		$text  = isset( $row['sec_sum'] ) ? trim( (string) $row['sec_sum'] ) : '';
		$image = 0;
		$words = '';
		foreach ( $section['rows'] as $r ) {
			foreach ( AIMP_Blog::row_blocks( $r ) as $block ) {
				$t = isset( $block['t'] ) ? $block['t'] : '';
				if ( 'title' === $t && '' === $title && '' !== trim( (string) $block['text'] ) ) {
					$title = trim( (string) $block['text'] );
				} elseif ( 'image' === $t && ! $image && ! empty( $block['id'] ) && wp_attachment_is_image( (int) $block['id'] ) ) {
					$image = (int) $block['id'];
				} elseif ( 'video' === $t && ! $image && ! empty( $block['poster'] ) ) {
					$image = (int) $block['poster'];
				} elseif ( 'text' === $t ) {
					// Paragraphs and lines apart before the tags go.
					$words .= ' ' . wp_strip_all_tags( preg_replace( '#(</p>|<br\s*/?>|</li>|</h\d>)#i', '$1 ', strip_shortcodes( (string) $block['html'] ) ) );
				}
			}
		}
		if ( '' === $text ) {
			$text = wp_trim_words( trim( preg_replace( '/\s+/', ' ', html_entity_decode( $words, ENT_QUOTES, 'UTF-8' ) ) ), 35, '…' );
		}
		return array(
			'title' => $title,
			'image' => $image,
			'text'  => $text,
		);
	}

	private static function card( $section, $n, $view_url ) {
		$d     = self::digest( $section );
		$src   = $d['image'] ? (string) wp_get_attachment_image_url( $d['image'], 'large' ) : '';
		$align = '' !== $src ? 'left' : 'center';
		$text  = '';
		if ( '' !== $d['title'] ) {
			$text .= self::title( $d['title'], 'h2', $align );
		}
		if ( '' !== $d['text'] ) {
			$text .= '<p style="margin:0 0 4px;font-size:15px;line-height:1.6;text-align:' . $align . ';color:' . self::brown() . '">' . esc_html( $d['text'] ) . '</p>';
		}
		if ( '' !== $view_url && class_exists( 'AIMP_Emails' ) ) {
			$button = AIMP_Emails::button( __( 'Continue reading', 'atelier-irisee-master-plugin' ), $view_url . '#s-' . $n );
			$text  .= '' !== $src ? str_replace( 'align="center" style="margin:24px auto;', 'align="left" style="margin:18px 0 4px;', $button ) : $button;
		}
		if ( '' === $src ) {
			return '<div class="aimp-nl-card" style="margin:0 0 8px">' . $text . '</div>';
		}
		// The picture beside the text (under each other on a phone): portrait photos stay a sensible size.
		$pic   = 200;
		$rest  = self::WIDTH - $pic;
		$img   = '<img src="' . esc_url( $src ) . '" width="' . ( $pic - 2 * self::PAD ) . '" alt="' . esc_attr( $d['title'] ) . '" style="display:block;width:100%;max-width:' . ( $pic - 2 * self::PAD ) . 'px;height:auto;margin:0 auto;border:0;border-radius:999px 999px 0 0">';
		$img   = '' !== $view_url ? '<a href="' . esc_url( $view_url . '#s-' . $n ) . '" target="_blank">' . $img . '</a>' : $img;
		$html  = '<div style="font-size:0;line-height:0;text-align:center"><!--[if mso]><table role="presentation" width="' . self::WIDTH . '" border="0" cellpadding="0" cellspacing="0"><tr><td width="' . $pic . '" valign="middle"><![endif]-->';
		$html .= '<div class="aimp-nl-col" style="display:inline-block;width:100%;max-width:' . $pic . 'px;vertical-align:middle;font-size:16px;line-height:1.6"><div style="padding:0 ' . self::PAD . 'px 12px">' . $img . '</div></div>';
		$html .= '<!--[if mso]></td><td width="' . $rest . '" valign="middle"><![endif]-->';
		$html .= '<div class="aimp-nl-col" style="display:inline-block;width:100%;max-width:' . $rest . 'px;vertical-align:middle;font-size:16px;line-height:1.6;text-align:left"><div style="padding:0 ' . self::PAD . 'px 12px">' . $text . '</div></div>';
		$html .= '<!--[if mso]></td></tr></table><![endif]--></div>';
		return '<div class="aimp-nl-card" style="margin:0 0 8px">' . $html . '</div>';
	}

	/* ------------------------------------------------------------------
	 * Rows and blocks
	 * ------------------------------------------------------------------ */

	private static function space( $key, $default ) {
		$map = array(
			'none'  => 0,
			'small' => 10,
			'large' => 36,
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : $default;
	}

	private static function row( $row, $first ) {
		$row  = AIMP_Blog::upgrade_row( $row );
		$cols = array();
		foreach ( $row['cols'] as $col ) {
			$col = array_values( array_filter( is_array( $col ) ? $col : array(), array( 'AIMP_Blog', 'filled' ) ) );
			if ( $col ) {
				$cols[] = $col;
			}
		}
		$count = count( $cols );
		if ( ! $count ) {
			return '';
		}
		$space = isset( $row['space'] ) ? $row['space'] : '';
		$top   = $first ? 0 : ( 'small' === $space ? 14 : ( 'large' === $space ? 48 : 28 ) );
		$stack = self::space( isset( $row['stack'] ) ? $row['stack'] : '', 18 );
		$bg    = ! empty( $row['bg'] );
		$inner = $bg ? self::WIDTH - 40 : self::WIDTH;

		// The columns' widths.
		$shares = array_fill( 0, $count, 1 / $count );
		if ( 2 === $count && isset( $row['w'] ) && '12' === $row['w'] ) {
			$shares = array( 1 / 3, 2 / 3 );
		} elseif ( 2 === $count && isset( $row['w'] ) && '21' === $row['w'] ) {
			$shares = array( 2 / 3, 1 / 3 );
		}
		$valign = 'middle';
		if ( isset( $row['v'] ) && in_array( $row['v'], array( 'top', 'bottom' ), true ) ) {
			$valign = $row['v'];
		}

		if ( 1 === $count ) {
			$body = self::column( $cols[0], $inner, $stack, true );
		} else {
			$body = '<!--[if mso]><table role="presentation" width="' . $inner . '" border="0" cellpadding="0" cellspacing="0"><tr><![endif]-->';
			$body = '<div style="font-size:0;line-height:0;text-align:center">' . $body;
			foreach ( $cols as $i => $col ) {
				$w     = (int) floor( $inner * $shares[ $i ] );
				$body .= '<!--[if mso]><td width="' . $w . '" valign="' . $valign . '"><![endif]-->';
				$body .= '<div class="aimp-nl-col" style="display:inline-block;width:100%;max-width:' . $w . 'px;vertical-align:' . $valign . ';font-size:16px;line-height:1.6;text-align:left">';
				$body .= '<div style="padding:0 ' . self::PAD . 'px 12px">' . self::column( $col, $w - 2 * self::PAD, $stack, false ) . '</div></div>';
				$body .= '<!--[if mso]></td><![endif]-->';
			}
			$body .= '<!--[if mso]></tr></table><![endif]--></div>';
		}

		if ( $bg ) {
			$tint = 'rgba(179,143,79,0.10)';
			return '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:' . $top . 'px 0 0;border-collapse:separate"><tr><td bgcolor="#f7f1e6" style="padding:20px;background-color:#f7f1e6;background-color:' . $tint . ';border-radius:18px">' . $body . '</td></tr></table>';
		}
		return '<div style="margin:' . $top . 'px 0 0">' . $body . '</div>';
	}

	private static function column( $blocks, $width, $stack, $alone ) {
		$html = '';
		foreach ( $blocks as $i => $block ) {
			$gap   = $i ? self::space( isset( $block['gap'] ) ? $block['gap'] : '', $stack ) : 0;
			$html .= '<div style="margin:' . $gap . 'px 0 0">' . self::block( $block, $width, $alone ) . '</div>';
		}
		return $html;
	}

	private static function block( $block, $width, $alone ) {
		$align = isset( $block['align'] ) && '' !== $block['align'] ? $block['align'] : ( $alone ? 'center' : 'left' );
		switch ( $block['t'] ) {
			case 'title':
				return self::title( (string) $block['text'], 'h3' === $block['size'] ? 'h3' : 'h2', $align );
			case 'text':
				$text = wpautop( wp_kses_post( strip_shortcodes( (string) $block['html'] ) ) );
				// Pictures in the text: never wider than the column.
				$text = preg_replace( '/<img /', '<img style="max-width:100%;height:auto" ', $text );
				return '<div style="font-size:15px;line-height:1.6;text-align:' . esc_attr( $align ) . ';color:' . self::brown() . '">' . $text . '</div>';
			case 'image':
				return self::image( $block, $width );
			case 'video':
				return self::video( $block, $width );
			case 'lijn':
				return self::lijn_img( isset( $block['width'] ) ? $block['width'] : '', false, $width );
			case 'button':
				return self::email_button( (string) $block['text'], self::absolute( (string) $block['url'] ), isset( $block['style'] ) ? $block['style'] : '', $align );
		}
		return '';
	}

	/**
	 * A link of the site ("/winkel") as a full address: emails are read outside the site.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	private static function absolute( $url ) {
		return 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? home_url( $url ) : $url;
	}

	/**
	 * A button that works in every mail program (a table cell), in the site's style: the gold double line, or
	 * filled gold with white text.
	 *
	 * @param string $text  Text.
	 * @param string $url   Link.
	 * @param string $style '' or filled.
	 * @param string $align left, center or right.
	 * @return string
	 */
	public static function email_button( $text, $url, $style = '', $align = 'center' ) {
		$gold   = self::gold();
		$filled = 'filled' === $style;
		$align  = in_array( $align, array( 'left', 'right' ), true ) ? $align : 'center';
		$margin = 'center' === $align ? '12px auto' : ( 'right' === $align ? '12px 0 12px auto' : '12px auto 12px 0' );
		return sprintf(
			'<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="%1$s" style="margin:%2$s;border-collapse:separate"><tr><td align="center" bgcolor="%3$s" style="background-color:%3$s;border:%4$s;border-radius:100px;mso-padding-alt:12px 28px"><a href="%5$s" target="_blank" style="display:inline-block;padding:12px 28px;color:%6$s;font-weight:bold;font-size:15px;line-height:20px;letter-spacing:0.06em;text-transform:uppercase;text-decoration:none;border-radius:100px">%7$s</a></td></tr></table>',
			$align,
			$margin,
			$filled ? $gold : '#ffffff',
			$filled ? "3px solid {$gold}" : "3px double {$gold}",
			esc_url( $url ),
			$filled ? '#ffffff' : self::brown(),
			esc_html( $text )
		);
	}

	private static function title( $text, $tag, $align ) {
		$size  = 'h3' === $tag ? 18 : 24;
		$gold  = self::gold();
		$html  = '<' . $tag . ' style="margin:0;padding:0;font-family:\'Trajan Pro\',\'Cinzel\',Georgia,\'Times New Roman\',serif;font-size:' . $size . 'px;line-height:1.3;font-weight:bold;color:' . $gold . ';text-align:' . esc_attr( $align ) . '">' . esc_html( $text ) . '</' . $tag . '>';
		if ( 'h2' === $tag ) {
			// The gold double line under a large title, as on the blog.
			$margin = 'center' === $align ? '10px auto 14px' : ( 'right' === $align ? '10px 0 14px auto' : '10px 0 14px' );
			$html  .= '<div style="width:64px;height:0;margin:' . $margin . ';border-top:4px double ' . $gold . ';font-size:0;line-height:0">&nbsp;</div>';
		} else {
			$html .= '<div style="height:8px;font-size:0;line-height:0">&nbsp;</div>';
		}
		return $html;
	}

	private static function image( $block, $width ) {
		$id  = (int) $block['id'];
		$src = (string) wp_get_attachment_image_url( $id, 'large' );
		if ( '' === $src ) {
			return '';
		}
		$alt    = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$alt    = '' !== $alt ? $alt : ( '' !== $block['caption'] ? $block['caption'] : '' );
		$radius = array(
			'arch'   => '999px 999px 0 0',
			'round'  => '50%',
			'square' => '16px',
			// As it is: rounded corners too.
			''       => '16px',
		);
		$style  = 'display:block;width:100%;max-width:' . (int) $width . 'px;height:auto;margin:0 auto;border:0';
		if ( isset( $radius[ $block['shape'] ] ) ) {
			$style .= ';border-radius:' . $radius[ $block['shape'] ];
		}
		$img = '<img src="' . esc_url( $src ) . '" width="' . (int) $width . '" alt="' . esc_attr( $alt ) . '" style="' . $style . '">';
		if ( ! empty( $block['frame'] ) ) {
			$r   = isset( $radius[ $block['shape'] ] ) ? $radius[ $block['shape'] ] : '6px';
			$img = '<div style="padding:5px;border:5px double ' . self::gold() . ';border-radius:' . $r . '">' . $img . '</div>';
		}
		if ( '' !== $block['link'] ) {
			$img = '<a href="' . esc_url( $block['link'] ) . '" target="_blank">' . $img . '</a>';
		}
		return $img . self::caption( $block['caption'] );
	}

	private static function caption( $caption ) {
		return '' !== $caption ? '<p style="margin:8px 0 0;font-size:13px;line-height:1.5;font-style:italic;text-align:center;opacity:.85;color:' . self::brown() . '">' . esc_html( $caption ) . '</p>' : '';
	}

	// A video: its picture and a "Watch the video" link (mail programs don't play videos).
	private static function video( $block, $width ) {
		$url = $block['id'] ? (string) wp_get_attachment_url( (int) $block['id'] ) : (string) $block['url'];
		if ( '' === $url ) {
			return '';
		}
		$picture = $block['poster'] ? (string) wp_get_attachment_image_url( (int) $block['poster'], 'large' ) : '';
		if ( '' === $picture && '' !== (string) $block['url'] ) {
			$embed   = AIMP_Blog::embed( $block['url'] );
			$picture = $embed ? $embed[1] : '';
		}
		$html = '';
		if ( '' !== $picture ) {
			$html .= '<a href="' . esc_url( $url ) . '" target="_blank"><img src="' . esc_url( $picture ) . '" width="' . (int) $width . '" alt="' . esc_attr__( 'Video', 'atelier-irisee-master-plugin' ) . '" style="display:block;width:100%;max-width:' . (int) $width . 'px;height:auto;margin:0 auto;border:0;border-radius:' . ( 'plain' === $block['shape'] ? '0' : '16px' ) . '"></a>';
		}
		$html .= '<p style="margin:8px 0 0;font-size:14px;text-align:center"><a href="' . esc_url( $url ) . '" target="_blank" style="color:' . self::gold() . ';font-weight:bold">&#9654; ' . esc_html__( 'Watch the video', 'atelier-irisee-master-plugin' ) . '</a></p>';
		return $html . self::caption( $block['caption'] );
	}

	/**
	 * The atelier's line as a picture (mail programs don't show SVG).
	 *
	 * @param string $width     '' or narrow.
	 * @param bool   $separator Between summary cards (with space around).
	 * @param int    $max       The column's width.
	 * @return string
	 */
	public static function lijn_img( $width = '', $separator = false, $max = self::WIDTH ) {
		$w = (int) ( 'narrow' === $width ? round( $max * 0.6 ) : $max );
		return '<div style="margin:' . ( $separator ? '28px 0 24px' : '0' ) . ';text-align:center;font-size:0;line-height:0"><img src="' . esc_url( AIMP_PLUGIN_URL . 'assets/img/email/lijn.png' ) . '" width="' . $w . '" alt="" style="display:inline-block;width:100%;max-width:' . $w . 'px;height:auto;border:0"></div>';
	}

	/* ------------------------------------------------------------------
	 * Plain text
	 * ------------------------------------------------------------------ */

	/**
	 * @param array  $rows     Rows.
	 * @param string $layout   full or summary.
	 * @param string $view_url Web version.
	 * @return string
	 */
	public static function text( $rows, $layout = 'full', $view_url = '' ) {
		list( $intro, $sections ) = self::sections( $rows );
		if ( 'summary' === $layout && $sections ) {
			$out = self::text_rows( $intro );
			foreach ( $sections as $i => $section ) {
				$d    = self::digest( $section );
				$out .= "\n" . ( '' !== $d['title'] ? strtoupper( $d['title'] ) . "\n" : '' ) . ( '' !== $d['text'] ? $d['text'] . "\n" : '' );
				if ( '' !== $view_url ) {
					$out .= __( 'Continue reading', 'atelier-irisee-master-plugin' ) . ': ' . $view_url . '#s-' . ( $i + 1 ) . "\n";
				}
			}
			return $out;
		}
		return self::text_rows( $rows );
	}

	private static function text_rows( $rows ) {
		$out = '';
		foreach ( (array) $rows as $row ) {
			foreach ( AIMP_Blog::row_blocks( $row ) as $block ) {
				$t = isset( $block['t'] ) ? $block['t'] : '';
				if ( 'title' === $t && '' !== trim( (string) $block['text'] ) ) {
					$out .= "\n" . strtoupper( (string) $block['text'] ) . "\n";
				} elseif ( 'text' === $t ) {
					$out .= trim( html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $block['html'] ) ), ENT_QUOTES, 'UTF-8' ) ) . "\n";
				} elseif ( 'image' === $t && ! empty( $block['link'] ) ) {
					$out .= $block['link'] . "\n";
				} elseif ( 'video' === $t ) {
					$url  = ! empty( $block['id'] ) ? (string) wp_get_attachment_url( (int) $block['id'] ) : (string) $block['url'];
					$out .= '' !== $url ? __( 'Watch the video', 'atelier-irisee-master-plugin' ) . ': ' . $url . "\n" : '';
				} elseif ( 'button' === $t && ! empty( $block['url'] ) && '' !== trim( (string) $block['text'] ) ) {
					$out .= $block['text'] . ': ' . self::absolute( (string) $block['url'] ) . "\n";
				} elseif ( 'lijn' === $t ) {
					$out .= "\n— ✦ —\n";
				}
			}
		}
		return $out;
	}
}
