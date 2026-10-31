<?php
/**
 * Section shortcodes. Each one reads its defaults from the matching dashboard section,
 * and every attribute can override them (e.g. [bwing_top type="plugin" count="10" title="..."]).
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-bwing-render.php';
require_once __DIR__ . '/class-bwing-single.php';

class BWing_Shortcodes {

	/** Shortcode tag => [method, dashboard section, description]. */
	public static function map() {
		return array(
			'bwing_hero'             => array( 'hero', 'hero', __( 'Hero with headline, live search, buttons and counters.', 'bwing' ) ),
			'bwing_search'           => array( 'search', 'hero', __( 'Live search box for themes, plugins and authors.', 'bwing' ) ),
			'bwing_counters'         => array( 'counters', 'hero', __( 'Stat counters (themes, plugins, authors, snapshots).', 'bwing' ) ),
			'bwing_trending'         => array( 'trending', 'trending', __( 'Biggest moves: trending up / down. type="both|theme|plugin"', 'bwing' ) ),
			'bwing_featured'         => array( 'featured', 'featured', __( 'Featured themes & plugins: pinned companies plus daily random picks. type="both|theme|plugin"', 'bwing' ) ),
			'bwing_tools'            => array( 'tools', 'tools', __( 'AI tools directory (product finder) with search, categories and filters.', 'bwing' ) ),
			'bwing_submit_tool'      => array( 'submit_tool', 'tools', __( 'Front-end form to submit an AI tool for review (requires login).', 'bwing' ) ),
			'bwing_login'            => array( 'login', 'tools', __( 'Front-end log in form.', 'bwing' ) ),
			'bwing_register'         => array( 'register_form', 'tools', __( 'Front-end registration form.', 'bwing' ) ),
			'bwing_account'          => array( 'account', 'tools', __( 'Account page with the member\'s submissions and their status.', 'bwing' ) ),
			'bwing_notable'          => array( 'notable', 'notable', __( 'Notable moves feed: bracket crossings, top-10, anomalies.', 'bwing' ) ),
			'bwing_top'              => array( 'top', 'top_themes', __( 'Top free items list. type="theme|plugin" count="20"', 'bwing' ) ),
			'bwing_top_premium'      => array( 'top_premium', 'premium_themes', __( 'Top premium items. type="theme|plugin"', 'bwing' ) ),
			'bwing_top_authors'      => array( 'top_authors', 'theme_authors', __( 'Top authors. type="theme|plugin|all"', 'bwing' ) ),
			'bwing_mini_top'         => array( 'mini_top', 'top_themes', __( 'Compact top-5 list (sidebars, menus). type="theme|plugin|author"', 'bwing' ) ),
			'bwing_text'             => array( 'text', 'about', __( 'Text block. section="about|ecosystem"', 'bwing' ) ),
			'bwing_beyond'           => array( 'beyond', 'beyond', __( 'Feature cards grid.', 'bwing' ) ),
			'bwing_faq'              => array( 'faq', 'faq', __( 'FAQ accordion. section="home|theme|plugin|detector|hosting"', 'bwing' ) ),
			'bwing_cta'              => array( 'cta', 'cta', __( 'Closing call-to-action band.', 'bwing' ) ),
			'bwing_newsletter'       => array( 'newsletter', 'newsletter', __( 'Newsletter signup form. style="card|inline"', 'bwing' ) ),
			'bwing_subnav'           => array( 'subnav', 'ranking', __( 'Section tabs. current="themes|plugins|authors|stats|premium-themes|..."', 'bwing' ) ),
			'bwing_ranking'          => array( 'ranking', 'ranking', __( 'Full ranking table with filters, sort, search and pagination. type="theme|plugin"', 'bwing' ) ),
			'bwing_premium_ranking'  => array( 'premium_ranking', 'premium_themes', __( 'Full premium ranking table. type="theme|plugin"', 'bwing' ) ),
			'bwing_authors'          => array( 'authors', 'authors', __( 'Authors leaderboard with sorting and search.', 'bwing' ) ),
			'bwing_stats'            => array( 'stats', 'stats', __( 'Statistics dashboard. type="theme|plugin|author"', 'bwing' ) ),
			'bwing_fastest'          => array( 'fastest', 'fastest', __( 'Fastest themes Lighthouse table.', 'bwing' ) ),
			'bwing_detector'         => array( 'detector', 'detector', __( 'Theme & plugin detector tool.', 'bwing' ) ),
			'bwing_most_detected'    => array( 'most_detected', 'detector', __( 'Most detected themes or plugins. type="theme|plugin"', 'bwing' ) ),
			'bwing_hosting_checker'  => array( 'hosting', 'hosting', __( 'Hosting checker tool.', 'bwing' ) ),
			'bwing_item'             => array( 'item', 'single', __( 'Full single item page. type="theme|plugin" slug="astra"', 'bwing' ) ),
			'bwing_author'           => array( 'author', 'authors', __( 'Single author profile. slug="automattic"', 'bwing' ) ),
		);
	}

	public static function register() {
		foreach ( self::map() as $tag => $def ) {
			add_shortcode( $tag, array( __CLASS__, $def[0] ) );
		}
	}

	private static function atts( $section, $atts, $extra = array() ) {
		bwing_enqueue();
		$defaults = array_merge( BWing_Settings::section( $section ), $extra );
		return shortcode_atts( $defaults, (array) $atts );
	}

	/* ------------------------------------------------------------------ */
	/* Home sections                                                       */
	/* ------------------------------------------------------------------ */

	public static function hero( $atts ) {
		$a    = self::atts( 'hero', $atts );
		$btns = '';
		foreach ( BWing_Settings::rows( $a['buttons'] ) as $i => $b ) {
			if ( empty( $b[1] ) ) {
				continue;
			}
			$btns .= '<a class="bw-btn ' . ( $i ? 'bw-btn--ghost' : 'bw-btn--primary' ) . '" href="' . esc_url( BWing_Render::url( $b[1] ) ) . '">' . esc_html( $b[0] ) . '</a>';
		}
		$html  = '<div class="bw-hero__glow" aria-hidden="true"></div><div class="bw-hero__inner">';
		$html .= $a['eyebrow'] ? '<span class="bw-eyebrow"><i class="bw-pulse"></i>' . esc_html( $a['eyebrow'] ) . '</span>' : '';
		$html .= '<h1 class="bw-hero__title">' . esc_html( $a['title'] ) . '</h1>';
		$html .= $a['subtitle'] ? '<p class="bw-hero__sub">' . esc_html( $a['subtitle'] ) . '</p>' : '';
		$html .= BWing_Render::search_form( $a['placeholder'], 'bw-search--hero' );
		$html .= $btns ? '<div class="bw-hero__btns">' . $btns . '</div>' : '';
		$html .= ! empty( $a['show_stats'] ) ? self::counters( array() ) : '';
		$html .= '</div>';
		return BWing_Render::wrap( 'hero', $html, 'bw-hero' );
	}

	public static function search( $atts ) {
		$a = self::atts( 'hero', $atts );
		return BWing_Render::search_form( $a['placeholder'] );
	}

	public static function counters( $atts ) {
		bwing_enqueue();
		$c = BWing_Data::hero_counts();
		return '<div class="bw-counters">'
			. BWing_Render::stat( number_format_i18n( $c['themes'] ), __( 'Themes tracked', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( $c['plugins'] ), __( 'Plugins tracked', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( $c['authors'] ), __( 'Authors', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( $c['days'] ), __( 'Daily snapshots', 'bwing' ) )
			. '</div>';
	}

	public static function trending( $atts ) {
		$a     = self::atts( 'trending', $atts, array( 'type' => 'both' ) );
		$types = 'both' === $a['type'] ? array( 'theme', 'plugin' ) : array( $a['type'] );
		$cols  = '';
		foreach ( $types as $type ) {
			$up   = BWing_Data::movers( $type, (int) $a['days'], (int) $a['min_installs'], (int) $a['count'], 'up' );
			$down = BWing_Data::movers( $type, (int) $a['days'], (int) $a['min_installs'], (int) $a['count'], 'down' );
			$label = 'theme' === $type ? __( 'Themes', 'bwing' ) : __( 'Plugins', 'bwing' );
			$col   = '<div class="bw-movers bw-movers--' . $type . '"><div class="bw-movers__head"><h3 class="bw-movers__title">' . esc_html( $label ) . '</h3>'
				. '<a class="bw-movers__all" href="' . esc_url( bwing_url( 'theme' === $type ? 'themes' : 'plugins' ) ) . '">' . esc_html__( 'Full ranking', 'bwing' ) . ' →</a></div><div class="bw-movers__grid">';
			foreach ( array( 'up' => array( $up, __( 'Climbing', 'bwing' ) ), 'down' => array( $down, __( 'Falling', 'bwing' ) ) ) as $dir => $set ) {
				$col .= '<div class="bw-movers__list bw-movers__list--' . $dir . '"><h4 class="bw-movers__label"><span aria-hidden="true">' . ( 'up' === $dir ? '▲' : '▼' ) . '</span>' . esc_html( $set[1] ) . '</h4>';
				if ( $set[0] ) {
					$col .= '<ol class="bw-movers__items">';
					foreach ( $set[0] as $item ) {
						$col .= '<li>' . BWing_Render::mover( $item, $type ) . '</li>';
					}
					$col .= '</ol>';
				} else {
					$col .= '<p class="bw-muted bw-movers__empty">' . esc_html__( 'No movement in this window.', 'bwing' ) . '</p>';
				}
				$col .= '</div>';
			}
			$cols .= $col . '</div></div>';
		}
		return BWing_Render::wrap( 'trending', BWing_Render::head( $a['title'], $a['subtitle'] ) . '<div class="bw-movers-wrap bw-movers-wrap--' . count( $types ) . '">' . $cols . '</div>' );
	}

	public static function featured( $atts ) {
		$a     = self::atts( 'featured', $atts, array( 'type' => 'both' ) );
		$types = 'both' === $a['type'] ? array( 'theme', 'plugin' ) : array( 'plugin' === $a['type'] ? 'plugin' : 'theme' );
		$cols  = '';
		foreach ( $types as $type ) {
			$rows = BWing_Data::featured( $type, $a );
			$col  = '<div class="bw-feat bw-feat--' . $type . '"><div class="bw-movers__head"><h3 class="bw-movers__title">' . esc_html( 'theme' === $type ? __( 'Themes', 'bwing' ) : __( 'Plugins', 'bwing' ) ) . '</h3>'
				. '<a class="bw-movers__all" href="' . esc_url( bwing_url( 'theme' === $type ? 'themes' : 'plugins' ) ) . '">' . esc_html__( 'Full ranking', 'bwing' ) . ' →</a></div>';
			if ( $rows ) {
				$col .= '<ul class="bw-feat__items">';
				foreach ( $rows as $item ) {
					$col .= '<li><a class="bw-feat__item' . ( $item->pinned ? ' is-pinned' : '' ) . '" href="' . esc_url( BWing_Format::item_url( $type, $item->slug ) ) . '">'
						. BWing_Format::thumb( $item, 'bw-thumb bw-thumb--' . $type )
						. '<span class="bw-feat__body"><strong title="' . esc_attr( $item->name ) . '">' . esc_html( $item->name ) . '</strong>'
						. '<small>' . esc_html__( 'by', 'bwing' ) . ' ' . esc_html( $item->author_name ? $item->author_name : $item->author ) . '</small>'
						. '<span class="bw-feat__meta"><b>' . esc_html( BWing_Format::installs( $item->active_installs ) ) . '</b> ' . esc_html__( 'installs', 'bwing' ) . ( $item->num_ratings ? ' · ' . esc_html( BWing_Format::stars( $item->rating ) ) . '★' : '' ) . '</span></span>'
						. '<span class="bw-feat__badge">' . ( $item->pinned ? esc_html__( 'Featured', 'bwing' ) : esc_html__( "Today's pick", 'bwing' ) ) . '</span></a></li>';
				}
				$col .= '</ul>';
			} else {
				$col .= BWing_Render::empty_state( __( 'No data yet. Run a sync from the BWing dashboard.', 'bwing' ) );
			}
			$cols .= $col . '</div>';
		}
		return BWing_Render::wrap( 'featured', BWing_Render::head( $a['title'], $a['subtitle'] ) . '<div class="bw-feat-wrap bw-feat-wrap--' . count( $types ) . '">' . $cols . '</div>' );
	}

	public static function tools( $atts ) {
		return BWing_Tools::directory( self::atts( 'tools', $atts ) );
	}

	public static function submit_tool( $atts ) {
		return BWing_Tools::submit_form( self::atts( 'tools', $atts ) );
	}

	public static function login( $atts ) {
		bwing_enqueue();
		return BWing_Account::login_form();
	}

	public static function register_form( $atts ) {
		bwing_enqueue();
		return BWing_Account::register_form();
	}

	public static function account( $atts ) {
		bwing_enqueue();
		return BWing_Account::account();
	}

	public static function notable( $atts ) {
		$a      = self::atts( 'notable', $atts );
		$events = BWing_Data::notable( (int) $a['days'], (int) $a['count'] );
		$labels = array(
			'bracket'  => __( 'Bracket up', 'bwing' ),
			'top10'    => __( 'Top 10', 'bwing' ),
			'top10out' => __( 'Left top 10', 'bwing' ),
			'anomaly'  => __( '⚠ Anomaly', 'bwing' ),
		);
		if ( ! $events ) {
			$body = BWing_Render::empty_state( __( 'No notable moves yet. They appear once there is enough daily history.', 'bwing' ) );
		} else {
			$body = '<div class="bw-notable">';
			foreach ( $events as $e ) {
				$it    = $e['item'];
				$body .= sprintf(
					'<a class="bw-note bw-note--%1$s" href="%2$s"><span class="bw-note__badge">%3$s</span><span class="bw-note__item">%4$s<span><strong>%5$s</strong><small>%6$s</small></span></span><span class="bw-note__text">%7$s</span></a>',
					esc_attr( $e['kind'] ),
					esc_url( BWing_Format::item_url( $it->type, $it->slug ) ),
					esc_html( $labels[ $e['kind'] ] ),
					BWing_Format::thumb( $it, 'bw-thumb bw-thumb--sm bw-thumb--' . $it->type ),
					esc_html( $it->name ),
					esc_html( 'theme' === $it->type ? __( 'Theme', 'bwing' ) : __( 'Plugin', 'bwing' ) ),
					esc_html( $e['text'] )
				);
			}
			$body .= '</div>';
		}
		return BWing_Render::wrap( 'notable', BWing_Render::head( $a['title'], $a['subtitle'] ) . $body );
	}

	public static function top( $atts ) {
		$type    = isset( $atts['type'] ) && 'plugin' === $atts['type'] ? 'plugin' : 'theme';
		$a       = self::atts( 'theme' === $type ? 'top_themes' : 'top_plugins', $atts, array( 'type' => $type ) );
		$items   = BWing_Data::top_items( $type, (int) $a['count'] );
		$show_dl = ! in_array( (string) $a['show_downloads'], array( '', '0', 'no', 'false' ), true );
		if ( $show_dl ) {
			$items = BWing_Data::attach_downloads( $items );
		}
		$list = '';
		foreach ( $items as $i => $item ) {
			$extra = $show_dl ? BWing_Render::downloads_strip( $item ) : '';
			$list .= BWing_Render::rank_row( $i + 1, $item, $type, '<b>' . esc_html( BWing_Format::installs( $item->active_installs ) ) . '</b>' . BWing_Format::stars_html( $item->rating ), $extra );
		}
		if ( $list && $show_dl ) {
			$list = BWing_Render::downloads_head( 'theme' === $type ? __( 'Theme', 'bwing' ) : __( 'Plugin', 'bwing' ) ) . $list;
		}
		$body = $list ? '<ol class="bw-ranklist' . ( $show_dl ? ' bw-ranklist--dl' : '' ) . '">' . $list . '</ol>' : BWing_Render::empty_state( __( 'No data yet. Run a sync from the BWing dashboard.', 'bwing' ) );
		return BWing_Render::wrap( 'top-' . $type, BWing_Render::head( $a['title'], $a['subtitle'], 'h2', array( 'url' => $a['link_url'], 'text' => $a['link_text'] ) ) . $body );
	}

	public static function top_premium( $atts ) {
		$type  = isset( $atts['type'] ) && 'plugin' === $atts['type'] ? 'plugin' : 'theme';
		$a     = self::atts( 'theme' === $type ? 'premium_themes' : 'premium_plugins', $atts, array( 'type' => $type ) );
		$items = BWing_Data::premium( $type, (int) $a['count'] );
		$list  = '';
		foreach ( $items as $i => $p ) {
			$list .= sprintf(
				'<li class="bw-rank"><span class="bw-rank__n">%1$d</span><a class="bw-rank__link" href="%2$s">%3$s<span class="bw-rank__body"><strong>%4$s</strong><small>%5$s</small></span></a><span class="bw-rank__meta"><b>%6$s</b><small>%7$s</small></span></li>',
				$i + 1,
				esc_url( BWing_Format::premium_url( $type, $p->slug ) ),
				BWing_Format::thumb( $p, 'bw-thumb bw-thumb--logo' ),
				esc_html( $p->name ),
				esc_html( $p->vendor ),
				esc_html( BWing_Format::short( $p->live_sites ) ),
				esc_html__( 'live sites', 'bwing' )
			);
		}
		$body = $list ? '<ol class="bw-ranklist">' . $list . '</ol>' : BWing_Render::empty_state( __( 'No premium items yet. Add them in BWing → Premium.', 'bwing' ) );
		return BWing_Render::wrap( 'premium-' . $type, BWing_Render::head( $a['title'], $a['subtitle'], 'h2', array( 'url' => $a['link_url'], 'text' => $a['link_text'] ) ) . $body );
	}

	public static function top_authors( $atts ) {
		$type = isset( $atts['type'] ) ? $atts['type'] : 'theme';
		$sec  = 'plugin' === $type ? 'plugin_authors' : 'theme_authors';
		$a    = self::atts( $sec, $atts, array( 'type' => $type ) );
		$res  = BWing_Data::authors( array( 'type' => $type, 'per_page' => (int) $a['count'], 'sort' => 'total' ) );
		$list = '';
		foreach ( $res['rows'] as $i => $r ) {
			$count = 'plugin' === $type ? $r->plugins : ( 'theme' === $type ? $r->themes : $r->plugins + $r->themes );
			$list .= sprintf(
				'<li class="bw-rank"><span class="bw-rank__n">%1$d</span><a class="bw-rank__link" href="%2$s">%3$s<span class="bw-rank__body"><strong>%4$s</strong><small>@%5$s · %6$s</small></span></a><span class="bw-rank__meta"><b>%7$s</b></span></li>',
				$i + 1,
				esc_url( BWing_Format::author_url( $r->author ) ),
				BWing_Format::avatar( $r->author, 64, BWing_Format::author_image( $r, 'theme' === $type ? 'brand' : 'photo' ) ),
				esc_html( $r->author_name ? $r->author_name : $r->author ),
				esc_html( $r->author ),
				/* translators: %d items */
				esc_html( sprintf( _n( '%d item', '%d items', $count, 'bwing' ), $count ) ),
				esc_html( BWing_Format::short( $r->total, true ) )
			);
		}
		$body = $list ? '<ol class="bw-ranklist">' . $list . '</ol>' : BWing_Render::empty_state( __( 'No authors yet.', 'bwing' ) );
		return BWing_Render::wrap( 'authors-' . $type, BWing_Render::head( $a['title'], $a['subtitle'], 'h2', array( 'url' => '/authors/', 'text' => __( 'Author leaderboard →', 'bwing' ) ) ) . $body );
	}

	public static function mini_top( $atts ) {
		bwing_enqueue();
		$a = shortcode_atts( array( 'type' => 'theme', 'count' => 5, 'title' => '' ), $atts );
		$o = '<ol class="bw-mini">';
		if ( 'author' === $a['type'] ) {
			foreach ( BWing_Data::authors( array( 'per_page' => (int) $a['count'] ) )['rows'] as $i => $r ) {
				$o .= '<li><a href="' . esc_url( BWing_Format::author_url( $r->author ) ) . '"><span class="bw-mini__n">' . ( $i + 1 ) . '</span>' . BWing_Format::avatar( $r->author, 64, BWing_Format::author_image( $r ) ) . '<span class="bw-mini__name">' . esc_html( $r->author_name ? $r->author_name : $r->author ) . '</span><span class="bw-mini__v">' . esc_html( BWing_Format::short( $r->total, true ) ) . '</span></a></li>';
			}
		} else {
			foreach ( BWing_Data::top_items( $a['type'], (int) $a['count'] ) as $i => $it ) {
				$o .= '<li><a href="' . esc_url( BWing_Format::item_url( $it->type, $it->slug ) ) . '"><span class="bw-mini__n">' . ( $i + 1 ) . '</span>' . BWing_Format::thumb( $it, 'bw-thumb bw-thumb--xs bw-thumb--' . $it->type ) . '<span class="bw-mini__name">' . esc_html( $it->name ) . '</span><span class="bw-mini__v">' . esc_html( BWing_Format::installs( $it->active_installs ) ) . '</span></a></li>';
			}
		}
		$o .= '</ol>';
		return ( $a['title'] ? '<h4 class="bw-mini__title">' . esc_html( $a['title'] ) . '</h4>' : '' ) . $o;
	}

	public static function text( $atts ) {
		$section = isset( $atts['section'] ) && 'ecosystem' === $atts['section'] ? 'ecosystem' : 'about';
		$a       = self::atts( $section, $atts );
		return BWing_Render::wrap( 'text-' . $section, '<div class="bw-prose">' . BWing_Render::head( $a['title'] ) . wp_kses_post( wpautop( $a['content'] ) ) . '</div>' );
	}

	public static function beyond( $atts ) {
		$a     = self::atts( 'beyond', $atts );
		$cards = '';
		$icons = array( '◆', '▲', '⚡', '⌕', '☁', '★', '●', '■' );
		foreach ( BWing_Settings::rows( $a['cards'] ) as $i => $c ) {
			$cards .= '<a class="bw-feature" href="' . esc_url( BWing_Render::url( isset( $c[2] ) ? $c[2] : '#' ) ) . '"><span class="bw-feature__icon">' . esc_html( $icons[ $i % count( $icons ) ] ) . '</span><strong>' . esc_html( $c[0] ) . '</strong><span>' . esc_html( isset( $c[1] ) ? $c[1] : '' ) . '</span><em>' . esc_html__( 'Explore →', 'bwing' ) . '</em></a>';
		}
		return BWing_Render::wrap( 'beyond', BWing_Render::head( $a['title'], $a['subtitle'] ) . '<div class="bw-features">' . $cards . '</div>' );
	}

	public static function faq( $atts ) {
		$a   = self::atts( 'faq', $atts, array( 'section' => 'home' ) );
		$map = array(
			'home'     => BWing_Settings::get( 'faq', 'items' ),
			'theme'    => BWing_Settings::get( 'faq', 'theme_items' ),
			'plugin'   => BWing_Settings::get( 'faq', 'plugin_items' ),
			'detector' => BWing_Settings::get( 'detector', 'faq' ),
			'hosting'  => BWing_Settings::get( 'hosting', 'faq' ),
		);
		$raw = isset( $map[ $a['section'] ] ) ? $map[ $a['section'] ] : $map['home'];
		return BWing_Render::wrap( 'faq', BWing_Render::faq( BWing_Settings::rows( $raw ), $a['title'] ) );
	}

	public static function cta( $atts ) {
		$a = self::atts( 'cta', $atts );
		return BWing_Render::wrap( 'cta', '<div class="bw-cta"><div><h2>' . esc_html( $a['title'] ) . '</h2><p>' . esc_html( $a['text'] ) . '</p></div><div class="bw-cta__actions"><a class="bw-btn bw-btn--light" href="' . esc_url( BWing_Render::url( $a['button_url'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $a['button_label'] ) . '</a><a class="bw-btn bw-btn--ghost-light" href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : bwing_url( 'privacy-policy' ) ) . '">' . esc_html__( 'Privacy policy', 'bwing' ) . '</a></div></div>' );
	}

	public static function newsletter( $atts ) {
		$a = self::atts( 'newsletter', $atts, array( 'style' => 'card' ) );
		return '<div class="bw-newsletter bw-newsletter--' . esc_attr( $a['style'] ) . '">'
			. ( $a['title'] ? '<h3 class="bw-newsletter__title">' . esc_html( $a['title'] ) . '</h3>' : '' )
			. ( $a['text'] ? '<p class="bw-newsletter__text">' . esc_html( $a['text'] ) . '</p>' : '' )
			. '<form class="bw-newsletter__form" data-bw-newsletter data-success="' . esc_attr( $a['success'] ) . '">'
			. '<input type="email" name="email" required placeholder="' . esc_attr( $a['placeholder'] ) . '" aria-label="' . esc_attr__( 'Email address', 'bwing' ) . '">'
			. '<input type="text" name="website" class="bw-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
			. '<button type="submit" class="bw-btn bw-btn--primary">' . esc_html( $a['button'] ) . '</button>'
			. '</form><p class="bw-newsletter__msg" role="status"></p></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Listing pages                                                       */
	/* ------------------------------------------------------------------ */

	public static function subnav( $atts ) {
		bwing_enqueue();
		$a    = shortcode_atts( array( 'current' => '', 'group' => 'themes' ), $atts );
		$sets = array(
			'themes'  => array(
				'themes'                   => __( 'WordPress.org popular', 'bwing' ),
				'premium-themes'           => __( 'Premium themes', 'bwing' ),
				'fastest-wordpress-themes' => __( 'Fastest themes', 'bwing' ),
				'most-detected-themes'     => __( 'Real-world usage', 'bwing' ),
				'wordpress-theme-stats'    => __( 'Theme stats', 'bwing' ),
			),
			'plugins' => array(
				'plugins'                => __( 'WordPress.org popular', 'bwing' ),
				'premium-plugins'        => __( 'Premium plugins', 'bwing' ),
				'most-detected-plugins'  => __( 'Real-world usage', 'bwing' ),
				'wordpress-plugin-stats' => __( 'Plugin ecosystem', 'bwing' ),
			),
			'authors' => array(
				'authors'                => __( 'Leaderboard', 'bwing' ),
				'wordpress-author-stats' => __( 'Author stats', 'bwing' ),
			),
			'tools'   => array(
				'theme-detector'  => __( 'Theme detector', 'bwing' ),
				'hosting-checker' => __( 'Hosting checker', 'bwing' ),
			),
		);
		$set = isset( $sets[ $a['group'] ] ) ? $sets[ $a['group'] ] : $sets['themes'];
		$o   = '<nav class="bw-subnav" aria-label="' . esc_attr__( 'Section navigation', 'bwing' ) . '">';
		foreach ( array( 'themes' => __( 'Themes', 'bwing' ), 'plugins' => __( 'Plugins', 'bwing' ), 'authors' => __( 'Authors', 'bwing' ), 'tools' => __( 'Tools', 'bwing' ) ) as $g => $label ) {
			$first = key( $sets[ $g ] );
			$o    .= '<a class="bw-subnav__group' . ( $g === $a['group'] ? ' is-active' : '' ) . '" href="' . esc_url( bwing_url( $first ) ) . '">' . esc_html( $label ) . '</a>';
		}
		$o .= '</nav><nav class="bw-tabs">';
		foreach ( $set as $slug => $label ) {
			$o .= '<a class="bw-tabs__tab' . ( $slug === $a['current'] ? ' is-active' : '' ) . '" href="' . esc_url( bwing_url( $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return $o . '</nav>';
	}

	private static function qv( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function ranking( $atts ) {
		$type = isset( $atts['type'] ) && 'plugin' === $atts['type'] ? 'plugin' : 'theme';
		$a    = self::atts( 'ranking', $atts, array( 'type' => $type, 'show_title' => 'yes', 'show_movers' => 'yes', 'show_faq' => 'yes' ) );
		$args = array(
			'type'     => $type,
			'page'     => max( 1, absint( self::qv( 'pg', 1 ) ) ),
			'per_page' => (int) BWing_Settings::get( 'general', 'per_page', 50 ),
			'sort'     => self::qv( 'bw_sort', 'popular' ),
			's'        => self::qv( 'bw_s' ),
			'min'      => absint( self::qv( 'bw_min', 0 ) ),
			'kind'     => self::qv( 'bw_kind' ),
		);
		$res   = BWing_Data::ranking( $args );
		$stats = BWing_Data::stats( $type );
		$label = 'theme' === $type ? __( 'Theme', 'bwing' ) : __( 'Plugin', 'bwing' );
		$o     = '';

		if ( 'yes' === $a['show_title'] ) {
			$o .= '<header class="bw-pagehead"><h1>' . esc_html( $a[ $type . '_title' ] ) . '</h1><p>' . esc_html( $a[ $type . '_intro' ] ) . '</p></header>';
		}
		if ( 'yes' === $a['show_movers'] ) {
			$o .= self::trending( array( 'type' => $type, 'title' => __( 'Biggest moves this week', 'bwing' ), 'subtitle' => '' ) );
		}

		$latest = BWing_Data::latest_date();
		$prev   = BWing_Data::past_date( 1 );
		$o     .= '<p class="bw-metaline"><span class="bw-dot"></span>' . sprintf(
			/* translators: 1 date 2 prev date 3 count */
			esc_html__( 'Ranking for %1$s · compared with %2$s · %3$s tracked', 'bwing' ),
			'<b>' . esc_html( $latest ? $latest : '—' ) . '</b>',
			esc_html( $prev ? $prev : '—' ),
			esc_html( number_format_i18n( $stats['count'] ) )
		) . '</p>';

		$o .= '<div class="bw-statgrid">'
			. BWing_Render::stat( number_format_i18n( $stats['count'] ), 'theme' === $type ? __( 'Themes', 'bwing' ) : __( 'Plugins', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $stats['installs'] ), __( 'Active installs', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $stats['downloads'] ), __( 'Total downloads', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( $stats['authors'] ), __( 'Authors', 'bwing' ) )
			. '</div>';

		// Filters.
		$base = remove_query_arg( array( 'pg', 'bw_sort', 'bw_s', 'bw_min', 'bw_kind' ) );
		$o   .= '<form class="bw-filters" method="get" action="' . esc_url( $base ) . '">';
		if ( 'theme' === $type ) {
			$o .= '<div class="bw-seg bw-seg--links">';
			foreach ( array( '' => __( 'All', 'bwing' ), 'block' => __( 'Block', 'bwing' ), 'classic' => __( 'Classic', 'bwing' ) ) as $k => $l ) {
				$o .= '<a class="bw-seg__btn' . ( $args['kind'] === $k ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'bw_kind' => $k, 'pg' => false ) ) ) . '">' . esc_html( $l ) . '</a>';
			}
			$o .= '</div><input type="hidden" name="bw_kind" value="' . esc_attr( $args['kind'] ) . '">';
		}
		$o .= '<label class="bw-field"><span>' . esc_html__( 'Installs', 'bwing' ) . '</span><select name="bw_min" data-bw-autosubmit>';
		foreach ( array( 0 => __( 'Any', 'bwing' ), 1000000 => '1M+', 500000 => '500K+', 100000 => '100K+', 50000 => '50K+', 10000 => '10K+', 1000 => '1K+' ) as $k => $l ) {
			$o .= '<option value="' . (int) $k . '"' . selected( $args['min'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$o .= '</select></label><label class="bw-field"><span>' . esc_html__( 'Sort', 'bwing' ) . '</span><select name="bw_sort" data-bw-autosubmit>';
		foreach ( array( 'popular' => __( 'Most popular', 'bwing' ), 'installs' => __( 'Active installs', 'bwing' ), 'downloads' => __( 'All-time downloads', 'bwing' ), 'rating' => __( 'Rating', 'bwing' ), 'newest' => __( 'Newest in directory', 'bwing' ), 'updated' => __( 'Recently updated', 'bwing' ) ) as $k => $l ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $args['sort'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		/* translators: %s type */
		$o .= '</select></label><label class="bw-field bw-field--grow"><span class="screen-reader-text">' . esc_html__( 'Search', 'bwing' ) . '</span><input type="search" name="bw_s" value="' . esc_attr( $args['s'] ) . '" placeholder="' . esc_attr( sprintf( __( 'Filter %ss…', 'bwing' ), strtolower( $label ) ) ) . '"></label><button class="bw-btn bw-btn--primary bw-btn--sm" type="submit">' . esc_html__( 'Apply', 'bwing' ) . '</button></form>';

		// Table.
		if ( ! $res['rows'] ) {
			$o .= BWing_Render::empty_state( __( 'Nothing matches these filters.', 'bwing' ) );
		} else {
			$o .= '<div class="bw-tablewrap"><table class="bw-table bw-table--ranking"><thead><tr><th>#</th><th>' . esc_html( $label ) . '</th><th class="bw-c">' . esc_html__( '1d', 'bwing' ) . '</th><th class="bw-c">' . esc_html__( '7d', 'bwing' ) . '</th><th class="bw-c">' . esc_html__( '30d', 'bwing' ) . '</th>' . ( 'theme' === $type ? '<th>' . esc_html__( 'Type', 'bwing' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Rating', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Installs', 'bwing' ) . '</th><th>' . esc_html__( 'Next milestone', 'bwing' ) . '</th></tr></thead><tbody>';
			foreach ( $res['rows'] as $i => $r ) {
				$num = 'popular' === $args['sort'] && ! $args['s'] && ! $args['min'] && ! $args['kind'] ? (int) $r->pos : ( $res['page'] - 1 ) * $res['per'] + $i + 1;
				$o  .= '<tr>'
					. '<td class="bw-num">' . esc_html( $num ? $num : '—' ) . '</td>'
					. '<td><a class="bw-cell-item" href="' . esc_url( BWing_Format::item_url( $type, $r->slug ) ) . '">' . BWing_Format::thumb( $r, 'bw-thumb bw-thumb--sm bw-thumb--' . $type ) . '<span><strong>' . esc_html( $r->name ) . '</strong><small>' . esc_html( $r->author_name ) . ' · ' . esc_html__( 'Updated', 'bwing' ) . ' ' . esc_html( $r->last_updated ? mysql2date( 'M j, Y', $r->last_updated ) : '—' ) . '</small></span></a></td>'
					. '<td class="bw-c">' . BWing_Format::trend( $r->d1 ) . '</td>'
					. '<td class="bw-c">' . BWing_Format::trend( $r->d7 ) . '</td>'
					. '<td class="bw-c">' . BWing_Format::trend( $r->d30 ) . '</td>'
					. ( 'theme' === $type ? '<td><span class="bw-tag' . ( $r->is_block ? ' bw-tag--block' : '' ) . '">' . esc_html( $r->is_block ? __( 'Block', 'bwing' ) : __( 'Classic', 'bwing' ) ) . '</span></td>' : '' )
					. '<td>' . BWing_Format::stars_html( $r->rating, $r->num_ratings ) . '</td>'
					. '<td class="bw-r"><b>' . esc_html( BWing_Format::installs( $r->active_installs ) ) . '</b></td>'
					. '<td><small class="bw-muted">' . esc_html( BWing_Format::next_milestone( $r->active_installs ) ) . '</small></td>'
					. '</tr>';
			}
			$o .= '</tbody></table></div>' . BWing_Render::pagination( $res['page'], $res['pages'] );
		}

		if ( $a['about'] ) {
			$o .= '<div class="bw-prose bw-about">' . wp_kses_post( wpautop( $a['about'] ) ) . '</div>';
		}
		if ( 'yes' === $a['show_faq'] ) {
			$o .= self::faq( array( 'section' => $type ) );
		}
		return '<div class="bw-ranking bw-ranking--' . esc_attr( $type ) . '">' . $o . '</div>';
	}

	public static function premium_ranking( $atts ) {
		$type = isset( $atts['type'] ) && 'plugin' === $atts['type'] ? 'plugin' : 'theme';
		$a    = self::atts( 'theme' === $type ? 'premium_themes' : 'premium_plugins', $atts, array( 'type' => $type, 'show_title' => 'yes' ) );
		$rows = BWing_Data::premium( $type, 500 );
		$o    = 'yes' === $a['show_title'] ? '<header class="bw-pagehead"><h1>' . esc_html( $a['title'] ) . '</h1><p>' . esc_html( $a['intro'] ) . '</p></header>' : '';
		$sum  = array_sum( wp_list_pluck( $rows, 'live_sites' ) );
		$o   .= '<div class="bw-statgrid">' . BWing_Render::stat( count( $rows ), __( 'Products tracked', 'bwing' ) ) . BWing_Render::stat( BWing_Format::short( $sum ), __( 'Live sites combined', 'bwing' ) ) . BWing_Render::stat( $rows ? $rows[0]->name : '—', __( 'Leader', 'bwing' ) ) . '</div>';
		if ( ! $rows ) {
			return $o . BWing_Render::empty_state( __( 'No premium items yet.', 'bwing' ) );
		}
		$o .= '<div class="bw-tablewrap"><table class="bw-table"><thead><tr><th>#</th><th>' . esc_html__( 'Product', 'bwing' ) . '</th><th>' . esc_html__( 'Price', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Live sites', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Change', 'bwing' ) . '</th><th class="bw-r">' . esc_html__( 'Share', 'bwing' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $i => $p ) {
			$chg = (int) $p->live_sites - (int) $p->prev_live_sites;
			$o  .= '<tr><td class="bw-num">' . ( $i + 1 ) . '</td><td><a class="bw-cell-item" href="' . esc_url( BWing_Format::premium_url( $type, $p->slug ) ) . '">' . BWing_Format::thumb( $p, 'bw-thumb bw-thumb--sm' ) . '<span><strong>' . esc_html( $p->name ) . '</strong><small>' . esc_html( $p->vendor ) . '</small></span></a></td><td>' . esc_html( $p->price ) . '</td><td class="bw-r"><b>' . esc_html( number_format_i18n( $p->live_sites ) ) . '</b></td><td class="bw-r"><span class="bw-trend bw-trend--' . ( $chg > 0 ? 'up' : ( $chg < 0 ? 'down' : 'flat' ) ) . '">' . esc_html( BWing_Format::signed( $chg ) ) . '</span></td><td class="bw-r">' . esc_html( $sum ? round( $p->live_sites / $sum * 100, 1 ) : 0 ) . '%</td></tr>';
		}
		return $o . '</tbody></table></div>';
	}

	public static function authors( $atts ) {
		$a     = self::atts( 'authors', $atts, array( 'show_title' => 'yes', 'type' => 'all' ) );
		$sort  = self::qv( 'bw_sort', 'total' );
		$order = self::qv( 'bw_order', 'desc' );
		$res   = BWing_Data::authors( array(
			'type'     => $a['type'],
			'page'     => max( 1, absint( self::qv( 'pg', 1 ) ) ),
			'per_page' => (int) BWing_Settings::get( 'general', 'per_page', 50 ),
			'sort'     => $sort,
			'order'    => $order,
			's'        => self::qv( 'bw_s' ),
		) );
		$o = 'yes' === $a['show_title'] ? '<header class="bw-pagehead"><h1>' . esc_html( $a['title'] ) . '</h1><p>' . esc_html( $a['intro'] ) . '</p></header>' : '';

		$picks = array_filter( array_map( 'trim', explode( ',', $a['picks'] ) ) );
		if ( $picks ) {
			$o .= '<div class="bw-chips"><span>' . esc_html__( 'Quick picks:', 'bwing' ) . '</span>';
			foreach ( $picks as $p ) {
				$o .= '<a class="bw-chip" href="' . esc_url( BWing_Format::author_url( sanitize_title( $p ) ) ) . '">@' . esc_html( $p ) . '</a>';
			}
			$o .= '</div>';
		}
		$o .= '<form class="bw-filters" method="get"><label class="bw-field bw-field--grow"><input type="search" name="bw_s" value="' . esc_attr( self::qv( 'bw_s' ) ) . '" placeholder="' . esc_attr__( 'Search authors…', 'bwing' ) . '"></label><button class="bw-btn bw-btn--primary bw-btn--sm">' . esc_html__( 'Search', 'bwing' ) . '</button>';
		/* translators: %s count */
		$o .= '<span class="bw-muted">' . esc_html( sprintf( __( '%s authors', 'bwing' ), number_format_i18n( $res['total'] ) ) ) . '</span></form>';

		$th = function ( $key, $label, $cls = '' ) use ( $sort, $order ) {
			$next  = ( $sort === $key && 'desc' === $order ) ? 'asc' : 'desc';
			$arrow = $sort === $key ? ( 'desc' === $order ? ' ↓' : ' ↑' ) : '';
			return '<th class="' . esc_attr( $cls ) . '"><a href="' . esc_url( add_query_arg( array( 'bw_sort' => $key, 'bw_order' => $next, 'pg' => false ) ) ) . '">' . esc_html( $label . $arrow ) . '</a></th>';
		};
		if ( ! $res['rows'] ) {
			return $o . BWing_Render::empty_state( __( 'No authors found.', 'bwing' ) );
		}
		$o .= '<div class="bw-tablewrap"><table class="bw-table"><thead><tr><th>#</th><th>' . esc_html__( 'Author', 'bwing' ) . '</th>'
			. $th( 'plugins', __( 'Plugins', 'bwing' ), 'bw-r' ) . $th( 'themes', __( 'Themes', 'bwing' ), 'bw-r' ) . $th( 'plugin_installs', __( 'Plugin installs', 'bwing' ), 'bw-r' ) . $th( 'theme_installs', __( 'Theme installs', 'bwing' ), 'bw-r' ) . $th( 'total', __( 'Total installs', 'bwing' ), 'bw-r' )
			. '<th class="bw-r">' . esc_html__( '30-day trend', 'bwing' ) . '</th></tr></thead><tbody>';
		foreach ( $res['rows'] as $i => $r ) {
			$o .= '<tr><td class="bw-num">' . ( ( $res['page'] - 1 ) * $res['per'] + $i + 1 ) . '</td><td><a class="bw-cell-item" href="' . esc_url( BWing_Format::author_url( $r->author ) ) . '">' . BWing_Format::avatar( $r->author, 64, BWing_Format::author_image( $r ) ) . '<span><strong>' . esc_html( $r->author_name ? $r->author_name : $r->author ) . '</strong><small>@' . esc_html( $r->author ) . '</small></span></a></td>'
				. '<td class="bw-r">' . (int) $r->plugins . '</td><td class="bw-r">' . (int) $r->themes . '</td><td class="bw-r">' . esc_html( BWing_Format::short( $r->plugin_installs, true ) ) . '</td><td class="bw-r">' . esc_html( BWing_Format::short( $r->theme_installs, true ) ) . '</td><td class="bw-r"><b>' . esc_html( BWing_Format::short( $r->total, true ) ) . '</b></td>'
				. '<td class="bw-r"><span class="bw-trend bw-trend--' . ( $r->trend > 0 ? 'up' : ( $r->trend < 0 ? 'down' : 'flat' ) ) . '">' . esc_html( BWing_Format::signed( $r->trend ) ) . '</span></td></tr>';
		}
		return $o . '</tbody></table></div>' . BWing_Render::pagination( $res['page'], $res['pages'] );
	}

	public static function stats( $atts ) {
		$type = isset( $atts['type'] ) && in_array( $atts['type'], array( 'plugin', 'author' ), true ) ? $atts['type'] : 'theme';
		$a    = self::atts( 'stats', $atts, array( 'type' => $type, 'show_title' => 'yes' ) );
		$o    = 'yes' === $a['show_title'] ? '<header class="bw-pagehead"><h1>' . esc_html( $a[ $type . '_title' ] ) . '</h1><p>' . esc_html( $a[ $type . '_intro' ] ) . '</p><p class="bw-metaline"><span class="bw-dot"></span>' . esc_html__( 'Snapshot', 'bwing' ) . ' ' . esc_html( BWing_Data::latest_date() ) . '</p></header>' : '';
		if ( 'author' === $type ) {
			return $o . self::author_stats();
		}
		$s    = BWing_Data::stats( $type );
		$noun = 'theme' === $type ? __( 'Themes', 'bwing' ) : __( 'Plugins', 'bwing' );

		$o .= '<div class="bw-statgrid bw-statgrid--6">'
			. BWing_Render::stat( number_format_i18n( $s['count'] ), $noun . ' ' . __( 'tracked', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $s['downloads'] ), __( 'Downloads', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $s['installs'] ), __( 'Active installs', 'bwing' ) )
			. BWing_Render::stat( $s['avg_rating'] . '★', __( 'Average rating', 'bwing' ) )
			. BWing_Render::stat( number_format_i18n( $s['authors'] ), __( 'Authors', 'bwing' ) )
			. BWing_Render::stat( $s['diversity'] . '%', __( 'Diversity score', 'bwing' ), __( '100 − top-10 share', 'bwing' ) )
			. '</div>';

		if ( 'theme' === $type && $s['installs'] ) {
			$ex = $s['ex_installs'] / $s['installs'] * 100 - 100;
			$ed = $s['downloads'] ? $s['ex_downloads'] / $s['downloads'] * 100 - 100 : 0;
			$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Excluding default “Twenty” themes', 'bwing' ) . '</h3><div class="bw-statgrid">'
				. BWing_Render::stat( number_format_i18n( $s['ex_count'] ), __( 'Themes', 'bwing' ) )
				. BWing_Render::stat( BWing_Format::short( $s['ex_installs'] ), __( 'Active installs', 'bwing' ), '<span class="bw-trend bw-trend--down">' . round( $ex ) . '%</span>' )
				. BWing_Render::stat( BWing_Format::short( $s['ex_downloads'] ), __( 'Downloads', 'bwing' ), '<span class="bw-trend bw-trend--down">' . round( $ed ) . '%</span>' )
				. '</div></div>';
			$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Block vs classic', 'bwing' ) . '</h3>' . BWing_Render::split( __( 'Block', 'bwing' ), $s['block'], __( 'Classic', 'bwing' ), $s['classic'] ) . '</div>';
		}

		$o .= '<div class="bw-grid2">';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Install distribution', 'bwing' ) . '</h3>' . BWing_Render::columns( $s['histogram'] ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'New in the directory (12 months)', 'bwing' ) . '</h3>' . BWing_Render::columns( $s['months'] ) . '</div>';
		$o .= '</div>';

		$conc = '';
		foreach ( $s['concentration'] as $n => $c ) {
			$conc .= '<tr><td>' . esc_html__( 'Top', 'bwing' ) . ' ' . (int) $n . '</td><td><span class="bw-meter"><span style="width:' . esc_attr( $c['installs'] ) . '%"></span></span> ' . esc_html( $c['installs'] ) . '%</td><td>' . esc_html( $c['downloads'] ) . '%</td></tr>';
		}
		$o .= '<div class="bw-grid2"><div class="bw-panel"><h3>' . esc_html__( 'Concentration', 'bwing' ) . '</h3><table class="bw-table bw-table--compact"><thead><tr><th></th><th>' . esc_html__( 'Share of installs', 'bwing' ) . '</th><th>' . esc_html__( 'Share of downloads', 'bwing' ) . '</th></tr></thead><tbody>' . $conc . '</tbody></table></div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Age of listings', 'bwing' ) . '</h3>' . BWing_Render::bars( $s['ages'] ) . '</div></div>';

		$list = function ( $rows, $metric ) {
			$h = '<ol class="bw-mini">';
			foreach ( $rows as $i => $r ) {
				$h .= '<li><a href="' . esc_url( isset( $r->slug ) ? BWing_Format::item_url( $r->type, $r->slug ) : BWing_Format::author_url( $r->author ) ) . '"><span class="bw-mini__n">' . ( $i + 1 ) . '</span><span class="bw-mini__name">' . esc_html( isset( $r->name ) ? $r->name : ( $r->author_name ? $r->author_name : $r->author ) ) . '</span><span class="bw-mini__v">' . wp_kses_post( $metric( $r ) ) . '</span></a></li>';
			}
			return $h . '</ol>';
		};
		$o .= '<div class="bw-grid2">';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Top authors by count', 'bwing' ) . '</h3>' . $list( $s['top_by_count'], function ( $r ) { return number_format_i18n( $r->c ); } ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Top authors by installs', 'bwing' ) . '</h3>' . $list( $s['top_by_installs'], function ( $r ) { return BWing_Format::short( $r->c, true ); } ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Best rated (20+ reviews)', 'bwing' ) . '</h3>' . $list( $s['best'], function ( $r ) { return BWing_Format::stars( $r->rating ) . '★ · ' . BWing_Format::short( $r->num_ratings ); } ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Lowest rated (10+ reviews)', 'bwing' ) . '</h3>' . $list( $s['worst'], function ( $r ) { return BWing_Format::stars( $r->rating ) . '★ · ' . BWing_Format::short( $r->num_ratings ); } ) . '</div>';
		$o .= '</div>';

		$ab = '';
		foreach ( $s['abandoned'] as $y => $r ) {
			/* translators: %d years */
			$ab .= '<tr><td>' . esc_html( sprintf( _n( '%d+ year without update', '%d+ years without update', $y, 'bwing' ), $y ) ) . '</td><td class="bw-r">' . esc_html( number_format_i18n( $r['count'] ) ) . '</td><td class="bw-r">' . esc_html( $r['pct'] ) . '%</td><td class="bw-r">' . esc_html( BWing_Format::short( $r['installs'] ) ) . '</td></tr>';
		}
		$o .= '<div class="bw-grid2"><div class="bw-panel"><h3>' . esc_html__( 'Abandoned listings', 'bwing' ) . '</h3><table class="bw-table bw-table--compact"><thead><tr><th></th><th class="bw-r">' . esc_html( $noun ) . '</th><th class="bw-r">%</th><th class="bw-r">' . esc_html__( 'Trapped installs', 'bwing' ) . '</th></tr></thead><tbody>' . $ab . '</tbody></table></div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Most-installed abandoned (2+ years)', 'bwing' ) . '</h3>' . ( $s['abandoned_top'] ? $list( $s['abandoned_top'], function ( $r ) { return BWing_Format::installs( $r->active_installs ) . ' · ' . mysql2date( 'Y', $r->last_updated ); } ) : '<p class="bw-muted">' . esc_html__( 'None — nice.', 'bwing' ) . '</p>' ) . '</div></div>';

		return '<div class="bw-stats">' . $o . '</div>';
	}

	private static function author_stats() {
		$all    = BWing_Data::authors( array( 'per_page' => 10 ) );
		$themes = BWing_Data::authors( array( 'type' => 'theme', 'per_page' => 10, 'sort' => 'themes' ) );
		$plugs  = BWing_Data::authors( array( 'type' => 'plugin', 'per_page' => 10, 'sort' => 'plugins' ) );
		$sum    = array_sum( wp_list_pluck( $all['rows'], 'total' ) );
		$grand  = BWing_Data::stats( 'theme' )['installs'] + BWing_Data::stats( 'plugin' )['installs'];
		$o      = '<div class="bw-statgrid">'
			. BWing_Render::stat( number_format_i18n( $all['total'] ), __( 'Authors tracked', 'bwing' ) )
			. BWing_Render::stat( BWing_Format::short( $grand ), __( 'Installs combined', 'bwing' ) )
			. BWing_Render::stat( ( $grand ? round( $sum / $grand * 100 ) : 0 ) . '%', __( 'Held by top 10 authors', 'bwing' ) )
			. '</div>';
		$list = function ( $rows, $metric ) {
			$h = '<ol class="bw-mini">';
			foreach ( $rows as $i => $r ) {
				$h .= '<li><a href="' . esc_url( BWing_Format::author_url( $r->author ) ) . '"><span class="bw-mini__n">' . ( $i + 1 ) . '</span>' . BWing_Format::avatar( $r->author, 64, BWing_Format::author_image( $r ) ) . '<span class="bw-mini__name">' . esc_html( $r->author_name ? $r->author_name : $r->author ) . '</span><span class="bw-mini__v">' . esc_html( $metric( $r ) ) . '</span></a></li>';
			}
			return $h . '</ol>';
		};
		$o .= '<div class="bw-grid3">';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Most installs overall', 'bwing' ) . '</h3>' . $list( $all['rows'], function ( $r ) { return BWing_Format::short( $r->total, true ); } ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Most themes published', 'bwing' ) . '</h3>' . $list( $themes['rows'], function ( $r ) { return (int) $r->themes; } ) . '</div>';
		$o .= '<div class="bw-panel"><h3>' . esc_html__( 'Most plugins published', 'bwing' ) . '</h3>' . $list( $plugs['rows'], function ( $r ) { return (int) $r->plugins; } ) . '</div>';
		return $o . '</div>';
	}

	public static function fastest( $atts ) {
		$a    = self::atts( 'fastest', $atts, array( 'show_title' => 'yes' ) );
		$rows = BWing_Data::fastest( (int) $a['count'] );
		$o    = 'yes' === $a['show_title'] ? '<header class="bw-pagehead"><h1>' . esc_html( $a['title'] ) . '</h1><p>' . esc_html( $a['intro'] ) . '</p></header>' : '';
		if ( ! $rows ) {
			return $o . BWing_Render::empty_state( __( 'No Lighthouse data yet. Add scores in BWing → Items.', 'bwing' ) );
		}
		$o .= '<div class="bw-podium">';
		foreach ( array_slice( $rows, 0, 3 ) as $i => $r ) {
			$o .= '<a class="bw-podium__item bw-podium__item--' . ( $i + 1 ) . '" href="' . esc_url( BWing_Format::item_url( 'theme', $r->slug ) ) . '"><span class="bw-podium__place">' . ( $i + 1 ) . '</span>' . BWing_Format::thumb( $r, 'bw-thumb bw-thumb--theme' ) . '<strong>' . esc_html( $r->name ) . '</strong>' . BWing_Render::score( $r->perf_mobile, __( 'Mobile', 'bwing' ) ) . '</a>';
		}
		$o .= '</div><div class="bw-tablewrap"><table class="bw-table"><thead><tr><th>#</th><th>' . esc_html__( 'Theme', 'bwing' ) . '</th><th class="bw-c">' . esc_html__( 'Mobile', 'bwing' ) . '</th><th class="bw-c">' . esc_html__( 'Desktop', 'bwing' ) . '</th><th class="bw-r">LCP</th><th class="bw-r">FCP</th><th class="bw-r">TBT</th><th class="bw-r">CLS</th><th class="bw-r">' . esc_html__( 'Weight', 'bwing' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $i => $r ) {
			$tone = function ( $v ) {
				return $v >= 90 ? 'good' : ( $v >= 50 ? 'ok' : 'bad' );
			};
			$o .= '<tr><td class="bw-num">' . ( $i + 1 ) . '</td><td><a class="bw-cell-item" href="' . esc_url( BWing_Format::item_url( 'theme', $r->slug ) ) . '">' . BWing_Format::thumb( $r, 'bw-thumb bw-thumb--sm bw-thumb--theme' ) . '<span><strong>' . esc_html( $r->name ) . '</strong><small>' . esc_html( BWing_Format::installs( $r->active_installs ) ) . ' ' . esc_html__( 'installs', 'bwing' ) . '</small></span></a></td>'
				. '<td class="bw-c"><span class="bw-pill bw-pill--' . $tone( $r->perf_mobile ) . '">' . (int) $r->perf_mobile . '</span></td><td class="bw-c"><span class="bw-pill bw-pill--' . $tone( $r->perf_desktop ) . '">' . (int) $r->perf_desktop . '</span></td>'
				. '<td class="bw-r">' . esc_html( $r->lcp ) . 's</td><td class="bw-r">' . esc_html( $r->fcp ) . 's</td><td class="bw-r">' . (int) $r->tbt . 'ms</td><td class="bw-r">' . esc_html( $r->cls ) . '</td><td class="bw-r">' . (int) $r->page_weight . ' KB</td></tr>';
		}
		return $o . '</tbody></table></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Tools                                                               */
	/* ------------------------------------------------------------------ */

	public static function detector( $atts ) {
		$a  = self::atts( 'detector', $atts, array( 'show_title' => 'yes', 'show_extras' => 'yes' ) );
		$o  = '<div class="bw-tool">';
		$o .= 'yes' === $a['show_title'] ? '<h1 class="bw-tool__title">' . esc_html( $a['title'] ) . '</h1><p class="bw-tool__sub">' . esc_html( $a['subtitle'] ) . '</p>' : '';
		$o .= '<form class="bw-tool__form" data-bw-tool="detect"><input type="text" name="url" required placeholder="example.com" inputmode="url" aria-label="' . esc_attr__( 'Website address', 'bwing' ) . '"><button class="bw-btn bw-btn--primary" type="submit">' . esc_html( $a['button'] ) . '</button></form>';
		$o .= '<p class="bw-tool__note">' . esc_html( $a['note'] ) . '</p><div class="bw-tool__result" aria-live="polite"></div></div>';

		if ( 'yes' !== $a['show_extras'] ) {
			return $o;
		}
		$recent = BWing_Data::recent_detections( (int) $a['recent'] );
		$o     .= BWing_Render::head( __( 'Recently detected', 'bwing' ) );
		if ( $recent ) {
			$o .= '<div class="bw-sites">';
			foreach ( $recent as $d ) {
				$o .= self::site_card( $d );
			}
			$o .= '</div>';
		} else {
			$o .= BWing_Render::empty_state( __( 'No scans yet — be the first to detect a site.', 'bwing' ) );
		}
		$o .= '<div class="bw-grid2">' . self::most_detected( array( 'type' => 'theme', 'count' => 10 ) ) . self::most_detected( array( 'type' => 'plugin', 'count' => 10 ) ) . '</div>';
		$o .= self::cards_section( __( 'What the detector reveals', 'bwing' ), $a['reveals'] );
		$o .= self::cards_section( __( 'How it works', 'bwing' ), $a['steps'], true );
		$o .= self::faq( array( 'section' => 'detector' ) );
		return '<div class="bw-detector">' . $o . '</div>';
	}

	public static function site_card( $d ) {
		$plugins = json_decode( (string) $d->plugins, true );
		return '<div class="bw-site"><img class="bw-site__fav" src="' . esc_url( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $d->domain ) ) . '" alt="" loading="lazy"><div class="bw-site__body"><strong>' . esc_html( $d->title ? $d->title : $d->domain ) . '</strong><small>' . esc_html( $d->domain ) . '</small><span>' . ( $d->theme ? '<a href="' . esc_url( BWing_Format::item_url( 'theme', $d->parent_theme ? $d->parent_theme : $d->theme ) ) . '">' . esc_html( $d->theme_name ? $d->theme_name : $d->theme ) . '</a>' : '—' ) . ' · ' . esc_html( sprintf( /* translators: %d count */ _n( '%d plugin', '%d plugins', count( (array) $plugins ), 'bwing' ), count( (array) $plugins ) ) ) . '</span></div><button type="button" class="bw-site__scan" data-bw-rescan="' . esc_attr( $d->domain ) . '">' . esc_html__( 'Scan', 'bwing' ) . '</button></div>';
	}

	private static function cards_section( $title, $raw, $numbered = false ) {
		$rows = BWing_Settings::rows( $raw );
		if ( ! $rows ) {
			return '';
		}
		$o = '<div class="bw-steps' . ( $numbered ? ' bw-steps--numbered' : '' ) . '">';
		foreach ( $rows as $i => $r ) {
			$o .= '<div class="bw-step">' . ( $numbered ? '<span class="bw-step__n">' . ( $i + 1 ) . '</span>' : '' ) . '<strong>' . esc_html( $r[0] ) . '</strong><p>' . esc_html( isset( $r[1] ) ? $r[1] : '' ) . '</p></div>';
		}
		return BWing_Render::wrap( 'steps', BWing_Render::head( $title ) . $o . '</div>' );
	}

	public static function most_detected( $atts ) {
		bwing_enqueue();
		$a    = shortcode_atts( array( 'type' => 'theme', 'count' => 20, 'title' => '', 'show_title' => 'no' ), $atts );
		$type = 'plugin' === $a['type'] ? 'plugin' : 'theme';
		$res  = BWing_Data::most_detected( $type, (int) $a['count'] );
		$ttl  = $a['title'] ? $a['title'] : ( 'theme' === $type ? __( 'Most detected themes', 'bwing' ) : __( 'Most detected plugins', 'bwing' ) );
		$o    = 'yes' === $a['show_title'] ? '<header class="bw-pagehead"><h1>' . esc_html( $ttl ) . '</h1><p>' . esc_html( sprintf( /* translators: %s count */ __( 'Real-world usage across %s sites scanned with our detector.', 'bwing' ), number_format_i18n( $res['total'] ) ) ) . '</p></header>' : '<h3>' . esc_html( $ttl ) . '</h3>';
		if ( ! $res['counts'] ) {
			return '<div class="bw-panel">' . $o . BWing_Render::empty_state( __( 'Scan a few sites with the detector to build this list.', 'bwing' ) ) . '</div>';
		}
		$o .= '<ol class="bw-mini">';
		$i  = 0;
		foreach ( $res['counts'] as $slug => $c ) {
			$item  = BWing_Data::item( $type, $slug );
			$name  = $item ? $item->name : ucwords( str_replace( '-', ' ', $slug ) );
			$url   = $item ? BWing_Format::item_url( $type, $slug ) : '#';
			$pct   = $res['total'] ? round( $c / $res['total'] * 100, 1 ) : 0;
			$o    .= '<li><a href="' . esc_url( $url ) . '"><span class="bw-mini__n">' . ( ++$i ) . '</span><span class="bw-mini__name">' . esc_html( $name ) . '</span><span class="bw-mini__v">' . esc_html( number_format_i18n( $c ) ) . ' · ' . esc_html( $pct ) . '%</span></a></li>';
		}
		return '<div class="bw-panel">' . $o . '</ol></div>';
	}

	public static function hosting( $atts ) {
		$a  = self::atts( 'hosting', $atts, array( 'show_title' => 'yes' ) );
		$o  = '<div class="bw-tool">';
		$o .= 'yes' === $a['show_title'] ? '<h1 class="bw-tool__title">' . esc_html( $a['title'] ) . '</h1><p class="bw-tool__sub">' . esc_html( $a['subtitle'] ) . '</p>' : '';
		$o .= '<form class="bw-tool__form" data-bw-tool="hosting"><input type="text" name="url" required placeholder="example.com" aria-label="' . esc_attr__( 'Domain', 'bwing' ) . '"><button class="bw-btn bw-btn--primary" type="submit">' . esc_html( $a['button'] ) . '</button></form><div class="bw-tool__result" aria-live="polite"></div></div>';
		return '<div class="bw-hosting">' . $o . self::faq( array( 'section' => 'hosting' ) ) . '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Singles                                                             */
	/* ------------------------------------------------------------------ */

	public static function item( $atts ) {
		bwing_enqueue();
		$a = shortcode_atts( array( 'type' => 'theme', 'slug' => '' ), $atts );
		return BWing_Single::item( $a['type'], sanitize_title( $a['slug'] ) );
	}

	public static function author( $atts ) {
		bwing_enqueue();
		$a     = shortcode_atts( array( 'slug' => '' ), $atts );
		$items = BWing_Data::author_items( sanitize_title( $a['slug'] ) );
		if ( ! $items ) {
			return BWing_Render::empty_state( __( 'Author not found.', 'bwing' ) );
		}
		return BWing_Single::author( (object) array( 'slug' => $a['slug'], 'name' => $items[0]->author_name, 'items' => $items ) );
	}

	public static function author_profile( $o ) {
		return BWing_Single::author( $o );
	}

	public static function premium_single( $o ) {
		return BWing_Single::premium( $o );
	}
}
