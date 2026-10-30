<?php
/**
 * Default content so every section looks complete before the first sync.
 * Run "Sync now" in the dashboard to replace it with live WordPress.org data.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Seed {

	const HISTORY_DAYS = 90;

	public static function run( $force = false ) {
		global $wpdb;
		if ( ! $force && BWing_Data::count_items() > 0 ) {
			self::premium(); // Only fills missing premium rows.
			return;
		}
		if ( $force ) {
			self::wipe_items();
		}
		self::items();
		self::premium();
		update_option( 'bwing_demo_data', 1 );
		BWing_Data::flush_cache();
	}

	/**
	 * Columns: slug, name, author, author_name, installs, downloads, rating(0-100), ratings, block, added, updated, version, momentum.
	 */
	private static function themes() {
		return array(
			array( 'twentytwentyfive', 'Twenty Twenty-Five', 'wordpressdotorg', 'WordPress.org', 1000000, 9800000, 80, 14, 1, '2024-11-12', '2026-08-05', '1.3', 0 ),
			array( 'hello-elementor', 'Hello Elementor', 'elemntor', 'Elementor', 1000000, 48000000, 88, 420, 0, '2016-10-20', '2026-09-14', '3.4.4', 0 ),
			array( 'astra', 'Astra', 'brainstormforce', 'Brainstorm Force', 1000000, 36000000, 98, 6500, 0, '2017-03-31', '2026-10-21', '4.11.9', 0 ),
			array( 'twentytwentyfour', 'Twenty Twenty-Four', 'wordpressdotorg', 'WordPress.org', 500000, 11000000, 76, 39, 1, '2023-11-07', '2026-04-15', '1.3', 0 ),
			array( 'kadence', 'Kadence', 'kadencewp', 'Kadence WP', 400000, 4900000, 98, 610, 0, '2020-07-14', '2026-10-17', '1.2.24', -2 ),
			array( 'generatepress', 'GeneratePress', 'edge22', 'Tom Usborne', 400000, 8100000, 98, 1300, 0, '2014-08-29', '2026-09-30', '3.6.0', 1 ),
			array( 'oceanwp', 'OceanWP', 'oceanwp', 'OceanWP', 400000, 15200000, 98, 4800, 0, '2016-12-07', '2026-10-02', '4.1.2', 1 ),
			array( 'twentytwentythree', 'Twenty Twenty-Three', 'wordpressdotorg', 'WordPress.org', 400000, 9200000, 72, 52, 1, '2022-11-01', '2026-04-15', '1.6', 0 ),
			array( 'blocksy', 'Blocksy', 'creativethemeshq', 'CreativeThemes', 300000, 3300000, 100, 1250, 0, '2019-07-08', '2026-10-24', '2.1.16', -3 ),
			array( 'twentyseventeen', 'Twenty Seventeen', 'wordpressdotorg', 'WordPress.org', 300000, 6100000, 74, 88, 0, '2016-12-06', '2026-04-15', '3.9', 2 ),
			array( 'extendable', 'Extendable', 'extendify', 'Extendify', 200000, 1300000, 82, 18, 1, '2022-04-13', '2026-10-01', '2.0.29', 0 ),
			array( 'twentytwentyone', 'Twenty Twenty-One', 'wordpressdotorg', 'WordPress.org', 200000, 7800000, 78, 64, 0, '2020-12-08', '2026-04-15', '2.5', 1 ),
			array( 'neve', 'Neve', 'themeisle', 'ThemeIsle', 200000, 3900000, 96, 1050, 0, '2018-11-19', '2026-10-09', '4.1.4', 0 ),
			array( 'twentytwenty', 'Twenty Twenty', 'wordpressdotorg', 'WordPress.org', 200000, 7400000, 80, 70, 0, '2019-11-12', '2026-04-15', '2.9', 1 ),
			array( 'hello-biz', 'Hello Biz', 'elemntor', 'Elementor', 100000, 600000, 90, 22, 0, '2024-10-02', '2026-08-20', '1.2.0', -4 ),
			array( 'twentytwentytwo', 'Twenty Twenty-Two', 'wordpressdotorg', 'WordPress.org', 100000, 6000000, 70, 45, 1, '2022-01-25', '2026-04-15', '2.0', 1 ),
			array( 'royal-elementor-kit', 'Royal Elementor Kit', 'wproyal', 'WP Royal', 100000, 1500000, 98, 330, 0, '2021-05-18', '2026-07-30', '1.0.130', -1 ),
			array( 'popularfx', 'PopularFX', 'pagelayer', 'Pagelayer', 100000, 1200000, 92, 160, 0, '2020-03-24', '2026-09-11', '1.2.7', 0 ),
			array( 'lightning', 'Lightning', 'kurudrive', 'Vektor, Inc.', 100000, 1600000, 92, 44, 0, '2015-05-22', '2026-10-15', '15.30.1', 0 ),
			array( 'twentynineteen', 'Twenty Nineteen', 'wordpressdotorg', 'WordPress.org', 90000, 5200000, 74, 63, 0, '2018-12-06', '2026-04-15', '3.1', 2 ),
			array( 'shapely', 'Shapely', 'colorlib', 'Colorlib', 80000, 1400000, 90, 160, 0, '2016-07-11', '2024-09-02', '1.2.18', -3 ),
			array( 'ashe', 'Ashe', 'wp-royal', 'WP Royal Themes', 70000, 1700000, 98, 900, 0, '2017-08-03', '2026-06-21', '2.249', -2 ),
			array( 'page-builder-framework', 'Page Builder Framework', 'mapsteps', 'David Vongries', 50000, 900000, 98, 230, 0, '2017-01-04', '2026-08-30', '2.11.6', -2 ),
			array( 'graphene', 'Graphene', 'syahir', 'Graphene Themes', 40000, 3200000, 92, 200, 0, '2010-01-21', '2023-03-01', '2.9.4', 3 ),
			array( 'responsive', 'Responsive', 'cyberchimps', 'CyberChimps', 40000, 4800000, 92, 300, 0, '2011-12-07', '2026-08-12', '6.2.1', 2 ),
			array( 'twentythirteen', 'Twenty Thirteen', 'wordpressdotorg', 'WordPress.org', 40000, 4400000, 76, 60, 0, '2013-08-01', '2026-04-15', '4.3', 1 ),
			array( 'envo-royal', 'Envo Royal', 'envothemes', 'EnvoThemes', 20000, 160000, 96, 30, 0, '2022-09-12', '2026-09-26', '1.1.6', -4 ),
			array( 'twentytwelve', 'Twenty Twelve', 'wordpressdotorg', 'WordPress.org', 30000, 5500000, 78, 80, 0, '2012-12-11', '2026-04-15', '4.5', 2 ),
			array( 'bravada', 'Bravada', 'cryoutcreations', 'Cryout Creations', 10000, 240000, 96, 60, 0, '2020-04-01', '2026-03-03', '1.1.4', -3 ),
			array( 'newsnal', 'Newsnal', 'themeansar', 'Themeansar', 10000, 40000, 100, 3, 0, '2025-02-10', '2026-10-20', '1.0.9', -6 ),
		);
	}

	private static function plugins() {
		return array(
			array( 'elementor', 'Elementor Website Builder', 'elemntor', 'Elementor', 10000000, 410000000, 90, 7200, 0, '2016-05-30', '2026-10-27', '3.32.5', 0 ),
			array( 'wordpress-seo', 'Yoast SEO', 'yoast', 'Team Yoast', 10000000, 380000000, 96, 27900, 0, '2010-10-01', '2026-10-21', '26.2', 0 ),
			array( 'contact-form-7', 'Contact Form 7', 'takayukister', 'Takayuki Miyoshi', 10000000, 360000000, 80, 2100, 0, '2007-08-02', '2026-09-19', '6.1.2', 0 ),
			array( 'classic-editor', 'Classic Editor', 'wordpressdotorg', 'WordPress Contributors', 9000000, 120000000, 98, 1200, 0, '2017-10-24', '2026-04-03', '1.6.7', 0 ),
			array( 'woocommerce', 'WooCommerce', 'automattic', 'Automattic', 7000000, 240000000, 90, 4500, 0, '2011-09-27', '2026-10-28', '10.3.3', 0 ),
			array( 'litespeed-cache', 'LiteSpeed Cache', 'litespeedtech', 'LiteSpeed Technologies', 7000000, 80000000, 96, 2600, 0, '2015-12-07', '2026-10-14', '7.6.2', 1 ),
			array( 'akismet', 'Akismet Anti-spam', 'automattic', 'Automattic', 6000000, 410000000, 94, 1100, 0, '2005-10-25', '2026-10-07', '5.5', 0 ),
			array( 'wpforms-lite', 'WPForms', 'smub', 'Syed Balkhi', 6000000, 98000000, 98, 13500, 0, '2016-03-18', '2026-10-23', '1.9.8', 0 ),
			array( 'google-site-kit', 'Site Kit by Google', 'google', 'Google', 5000000, 66000000, 82, 760, 0, '2019-10-31', '2026-10-20', '1.164.0', -1 ),
			array( 'all-in-one-wp-migration', 'All-in-One WP Migration', 'servmask', 'ServMask', 5000000, 79000000, 92, 7900, 0, '2013-11-13', '2026-09-30', '7.100', 1 ),
			array( 'wordfence', 'Wordfence Security', 'mmaunder', 'Wordfence', 5000000, 340000000, 94, 4300, 0, '2011-09-28', '2026-10-22', '8.1.1', 0 ),
			array( 'really-simple-ssl', 'Really Simple Security', 'reallysimpleplugins', 'Really Simple Plugins', 4000000, 52000000, 98, 5800, 0, '2014-08-08', '2026-10-15', '9.5.3', 0 ),
			array( 'wp-mail-smtp', 'WP Mail SMTP', 'smub', 'Syed Balkhi', 4000000, 69000000, 96, 4700, 0, '2007-12-23', '2026-10-09', '4.6.0', 0 ),
			array( 'jetpack', 'Jetpack', 'automattic', 'Automattic', 4000000, 330000000, 76, 1700, 0, '2011-01-20', '2026-10-27', '15.1', 1 ),
			array( 'duplicate-post', 'Yoast Duplicate Post', 'yoast', 'Team Yoast', 4000000, 52000000, 94, 820, 0, '2007-11-09', '2026-08-13', '4.5', 0 ),
			array( 'seo-by-rank-math', 'Rank Math SEO', 'rankmath', 'Rank Math SEO', 3000000, 41000000, 98, 6900, 0, '2019-11-07', '2026-10-24', '1.0.256', -1 ),
			array( 'updraftplus', 'UpdraftPlus', 'davidanderson', 'TeamUpdraft', 3000000, 110000000, 96, 6700, 0, '2012-07-21', '2026-10-16', '1.25.8', 1 ),
			array( 'duplicate-page', 'Duplicate Page', 'mndpsingh287', 'mndpsingh287', 3000000, 30000000, 92, 410, 0, '2015-09-18', '2026-06-04', '4.5.5', 0 ),
			array( 'insert-headers-and-footers', 'WPCode', 'smub', 'Syed Balkhi', 2000000, 20000000, 98, 1800, 0, '2012-12-14', '2026-10-20', '2.3.1', 0 ),
			array( 'hostinger', 'Hostinger Tools', 'hostinger', 'Hostinger', 2000000, 9000000, 80, 60, 0, '2022-12-05', '2026-10-24', '3.0.53', -2 ),
			array( 'imagify', 'Imagify Image Optimizer', 'wp_media', 'WP Media', 900000, 12000000, 90, 1200, 0, '2016-03-29', '2026-10-02', '2.2.6', -1 ),
			array( 'polylang', 'Polylang', 'chouby', 'WP SYNTEX', 800000, 18000000, 94, 1700, 0, '2011-11-09', '2026-10-07', '3.7.4', 1 ),
			array( 'ninja-forms', 'Ninja Forms', 'wpninjas', 'Saturday Drive', 600000, 31000000, 86, 1100, 0, '2011-08-15', '2026-09-17', '3.12.1', 2 ),
			array( 'fluentform', 'Fluent Forms', 'techjewel', 'WPManageNinja', 600000, 5500000, 98, 1700, 0, '2018-03-20', '2026-10-22', '6.1.5', -3 ),
			array( 'siteguard', 'SiteGuard WP Plugin', 'jp-secure', 'JP-Secure', 500000, 5200000, 94, 120, 0, '2014-06-03', '2026-05-20', '1.7.9', 0 ),
			array( 'post-views-counter', 'Post Views Counter', 'dfactory', 'dFactory', 300000, 4700000, 94, 360, 0, '2014-11-05', '2026-09-04', '1.5.6', -2 ),
			array( 'metform', 'MetForm', 'xpeedstudio', 'Wpmet', 300000, 3900000, 90, 260, 0, '2020-03-02', '2026-10-13', '4.0.6', 2 ),
			array( 'patchstack', 'Patchstack Security', 'patchstack', 'Patchstack', 90000, 700000, 92, 20, 0, '2020-10-21', '2026-10-01', '2.3.3', -4 ),
			array( 'speedycache', 'SpeedyCache', 'softaculous', 'Softaculous', 60000, 600000, 90, 70, 0, '2022-11-25', '2026-10-10', '1.3.4', -3 ),
			array( 'wp-paginate', 'WP-Paginate', 'emartin24', 'Max Foundry', 40000, 2300000, 94, 50, 0, '2009-04-22', '2026-02-07', '2.2.4', 3 ),
		);
	}

	/** Lighthouse demo values: slug => mobile, desktop, lcp, fcp, tbt, cls, weight KB. */
	private static function perf() {
		return array(
			'generatepress'          => array( 98, 100, 1.6, 1.2, 0, 0, 34 ),
			'kadence'                => array( 96, 100, 1.8, 1.3, 10, 0, 61 ),
			'twentytwentyfive'       => array( 97, 100, 1.7, 1.3, 0, 0, 48 ),
			'blocksy'                => array( 95, 99, 1.9, 1.4, 20, 0, 70 ),
			'astra'                  => array( 95, 100, 1.9, 1.4, 10, 0, 52 ),
			'neve'                   => array( 94, 99, 2.0, 1.5, 20, 0.01, 58 ),
			'hello-elementor'        => array( 96, 100, 1.8, 1.3, 0, 0, 40 ),
			'twentytwentyfour'       => array( 93, 99, 2.1, 1.5, 0, 0, 120 ),
			'page-builder-framework' => array( 97, 100, 1.7, 1.2, 0, 0, 38 ),
			'oceanwp'                => array( 88, 98, 2.6, 1.8, 60, 0.02, 160 ),
			'popularfx'              => array( 86, 97, 2.8, 1.9, 80, 0.01, 180 ),
			'twentytwentyone'        => array( 92, 99, 2.2, 1.6, 10, 0, 75 ),
			'extendable'             => array( 91, 99, 2.3, 1.6, 10, 0, 140 ),
			'royal-elementor-kit'    => array( 82, 96, 3.1, 2.0, 120, 0.03, 260 ),
			'ashe'                   => array( 80, 95, 3.3, 2.1, 140, 0.04, 310 ),
			'lightning'              => array( 87, 98, 2.7, 1.8, 70, 0.01, 190 ),
		);
	}

	private static function items() {
		global $wpdb;
		$items = BWing_DB::table( 'items' );
		$snaps = BWing_DB::table( 'snapshots' );
		$perf  = self::perf();
		$today = current_time( 'Y-m-d' );

		foreach ( array( 'theme' => self::themes(), 'plugin' => self::plugins() ) as $type => $list ) {
			$ids = array();
			foreach ( $list as $i => $r ) {
				$slug = $r[0];
				$row  = array(
					'type'            => $type,
					'slug'            => $slug,
					'name'            => $r[1],
					'author'          => $r[2],
					'author_name'     => $r[3],
					'active_installs' => $r[4],
					'downloaded'      => $r[5],
					'rating'          => $r[6],
					'num_ratings'     => $r[7],
					'is_block'        => $r[8],
					'added'           => $r[9],
					'last_updated'    => $r[10],
					'version'         => $r[11],
					'pos'             => $i + 1,
					'peak_pos'        => max( 1, $i + 1 - max( 0, $r[12] ) ),
					'image'           => 'theme' === $type ? 'https://ts.w.org/wp-content/themes/' . $slug . '/screenshot.png' : 'https://ps.w.org/' . $slug . '/assets/icon-128x128.png',
					'homepage'        => BWing_Format::wporg_url( $type, $slug ),
					'preview_url'     => 'theme' === $type ? 'https://wp-themes.com/' . $slug . '/' : '',
					'description'     => sprintf( 'theme' === $type ? '%s is a popular WordPress theme by %s. It is lightweight, customizable and works with the block editor and popular page builders.' : '%s by %s is one of the most installed plugins in the WordPress.org directory.', $r[1], $r[3] ),
					'tags'            => 'theme' === $type ? ( $r[8] ? 'full-site-editing,block-patterns,blog,one-column,custom-colors' : 'blog,e-commerce,custom-logo,right-sidebar,custom-menu' ) : 'seo,forms,security,performance',
					'requires'        => '6.0',
					'requires_php'    => '7.4',
					'first_tracked'   => gmdate( 'Y-m-d', strtotime( $today . ' -' . self::HISTORY_DAYS . ' days' ) ),
					'updated_at'      => current_time( 'mysql' ),
				);
				if ( isset( $perf[ $slug ] ) ) {
					list( $row['perf_mobile'], $row['perf_desktop'], $row['lcp'], $row['fcp'], $row['tbt'], $row['cls'], $row['page_weight'] ) = $perf[ $slug ];
				}
				$row['live_sites'] = (int) round( $r[4] * 0.6 );
				$wpdb->replace( $items, $row );
				$ids[ $i ] = array( 'id' => (int) $wpdb->insert_id, 'r' => $r );
			}

			// Synthetic history: positions drift by momentum, re-ranked per day.
			$n = count( $ids );
			for ( $d = self::HISTORY_DAYS; $d >= 0; $d-- ) {
				$date   = gmdate( 'Y-m-d', strtotime( $today . ' -' . $d . ' days' ) );
				$scores = array();
				foreach ( $ids as $i => $x ) {
					mt_srand( crc32( $x['r'][0] . $d ) );
					$noise         = $d ? ( mt_rand( -30, 30 ) / 100 ) : 0;
					$scores[ $i ]  = ( $n - $i ) + ( $x['r'][12] * $d / 12 ) + $noise;
				}
				arsort( $scores );
				$p = 0;
				foreach ( array_keys( $scores ) as $i ) {
					$p++;
					$r        = $ids[ $i ]['r'];
					$growth   = 1 - ( $r[12] * -0.0025 * $d ); // Climbers had fewer installs before.
					$installs = BWing_Data::bracket( (int) round( $r[4] * min( 1.2, max( 0.6, $growth ) ) ) );
					$daily    = max( 1, $r[5] / max( 365, ( time() - strtotime( $r[9] ) ) / DAY_IN_SECONDS ) );
					$wpdb->replace( $snaps, array(
						'item_id'         => $ids[ $i ]['id'],
						'snap_date'       => $date,
						'pos'             => $p,
						'active_installs' => 0 === $d ? $r[4] : $installs,
						'downloaded'      => (int) max( 0, $r[5] - $daily * $d ),
					) );
				}
			}
		}
	}

	/** Columns: type, slug, name, vendor, price, live_sites, prev, url, free_slug. */
	public static function premium_rows() {
		return array(
			array( 'theme', 'divi', 'Divi', 'Elegant Themes', '$89/yr', 1450000, 1440000, 'https://www.elegantthemes.com/gallery/divi/', '' ),
			array( 'theme', 'astra-pro', 'Astra Pro', 'Brainstorm Force', '$47/yr', 1100000, 1080000, 'https://wpastra.com/pro/', 'astra' ),
			array( 'theme', 'avada', 'Avada', 'ThemeFusion', '$69', 920000, 925000, 'https://avada.com/', '' ),
			array( 'theme', 'flatsome', 'Flatsome', 'UX Themes', '$59', 640000, 630000, 'https://flatsome.uxthemes.com/', '' ),
			array( 'theme', 'kadence-pro', 'Kadence Pro', 'StellarWP', '$149/yr', 410000, 395000, 'https://www.kadencewp.com/kadence-theme/pro/', 'kadence' ),
			array( 'theme', 'blocksy-pro', 'Blocksy Pro', 'CreativeThemes', '$49/yr', 260000, 245000, 'https://creativethemes.com/blocksy/pricing/', 'blocksy' ),
			array( 'theme', 'woodmart', 'WoodMart', 'xtemos', '$59', 240000, 236000, 'https://woodmart.xtemos.com/', '' ),
			array( 'theme', 'oceanwp-pro', 'OceanWP Core Extensions', 'OceanWP', '$43/yr', 220000, 221000, 'https://oceanwp.org/core-extensions-bundle/', 'oceanwp' ),
			array( 'theme', 'enfold', 'Enfold', 'Kriesi', '$59', 210000, 214000, 'https://kriesi.at/themes/enfold/', '' ),
			array( 'theme', 'betheme', 'BeTheme', 'Muffin Group', '$60', 190000, 192000, 'https://muffingroup.com/betheme/', '' ),
			array( 'theme', 'salient', 'Salient', 'ThemeNectar', '$60', 170000, 171000, 'https://themenectar.com/salient/', '' ),
			array( 'theme', 'generatepress-premium', 'GP Premium', 'GeneratePress', '$59/yr', 160000, 152000, 'https://generatepress.com/premium/', 'generatepress' ),
			array( 'theme', 'neve-pro', 'Neve Pro', 'ThemeIsle', '$69/yr', 120000, 118000, 'https://themeisle.com/themes/neve/', 'neve' ),
			array( 'theme', 'the7', 'The7', 'Dream-Theme', '$39', 110000, 113000, 'https://the7.io/', '' ),
			array( 'theme', 'bridge', 'Bridge', 'Qode Interactive', '$69', 100000, 104000, 'https://bridge.qodeinteractive.com/', '' ),
			array( 'theme', 'studiopress-genesis', 'Genesis Framework', 'StudioPress', 'Included w/ WP Engine', 95000, 99000, 'https://www.studiopress.com/', '' ),
			array( 'theme', 'impreza', 'Impreza', 'UpSolution', '$59', 80000, 79000, 'https://impreza.us-themes.com/', '' ),
			array( 'theme', 'jupiterx', 'Jupiter X', 'Artbees', '$59', 70000, 72000, 'https://jupiterx.com/', '' ),
			array( 'theme', 'newspaper', 'Newspaper', 'tagDiv', '$59', 65000, 64000, 'https://tagdiv.com/newspaper/', '' ),
			array( 'theme', 'bricks', 'Bricks', 'Bricks Builder', '$79/yr', 60000, 52000, 'https://bricksbuilder.io/', '' ),
			array( 'plugin', 'elementor-pro', 'Elementor Pro', 'Elementor', '$59/yr', 4200000, 4150000, 'https://elementor.com/pro/', 'elementor' ),
			array( 'plugin', 'yoast-seo-premium', 'Yoast SEO Premium', 'Yoast', '$99/yr', 1600000, 1590000, 'https://yoast.com/wordpress/plugins/seo/', 'wordpress-seo' ),
			array( 'plugin', 'slider-revolution', 'Slider Revolution', 'ThemePunch', '$35/yr', 1500000, 1510000, 'https://www.sliderrevolution.com/', '' ),
			array( 'plugin', 'wpbakery', 'WPBakery Page Builder', 'WPBakery', '$64', 1400000, 1420000, 'https://wpbakery.com/', '' ),
			array( 'plugin', 'wp-rocket', 'WP Rocket', 'WP Media', '$59/yr', 1300000, 1280000, 'https://wp-rocket.me/', '' ),
			array( 'plugin', 'rank-math-pro', 'Rank Math PRO', 'Rank Math', '$95.88/yr', 900000, 870000, 'https://rankmath.com/pricing/', 'seo-by-rank-math' ),
			array( 'plugin', 'gravity-forms', 'Gravity Forms', 'Rocketgenius', '$59/yr', 850000, 848000, 'https://www.gravityforms.com/', '' ),
			array( 'plugin', 'monsterinsights-pro', 'MonsterInsights Pro', 'Awesome Motive', '$99.60/yr', 600000, 595000, 'https://www.monsterinsights.com/', '' ),
			array( 'plugin', 'wpforms-pro', 'WPForms Pro', 'Awesome Motive', '$199.50/yr', 560000, 550000, 'https://wpforms.com/', 'wpforms-lite' ),
			array( 'plugin', 'wpml', 'WPML', 'OnTheGoSystems', '$39/yr', 540000, 541000, 'https://wpml.org/', '' ),
			array( 'plugin', 'advanced-custom-fields-pro', 'ACF PRO', 'WP Engine', '$49/yr', 520000, 510000, 'https://www.advancedcustomfields.com/pro/', '' ),
			array( 'plugin', 'essential-addons-pro', 'Essential Addons Pro', 'WPDeveloper', '$39.97/yr', 300000, 292000, 'https://essential-addons.com/', '' ),
			array( 'plugin', 'elementskit-pro', 'ElementsKit Pro', 'Wpmet', '$39/yr', 240000, 236000, 'https://wpmet.com/plugin/elementskit/', '' ),
			array( 'plugin', 'complianz-premium', 'Complianz Premium', 'Complianz', '$59/yr', 230000, 215000, 'https://complianz.io/', '' ),
			array( 'plugin', 'kadence-blocks-pro', 'Kadence Blocks Pro', 'StellarWP', '$149/yr', 210000, 203000, 'https://www.kadencewp.com/kadence-blocks/pro/', '' ),
			array( 'plugin', 'premium-addons-pro', 'Premium Addons Pro', 'Leap13', '$39/yr', 190000, 188000, 'https://premiumaddons.com/', '' ),
			array( 'plugin', 'smart-slider-3-pro', 'Smart Slider 3 Pro', 'Nextend', '$49/yr', 160000, 162000, 'https://smartslider3.com/', '' ),
			array( 'plugin', 'events-calendar-pro', 'Events Calendar Pro', 'StellarWP', '$99/yr', 140000, 141000, 'https://theeventscalendar.com/products/wordpress-events-calendar/', '' ),
			array( 'plugin', 'fluent-forms-pro', 'Fluent Forms Pro', 'WPManageNinja', '$79/yr', 120000, 108000, 'https://fluentforms.com/', 'fluentform' ),
			array( 'plugin', 'popup-maker-pro', 'Popup Maker Pro', 'Code Atlantic', '$87/yr', 90000, 89000, 'https://wppopupmaker.com/', '' ),
		);
	}

	public static function premium() {
		global $wpdb;
		$t = BWing_DB::table( 'premium' );
		foreach ( self::premium_rows() as $i => $r ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE type = %s AND slug = %s", $r[0], $r[1] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert( $t, array(
				'type'            => $r[0],
				'slug'            => $r[1],
				'name'            => $r[2],
				'vendor'          => $r[3],
				'price'           => $r[4],
				'live_sites'      => $r[5],
				'prev_live_sites' => $r[6],
				'url'             => $r[7],
				'free_slug'       => $r[8],
				'description'     => sprintf( '%1$s is a commercial WordPress %2$s by %3$s, currently detected on roughly %4$s live websites.', $r[2], $r[0], $r[3], BWing_Format::short( $r[5] ) ),
				'sort_order'      => $i,
			) );
		}
	}

	/** Remove every item + snapshot (used before a clean live sync). */
	public static function wipe_items() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . BWing_DB::table( 'snapshots' ) ); // phpcs:ignore
		$wpdb->query( 'TRUNCATE TABLE ' . BWing_DB::table( 'items' ) ); // phpcs:ignore
		delete_option( 'bwing_demo_data' );
		BWing_Data::flush_cache();
	}
}
