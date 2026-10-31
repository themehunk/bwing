<?php
/**
 * SEO for the routed theme / plugin / premium / author pages: meta description,
 * canonical, Open Graph, JSON-LD, an XML sitemap of every tracked URL and /llms.txt
 * for AI search engines. Defers to Yoast SEO or Rank Math when one is active.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_SEO {

	const RULES_VERSION = '2';
	const PER_SITEMAP   = 2000;

	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve_files' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20, 2 );

		// Yoast SEO.
		add_filter( 'wpseo_canonical', array( __CLASS__, 'filter_canonical' ) );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'filter_canonical' ) );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'filter_description' ) );
		add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'filter_description' ) );
		add_filter( 'wpseo_title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'wpseo_sitemap_index', array( __CLASS__, 'sitemap_index_entry' ) );
		// Rank Math.
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'filter_canonical' ) );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'filter_description' ) );
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'rank_math/sitemap/index', array( __CLASS__, 'sitemap_index_entry' ) );

		if ( get_option( 'bwing_seo_rules' ) !== self::RULES_VERSION ) {
			add_action( 'wp_loaded', function () {
				flush_rewrite_rules( false );
				update_option( 'bwing_seo_rules', self::RULES_VERSION );
			} );
		}
	}

	public static function add_rules() {
		add_rewrite_rule( '^bwing-sitemap\.xml$', 'index.php?bwing_sitemap=index', 'top' );
		add_rewrite_rule( '^bwing-sitemap-(theme|plugin|author|premium)-([0-9]+)\.xml$', 'index.php?bwing_sitemap=$matches[1]&bwing_sitemap_page=$matches[2]', 'top' );
		add_rewrite_rule( '^llms\.txt$', 'index.php?bwing_llms=1', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'bwing_sitemap';
		$vars[] = 'bwing_sitemap_page';
		$vars[] = 'bwing_llms';
		return $vars;
	}

	private static function seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/* ------------------------------------------------------------------ */
	/* Page meta                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Meta for the current routed page.
	 *
	 * @return array|null title, description, url, image, schema.
	 */
	public static function meta() {
		static $meta = false;
		if ( false !== $meta ) {
			return $meta;
		}
		$cur  = BWing_Router::current();
		$meta = null;
		if ( ! $cur ) {
			return $meta;
		}
		$o     = $cur['object'];
		$brand = BWing_Settings::get( 'general', 'brand', get_bloginfo( 'name' ) );
		switch ( $cur['type'] ) {
			case 'theme':
			case 'plugin':
				$url  = BWing_Format::item_url( $cur['type'], $o->slug );
				$meta = array(
					'title'       => wp_get_document_title(),
					'description' => self::item_summary( $o, true ),
					'url'         => $url,
					'image'       => $o->image,
					'schema'      => array(
						self::software_schema( $o, $url ),
						self::breadcrumbs( array(
							array( 'theme' === $cur['type'] ? __( 'Themes', 'bwing' ) : __( 'Plugins', 'bwing' ), bwing_url( 'theme' === $cur['type'] ? 'themes' : 'plugins' ) ),
							array( $o->name, $url ),
						) ),
					),
				);
				break;
			case 'author':
				$url    = BWing_Format::author_url( $o->slug );
				$themes = count( wp_list_filter( $o->items, array( 'type' => 'theme' ) ) );
				$total  = array_sum( wp_list_pluck( $o->items, 'active_installs' ) );
				/* translators: 1 author 2 themes 3 plugins 4 installs 5 brand */
				$desc = sprintf( __( '%1$s publishes %2$d WordPress themes and %3$d plugins on WordPress.org with %4$s combined active installs. Daily rankings and install history on %5$s.', 'bwing' ), $o->name, $themes, count( $o->items ) - $themes, BWing_Format::short( $total, true ), $brand );
				$meta = array(
					'title'       => wp_get_document_title(),
					'description' => $desc,
					'url'         => $url,
					'image'       => '',
					'schema'      => array(
						array(
							'@context'   => 'https://schema.org',
							'@type'      => 'ProfilePage',
							'url'        => $url,
							'name'       => $o->name,
							'mainEntity' => array(
								'@type'  => 'Person',
								'name'   => $o->name,
								'url'    => $url,
								'sameAs' => array( 'https://profiles.wordpress.org/' . $o->slug . '/' ),
							),
						),
						self::breadcrumbs( array( array( __( 'Authors', 'bwing' ), bwing_url( 'authors' ) ), array( $o->name, $url ) ) ),
					),
				);
				break;
			default:
				$url = BWing_Format::premium_url( $o->type, $o->slug );
				/* translators: 1 name 2 vendor 3 sites 4 price */
				$desc = sprintf( __( '%1$s by %2$s runs on %3$s detected live websites. Pricing from %4$s, market share and the closest alternatives.', 'bwing' ), $o->name, $o->vendor, number_format_i18n( $o->live_sites ), $o->price ? $o->price : '—' );
				$meta = array(
					'title'       => wp_get_document_title(),
					'description' => $desc,
					'url'         => $url,
					'image'       => $o->image,
					'schema'      => array(
						array_filter( array(
							'@context'            => 'https://schema.org',
							'@type'               => 'SoftwareApplication',
							'name'                => $o->name,
							'url'                 => $url,
							'applicationCategory' => 'WebApplication',
							'operatingSystem'     => 'WordPress',
							'image'               => $o->image,
							'author'              => array( '@type' => 'Organization', 'name' => $o->vendor ),
							'sameAs'              => $o->url ? array( $o->url ) : null,
						) ),
						self::breadcrumbs( array(
							array( 'plugin' === $o->type ? __( 'Premium plugins', 'bwing' ) : __( 'Premium themes', 'bwing' ), bwing_url( 'plugin' === $o->type ? 'premium-plugins' : 'premium-themes' ) ),
							array( $o->name, $url ),
						) ),
					),
				);
		}
		$meta['description'] = wp_html_excerpt( $meta['description'], 158, '…' );
		return $meta;
	}

	/**
	 * One answer-first paragraph describing a theme or plugin. Used as the meta
	 * description and as the visible intro, so search and AI engines can quote it.
	 *
	 * @param object $item  Item row.
	 * @param bool   $short Meta-description length.
	 */
	public static function item_summary( $item, $short = false ) {
		$noun   = 'theme' === $item->type ? __( 'theme', 'bwing' ) : __( 'plugin', 'bwing' );
		$author = $item->author_name ? $item->author_name : $item->author;
		/* translators: 1 name 2 noun 3 author 4 installs */
		$s = sprintf( __( '%1$s is a free WordPress %2$s by %3$s with %4$s active installs', 'bwing' ), $item->name, $noun, $author, wp_strip_all_tags( BWing_Format::installs( $item->active_installs ) ) );
		if ( $item->pos > 0 ) {
			/* translators: 1 rank 2 noun */
			$s .= sprintf( __( ', ranked #%1$d among the most popular WordPress %2$ss today', 'bwing' ), $item->pos, $noun );
		}
		$s .= '.';
		if ( $short ) {
			$more = ' ' . __( 'Daily rank, install and download history.', 'bwing' );
			return mb_strlen( $s . $more ) <= 158 ? $s . $more : $s;
		}
		if ( $item->num_ratings ) {
			/* translators: 1 rating 2 reviews */
			$s .= ' ' . sprintf( __( 'It is rated %1$s/5 from %2$s reviews', 'bwing' ), BWing_Format::stars( $item->rating ), number_format_i18n( $item->num_ratings ) );
			/* translators: %s downloads */
			$s .= $item->downloaded ? sprintf( __( ' and has been downloaded %s times.', 'bwing' ), BWing_Format::short( $item->downloaded ) ) : '.';
		}
		if ( $item->last_updated ) {
			/* translators: 1 version 2 date */
			$s .= ' ' . sprintf( __( 'The latest version %1$s was released on %2$s.', 'bwing' ), $item->version, wp_strip_all_tags( BWing_Format::date( $item->last_updated ) ) );
		}
		return $s;
	}

	private static function software_schema( $o, $url ) {
		$schema = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'SoftwareApplication',
			'name'                => $o->name,
			'url'                 => $url,
			'description'         => self::item_summary( $o ),
			'applicationCategory' => 'theme' === $o->type ? 'DesignApplication' : 'WebApplication',
			'applicationSubCategory' => 'theme' === $o->type ? 'WordPress Theme' : 'WordPress Plugin',
			'operatingSystem'     => 'WordPress' . ( $o->requires ? ' ' . $o->requires . '+' : '' ),
			'softwareVersion'     => $o->version,
			'downloadUrl'         => BWing_Format::wporg_url( $o->type, $o->slug ),
			'sameAs'              => array( BWing_Format::wporg_url( $o->type, $o->slug ) ),
			'author'              => array(
				'@type' => 'Person',
				'name'  => $o->author_name ? $o->author_name : $o->author,
				'url'   => BWing_Format::author_url( $o->author ),
			),
			'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ),
			'interactionStatistic' => array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/DownloadAction',
				'userInteractionCount' => (int) $o->downloaded,
			),
		);
		if ( $o->image ) {
			$schema['image'] = $o->image;
		}
		if ( $o->last_updated ) {
			$schema['dateModified'] = $o->last_updated;
		}
		if ( $o->added ) {
			$schema['datePublished'] = $o->added;
		}
		if ( $o->num_ratings > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => BWing_Format::stars( $o->rating ),
				'bestRating'  => 5,
				'ratingCount' => (int) $o->num_ratings,
			);
		}
		return $schema;
	}

	private static function breadcrumbs( $trail ) {
		array_unshift( $trail, array( BWing_Settings::get( 'general', 'brand', get_bloginfo( 'name' ) ), home_url( '/' ) ) );
		$list = array();
		foreach ( $trail as $i => $crumb ) {
			$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1] );
		}
		return array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list );
	}

	public static function head() {
		if ( is_front_page() && ! self::seo_plugin() ) {
			self::json_ld( array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'name'            => BWing_Settings::get( 'general', 'brand', get_bloginfo( 'name' ) ),
				'description'     => BWing_Settings::get( 'general', 'tagline', get_bloginfo( 'description' ) ),
				'url'             => home_url( '/' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => home_url( '/?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				),
			) );
			return;
		}
		$m = self::meta();
		if ( ! $m ) {
			return;
		}
		if ( ! self::seo_plugin() ) {
			echo '<meta name="description" content="' . esc_attr( $m['description'] ) . '">' . "\n";
			echo '<link rel="canonical" href="' . esc_url( $m['url'] ) . '">' . "\n";
			echo '<meta property="og:type" content="website">' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( BWing_Settings::get( 'general', 'brand', get_bloginfo( 'name' ) ) ) . '">' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $m['title'] ) . '">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $m['description'] ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $m['url'] ) . '">' . "\n";
			if ( $m['image'] ) {
				echo '<meta property="og:image" content="' . esc_url( $m['image'] ) . '">' . "\n";
			}
			echo '<meta name="twitter:card" content="' . ( $m['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		}
		foreach ( $m['schema'] as $schema ) {
			self::json_ld( $schema );
		}
	}

	private static function json_ld( $data ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public static function filter_canonical( $value ) {
		$m = self::meta();
		return $m ? $m['url'] : $value;
	}

	public static function filter_description( $value ) {
		$m = self::meta();
		return $m ? $m['description'] : $value;
	}

	public static function filter_title( $value ) {
		$m = self::meta();
		return $m ? $m['title'] : $value;
	}

	/* ------------------------------------------------------------------ */
	/* Sitemap, robots.txt, llms.txt                                       */
	/* ------------------------------------------------------------------ */

	public static function serve_files() {
		$map = get_query_var( 'bwing_sitemap' );
		if ( $map ) {
			self::sitemap( $map, max( 1, (int) get_query_var( 'bwing_sitemap_page' ) ) );
			exit;
		}
		if ( get_query_var( 'bwing_llms' ) ) {
			self::llms();
			exit;
		}
	}

	public static function sitemap_url() {
		return home_url( '/bwing-sitemap.xml' );
	}

	/** Adds the BWing sitemap to the Yoast / Rank Math sitemap index. */
	public static function sitemap_index_entry( $xml ) {
		return $xml . '<sitemap><loc>' . esc_url( self::sitemap_url() ) . '</loc></sitemap>' . "\n";
	}

	public static function robots( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		return rtrim( $output ) . "\nSitemap: " . self::sitemap_url() . "\n";
	}

	/** SQL per sitemap group: rows of (slug, type, lastmod). */
	private static function sitemap_sql( $group, $count = false ) {
		$items   = BWing_DB::table( 'items' );
		$premium = BWing_DB::table( 'premium' );
		switch ( $group ) {
			case 'theme':
			case 'plugin':
				return $count ? "SELECT COUNT(*) FROM {$items} WHERE type = '{$group}' AND pos > 0"
					: "SELECT slug, type, updated_at AS lastmod FROM {$items} WHERE type = '{$group}' AND pos > 0 ORDER BY pos ASC";
			case 'author':
				return $count ? "SELECT COUNT(DISTINCT author) FROM {$items} WHERE pos > 0 AND author <> ''"
					: "SELECT author AS slug, 'author' AS type, MAX(updated_at) AS lastmod FROM {$items} WHERE pos > 0 AND author <> '' GROUP BY author ORDER BY SUM(active_installs) DESC";
			default:
				return $count ? "SELECT COUNT(*) FROM {$premium}" : "SELECT slug, type, NULL AS lastmod FROM {$premium} ORDER BY live_sites DESC";
		}
	}

	private static function sitemap( $group, $page ) {
		global $wpdb;
		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

		if ( 'index' === $group ) {
			echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
			foreach ( array( 'theme', 'plugin', 'author', 'premium' ) as $g ) {
				$pages = (int) ceil( (int) $wpdb->get_var( self::sitemap_sql( $g, true ) ) / self::PER_SITEMAP ); // phpcs:ignore
				for ( $p = 1; $p <= $pages; $p++ ) {
					echo '<sitemap><loc>' . esc_url( home_url( '/bwing-sitemap-' . $g . '-' . $p . '.xml' ) ) . '</loc></sitemap>' . "\n";
				}
			}
			echo '</sitemapindex>';
			return;
		}

		$rows = $wpdb->get_results( self::sitemap_sql( $group ) . ' LIMIT ' . ( ( $page - 1 ) * self::PER_SITEMAP ) . ', ' . self::PER_SITEMAP ); // phpcs:ignore
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		if ( 1 === $page && 'premium' !== $group && 'author' !== $group ) {
			echo '<url><loc>' . esc_url( bwing_url( 'theme' === $group ? 'themes' : 'plugins' ) ) . '</loc><changefreq>daily</changefreq></url>' . "\n";
		}
		foreach ( $rows as $r ) {
			if ( 'author' === $group ) {
				$loc = BWing_Format::author_url( $r->slug );
			} elseif ( 'premium' === $group ) {
				$loc = BWing_Format::premium_url( $r->type, $r->slug );
			} else {
				$loc = BWing_Format::item_url( $r->type, $r->slug );
			}
			echo '<url><loc>' . esc_url( $loc ) . '</loc>' . ( $r->lastmod ? '<lastmod>' . esc_html( gmdate( 'Y-m-d', strtotime( $r->lastmod ) ) ) . '</lastmod>' : '' ) . '<changefreq>daily</changefreq></url>' . "\n";
		}
		echo '</urlset>';
	}

	/** /llms.txt — a plain-Markdown map of the site for AI crawlers (llmstxt.org). */
	private static function llms() {
		header( 'Content-Type: text/plain; charset=utf-8' );
		$brand = BWing_Settings::get( 'general', 'brand', get_bloginfo( 'name' ) );
		$out   = '# ' . $brand . "\n\n> " . BWing_Settings::get( 'general', 'tagline', get_bloginfo( 'description' ) ) . "\n\n";
		$out  .= sprintf(
			/* translators: 1 brand 2 date */
			__( '%1$s snapshots the WordPress.org theme and plugin directories every day and publishes rankings, active installs, downloads and rank history for each one. Data last updated %2$s.', 'bwing' ),
			$brand,
			BWing_Data::latest_date()
		) . "\n\n";

		$out .= "## Rankings and statistics\n\n";
		$pages = array(
			'themes'                   => __( 'Most popular WordPress themes, ranked daily', 'bwing' ),
			'plugins'                  => __( 'Most popular WordPress plugins, ranked daily', 'bwing' ),
			'premium-themes'           => __( 'Top premium WordPress themes by live sites', 'bwing' ),
			'premium-plugins'          => __( 'Top premium WordPress plugins by live sites', 'bwing' ),
			'wordpress-theme-stats'    => __( 'WordPress theme statistics', 'bwing' ),
			'wordpress-plugin-stats'   => __( 'WordPress plugin ecosystem statistics', 'bwing' ),
			'authors'                  => __( 'WordPress.org author leaderboard', 'bwing' ),
			'fastest-wordpress-themes' => __( 'Fastest WordPress themes (Lighthouse)', 'bwing' ),
			'theme-detector'           => __( 'Detect the theme and plugins of any WordPress site', 'bwing' ),
			'ai-tools'                 => __( 'Directory of the best AI tools and websites', 'bwing' ),
		);
		foreach ( $pages as $slug => $label ) {
			$out .= '- [' . $label . '](' . bwing_url( $slug ) . ")\n";
		}
		foreach ( array( 'theme' => __( 'Top WordPress themes today', 'bwing' ), 'plugin' => __( 'Top WordPress plugins today', 'bwing' ) ) as $type => $label ) {
			$out .= "\n## " . $label . "\n\n";
			foreach ( BWing_Data::top_items( $type, 25 ) as $i ) {
				$out .= '- [' . $i->name . '](' . BWing_Format::item_url( $type, $i->slug ) . '): #' . (int) $i->pos . ', ' . wp_strip_all_tags( BWing_Format::installs( $i->active_installs ) ) . ' ' . __( 'active installs', 'bwing' ) . ', ' . __( 'by', 'bwing' ) . ' ' . ( $i->author_name ? $i->author_name : $i->author ) . "\n";
			}
		}
		$out .= "\n## Optional\n\n- [" . __( 'XML sitemap of every tracked theme, plugin and author', 'bwing' ) . '](' . self::sitemap_url() . ")\n";
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
