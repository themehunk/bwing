<?php
/**
 * Shared render helpers: section wrappers, cards, charts, pagination.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Render {

	public static function head( $title, $subtitle = '', $tag = 'h2', $link = array() ) {
		if ( ! $title && ! $subtitle ) {
			return '';
		}
		$h = '<div class="bw-head">';
		$h .= '<div class="bw-head__text">';
		if ( $title ) {
			$h .= '<' . $tag . ' class="bw-head__title">' . esc_html( $title ) . '</' . $tag . '>';
		}
		if ( $subtitle ) {
			$h .= '<p class="bw-head__sub">' . esc_html( $subtitle ) . '</p>';
		}
		$h .= '</div>';
		if ( ! empty( $link['url'] ) && ! empty( $link['text'] ) ) {
			$h .= '<a class="bw-head__link" href="' . esc_url( self::url( $link['url'] ) ) . '">' . esc_html( $link['text'] ) . '</a>';
		}
		return $h . '</div>';
	}

	public static function wrap( $id, $inner, $class = '' ) {
		return '<section class="bw-section bw-section--' . esc_attr( $id ) . ' ' . esc_attr( $class ) . '">' . $inner . '</section>';
	}

	/** Relative URLs become home URLs. */
	public static function url( $url ) {
		if ( $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$parts = explode( '#', $url, 2 );
			return bwing_url( $parts[0] ) . ( isset( $parts[1] ) ? '#' . $parts[1] : '' );
		}
		return $url;
	}

	public static function empty_state( $text ) {
		return '<div class="bw-empty"><span class="bw-empty__icon" aria-hidden="true">◌</span><p>' . esc_html( $text ) . '</p></div>';
	}

	/** Numbered rank row used in "Top" lists. */
	public static function rank_row( $n, $item, $type, $meta ) {
		$url = BWing_Format::item_url( $type, $item->slug );
		return sprintf(
			'<li class="bw-rank"><span class="bw-rank__n">%1$s</span><a class="bw-rank__link" href="%2$s">%3$s<span class="bw-rank__body"><strong>%4$s</strong><small>%5$s</small></span></a><span class="bw-rank__meta">%6$s</span></li>',
			esc_html( $n ),
			esc_url( $url ),
			BWing_Format::thumb( $item, 'bw-thumb bw-thumb--' . $type ),
			esc_html( $item->name ),
			esc_html( $item->author_name ? $item->author_name : $item->author ),
			$meta
		);
	}

	/** Compact mover card. */
	public static function mover( $item, $type ) {
		return sprintf(
			'<a class="bw-mover" href="%1$s">%2$s<span class="bw-mover__body"><strong>%3$s</strong><small>#%4$d · %5$s</small></span>%6$s</a>',
			esc_url( BWing_Format::item_url( $type, $item->slug ) ),
			BWing_Format::thumb( $item, 'bw-thumb bw-thumb--sm bw-thumb--' . $type ),
			esc_html( $item->name ),
			(int) $item->pos,
			esc_html( BWing_Format::installs( $item->active_installs ) ),
			BWing_Format::trend( (int) $item->delta )
		);
	}

	public static function item_card( $item ) {
		return sprintf(
			'<a class="bw-card bw-card--item" href="%1$s">%2$s<span class="bw-card__body"><strong>%3$s</strong><small>%4$s · %5$s</small></span></a>',
			esc_url( BWing_Format::item_url( $item->type, $item->slug ) ),
			BWing_Format::thumb( $item, 'bw-thumb bw-thumb--' . $item->type ),
			esc_html( $item->name ),
			esc_html( BWing_Format::installs( $item->active_installs ) ),
			esc_html( BWing_Format::stars( $item->rating ) ) . '★'
		);
	}

	public static function stat( $value, $label, $note = '', $tone = '' ) {
		return '<div class="bw-stat ' . ( $tone ? 'bw-stat--' . esc_attr( $tone ) : '' ) . '"><span class="bw-stat__value">' . esc_html( $value ) . '</span><span class="bw-stat__label">' . esc_html( $label ) . '</span>' . ( $note ? '<span class="bw-stat__note">' . wp_kses_post( $note ) . '</span>' : '' ) . '</div>';
	}

	public static function pagination( $page, $pages, $param = 'pg' ) {
		if ( $pages < 2 ) {
			return '';
		}
		$out   = '<nav class="bw-pager" aria-label="' . esc_attr__( 'Pagination', 'bwing' ) . '">';
		$link  = function ( $p ) use ( $param ) {
			return esc_url( add_query_arg( $param, $p ) );
		};
		if ( $page > 1 ) {
			$out .= '<a class="bw-pager__btn" href="' . $link( $page - 1 ) . '">‹ ' . esc_html__( 'Prev', 'bwing' ) . '</a>';
		}
		$window = array_unique( array_filter( array( 1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages ), function ( $p ) use ( $pages ) {
			return $p >= 1 && $p <= $pages;
		} ) );
		sort( $window );
		$prev = 0;
		foreach ( $window as $p ) {
			if ( $prev && $p - $prev > 1 ) {
				$out .= '<span class="bw-pager__gap">…</span>';
			}
			$out .= $p === $page ? '<span class="bw-pager__cur" aria-current="page">' . (int) $p . '</span>' : '<a href="' . $link( $p ) . '">' . (int) $p . '</a>';
			$prev = $p;
		}
		if ( $page < $pages ) {
			$out .= '<a class="bw-pager__btn" href="' . $link( $page + 1 ) . '">' . esc_html__( 'Next', 'bwing' ) . ' ›</a>';
		}
		return $out . '</nav>';
	}

	/**
	 * Inline SVG line/area chart.
	 *
	 * @param array $points  [ [label, value], ... ].
	 * @param bool  $invert  Lower is better (rank).
	 * @param bool  $short   Format axis values as 1.2M.
	 */
	public static function line_chart( $points, $invert = false, $short = true ) {
		$n = count( $points );
		if ( $n < 2 ) {
			return self::empty_state( __( 'Not enough history yet — check back after a few daily snapshots.', 'bwing' ) );
		}
		$w    = 720;
		$h    = 220;
		$pad  = array( 16, 12, 28, 52 ); // top right bottom left.
		$vals = wp_list_pluck( $points, 1 );
		$min  = min( $vals );
		$max  = max( $vals );
		if ( $min === $max ) {
			$min = $invert ? max( 0, $min - 1 ) : $min * 0.95;
			$max = $invert ? $max + 1 : ( $max ? $max * 1.05 : 1 );
		}
		$x = function ( $i ) use ( $n, $w, $pad ) {
			return $pad[3] + ( $w - $pad[1] - $pad[3] ) * $i / ( $n - 1 );
		};
		$y = function ( $v ) use ( $min, $max, $h, $pad, $invert ) {
			$r = ( $v - $min ) / ( $max - $min );
			if ( $invert ) {
				$r = 1 - $r;
			}
			return $h - $pad[2] - ( $h - $pad[0] - $pad[2] ) * $r;
		};
		$line = '';
		foreach ( $points as $i => $p ) {
			$line .= ( $i ? 'L' : 'M' ) . round( $x( $i ), 1 ) . ' ' . round( $y( $p[1] ), 1 );
		}
		$area = $line . 'L' . round( $x( $n - 1 ), 1 ) . ' ' . ( $h - $pad[2] ) . 'L' . $pad[3] . ' ' . ( $h - $pad[2] ) . 'Z';
		$fmt  = function ( $v ) use ( $short, $invert ) {
			return $invert ? '#' . (int) round( $v ) : ( $short ? BWing_Format::short( $v ) : number_format_i18n( $v ) );
		};
		$grid = '';
		for ( $g = 0; $g <= 3; $g++ ) {
			$v     = $min + ( $max - $min ) * $g / 3;
			$gy    = round( $y( $v ), 1 );
			$grid .= '<line x1="' . $pad[3] . '" x2="' . ( $w - $pad[1] ) . '" y1="' . $gy . '" y2="' . $gy . '" class="bw-chart__grid"/>';
			$grid .= '<text x="' . ( $pad[3] - 8 ) . '" y="' . ( $gy + 4 ) . '" text-anchor="end" class="bw-chart__axis">' . esc_html( $fmt( $v ) ) . '</text>';
		}
		$labels = '';
		foreach ( array( 0, (int) floor( ( $n - 1 ) / 2 ), $n - 1 ) as $k => $i ) {
			$anchor  = 0 === $k ? 'start' : ( 2 === $k ? 'end' : 'middle' );
			$labels .= '<text x="' . round( $x( $i ), 1 ) . '" y="' . ( $h - 8 ) . '" text-anchor="' . $anchor . '" class="bw-chart__axis">' . esc_html( mysql2date( 'M j', $points[ $i ][0] ) ) . '</text>';
		}
		$last  = end( $points );
		$dot   = '<circle cx="' . round( $x( $n - 1 ), 1 ) . '" cy="' . round( $y( $last[1] ), 1 ) . '" r="4" class="bw-chart__dot"/>';
		$title = esc_attr( sprintf( '%s → %s', $fmt( $points[0][1] ), $fmt( $last[1] ) ) );
		$id    = 'g' . wp_rand( 1000, 999999 );
		return '<svg class="bw-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . $title . '" preserveAspectRatio="none">'
			. '<defs><linearGradient id="' . $id . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".28"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>'
			. $grid . '<path d="' . $area . '" fill="url(#' . $id . ')" class="bw-chart__area"/><path d="' . $line . '" class="bw-chart__line"/>' . $dot . $labels . '</svg>';
	}

	/** Chart with 30d / 90d / 1y / All toggles. */
	public static function ranged_chart( $history, $field, $invert = false ) {
		$ranges = array( '30' => '30d', '90' => '90d', '365' => '1y', '0' => __( 'All', 'bwing' ) );
		$latest = $history ? strtotime( end( $history )->snap_date ) : time();
		$btns   = '';
		$panes  = '';
		$first  = true;
		$prev   = -1;
		foreach ( $ranges as $days => $label ) {
			$pts = array();
			foreach ( $history as $h ) {
				if ( ! $days || strtotime( $h->snap_date ) >= $latest - $days * DAY_IN_SECONDS ) {
					$pts[] = array( $h->snap_date, (float) $h->$field );
				}
			}
			if ( count( $pts ) === $prev && $days ) {
				continue; // Same data as smaller range.
			}
			$prev   = count( $pts );
			$btns  .= '<button type="button" class="bw-seg__btn' . ( $first ? ' is-active' : '' ) . '" data-range="' . esc_attr( $days ) . '">' . esc_html( $label ) . '</button>';
			$panes .= '<div class="bw-chartpane' . ( $first ? ' is-active' : '' ) . '" data-range="' . esc_attr( $days ) . '">' . self::line_chart( $pts, $invert ) . '</div>';
			$first  = false;
		}
		return '<div class="bw-ranged"><div class="bw-seg" role="group">' . $btns . '</div>' . $panes . '</div>';
	}

	/** History rows → [[date, value], ...] for the trend explorer. */
	public static function points( $rows, $field ) {
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array( $r->snap_date, (float) $r->$field );
		}
		return $out;
	}

	/**
	 * Interactive multi-metric chart (rendered client-side by bwing.js).
	 *
	 * @param array $series key => [label, kind line|bar, invert, points].
	 */
	public static function trend_explorer( $series ) {
		$series = array_filter( $series, function ( $s ) {
			return count( $s['points'] ) > 1;
		} );
		if ( ! $series ) {
			return self::empty_state( __( 'Not enough history yet — check back after a few daily snapshots.', 'bwing' ) );
		}
		$tabs = '';
		$i    = 0;
		foreach ( $series as $key => $s ) {
			$tabs .= '<button type="button" class="bw-seg__btn' . ( 0 === $i++ ? ' is-active' : '' ) . '" data-metric="' . esc_attr( $key ) . '">' . esc_html( $s['label'] ) . '</button>';
		}
		$ranges = '';
		foreach ( array( '30' => '30d', '90' => '90d', '365' => '1y', '0' => __( 'All', 'bwing' ) ) as $d => $l ) {
			$ranges .= '<button type="button" class="bw-seg__btn' . ( '90' === (string) $d ? ' is-active' : '' ) . '" data-range="' . esc_attr( $d ) . '">' . esc_html( $l ) . '</button>';
		}
		return '<div class="bw-tx" data-bw-trends="' . esc_attr( wp_json_encode( $series ) ) . '">'
			. '<div class="bw-tx__bar"><div class="bw-seg bw-tx__metrics" role="tablist">' . $tabs . '</div><div class="bw-seg bw-tx__ranges">' . $ranges . '</div></div>'
			. '<div class="bw-tx__summary"><span class="bw-tx__value">—</span><span class="bw-tx__delta"></span><span class="bw-tx__span"></span></div>'
			. '<div class="bw-tx__plot" aria-hidden="true"><div class="bw-tx__tip" hidden></div></div>'
			. '<noscript><p class="bw-muted">' . esc_html__( 'Enable JavaScript to view the interactive chart.', 'bwing' ) . '</p></noscript>'
			. '</div>';
	}

	/** Monthly last-reading table. */
	public static function monthly_table( $history, $field, $label, $invert = false ) {
		$months = array();
		foreach ( $history as $h ) {
			$months[ substr( $h->snap_date, 0, 7 ) ] = $h;
		}
		$months = array_slice( array_reverse( $months, true ), 0, 12, true );
		if ( ! $months ) {
			return '';
		}
		$rows = '';
		$keys = array_keys( $months );
		foreach ( $keys as $i => $m ) {
			$v      = (float) $months[ $m ]->$field;
			$prev   = isset( $keys[ $i + 1 ] ) ? (float) $months[ $keys[ $i + 1 ] ]->$field : null;
			$change = null === $prev ? '—' : ( $invert ? BWing_Format::trend( (int) ( $prev - $v ) ) : BWing_Format::signed( $v - $prev ) );
			$rows  .= '<tr><td>' . esc_html( mysql2date( 'F Y', $m . '-01' ) ) . '</td><td>' . esc_html( $invert ? '#' . (int) $v : BWing_Format::short( $v ) ) . '</td><td>' . $change . '</td></tr>';
		}
		return '<details class="bw-monthly"><summary>' . esc_html__( 'Monthly readings', 'bwing' ) . '</summary><table class="bw-table bw-table--compact"><thead><tr><th>' . esc_html__( 'Month', 'bwing' ) . '</th><th>' . esc_html( $label ) . '</th><th>' . esc_html__( 'Change', 'bwing' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
	}

	/** Horizontal bars: label => value. */
	public static function bars( $data, $sqrt = false ) {
		if ( ! $data ) {
			return '';
		}
		$max = max( 1, max( $data ) );
		$out = '<div class="bw-bars">';
		foreach ( $data as $label => $v ) {
			$pct  = $sqrt ? sqrt( $v ) / sqrt( $max ) * 100 : $v / $max * 100;
			$out .= '<div class="bw-bars__row"><span class="bw-bars__label">' . esc_html( $label ) . '</span><span class="bw-bars__track"><span style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></span></span><span class="bw-bars__val">' . esc_html( number_format_i18n( $v ) ) . '</span></div>';
		}
		return $out . '</div>';
	}

	/** Vertical columns: label => value. */
	public static function columns( $data ) {
		$max = max( 1, max( $data ? $data : array( 1 ) ) );
		$out = '<div class="bw-cols">';
		foreach ( $data as $label => $v ) {
			$out .= '<div class="bw-cols__col"><span class="bw-cols__val">' . esc_html( number_format_i18n( $v ) ) . '</span><span class="bw-cols__bar" style="height:' . esc_attr( max( 2, round( $v / $max * 100 ) ) ) . '%"></span><span class="bw-cols__label">' . esc_html( $label ) . '</span></div>';
		}
		return $out . '</div>';
	}

	/** Two-part split bar. */
	public static function split( $a_label, $a, $b_label, $b ) {
		$t  = max( 1, $a + $b );
		$pa = round( $a / $t * 100 );
		return '<div class="bw-split"><div class="bw-split__bar"><span style="width:' . (int) $pa . '%"></span></div><div class="bw-split__legend"><span><i class="is-a"></i>' . esc_html( $a_label ) . ' <b>' . (int) $pa . '%</b> (' . esc_html( number_format_i18n( $a ) ) . ')</span><span><i class="is-b"></i>' . esc_html( $b_label ) . ' <b>' . (int) ( 100 - $pa ) . '%</b> (' . esc_html( number_format_i18n( $b ) ) . ')</span></div></div>';
	}

	/** Lighthouse score ring. */
	public static function score( $value, $label ) {
		$v    = (int) $value;
		$tone = $v >= 90 ? 'good' : ( $v >= 50 ? 'ok' : 'bad' );
		return '<div class="bw-score bw-score--' . $tone . '" style="--v:' . $v . '"><span class="bw-score__ring"><b>' . $v . '</b></span><span class="bw-score__label">' . esc_html( $label ) . '</span></div>';
	}

	/** FAQ accordion + schema. */
	public static function faq( $rows, $title = '' ) {
		if ( ! $rows ) {
			return '';
		}
		$html   = '<div class="bw-faq">';
		$schema = array();
		foreach ( $rows as $i => $r ) {
			if ( empty( $r[0] ) ) {
				continue;
			}
			$a        = isset( $r[1] ) ? $r[1] : '';
			$html    .= '<details class="bw-faq__item"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $r[0] ) . '<span class="bw-faq__icon" aria-hidden="true"></span></summary><div class="bw-faq__a"><p>' . esc_html( $a ) . '</p></div></details>';
			$schema[] = array(
				'@type'          => 'Question',
				'name'           => $r[0],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ),
			);
		}
		$html .= '</div>';
		$html .= '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema ) ) . '</script>';
		return ( $title ? self::head( $title ) : '' ) . $html;
	}

	public static function search_form( $placeholder, $class = '' ) {
		return '<form class="bw-search ' . esc_attr( $class ) . '" role="search" action="' . esc_url( home_url( '/' ) ) . '" method="get" data-bw-live>'
			. '<svg class="bw-search__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>'
			. '<input type="search" name="s" autocomplete="off" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr( $placeholder ) . '">'
			. '<kbd class="bw-search__kbd">/</kbd>'
			. '<div class="bw-search__results" role="listbox" hidden></div></form>';
	}
}
