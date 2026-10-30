<?php
/**
 * Single theme / plugin / premium / author pages.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Single {

	public static function item( $type, $slug ) {
		$item = BWing_Data::item( $type, $slug );
		if ( ! $item ) {
			return BWing_Render::empty_state( __( 'Item not found.', 'bwing' ) );
		}
		$s       = BWing_Settings::section( 'single' );
		$history = BWing_Data::history( $item->id );
		$item    = BWing_Data::attach_trends( array( $item ), array( 1, 7, 30, 90 ) )[0];
		$stats   = BWing_Data::stats( $type );
		$noun    = 'theme' === $type ? __( 'theme', 'bwing' ) : __( 'plugin', 'bwing' );
		$list    = 'theme' === $type ? bwing_url( 'themes' ) : bwing_url( 'plugins' );
		$premium = self::linked_premium( $item );

		// Downloads per day from history.
		$per_day = array();
		$prev    = null;
		foreach ( $history as $h ) {
			if ( $prev ) {
				$per_day[] = (object) array( 'snap_date' => $h->snap_date, 'dl' => max( 0, $h->downloaded - $prev->downloaded ) );
			}
			$prev = $h;
		}
		$dl_today = $per_day ? end( $per_day )->dl : 0;
		$dl_prev  = count( $per_day ) > 1 ? $per_day[ count( $per_day ) - 2 ]->dl : 0;
		$dl_7     = array_sum( wp_list_pluck( array_slice( $per_day, -7 ), 'dl' ) );
		$share    = $stats['installs'] ? round( $item->active_installs / $stats['installs'] * 100, 2 ) : 0;
		$days_top = 0;
		foreach ( $history as $h ) {
			if ( $h->pos > 0 && $h->pos <= 50 ) {
				$days_top++;
			}
		}

		$o  = '<a class="bw-back" href="' . esc_url( $list ) . '">← ' . esc_html( sprintf( /* translators: %s noun */ __( 'All popular %ss', 'bwing' ), $noun ) ) . '</a>';
		$o .= '<header class="bw-itemhead bw-itemhead--' . esc_attr( $type ) . '">' . BWing_Format::thumb( $item, 'bw-itemhead__img' ) . '<div class="bw-itemhead__body">';
		$o .= '<div class="bw-itemhead__tags">' . ( 'theme' === $type ? '<span class="bw-tag' . ( $item->is_block ? ' bw-tag--block' : '' ) . '">' . esc_html( $item->is_block ? __( 'Block', 'bwing' ) : __( 'Classic', 'bwing' ) ) . '</span>' : '' ) . ( $premium ? '<span class="bw-tag bw-tag--premium">' . esc_html__( 'Premium available', 'bwing' ) . '</span>' : '' ) . '<span class="bw-tag bw-tag--rank">#' . (int) $item->pos . '</span></div>';
		$o .= '<h1>' . esc_html( $item->name ) . '</h1>';
		$o .= '<p class="bw-itemhead__by">' . esc_html__( 'by', 'bwing' ) . ' <a href="' . esc_url( BWing_Format::author_url( $item->author ) ) . '">' . esc_html( $item->author_name ? $item->author_name : $item->author ) . '</a> · ' . BWing_Format::stars_html( $item->rating, $item->num_ratings ) . '</p>';
		$o .= '<div class="bw-itemhead__actions"><a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( BWing_Format::wporg_url( $type, $item->slug ) ) . '" target="_blank" rel="noopener nofollow">WordPress.org ↗</a>';
		if ( $item->preview_url ) {
			$o .= '<a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( $item->preview_url ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Live preview', 'bwing' ) . '</a>';
		}
		if ( $item->homepage && false === strpos( $item->homepage, 'wordpress.org' ) ) {
			$o .= '<a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( $item->homepage ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Homepage', 'bwing' ) . '</a>';
		}
		$o .= '</div></div></header>';

		if ( $premium ) {
			$o .= '<a class="bw-callout" href="' . esc_url( BWing_Format::premium_url( $premium->type, $premium->slug ) ) . '"><span>★</span><div><strong>' . esc_html( $premium->name ) . '</strong> ' . esc_html__( 'from', 'bwing' ) . ' ' . esc_html( $premium->price ) . ' · ' . esc_html( BWing_Format::short( $premium->live_sites ) ) . ' ' . esc_html__( 'live sites', 'bwing' ) . '</div><em>' . esc_html__( 'Premium stats →', 'bwing' ) . '</em></a>';
		}

		$o .= '<div class="bw-statgrid bw-statgrid--6">'
			. BWing_Render::stat( BWing_Format::installs( $item->active_installs ), __( 'Active installs', 'bwing' ), sprintf( /* translators: %s pct */ __( '%s%% of tracked installs', 'bwing' ), $share ) )
			. BWing_Render::stat( '#' . (int) $item->pos, __( 'Rank', 'bwing' ), sprintf( /* translators: %d peak */ __( 'Peak #%d', 'bwing' ), $item->peak_pos ) )
			. BWing_Render::stat( number_format_i18n( $dl_today ), __( 'Downloads / day', 'bwing' ), sprintf( /* translators: %s prev */ __( 'prev %s', 'bwing' ), number_format_i18n( $dl_prev ) ) )
			. BWing_Render::stat( BWing_Format::short( $dl_7 ), __( '7-day downloads', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $item->downloaded ), __( 'All-time downloads', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( count( $history ) ), __( 'Days tracked', 'bwing' ), sprintf( /* translators: %d days */ __( '%d in top 50', 'bwing' ), $days_top ) )
			. '</div>';

		$o .= '<div class="bw-trendrow">' . BWing_Format::trend( $item->d1, '1d' ) . BWing_Format::trend( $item->d7, '7d' ) . BWing_Format::trend( $item->d30, '30d' ) . BWing_Format::trend( $item->d90, '90d' ) . '</div>';

		$o .= self::highlights( $item, $history, $stats, $noun );

		if ( ! empty( $s['show_perf'] ) && 'theme' === $type && $item->perf_mobile ) {
			$o .= '<section class="bw-panel"><h2>' . esc_html__( 'Performance (Lighthouse)', 'bwing' ) . '</h2><div class="bw-perf">' . BWing_Render::score( $item->perf_mobile, __( 'Mobile', 'bwing' ) ) . BWing_Render::score( $item->perf_desktop, __( 'Desktop', 'bwing' ) )
				. '<dl class="bw-dl"><dt>LCP</dt><dd>' . esc_html( $item->lcp ) . 's</dd><dt>FCP</dt><dd>' . esc_html( $item->fcp ) . 's</dd><dt>TBT</dt><dd>' . (int) $item->tbt . 'ms</dd><dt>CLS</dt><dd>' . esc_html( $item->cls ) . '</dd><dt>' . esc_html__( 'Page weight', 'bwing' ) . '</dt><dd>' . (int) $item->page_weight . ' KB</dd></dl></div>'
				. '<p class="bw-muted">' . esc_html__( 'Tested on a clean install with demo content. Scores vary ±5 between runs.', 'bwing' ) . ' <a href="' . esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $item->preview_url ? $item->preview_url : $item->homepage ) ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Run a live test ↗', 'bwing' ) . '</a></p></section>';
		}

		if ( $item->live_sites ) {
			$o .= '<section class="bw-panel"><h2>' . esc_html__( 'Real-world usage', 'bwing' ) . '</h2><div class="bw-statgrid">' . BWing_Render::stat( BWing_Format::short( $item->live_sites ), __( 'Detected live sites', 'bwing' ) ) . BWing_Render::stat( BWing_Format::installs( $item->active_installs ), __( 'WordPress.org installs', 'bwing' ) ) . '</div></section>';
		}

		if ( ! empty( $s['show_history'] ) ) {
			$series = array(
				'rank'     => array( 'label' => __( 'Rank', 'bwing' ), 'kind' => 'line', 'invert' => true, 'points' => BWing_Render::points( $history, 'pos' ) ),
				'installs' => array( 'label' => __( 'Active installs', 'bwing' ), 'kind' => 'line', 'invert' => false, 'points' => BWing_Render::points( $history, 'active_installs' ) ),
			);
			if ( $per_day ) {
				$series['downloads'] = array( 'label' => __( 'Downloads / day', 'bwing' ), 'kind' => 'bar', 'invert' => false, 'points' => BWing_Render::points( $per_day, 'dl' ) );
			}
			$o .= '<section class="bw-panel bw-panel--trends"><h2>' . esc_html__( 'Trend explorer', 'bwing' ) . '</h2>' . BWing_Render::trend_explorer( $series )
				. '<div class="bw-grid2 bw-monthly-pair">' . BWing_Render::monthly_table( $history, 'pos', __( 'Rank', 'bwing' ), true ) . BWing_Render::monthly_table( $history, 'active_installs', __( 'Installs', 'bwing' ) ) . '</div></section>';
		}

		if ( ! empty( $s['show_sites'] ) ) {
			$sites = BWing_Data::detections_for( $type, $item->slug );
			/* translators: %s name */
			$o .= '<section class="bw-panel"><h2>' . esc_html( sprintf( __( 'Websites using %s', 'bwing' ), $item->name ) ) . '</h2>';
			if ( $sites ) {
				$o .= '<div class="bw-sites">';
				foreach ( $sites as $d ) {
					$o .= BWing_Shortcodes::site_card( $d );
				}
				$o .= '</div>';
			} else {
				$o .= '<p class="bw-muted">' . esc_html__( 'No detected sites yet.', 'bwing' ) . '</p>';
			}
			$o .= '<a class="bw-callout bw-callout--soft" href="' . esc_url( bwing_url( 'theme-detector' ) ) . '"><span>⌕</span><div>' . esc_html__( 'Check which theme and plugins any site runs.', 'bwing' ) . '</div><em>' . esc_html__( 'Open detector →', 'bwing' ) . '</em></a></section>';
		}

		$tags = array_filter( array_map( 'trim', explode( ',', (string) $item->tags ) ) );
		$o   .= '<div class="bw-grid2"><section class="bw-panel"><h2>' . esc_html__( 'Details', 'bwing' ) . '</h2><dl class="bw-details">'
			. '<dt>' . esc_html__( 'Author', 'bwing' ) . '</dt><dd><a href="' . esc_url( BWing_Format::author_url( $item->author ) ) . '">' . esc_html( $item->author_name ? $item->author_name : $item->author ) . '</a></dd>'
			. '<dt>' . esc_html__( 'Version', 'bwing' ) . '</dt><dd>' . esc_html( $item->version ? $item->version : '—' ) . '</dd>'
			. '<dt>' . esc_html__( 'Last updated', 'bwing' ) . '</dt><dd>' . BWing_Format::date( $item->last_updated ) . '</dd>'
			. '<dt>' . esc_html__( 'Added', 'bwing' ) . '</dt><dd>' . BWing_Format::date( $item->added ) . '</dd>'
			. '<dt>' . esc_html__( 'Requires WordPress', 'bwing' ) . '</dt><dd>' . esc_html( $item->requires ? $item->requires : '—' ) . '</dd>'
			. '<dt>' . esc_html__( 'Requires PHP', 'bwing' ) . '</dt><dd>' . esc_html( $item->requires_php ? $item->requires_php : '—' ) . '</dd>'
			. '<dt>' . esc_html__( 'First tracked', 'bwing' ) . '</dt><dd>' . BWing_Format::date( $item->first_tracked ) . '</dd>'
			. '</dl>';
		if ( $tags ) {
			$o .= '<div class="bw-chips">';
			foreach ( array_slice( $tags, 0, 14 ) as $t ) {
				$o .= '<span class="bw-chip">' . esc_html( str_replace( '-', ' ', $t ) ) . '</span>';
			}
			$o .= '</div>';
		}
		$o .= '</section><section class="bw-panel"><h2>' . esc_html__( 'Description', 'bwing' ) . '</h2><div class="bw-prose">' . wp_kses_post( wpautop( $item->description ) ) . '</div></section></div>';

		$faq = array(
			/* translators: 1 name 2 installs */
			array( sprintf( __( 'How many sites use %s?', 'bwing' ), $item->name ), sprintf( __( '%1$s has %2$s active installs according to WordPress.org.', 'bwing' ), $item->name, BWing_Format::installs( $item->active_installs ) ) ),
			/* translators: 1 name 2 rank 3 noun */
			array( sprintf( __( 'How popular is %s?', 'bwing' ), $item->name ), sprintf( __( 'It is currently ranked #%2$d among tracked %3$ss, with a peak of #%4$d.', 'bwing' ), $item->name, $item->pos, $noun, $item->peak_pos ) ),
			/* translators: 1 name 2 date */
			array( sprintf( __( 'When was %s last updated?', 'bwing' ), $item->name ), sprintf( __( 'The latest version (%2$s) was released on %1$s.', 'bwing' ), wp_strip_all_tags( BWing_Format::date( $item->last_updated ) ), $item->version ) ),
			/* translators: 1 name 2 author */
			array( sprintf( __( 'Who develops %s?', 'bwing' ), $item->name ), sprintf( __( '%1$s is developed by %2$s.', 'bwing' ), $item->name, $item->author_name ? $item->author_name : $item->author ) ),
		);
		$o .= '<section class="bw-panel">' . BWing_Render::faq( $faq, __( 'FAQ', 'bwing' ) ) . '</section>';

		if ( ! empty( $s['show_similar'] ) ) {
			$same = BWing_Data::same_author( $item );
			if ( $same ) {
				/* translators: %s author */
				$o .= BWing_Render::wrap( 'same', BWing_Render::head( sprintf( __( 'More by %s', 'bwing' ), $item->author_name ? $item->author_name : $item->author ) ) . '<div class="bw-cards">' . implode( '', array_map( array( 'BWing_Render', 'item_card' ), $same ) ) . '</div>' );
			}
			$sim = BWing_Data::similar( $item );
			if ( $sim ) {
				/* translators: %s noun */
				$o .= BWing_Render::wrap( 'similar', BWing_Render::head( sprintf( __( 'Similar %ss', 'bwing' ), $noun ) ) . '<div class="bw-cards">' . implode( '', array_map( array( 'BWing_Render', 'item_card' ), $sim ) ) . '</div>' );
			}
		}
		if ( ! empty( $s['disclosure'] ) ) {
			$o .= '<p class="bw-disclosure">' . esc_html( $s['disclosure'] ) . '</p>';
		}
		return '<article class="bw-single bw-single--' . esc_attr( $type ) . '">' . $o . '</article>';
	}

	private static function linked_premium( $item ) {
		global $wpdb;
		$t = BWing_DB::table( 'premium' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND free_slug = %s LIMIT 1", $item->type, $item->slug ) );
	}

	private static function highlights( $item, $history, $stats, $noun ) {
		$h = array();
		if ( $item->d30 > 0 ) {
			/* translators: %d positions */
			$h[] = sprintf( __( 'Climbed %d positions over the last 30 days.', 'bwing' ), $item->d30 );
		} elseif ( $item->d30 < 0 ) {
			/* translators: %d positions */
			$h[] = sprintf( __( 'Slipped %d positions over the last 30 days.', 'bwing' ), abs( $item->d30 ) );
		} elseif ( count( $history ) > 7 ) {
			$h[] = __( 'Held a steady position over the last 30 days.', 'bwing' );
		}
		if ( $item->pos && $stats['count'] ) {
			$pct = max( 0.1, round( $item->pos / $stats['count'] * 100, 1 ) );
			/* translators: 1 pct 2 noun */
			$h[] = sprintf( __( 'Ranks in the top %1$s%% of all tracked %2$ss.', 'bwing' ), $pct, $noun );
		}
		if ( $item->peak_pos && $item->peak_pos < $item->pos ) {
			/* translators: %d peak */
			$h[] = sprintf( __( 'Best position recorded: #%d.', 'bwing' ), $item->peak_pos );
		} elseif ( $item->pos && $item->peak_pos === $item->pos ) {
			$h[] = __( 'Currently at its all-time best position.', 'bwing' );
		}
		if ( $item->last_updated ) {
			$days = (int) floor( ( time() - strtotime( $item->last_updated ) ) / DAY_IN_SECONDS );
			/* translators: %s ago */
			$h[] = $days > 730 ? __( 'Not updated in over two years — check compatibility before using.', 'bwing' ) : sprintf( __( 'Last updated %s.', 'bwing' ), BWing_Format::ago( $item->last_updated ) );
		}
		$next = BWing_Format::next_milestone( $item->active_installs );
		/* translators: %s milestone */
		$h[] = sprintf( __( 'Next install milestone: %s.', 'bwing' ), $next );
		$o   = '<section class="bw-panel bw-highlights"><h2>' . esc_html__( 'Highlights', 'bwing' ) . '</h2><ul>';
		foreach ( $h as $line ) {
			$o .= '<li>' . esc_html( $line ) . '</li>';
		}
		return $o . '</ul></section>';
	}

	public static function author( $a ) {
		$items   = $a->items;
		$themes  = array_values( array_filter( $items, function ( $i ) { return 'theme' === $i->type; } ) );
		$plugins = array_values( array_filter( $items, function ( $i ) { return 'plugin' === $i->type; } ) );
		$total   = array_sum( wp_list_pluck( $items, 'active_installs' ) );
		$dls     = array_sum( wp_list_pluck( $items, 'downloaded' ) );
		$o       = '<a class="bw-back" href="' . esc_url( bwing_url( 'authors' ) ) . '">← ' . esc_html__( 'Author leaderboard', 'bwing' ) . '</a>';
		$o      .= '<header class="bw-itemhead bw-itemhead--author">' . BWing_Format::avatar( $a->slug ) . '<div class="bw-itemhead__body"><h1>' . esc_html( $a->name ) . '</h1><p class="bw-itemhead__by">@' . esc_html( $a->slug ) . ' · <a href="' . esc_url( 'https://profiles.wordpress.org/' . $a->slug . '/' ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'WordPress.org profile ↗', 'bwing' ) . '</a></p></div></header>';
		$o      .= '<div class="bw-statgrid">' . BWing_Render::stat( BWing_Format::short( $total, true ), __( 'Total installs', 'bwing' ) ) . BWing_Render::stat( BWing_Format::short( $dls ), __( 'Total downloads', 'bwing' ) ) . BWing_Render::stat( count( $themes ), __( 'Themes', 'bwing' ) ) . BWing_Render::stat( count( $plugins ), __( 'Plugins', 'bwing' ) ) . '</div>';
		foreach ( array( __( 'Themes', 'bwing' ) => $themes, __( 'Plugins', 'bwing' ) => $plugins ) as $label => $set ) {
			if ( ! $set ) {
				continue;
			}
			$o .= BWing_Render::wrap( 'portfolio', BWing_Render::head( $label ) . '<div class="bw-tablewrap"><table class="bw-table"><thead><tr><th>' . esc_html__( 'Name', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Rank', 'bwing' ) . '</th><th>' . esc_html__( 'Rating', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Installs', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Downloads', 'bwing' ) . '</th></tr></thead><tbody>' . implode( '', array_map( function ( $r ) {
				return '<tr><td><a class="bw-cell-item" href="' . esc_url( BWing_Format::item_url( $r->type, $r->slug ) ) . '">' . BWing_Format::thumb( $r, 'bw-thumb bw-thumb--sm bw-thumb--' . $r->type ) . '<span><strong>' . esc_html( $r->name ) . '</strong><small>v' . esc_html( $r->version ) . '</small></span></a></td><td class="bw-r">#' . (int) $r->pos . '</td><td>' . BWing_Format::stars_html( $r->rating, $r->num_ratings ) . '</td><td class="bw-r"><b>' . esc_html( BWing_Format::installs( $r->active_installs ) ) . '</b></td><td class="bw-r">' . esc_html( BWing_Format::short( $r->downloaded ) ) . '</td></tr>';
			}, $set ) ) . '</tbody></table></div>' );
		}
		return '<article class="bw-single bw-single--author">' . $o . '</article>';
	}

	public static function premium( $p ) {
		$type = $p->type;
		$rank = BWing_Data::premium_rank( $p );
		$chg  = (int) $p->live_sites - (int) $p->prev_live_sites;
		$free = $p->free_slug ? BWing_Data::item( $type, $p->free_slug ) : null;
		$o    = '<a class="bw-back" href="' . esc_url( bwing_url( 'theme' === $type ? 'premium-themes' : 'premium-plugins' ) ) . '">← ' . esc_html( 'theme' === $type ? __( 'Premium themes', 'bwing' ) : __( 'Premium plugins', 'bwing' ) ) . '</a>';
		$o   .= '<header class="bw-itemhead">' . BWing_Format::thumb( $p, 'bw-itemhead__img' ) . '<div class="bw-itemhead__body"><div class="bw-itemhead__tags"><span class="bw-tag bw-tag--premium">' . esc_html__( 'Premium', 'bwing' ) . '</span><span class="bw-tag bw-tag--rank">#' . (int) $rank . '</span></div><h1>' . esc_html( $p->name ) . '</h1><p class="bw-itemhead__by">' . esc_html__( 'by', 'bwing' ) . ' ' . esc_html( $p->vendor ) . ' · ' . esc_html( $p->price ) . '</p>';
		$o   .= $p->url ? '<div class="bw-itemhead__actions"><a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( $p->url ) . '" target="_blank" rel="noopener sponsored">' . esc_html__( 'Visit website ↗', 'bwing' ) . '</a></div>' : '';
		$o   .= '</div></header>';
		$o   .= '<div class="bw-statgrid">' . BWing_Render::stat( number_format_i18n( $p->live_sites ), __( 'Live sites', 'bwing' ) ) . BWing_Render::stat( '#' . $rank, __( 'Premium rank', 'bwing' ) ) . BWing_Render::stat( BWing_Format::signed( $chg ), __( 'Change since last check', 'bwing' ), '', $chg >= 0 ? 'up' : 'down' ) . BWing_Render::stat( $p->price ? $p->price : '—', __( 'Starting price', 'bwing' ) ) . '</div>';
		if ( $free ) {
			$o .= '<a class="bw-callout" href="' . esc_url( BWing_Format::item_url( $type, $free->slug ) ) . '"><span>↗</span><div>' . esc_html__( 'Free version:', 'bwing' ) . ' <strong>' . esc_html( $free->name ) . '</strong> · ' . esc_html( BWing_Format::installs( $free->active_installs ) ) . ' ' . esc_html__( 'active installs', 'bwing' ) . '</div><em>' . esc_html__( 'View stats →', 'bwing' ) . '</em></a>';
		}
		$o .= '<section class="bw-panel"><h2>' . esc_html__( 'About', 'bwing' ) . '</h2><div class="bw-prose">' . wp_kses_post( wpautop( $p->description ) ) . '</div></section>';
		$others = array_filter( BWing_Data::premium( $type, 7 ), function ( $x ) use ( $p ) { return $x->id !== $p->id; } );
		if ( $others ) {
			$o .= BWing_Render::wrap( 'similar', BWing_Render::head( __( 'Other premium leaders', 'bwing' ) ) . '<div class="bw-cards">' . implode( '', array_map( function ( $x ) {
				return '<a class="bw-card bw-card--item" href="' . esc_url( BWing_Format::premium_url( $x->type, $x->slug ) ) . '">' . BWing_Format::thumb( $x, 'bw-thumb' ) . '<span class="bw-card__body"><strong>' . esc_html( $x->name ) . '</strong><small>' . esc_html( BWing_Format::short( $x->live_sites ) ) . ' ' . esc_html__( 'live sites', 'bwing' ) . '</small></span></a>';
			}, array_slice( $others, 0, 6 ) ) ) . '</div>' );
		}
		$disc = BWing_Settings::get( 'single', 'disclosure' );
		return '<article class="bw-single bw-single--premium">' . $o . ( $disc ? '<p class="bw-disclosure">' . esc_html( $disc ) . '</p>' : '' ) . '</article>';
	}
}
