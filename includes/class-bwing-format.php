<?php
/**
 * Formatting helpers.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Format {

	/** Install brackets used by WordPress.org. */
	const BRACKETS = array( 10000000, 9000000, 8000000, 7000000, 6000000, 5000000, 4000000, 3000000, 2000000, 1000000, 900000, 800000, 700000, 600000, 500000, 400000, 300000, 200000, 100000, 90000, 80000, 70000, 60000, 50000, 40000, 30000, 20000, 10000, 9000, 8000, 7000, 6000, 5000, 4000, 3000, 2000, 1000, 900, 800, 700, 600, 500, 400, 300, 200, 100, 90, 80, 70, 60, 50, 40, 30, 20, 10, 0 );

	public static function short( $n, $plus = false ) {
		$n = (float) $n;
		if ( $n >= 1000000000 ) {
			$s = round( $n / 1000000000, 1 ) . 'B';
		} elseif ( $n >= 1000000 ) {
			$s = round( $n / 1000000, 1 ) . 'M';
		} elseif ( $n >= 1000 ) {
			$s = round( $n / 1000, 1 ) . 'K';
		} else {
			$s = number_format_i18n( $n );
		}
		$s = str_replace( '.0', '', $s );
		return $plus ? $s . '+' : $s;
	}

	public static function installs( $n ) {
		return $n > 0 ? self::short( $n, true ) : '<10';
	}

	/** Next install milestone text, e.g. "next 600K+". */
	public static function next_milestone( $n ) {
		$n = (int) $n;
		$asc = array_reverse( self::BRACKETS );
		foreach ( $asc as $b ) {
			if ( $b > $n ) {
				return sprintf( /* translators: %s bracket */ __( 'next %s', 'bwing' ), self::short( $b, true ) );
			}
		}
		return __( 'top bracket', 'bwing' );
	}

	public static function stars( $rating ) {
		// WordPress.org returns 0–100.
		$r = $rating > 5 ? $rating / 20 : $rating;
		return round( $r, 1 );
	}

	public static function stars_html( $rating, $count = 0 ) {
		if ( ! $count && ! $rating ) {
			return '<span class="bw-muted">—</span>';
		}
		$r = self::stars( $rating );
		$pct = min( 100, max( 0, $r * 20 ) );
		return sprintf(
			'<span class="bw-stars" title="%1$s"><span class="bw-stars__bar"><span style="width:%2$s%%"></span></span><b>%1$s</b>%3$s</span>',
			esc_attr( $r ),
			esc_attr( $pct ),
			$count ? ' <small>(' . esc_html( self::short( $count ) ) . ')</small>' : ''
		);
	}

	/**
	 * Rank change pill. Positive delta = climbed.
	 *
	 * @param int|null $delta Position delta, null when item is new.
	 */
	public static function trend( $delta, $label = '' ) {
		$lbl = $label ? '<i>' . esc_html( $label ) . '</i>' : '';
		if ( null === $delta ) {
			return '<span class="bw-trend bw-trend--new">' . $lbl . esc_html__( 'new', 'bwing' ) . '</span>';
		}
		$delta = (int) $delta;
		if ( 0 === $delta ) {
			return '<span class="bw-trend bw-trend--flat">' . $lbl . '▬</span>';
		}
		$cls = $delta > 0 ? 'up' : 'down';
		$arw = $delta > 0 ? '▲' : '▼';
		return '<span class="bw-trend bw-trend--' . $cls . '">' . $lbl . $arw . ' ' . esc_html( abs( $delta ) ) . '</span>';
	}

	public static function signed( $n ) {
		$n = (int) $n;
		if ( 0 === $n ) {
			return '—';
		}
		return ( $n > 0 ? '+' : '−' ) . self::short( abs( $n ) );
	}

	public static function item_url( $type, $slug ) {
		$base = 'plugin' === $type ? 'plugins' : 'themes';
		return bwing_url( $base . '/' . $slug );
	}

	public static function premium_url( $type, $slug ) {
		$base = 'plugin' === $type ? 'premium-plugins' : 'premium-themes';
		return bwing_url( $base . '/' . $slug );
	}

	public static function author_url( $slug ) {
		return bwing_url( 'authors/' . $slug );
	}

	public static function wporg_url( $type, $slug ) {
		return 'plugin' === $type ? 'https://wordpress.org/plugins/' . $slug . '/' : 'https://wordpress.org/themes/' . $slug . '/';
	}

	/** Image with generated letter-avatar fallback. */
	public static function thumb( $item, $class = 'bw-thumb' ) {
		$name = is_object( $item ) ? $item->name : ( isset( $item['name'] ) ? $item['name'] : '' );
		$img  = is_object( $item ) ? $item->image : ( isset( $item['image'] ) ? $item['image'] : '' );
		if ( $img ) {
			return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
		}
		$letter = strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 1 ) );
		$hue    = abs( crc32( $name ) ) % 360;
		return '<span class="' . esc_attr( $class ) . ' bw-thumb--letter" style="--h:' . (int) $hue . '">' . esc_html( $letter ) . '</span>';
	}

	/**
	 * Author picture: an image when one is known, else a coloured letter badge.
	 *
	 * @param string $author Author slug.
	 * @param int    $size   Unused, kept for back-compat.
	 * @param string $img    Image URL.
	 */
	public static function avatar( $author, $size = 64, $img = '' ) {
		if ( $img ) {
			return '<img class="bw-avatar bw-avatar--img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
		}
		$hue  = abs( crc32( $author ) ) % 360;
		return '<span class="bw-avatar" style="--h:' . (int) $hue . '">' . esc_html( strtoupper( mb_substr( $author, 0, 1 ) ) ) . '</span>';
	}

	/**
	 * Image URL for an author row (needs author_avatar / author_url).
	 *
	 * @param object $r    Author row.
	 * @param string $mode 'brand' = company logo from the author website, 'photo' = WordPress.org profile photo.
	 */
	public static function author_image( $r, $mode = 'photo' ) {
		$photo = isset( $r->author_avatar ) ? $r->author_avatar : '';
		$host  = ! empty( $r->author_url ) ? wp_parse_url( $r->author_url, PHP_URL_HOST ) : '';
		$brand = $host ? apply_filters( 'bwing_brand_logo_url', 'https://www.google.com/s2/favicons?sz=128&domain=' . rawurlencode( $host ), $host, $r ) : '';
		return 'brand' === $mode ? ( $brand ? $brand : $photo ) : ( $photo ? $photo : $brand );
	}

	public static function date( $date ) {
		if ( ! $date || '0000-00-00' === $date ) {
			return '—';
		}
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
	}

	public static function ago( $date ) {
		if ( ! $date ) {
			return '—';
		}
		/* translators: %s human time diff */
		return sprintf( __( '%s ago', 'bwing' ), human_time_diff( strtotime( $date ), time() ) );
	}
}
