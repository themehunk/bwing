<?php
/**
 * Section settings schema + storage. Every front-end section is described here once;
 * the admin dashboard and the shortcodes both read from this schema.
 *
 * Repeater fields are textareas: one row per line, columns separated by " | ".
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Settings {

	const OPTION = 'bwing_settings';

	private static $cache = null;

	/**
	 * Full schema: section => label, shortcode, icon, fields.
	 */
	public static function schema() {
		$faq_help = __( 'One per line: Question | Answer', 'bwing' );
		return apply_filters( 'bwing_settings_schema', array(
			'general'         => array(
				'label'     => __( 'General', 'bwing' ),
				'icon'      => 'admin-generic',
				'shortcode' => '',
				'fields'    => array(
					'brand'               => array( 'text', __( 'Brand name', 'bwing' ), 'BlogWings' ),
					'tagline'             => array( 'text', __( 'Tagline', 'bwing' ), 'WordPress themes & plugins, ranked every single day.' ),
					'x_url'               => array( 'url', __( 'X / Twitter URL', 'bwing' ), 'https://x.com/wpblogwings' ),
					'per_page'            => array( 'number', __( 'Rows per ranking page', 'bwing' ), 50 ),
					'sync_theme_pages'    => array( 'number', __( 'Theme pages to sync (100 themes per page, 50 = 5,000)', 'bwing' ), 50 ),
					'sync_plugin_pages'   => array( 'number', __( 'Plugin pages to sync (100 plugins per page, 20 = 2,000)', 'bwing' ), 20 ),
					'disclaimer'          => array( 'textarea', __( 'Footer disclaimer', 'bwing' ), 'Not affiliated with or endorsed by the WordPress Foundation. Data sourced from the public WordPress.org API.' ),
					'load_css_everywhere' => array( 'checkbox', __( 'Load BWing CSS/JS on every page', 'bwing' ), 0 ),
				),
			),
			'hero'            => array(
				'label'     => __( 'Hero', 'bwing' ),
				'icon'      => 'cover-image',
				'shortcode' => '[bwing_hero]',
				'fields'    => array(
					'eyebrow'     => array( 'text', __( 'Eyebrow', 'bwing' ), 'Updated daily · Free & premium' ),
					'title'       => array( 'text', __( 'Title', 'bwing' ), 'Every WordPress theme & plugin, ranked daily' ),
					'subtitle'    => array( 'textarea', __( 'Subtitle', 'bwing' ), 'We snapshot WordPress.org every day and show who is climbing, who is slipping and what the real web actually runs — for themes, plugins and the people who build them.' ),
					'placeholder' => array( 'text', __( 'Search placeholder', 'bwing' ), 'Search a theme, plugin or author…' ),
					'buttons'     => array( 'repeater', __( 'Buttons', 'bwing' ), "Popular Themes | /themes/\nPopular Plugins | /plugins/\nPremium Themes | /premium-themes/\nPremium Plugins | /premium-plugins/", __( 'One per line: Label | URL', 'bwing' ) ),
					'show_stats'  => array( 'checkbox', __( 'Show stat counters', 'bwing' ), 1 ),
				),
			),
			'trending'        => array(
				'label'     => __( 'Biggest Moves', 'bwing' ),
				'icon'      => 'chart-line',
				'shortcode' => '[bwing_trending type="both"]',
				'fields'    => array(
					'title'        => array( 'text', __( 'Title', 'bwing' ), 'Biggest moves this week' ),
					'subtitle'     => array( 'text', __( 'Subtitle', 'bwing' ), '7-day rank movement among items with 10K+ active installs.' ),
					'count'        => array( 'number', __( 'Items per list', 'bwing' ), 4 ),
					'days'         => array( 'number', __( 'Compare window (days)', 'bwing' ), 7 ),
					'min_installs' => array( 'number', __( 'Minimum installs', 'bwing' ), 10000 ),
				),
			),
			'notable'         => array(
				'label'     => __( 'Notable Moves', 'bwing' ),
				'icon'      => 'megaphone',
				'shortcode' => '[bwing_notable]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'Notable moves' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'Install-bracket jumps, top-10 crossings and anomalies from the last 30 days.' ),
					'count'    => array( 'number', __( 'Cards', 'bwing' ), 9 ),
					'days'     => array( 'number', __( 'Window (days)', 'bwing' ), 30 ),
				),
			),
			'top_themes'      => array(
				'label'     => __( 'Top Free Themes', 'bwing' ),
				'icon'      => 'admin-appearance',
				'shortcode' => '[bwing_top type="theme"]',
				'fields'    => array(
					'title'     => array( 'text', __( 'Title', 'bwing' ), 'Top free themes' ),
					'subtitle'  => array( 'text', __( 'Subtitle', 'bwing' ), 'Ranked by active installs on WordPress.org.' ),
					'count'     => array( 'number', __( 'Items', 'bwing' ), 20 ),
					'link_text' => array( 'text', __( 'Link text', 'bwing' ), 'See the full theme ranking →' ),
					'link_url'  => array( 'url', __( 'Link URL', 'bwing' ), '/themes/' ),
				),
			),
			'top_plugins'     => array(
				'label'     => __( 'Top Free Plugins', 'bwing' ),
				'icon'      => 'admin-plugins',
				'shortcode' => '[bwing_top type="plugin"]',
				'fields'    => array(
					'title'     => array( 'text', __( 'Title', 'bwing' ), 'Top free plugins' ),
					'subtitle'  => array( 'text', __( 'Subtitle', 'bwing' ), 'Ranked by active installs on WordPress.org.' ),
					'count'     => array( 'number', __( 'Items', 'bwing' ), 20 ),
					'link_text' => array( 'text', __( 'Link text', 'bwing' ), 'See the full plugin ranking →' ),
					'link_url'  => array( 'url', __( 'Link URL', 'bwing' ), '/plugins/' ),
				),
			),
			'premium_themes'  => array(
				'label'     => __( 'Premium Themes', 'bwing' ),
				'icon'      => 'star-filled',
				'shortcode' => '[bwing_top_premium type="theme"]',
				'fields'    => array(
					'title'     => array( 'text', __( 'Title', 'bwing' ), 'Top premium themes' ),
					'subtitle'  => array( 'text', __( 'Subtitle', 'bwing' ), 'Commercial themes ranked by detected live sites.' ),
					'count'     => array( 'number', __( 'Items', 'bwing' ), 20 ),
					'link_text' => array( 'text', __( 'Link text', 'bwing' ), 'All premium themes →' ),
					'link_url'  => array( 'url', __( 'Link URL', 'bwing' ), '/premium-themes/' ),
					'intro'     => array( 'textarea', __( 'Ranking page intro', 'bwing' ), 'Premium themes are not listed on WordPress.org, so we rank them by the number of live websites detected running each one.' ),
				),
			),
			'premium_plugins' => array(
				'label'     => __( 'Premium Plugins', 'bwing' ),
				'icon'      => 'awards',
				'shortcode' => '[bwing_top_premium type="plugin"]',
				'fields'    => array(
					'title'     => array( 'text', __( 'Title', 'bwing' ), 'Top premium plugins' ),
					'subtitle'  => array( 'text', __( 'Subtitle', 'bwing' ), 'Commercial plugins ranked by detected live sites.' ),
					'count'     => array( 'number', __( 'Items', 'bwing' ), 20 ),
					'link_text' => array( 'text', __( 'Link text', 'bwing' ), 'All premium plugins →' ),
					'link_url'  => array( 'url', __( 'Link URL', 'bwing' ), '/premium-plugins/' ),
					'intro'     => array( 'textarea', __( 'Ranking page intro', 'bwing' ), 'Premium plugins are sold outside WordPress.org. We rank them by how many live websites we detect running them.' ),
				),
			),
			'theme_authors'   => array(
				'label'     => __( 'Top Theme Authors', 'bwing' ),
				'icon'      => 'groups',
				'shortcode' => '[bwing_top_authors type="theme"]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'Top theme authors' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'By combined active installs across all their themes.' ),
					'count'    => array( 'number', __( 'Items', 'bwing' ), 20 ),
				),
			),
			'plugin_authors'  => array(
				'label'     => __( 'Top Plugin Authors', 'bwing' ),
				'icon'      => 'businessperson',
				'shortcode' => '[bwing_top_authors type="plugin"]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'Top plugin authors' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'By combined active installs across all their plugins.' ),
					'count'    => array( 'number', __( 'Items', 'bwing' ), 20 ),
				),
			),
			'about'           => array(
				'label'     => __( 'About Block', 'bwing' ),
				'icon'      => 'info',
				'shortcode' => '[bwing_text section="about"]',
				'fields'    => array(
					'title'   => array( 'text', __( 'Title', 'bwing' ), 'What is BlogWings?' ),
					'content' => array( 'editor', __( 'Content', 'bwing' ), "<p>BlogWings is a daily tracker for the WordPress ecosystem. Every morning we capture the public WordPress.org directory — position, active installs and downloads for every listed theme and plugin — and store it as a snapshot.</p><p>Comparing snapshots shows you movement over 1, 7 and 30 days, which is far more useful than a single install bracket. Premium products are ranked separately by detected live sites.</p>" ),
				),
			),
			'ecosystem'       => array(
				'label'     => __( 'Two Catalogs Block', 'bwing' ),
				'icon'      => 'networking',
				'shortcode' => '[bwing_text section="ecosystem"]',
				'fields'    => array(
					'title'   => array( 'text', __( 'Title', 'bwing' ), 'Two catalogs, one ecosystem' ),
					'content' => array( 'editor', __( 'Content', 'bwing' ), "<p><strong>Themes</strong> move slowly: a site has exactly one active theme, so installs are sticky and the top of the list rarely changes.</p><p><strong>Plugins</strong> move fast: a typical site runs 20+ plugins, new categories appear every year and a single feature launch can push a plugin up an entire bracket.</p>" ),
				),
			),
			'beyond'          => array(
				'label'     => __( 'Beyond Rankings', 'bwing' ),
				'icon'      => 'grid-view',
				'shortcode' => '[bwing_beyond]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'Beyond the rankings' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'Deeper tools built on the same daily data.' ),
					'cards'    => array( 'repeater', __( 'Cards', 'bwing' ), "Theme statistics | How many themes exist, block vs classic, abandoned themes and more. | /wordpress-theme-stats/\nPlugin ecosystem | Concentration, ratings and the long tail of the plugin directory. | /wordpress-plugin-stats/\nFastest themes | Lighthouse scores for popular themes on a clean install. | /fastest-wordpress-themes/\nTheme detector | Paste any URL to see the theme and plugins behind it. | /theme-detector/\nHosting checker | Find out who hosts a website and which CDN it uses. | /hosting-checker/\nAuthor stats | Who publishes the most, and who has the most installs. | /wordpress-author-stats/", __( 'One per line: Title | Description | URL', 'bwing' ) ),
				),
			),
			'faq'             => array(
				'label'     => __( 'FAQ', 'bwing' ),
				'icon'      => 'editor-help',
				'shortcode' => '[bwing_faq]',
				'fields'    => array(
					'title' => array( 'text', __( 'Home FAQ title', 'bwing' ), 'Frequently asked questions' ),
					'items' => array( 'repeater', __( 'Home FAQ', 'bwing' ), "What is the most popular WordPress theme? | By active installs on WordPress.org the default theme of the current year usually leads, followed by Hello Elementor and Astra. Check the live theme ranking for today's order.\nWhat is the most popular WordPress plugin? | Elementor, Yoast SEO and Contact Form 7 have held the top spots for years, each with 5M+ active installs.\nWhere does the data come from? | Free products come from the public WordPress.org API. Premium products are ranked by live sites detected on the web.\nHow often is the data updated? | Every day. A snapshot is captured once every 24 hours and movement is calculated against previous snapshots.", $faq_help ),
					'theme_items'  => array( 'repeater', __( 'Themes ranking FAQ', 'bwing' ), "How is the theme ranking calculated? | Themes are ordered by WordPress.org popularity, which mostly follows active installs.\nWhy are installs shown as ranges? | WordPress.org rounds active installs into brackets such as 10K+ or 1M+.\nWhat does a block theme mean? | Block themes are built for the Site Editor and use theme.json and HTML templates instead of PHP.\nHow often does the ranking change? | Positions are recalculated every day from a fresh snapshot.", $faq_help ),
					'plugin_items' => array( 'repeater', __( 'Plugins ranking FAQ', 'bwing' ), "How is the plugin ranking calculated? | Plugins are ordered by WordPress.org popularity, which follows active installs.\nWhy do some plugins jump suddenly? | Install brackets are rounded, so crossing a threshold looks like a big jump.\nAre premium plugins included? | No — premium plugins have their own ranking based on live sites.\nHow often does the ranking change? | Every day.", $faq_help ),
				),
			),
			'cta'             => array(
				'label'     => __( 'Closing CTA', 'bwing' ),
				'icon'      => 'flag',
				'shortcode' => '[bwing_cta]',
				'fields'    => array(
					'title'        => array( 'text', __( 'Title', 'bwing' ), 'Catch every move before everyone else' ),
					'text'         => array( 'textarea', __( 'Text', 'bwing' ), 'Daily rank changes, new top-10 entries and data anomalies — posted as they happen.' ),
					'button_label' => array( 'text', __( 'Button label', 'bwing' ), 'Follow on X' ),
					'button_url'   => array( 'url', __( 'Button URL', 'bwing' ), 'https://x.com/wpblogwings' ),
				),
			),
			'newsletter'      => array(
				'label'     => __( 'Newsletter', 'bwing' ),
				'icon'      => 'email-alt',
				'shortcode' => '[bwing_newsletter]',
				'fields'    => array(
					'title'       => array( 'text', __( 'Title', 'bwing' ), 'The weekly wing report' ),
					'text'        => array( 'text', __( 'Text', 'bwing' ), 'Top movers, new entries and one chart worth seeing. Every Monday.' ),
					'placeholder' => array( 'text', __( 'Placeholder', 'bwing' ), 'you@example.com' ),
					'button'      => array( 'text', __( 'Button', 'bwing' ), 'Subscribe' ),
					'success'     => array( 'text', __( 'Success message', 'bwing' ), 'Thanks! Check your inbox on Monday.' ),
				),
			),
			'ranking'         => array(
				'label'     => __( 'Ranking Tables', 'bwing' ),
				'icon'      => 'list-view',
				'shortcode' => '[bwing_ranking type="theme"]',
				'fields'    => array(
					'theme_title'  => array( 'text', __( 'Themes title', 'bwing' ), 'Most popular WordPress themes' ),
					'theme_intro'  => array( 'textarea', __( 'Themes intro', 'bwing' ), 'Every free theme on WordPress.org, ranked daily by popularity with 1, 7 and 30-day movement.' ),
					'plugin_title' => array( 'text', __( 'Plugins title', 'bwing' ), 'Most popular WordPress plugins' ),
					'plugin_intro' => array( 'textarea', __( 'Plugins intro', 'bwing' ), 'Every free plugin we track on WordPress.org, ranked daily by popularity with 1, 7 and 30-day movement.' ),
					'about'        => array( 'editor', __( 'About this ranking (below table)', 'bwing' ), '<h3>Reading the movement columns</h3><p>▲ means the item climbed positions compared with the snapshot 1, 7 or 30 days ago, ▼ means it fell and ▬ means no change. <em>new</em> means it was not in the tracked set at that time.</p><h3>Directory popularity vs. real-world usage</h3><p>WordPress.org installs only count sites that report back to WordPress.org. Real-world usage counts detected live sites and is a better signal for premium products.</p>' ),
				),
			),
			'authors'         => array(
				'label'     => __( 'Authors Leaderboard', 'bwing' ),
				'icon'      => 'id',
				'shortcode' => '[bwing_authors]',
				'fields'    => array(
					'title' => array( 'text', __( 'Title', 'bwing' ), 'WordPress.org authors leaderboard' ),
					'intro' => array( 'textarea', __( 'Intro', 'bwing' ), 'Theme and plugin authors ranked by the combined active installs of everything they publish.' ),
					'picks' => array( 'text', __( 'Quick-pick author slugs (comma separated)', 'bwing' ), 'automattic,elemntor,yoast,themeisle,brainstormforce' ),
				),
			),
			'stats'           => array(
				'label'     => __( 'Statistics', 'bwing' ),
				'icon'      => 'chart-bar',
				'shortcode' => '[bwing_stats type="theme"]',
				'fields'    => array(
					'theme_title'  => array( 'text', __( 'Theme stats title', 'bwing' ), 'How many WordPress themes are there?' ),
					'theme_intro'  => array( 'textarea', __( 'Theme stats intro', 'bwing' ), 'A snapshot of the WordPress.org theme directory: size, types, install distribution, ratings and abandonment.' ),
					'plugin_title' => array( 'text', __( 'Plugin stats title', 'bwing' ), 'WordPress plugin ecosystem in numbers' ),
					'plugin_intro' => array( 'textarea', __( 'Plugin stats intro', 'bwing' ), 'Concentration, ratings, update freshness and the long tail of the plugin directory.' ),
					'author_title' => array( 'text', __( 'Author stats title', 'bwing' ), 'WordPress author statistics' ),
					'author_intro' => array( 'textarea', __( 'Author stats intro', 'bwing' ), 'Who publishes the most, who has the most installs, and how concentrated the ecosystem is.' ),
				),
			),
			'fastest'         => array(
				'label'     => __( 'Fastest Themes', 'bwing' ),
				'icon'      => 'performance',
				'shortcode' => '[bwing_fastest]',
				'fields'    => array(
					'title' => array( 'text', __( 'Title', 'bwing' ), 'Fastest WordPress themes' ),
					'intro' => array( 'textarea', __( 'Intro', 'bwing' ), 'Lighthouse scores for popular themes tested on a clean WordPress install with demo content. Scores vary ±5 between runs.' ),
					'count' => array( 'number', __( 'Rows', 'bwing' ), 30 ),
				),
			),
			'detector'        => array(
				'label'     => __( 'Theme Detector', 'bwing' ),
				'icon'      => 'search',
				'shortcode' => '[bwing_detector]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'WordPress theme & plugin detector' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'Paste any website address to see the WordPress theme and plugins behind it.' ),
					'button'   => array( 'text', __( 'Button', 'bwing' ), 'Detect theme & plugins' ),
					'note'     => array( 'text', __( 'Note', 'bwing' ), 'Free, no signup. We only read the public home page. Results are cached for 24 hours.' ),
					'reveals'  => array( 'repeater', __( 'What it reveals', 'bwing' ), "Theme & history | Name, version, author, child/parent relationship and ranking history.\nPlugins | Plugins the home page loads, matched from assets, REST routes and markup.\nHosting & stack | WordPress version, server, CDN and hosting provider when exposed.\nSimilar sites | Other scanned sites using the same theme or plugin.", __( 'One per line: Title | Text', 'bwing' ) ),
					'steps'    => array( 'repeater', __( 'How it works', 'bwing' ), "Fetch | We load the public home page like a browser would.\nRead | We read theme and plugin paths, style.css, the generator tag and response headers.\nMatch | Everything is matched against our catalog and linked to its ranking page.", __( 'One per line: Title | Text', 'bwing' ) ),
					'faq'      => array( 'repeater', __( 'FAQ', 'bwing' ), "How accurate is the detector? | Very accurate for themes; plugins are detected only when they load something on the home page.\nDoes it detect child themes? | Yes — the child theme and its parent are both shown.\nCan it find premium themes? | Yes, premium themes are matched against our premium catalog.\nWhy are some plugins missing? | Plugins that only run in the admin or are combined by optimisation plugins cannot be seen.\nIs it free? | Yes, completely free.", $faq_help ),
					'recent'   => array( 'number', __( 'Recent scans shown', 'bwing' ), 8 ),
				),
			),
			'hosting'         => array(
				'label'     => __( 'Hosting Checker', 'bwing' ),
				'icon'      => 'cloud',
				'shortcode' => '[bwing_hosting_checker]',
				'fields'    => array(
					'title'    => array( 'text', __( 'Title', 'bwing' ), 'Who is hosting this website?' ),
					'subtitle' => array( 'text', __( 'Subtitle', 'bwing' ), 'Enter a domain to find its hosting provider, server, CDN and IP address.' ),
					'button'   => array( 'text', __( 'Button', 'bwing' ), 'Check hosting' ),
					'faq'      => array( 'repeater', __( 'FAQ', 'bwing' ), "How does the hosting checker work? | It resolves the domain, reads name servers and HTTP headers and matches them to known providers.\nWhy does it show Cloudflare? | Sites behind Cloudflare hide their origin host — we show the CDN instead.\nIs it free? | Yes.", $faq_help ),
				),
			),
			'single'          => array(
				'label'     => __( 'Single Item Page', 'bwing' ),
				'icon'      => 'media-document',
				'shortcode' => '[bwing_item type="theme" slug="astra"]',
				'fields'    => array(
					'show_perf'    => array( 'checkbox', __( 'Show performance block', 'bwing' ), 1 ),
					'show_history' => array( 'checkbox', __( 'Show history charts', 'bwing' ), 1 ),
					'show_sites'   => array( 'checkbox', __( 'Show detected websites', 'bwing' ), 1 ),
					'show_similar' => array( 'checkbox', __( 'Show similar & same-author items', 'bwing' ), 1 ),
					'disclosure'   => array( 'textarea', __( 'Disclosure', 'bwing' ), 'Some outbound links may be affiliate links. Rankings are based purely on usage data and are never paid.' ),
				),
			),
		) );
	}

	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $section => $def ) {
			foreach ( $def['fields'] as $key => $field ) {
				$out[ $section ][ $key ] = $field[2];
			}
		}
		return $out;
	}

	public static function install_defaults() {
		$current = get_option( self::OPTION, array() );
		update_option( self::OPTION, self::merge( self::defaults(), is_array( $current ) ? $current : array() ) );
		self::$cache = null;
	}

	private static function merge( $defaults, $saved ) {
		foreach ( $defaults as $section => $fields ) {
			if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
				$defaults[ $section ] = array_merge( $fields, $saved[ $section ] );
			}
		}
		return $defaults;
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	public static function section( $section ) {
		$all = self::all();
		return isset( $all[ $section ] ) ? $all[ $section ] : array();
	}

	public static function get( $section, $key, $fallback = '' ) {
		$s = self::section( $section );
		return isset( $s[ $key ] ) ? $s[ $key ] : $fallback;
	}

	public static function save_section( $section, $values ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $section ] ) ) {
			return;
		}
		$all = self::all();
		foreach ( $schema[ $section ]['fields'] as $key => $field ) {
			$raw = isset( $values[ $key ] ) ? wp_unslash( $values[ $key ] ) : '';
			switch ( $field[0] ) {
				case 'checkbox':
					$all[ $section ][ $key ] = empty( $raw ) ? 0 : 1;
					break;
				case 'number':
					$all[ $section ][ $key ] = absint( $raw );
					break;
				case 'url':
					$all[ $section ][ $key ] = esc_url_raw( $raw );
					break;
				case 'editor':
					$all[ $section ][ $key ] = wp_kses_post( $raw );
					break;
				case 'textarea':
				case 'repeater':
					$all[ $section ][ $key ] = sanitize_textarea_field( $raw );
					break;
				default:
					$all[ $section ][ $key ] = sanitize_text_field( $raw );
			}
		}
		update_option( self::OPTION, $all );
		self::$cache = null;
	}

	public static function reset_section( $section ) {
		$all      = self::all();
		$defaults = self::defaults();
		if ( isset( $defaults[ $section ] ) ) {
			$all[ $section ] = $defaults[ $section ];
			update_option( self::OPTION, $all );
			self::$cache = null;
		}
	}

	/**
	 * Parse a repeater textarea into rows of columns.
	 *
	 * @param string $value Raw value.
	 * @return array[]
	 */
	public static function rows( $value ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$rows[] = array_map( 'trim', explode( '|', $line ) );
		}
		return $rows;
	}
}
