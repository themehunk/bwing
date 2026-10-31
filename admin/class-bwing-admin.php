<?php
/**
 * Admin dashboard: overview, per-section settings, items, premium, subscribers, detections, tools.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save_section', 'reset_section', 'sync', 'tool', 'save_item', 'save_premium', 'delete_premium', 'delete_row', 'export_subscribers', 'sync_step', 'sync_reset' ) as $a ) {
			add_action( 'admin_post_bwing_' . $a, array( __CLASS__, 'handle_' . $a ) );
		}
		add_action( 'wp_ajax_bwing_admin_sync', array( __CLASS__, 'ajax_sync' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BWING_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=bwing' ) ) . '">' . esc_html__( 'Dashboard', 'bwing' ) . '</a>' );
			return $links;
		} );
	}

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad"><path d="M2 14c4-1 7-4 10-10 3 6 6 9 10 10-4 1-7 3-10 6-3-3-6-5-10-6z"/></svg>' );
		add_menu_page( 'BWing', 'BWing', self::CAP, 'bwing', array( __CLASS__, 'page_dashboard' ), $icon, 58 );
		$pages = array(
			'bwing'             => array( __( 'Dashboard', 'bwing' ), 'page_dashboard' ),
			'bwing-sections'    => array( __( 'Sections', 'bwing' ), 'page_sections' ),
			'bwing-items'       => array( __( 'Themes & Plugins', 'bwing' ), 'page_items' ),
			'bwing-premium'     => array( __( 'Premium', 'bwing' ), 'page_premium' ),
			'bwing-subscribers' => array( __( 'Subscribers', 'bwing' ), 'page_subscribers' ),
			'bwing-detections'  => array( __( 'Detections', 'bwing' ), 'page_detections' ),
			'bwing-tools'       => array( __( 'Tools & Shortcodes', 'bwing' ), 'page_tools' ),
		);
		foreach ( $pages as $slug => $p ) {
			add_submenu_page( 'bwing', $p[0] . ' ‹ BWing', $p[0], self::CAP, $slug, array( __CLASS__, $p[1] ) );
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'bwing' ) ) {
			return;
		}
		wp_enqueue_style( 'bwing-admin', BWING_URL . 'assets/css/admin.css', array(), BWING_VERSION );
		wp_enqueue_script( 'bwing-admin', BWING_URL . 'assets/js/admin.js', array(), BWING_VERSION, true );
		wp_enqueue_editor();
	}

	/* ------------------------------------------------------------------ */
	/* Layout helpers                                                      */
	/* ------------------------------------------------------------------ */

	private static function header( $title, $sub = '' ) {
		$tabs = array(
			'bwing'             => __( 'Dashboard', 'bwing' ),
			'bwing-sections'    => __( 'Sections', 'bwing' ),
			'bwing-items'       => __( 'Themes & Plugins', 'bwing' ),
			'bwing-premium'     => __( 'Premium', 'bwing' ),
			'bwing-subscribers' => __( 'Subscribers', 'bwing' ),
			'bwing-detections'  => __( 'Detections', 'bwing' ),
			'bwing-tools'       => __( 'Tools & Shortcodes', 'bwing' ),
		);
		$cur = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'bwing'; // phpcs:ignore
		echo '<div class="wrap bwa"><div class="bwa-top"><div class="bwa-brand"><span class="bwa-logo">✦</span><div><h1>' . esc_html( $title ) . '</h1>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div></div><a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank">' . esc_html__( 'View site ↗', 'bwing' ) . '</a></div>';
		echo '<nav class="bwa-tabs">';
		foreach ( $tabs as $slug => $label ) {
			echo '<a class="' . ( $slug === $cur ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( isset( $_GET['bwing_msg'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['bwing_msg'] ) ) ) . '</p></div>'; // phpcs:ignore
		}
	}

	private static function footer() {
		echo '</div>';
	}

	private static function action_url( $action, $args = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'bwing_' . $action ), $args ), admin_url( 'admin-post.php' ) ), 'bwing_' . $action );
	}

	private static function back( $page, $msg, $extra = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'bwing_msg' => rawurlencode( $msg ) ), $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'bwing' ) );
		}
		check_admin_referer( 'bwing_' . $action );
	}

	private static function shortcode_box( $code ) {
		if ( ! $code ) {
			return '';
		}
		return '<div class="bwa-code"><code>' . esc_html( $code ) . '</code><button type="button" class="button button-small" data-bwa-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Copy', 'bwing' ) . '</button></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	public static function page_dashboard() {
		global $wpdb;
		if ( isset( $_GET['bwing_recheck'] ) ) { // phpcs:ignore
			delete_transient( 'bwing_health_api' );
		}
		self::header( __( 'BWing Dashboard', 'bwing' ), __( 'Rankings, sections and tools for your BlogWings site.', 'bwing' ) );
		$c    = BWing_Data::hero_counts();
		$sync = get_option( 'bwing_last_sync' );
		$subs = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . BWing_DB::table( 'subscribers' ) ); // phpcs:ignore
		$det  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . BWing_DB::table( 'detections' ) ); // phpcs:ignore
		$prem = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . BWing_DB::table( 'premium' ) ); // phpcs:ignore

		if ( get_option( 'bwing_demo_data' ) ) {
			echo '<div class="bwa-banner"><div><strong>' . esc_html__( 'You are looking at demo data.', 'bwing' ) . '</strong> ' . esc_html__( 'Default themes, plugins and 90 days of sample history were installed so every section looks complete. Replace it with live WordPress.org data whenever you are ready.', 'bwing' ) . '</div><a class="button button-primary" href="' . esc_url( self::action_url( 'tool', array( 'tool' => 'live' ) ) ) . '" data-bwa-confirm="' . esc_attr__( 'This deletes the demo items and history and runs a live sync. Continue?', 'bwing' ) . '">' . esc_html__( 'Switch to live data', 'bwing' ) . '</a></div>';
		}

		echo '<div class="bwa-stats">';
		foreach ( array(
			array( $c['themes'], __( 'Themes', 'bwing' ), 'bwing-items&type=theme' ),
			array( $c['plugins'], __( 'Plugins', 'bwing' ), 'bwing-items&type=plugin' ),
			array( $c['authors'], __( 'Authors', 'bwing' ), '' ),
			array( $c['days'], __( 'Snapshots', 'bwing' ), '' ),
			array( $prem, __( 'Premium items', 'bwing' ), 'bwing-premium' ),
			array( $subs, __( 'Subscribers', 'bwing' ), 'bwing-subscribers' ),
			array( $det, __( 'Detections', 'bwing' ), 'bwing-detections' ),
		) as $s ) {
			$tag = $s[2] ? 'a href="' . esc_url( admin_url( 'admin.php?page=' . $s[2] ) ) . '"' : 'div';
			echo '<' . $tag . ' class="bwa-stat"><b>' . esc_html( number_format_i18n( $s[0] ) ) . '</b><span>' . esc_html( $s[1] ) . '</span></' . ( $s[2] ? 'a' : 'div' ) . '>'; // phpcs:ignore
		}
		echo '</div>';

		echo '<div class="bwa-grid"><div class="bwa-card"><h2>' . esc_html__( 'Daily sync', 'bwing' ) . '</h2>';
		$next = wp_next_scheduled( 'bwing_daily_sync' );
		echo '<p>' . esc_html__( 'Last sync:', 'bwing' ) . ' <b>' . esc_html( $sync ? $sync['time'] : __( 'never', 'bwing' ) ) . '</b><br>' . esc_html__( 'Next scheduled:', 'bwing' ) . ' <b>' . esc_html( $next ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ) ) : '—' ) . '</b><br>' . esc_html__( 'Latest snapshot:', 'bwing' ) . ' <b>' . esc_html( BWing_Data::latest_date() ? BWing_Data::latest_date() : '—' ) . '</b></p>';
		if ( $sync && ! empty( $sync['log'] ) ) {
			echo '<ul class="bwa-log">';
			foreach ( $sync['log'] as $l ) {
				echo '<li>' . esc_html( $l ) . '</li>';
			}
			echo '</ul>';
		}
		$job = BWing_Sync::job();
		echo '<div class="bwa-sync" data-bwa-sync data-nonce="' . esc_attr( wp_create_nonce( 'bwing_admin_sync' ) ) . '">';
		echo '<div class="bwa-progress"' . ( $job ? '' : ' hidden' ) . '><span style="width:' . esc_attr( $job && $job['total'] ? round( $job['done'] / $job['total'] * 100 ) : 0 ) . '%"></span></div><p class="bwa-sync__status description">' . ( $job ? esc_html__( 'A sync is in progress in the background — click Sync now to follow it here.', 'bwing' ) : '' ) . '</p>';
		/* translators: 1 themes 2 plugins */
		echo '<p class="description">' . esc_html( sprintf( __( 'Fetches up to %1$s themes and %2$s plugins (change in Sections → General).', 'bwing' ), number_format_i18n( BWing_Settings::get( 'general', 'sync_theme_pages', 50 ) * 100 ), number_format_i18n( BWing_Settings::get( 'general', 'sync_plugin_pages', 20 ) * 100 ) ) ) . '</p>';
		echo '<button type="button" class="button button-primary" data-bwa-sync-start>' . esc_html__( 'Sync now from WordPress.org', 'bwing' ) . '</button></div></div>';

		echo '<div class="bwa-card"><h2>' . esc_html__( 'Top today', 'bwing' ) . '</h2><div class="bwa-two">';
		foreach ( array( 'theme' => __( 'Themes', 'bwing' ), 'plugin' => __( 'Plugins', 'bwing' ) ) as $t => $l ) {
			echo '<div><h4>' . esc_html( $l ) . '</h4><ol>';
			foreach ( BWing_Data::top_items( $t, 5 ) as $it ) {
				echo '<li><a href="' . esc_url( BWing_Format::item_url( $t, $it->slug ) ) . '" target="_blank">' . esc_html( $it->name ) . '</a> <small>' . esc_html( BWing_Format::installs( $it->active_installs ) ) . '</small></li>';
			}
			echo '</ol></div>';
		}
		echo '</div></div></div>';

		self::health_card();

		echo '<h2 class="bwa-h2">' . esc_html__( 'Sections', 'bwing' ) . '</h2><p class="description">' . esc_html__( 'Each section has its own settings and a shortcode you can drop into any page, post or widget.', 'bwing' ) . '</p><div class="bwa-sections">';
		foreach ( BWing_Settings::schema() as $key => $def ) {
			if ( 'general' === $key ) {
				continue;
			}
			echo '<div class="bwa-section"><span class="dashicons dashicons-' . esc_attr( $def['icon'] ) . '"></span><strong>' . esc_html( $def['label'] ) . '</strong>' . self::shortcode_box( $def['shortcode'] ) . '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=bwing-sections&section=' . $key ) ) . '">' . esc_html__( 'Edit section', 'bwing' ) . '</a></div>'; // phpcs:ignore
		}
		echo '</div>';
		self::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Sections                                                            */
	/* ------------------------------------------------------------------ */

	public static function page_sections() {
		$schema  = BWing_Settings::schema();
		$current = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'general'; // phpcs:ignore
		if ( ! isset( $schema[ $current ] ) ) {
			$current = 'general';
		}
		$def    = $schema[ $current ];
		$values = BWing_Settings::section( $current );
		self::header( __( 'Sections', 'bwing' ), __( 'Edit the content and defaults of every front-end section.', 'bwing' ) );

		echo '<div class="bwa-split"><aside class="bwa-side">';
		foreach ( $schema as $key => $d ) {
			echo '<a class="' . ( $key === $current ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=bwing-sections&section=' . $key ) ) . '"><span class="dashicons dashicons-' . esc_attr( $d['icon'] ) . '"></span>' . esc_html( $d['label'] ) . '</a>';
		}
		echo '</aside><div class="bwa-main"><div class="bwa-card">';
		echo '<div class="bwa-cardhead"><h2><span class="dashicons dashicons-' . esc_attr( $def['icon'] ) . '"></span> ' . esc_html( $def['label'] ) . '</h2>' . self::shortcode_box( $def['shortcode'] ) . '</div>'; // phpcs:ignore
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'bwing_save_section' );
		echo '<input type="hidden" name="action" value="bwing_save_section"><input type="hidden" name="section" value="' . esc_attr( $current ) . '"><table class="form-table" role="presentation">';
		foreach ( $def['fields'] as $key => $f ) {
			$name = 'bwing[' . $key . ']';
			$id   = 'bwing_' . $current . '_' . $key;
			$val  = isset( $values[ $key ] ) ? $values[ $key ] : $f[2];
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[1] ) . '</label></th><td>';
			switch ( $f[0] ) {
				case 'checkbox':
					echo '<label class="bwa-switch"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $val, 1, false ) . '><span></span></label>';
					break;
				case 'number':
					echo '<input type="number" min="0" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
					break;
				case 'textarea':
					echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea>';
					break;
				case 'repeater':
					echo '<textarea class="large-text code bwa-repeater" rows="' . (int) min( 12, max( 4, substr_count( $val, "\n" ) + 2 ) ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea>';
					if ( ! empty( $f[3] ) ) {
						echo '<p class="description">' . esc_html( $f[3] ) . '</p>';
					}
					break;
				case 'editor':
					wp_editor( $val, $id, array( 'textarea_name' => $name, 'textarea_rows' => 8, 'media_buttons' => false ) );
					break;
				case 'authors':
					echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" list="' . esc_attr( $id ) . '_list" placeholder="themehunk, automattic">';
					echo '<datalist id="' . esc_attr( $id ) . '_list">';
					foreach ( BWing_Data::authors( array( 'per_page' => 300 ) )['rows'] as $au ) {
						echo '<option value="' . esc_attr( $au->author ) . '">' . esc_html( $au->author_name . ' · ' . $au->themes . 'T / ' . $au->plugins . 'P' ) . '</option>';
					}
					echo '</datalist><p class="description">' . esc_html__( 'Their themes and plugins are always shown. Start typing to search authors.', 'bwing' ) . '</p>';
					break;
				default:
					echo '<input type="' . ( 'url' === $f[0] ? 'url' : 'text' ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table><p class="submit"><button class="button button-primary">' . esc_html__( 'Save section', 'bwing' ) . '</button> <a class="button" href="' . esc_url( self::action_url( 'reset_section', array( 'section' => $current ) ) ) . '" data-bwa-confirm="' . esc_attr__( 'Reset this section to its default content?', 'bwing' ) . '">' . esc_html__( 'Reset to defaults', 'bwing' ) . '</a></p></form></div>';

		if ( 'general' !== $current ) {
			echo '<div class="bwa-card bwa-help"><h3>' . esc_html__( 'Using this section', 'bwing' ) . '</h3><p>' . esc_html__( 'Paste the shortcode into any page, post, Shortcode block or text widget. Any setting above can be overridden per use as an attribute, e.g.', 'bwing' ) . '</p>';
			$first = key( $def['fields'] );
			echo self::shortcode_box( rtrim( $def['shortcode'], ']' ) . ' ' . $first . '="…"]' ) . '</div>'; // phpcs:ignore
		}
		echo '</div></div>';
		self::footer();
	}

	public static function handle_save_section() {
		self::guard( 'save_section' );
		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';
		BWing_Settings::save_section( $section, isset( $_POST['bwing'] ) ? (array) $_POST['bwing'] : array() ); // phpcs:ignore
		BWing_Data::flush_cache();
		self::back( 'bwing-sections', __( 'Section saved.', 'bwing' ), array( 'section' => $section ) );
	}

	public static function handle_reset_section() {
		self::guard( 'reset_section' );
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';
		BWing_Settings::reset_section( $section );
		self::back( 'bwing-sections', __( 'Section reset to defaults.', 'bwing' ), array( 'section' => $section ) );
	}

	/* ------------------------------------------------------------------ */
	/* Items                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_items() {
		$edit = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore
		if ( $edit && ( $item = BWing_Data::item_by_id( $edit ) ) ) {
			self::header( __( 'Edit item', 'bwing' ), $item->name );
			echo '<div class="bwa-card"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'bwing_save_item' );
			echo '<input type="hidden" name="action" value="bwing_save_item"><input type="hidden" name="id" value="' . (int) $item->id . '"><p class="description">' . esc_html__( 'Synced fields (name, installs, rating…) are refreshed daily. Fields below are yours and are never overwritten by sync.', 'bwing' ) . '</p><table class="form-table">';
			$fields = array(
				'live_sites'   => __( 'Detected live sites', 'bwing' ),
				'perf_mobile'  => __( 'Lighthouse mobile (0-100)', 'bwing' ),
				'perf_desktop' => __( 'Lighthouse desktop (0-100)', 'bwing' ),
				'lcp'          => __( 'LCP (s)', 'bwing' ),
				'fcp'          => __( 'FCP (s)', 'bwing' ),
				'tbt'          => __( 'TBT (ms)', 'bwing' ),
				'cls'          => 'CLS',
				'page_weight'  => __( 'Page weight (KB)', 'bwing' ),
			);
			foreach ( $fields as $k => $l ) {
				echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="number" step="any" min="0" name="item[' . esc_attr( $k ) . ']" value="' . esc_attr( $item->$k ) . '" class="regular-text"></td></tr>';
			}
			echo '</table><p><button class="button button-primary">' . esc_html__( 'Save', 'bwing' ) . '</button> <a class="button" href="' . esc_url( BWing_Format::item_url( $item->type, $item->slug ) ) . '" target="_blank">' . esc_html__( 'View page ↗', 'bwing' ) . '</a></p></form></div>';
			self::footer();
			return;
		}

		$type = isset( $_GET['type'] ) && 'plugin' === $_GET['type'] ? 'plugin' : 'theme'; // phpcs:ignore
		$s    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$pg   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore
		$res  = BWing_Data::ranking( array( 'type' => $type, 's' => $s, 'page' => $pg, 'per_page' => 50 ) );
		self::header( __( 'Themes & Plugins', 'bwing' ), __( 'Everything currently tracked. Edit an item to add Lighthouse scores or live-site counts.', 'bwing' ) );
		echo '<form class="bwa-filter" method="get"><input type="hidden" name="page" value="bwing-items"><select name="type"><option value="theme"' . selected( $type, 'theme', false ) . '>' . esc_html__( 'Themes', 'bwing' ) . '</option><option value="plugin"' . selected( $type, 'plugin', false ) . '>' . esc_html__( 'Plugins', 'bwing' ) . '</option></select><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Search…', 'bwing' ) . '"><button class="button">' . esc_html__( 'Filter', 'bwing' ) . '</button><span class="bwa-muted">' . esc_html( number_format_i18n( $res['total'] ) ) . ' ' . esc_html__( 'items', 'bwing' ) . '</span></form>';
		echo '<table class="widefat striped bwa-table"><thead><tr><th>#</th><th>' . esc_html__( 'Name', 'bwing' ) . '</th><th>' . esc_html__( 'Author', 'bwing' ) . '</th><th>' . esc_html__( 'Installs', 'bwing' ) . '</th><th>7d</th><th>' . esc_html__( 'Rating', 'bwing' ) . '</th><th>' . esc_html__( 'Lighthouse', 'bwing' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $res['rows'] as $r ) {
			echo '<tr><td>' . (int) $r->pos . '</td><td><strong>' . esc_html( $r->name ) . '</strong><br><code>' . esc_html( $r->slug ) . '</code></td><td>' . esc_html( $r->author_name ) . '</td><td>' . esc_html( BWing_Format::installs( $r->active_installs ) ) . '</td><td>' . wp_kses_post( BWing_Format::trend( $r->d7 ) ) . '</td><td>' . esc_html( BWing_Format::stars( $r->rating ) ) . '★ (' . (int) $r->num_ratings . ')</td><td>' . ( $r->perf_mobile ? (int) $r->perf_mobile . ' / ' . (int) $r->perf_desktop : '—' ) . '</td><td><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=bwing-items&edit=' . $r->id ) ) . '">' . esc_html__( 'Edit', 'bwing' ) . '</a> <a class="button button-small" href="' . esc_url( BWing_Format::item_url( $type, $r->slug ) ) . '" target="_blank">' . esc_html__( 'View', 'bwing' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		self::admin_pager( $res['page'], $res['pages'] );
		self::footer();
	}

	private static function admin_pager( $page, $pages ) {
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links( array( 'base' => add_query_arg( 'pg', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ); // phpcs:ignore
		echo '</div></div>';
	}

	public static function handle_save_item() {
		self::guard( 'save_item' );
		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$in   = isset( $_POST['item'] ) ? (array) wp_unslash( $_POST['item'] ) : array(); // phpcs:ignore
		$data = array();
		foreach ( array( 'live_sites', 'perf_mobile', 'perf_desktop', 'tbt', 'page_weight' ) as $k ) {
			$data[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0;
		}
		foreach ( array( 'lcp', 'fcp', 'cls' ) as $k ) {
			$data[ $k ] = isset( $in[ $k ] ) ? (float) $in[ $k ] : 0;
		}
		$data['perf_mobile']  = min( 100, $data['perf_mobile'] );
		$data['perf_desktop'] = min( 100, $data['perf_desktop'] );
		$wpdb->update( BWing_DB::table( 'items' ), $data, array( 'id' => $id ) );
		BWing_Data::flush_cache();
		self::back( 'bwing-items', __( 'Item saved.', 'bwing' ), array( 'edit' => $id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Premium                                                             */
	/* ------------------------------------------------------------------ */

	public static function page_premium() {
		global $wpdb;
		$t    = BWing_DB::table( 'premium' );
		$edit = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore
		$new  = isset( $_GET['new'] ); // phpcs:ignore
		self::header( __( 'Premium themes & plugins', 'bwing' ), __( 'Commercial products ranked by detected live sites.', 'bwing' ) );

		if ( $edit || $new ) {
			$p = $edit ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $edit ) ) : (object) array( 'id' => 0, 'type' => 'theme', 'slug' => '', 'name' => '', 'vendor' => '', 'price' => '', 'live_sites' => 0, 'prev_live_sites' => 0, 'url' => '', 'image' => '', 'description' => '', 'free_slug' => '' );
			echo '<div class="bwa-card"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'bwing_save_premium' );
			echo '<input type="hidden" name="action" value="bwing_save_premium"><input type="hidden" name="id" value="' . (int) $p->id . '"><table class="form-table">';
			echo '<tr><th>' . esc_html__( 'Type', 'bwing' ) . '</th><td><select name="p[type]"><option value="theme"' . selected( $p->type, 'theme', false ) . '>' . esc_html__( 'Theme', 'bwing' ) . '</option><option value="plugin"' . selected( $p->type, 'plugin', false ) . '>' . esc_html__( 'Plugin', 'bwing' ) . '</option></select></td></tr>';
			$fields = array(
				'name'            => array( __( 'Name', 'bwing' ), 'text' ),
				'slug'            => array( __( 'Slug (folder name)', 'bwing' ), 'text' ),
				'vendor'          => array( __( 'Vendor', 'bwing' ), 'text' ),
				'price'           => array( __( 'Starting price', 'bwing' ), 'text' ),
				'live_sites'      => array( __( 'Live sites', 'bwing' ), 'number' ),
				'prev_live_sites' => array( __( 'Previous live sites', 'bwing' ), 'number' ),
				'url'             => array( __( 'Website URL', 'bwing' ), 'url' ),
				'image'           => array( __( 'Logo / image URL', 'bwing' ), 'url' ),
				'free_slug'       => array( __( 'Free version slug (optional)', 'bwing' ), 'text' ),
			);
			foreach ( $fields as $k => $f ) {
				echo '<tr><th>' . esc_html( $f[0] ) . '</th><td><input type="' . esc_attr( $f[1] ) . '" class="regular-text" name="p[' . esc_attr( $k ) . ']" value="' . esc_attr( $p->$k ) . '"' . ( 'image' === $k ? ' data-bwa-media' : '' ) . '></td></tr>';
			}
			echo '<tr><th>' . esc_html__( 'Description', 'bwing' ) . '</th><td><textarea class="large-text" rows="4" name="p[description]">' . esc_textarea( $p->description ) . '</textarea></td></tr></table><p><button class="button button-primary">' . esc_html__( 'Save', 'bwing' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=bwing-premium' ) ) . '">' . esc_html__( 'Cancel', 'bwing' ) . '</a></p></form></div>';
			self::footer();
			return;
		}

		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bwing-premium&new=1' ) ) . '">' . esc_html__( '+ Add premium item', 'bwing' ) . '</a></p>';
		foreach ( array( 'theme' => __( 'Premium themes', 'bwing' ), 'plugin' => __( 'Premium plugins', 'bwing' ) ) as $type => $label ) {
			echo '<h2 class="bwa-h2">' . esc_html( $label ) . '</h2><table class="widefat striped bwa-table"><thead><tr><th>#</th><th>' . esc_html__( 'Name', 'bwing' ) . '</th><th>' . esc_html__( 'Vendor', 'bwing' ) . '</th><th>' . esc_html__( 'Price', 'bwing' ) . '</th><th>' . esc_html__( 'Live sites', 'bwing' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( BWing_Data::premium( $type, 500 ) as $i => $p ) {
				echo '<tr><td>' . ( $i + 1 ) . '</td><td><strong>' . esc_html( $p->name ) . '</strong></td><td>' . esc_html( $p->vendor ) . '</td><td>' . esc_html( $p->price ) . '</td><td>' . esc_html( number_format_i18n( $p->live_sites ) ) . '</td><td><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=bwing-premium&edit=' . $p->id ) ) . '">' . esc_html__( 'Edit', 'bwing' ) . '</a> <a class="button button-small" href="' . esc_url( self::action_url( 'delete_premium', array( 'id' => $p->id ) ) ) . '" data-bwa-confirm="' . esc_attr__( 'Delete this item?', 'bwing' ) . '">' . esc_html__( 'Delete', 'bwing' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		self::footer();
	}

	public static function handle_save_premium() {
		self::guard( 'save_premium' );
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$in = isset( $_POST['p'] ) ? (array) wp_unslash( $_POST['p'] ) : array(); // phpcs:ignore
		$d  = array(
			'type'            => isset( $in['type'] ) && 'plugin' === $in['type'] ? 'plugin' : 'theme',
			'name'            => sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ),
			'slug'            => sanitize_title( ! empty( $in['slug'] ) ? $in['slug'] : ( isset( $in['name'] ) ? $in['name'] : '' ) ),
			'vendor'          => sanitize_text_field( isset( $in['vendor'] ) ? $in['vendor'] : '' ),
			'price'           => sanitize_text_field( isset( $in['price'] ) ? $in['price'] : '' ),
			'live_sites'      => absint( isset( $in['live_sites'] ) ? $in['live_sites'] : 0 ),
			'prev_live_sites' => absint( isset( $in['prev_live_sites'] ) ? $in['prev_live_sites'] : 0 ),
			'url'             => esc_url_raw( isset( $in['url'] ) ? $in['url'] : '' ),
			'image'           => esc_url_raw( isset( $in['image'] ) ? $in['image'] : '' ),
			'free_slug'       => sanitize_title( isset( $in['free_slug'] ) ? $in['free_slug'] : '' ),
			'description'     => sanitize_textarea_field( isset( $in['description'] ) ? $in['description'] : '' ),
		);
		if ( ! $d['name'] ) {
			self::back( 'bwing-premium', __( 'Name is required.', 'bwing' ), array( 'new' => 1 ) );
		}
		$t = BWing_DB::table( 'premium' );
		if ( $id ) {
			$wpdb->update( $t, $d, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $t, $d );
		}
		self::back( 'bwing-premium', __( 'Premium item saved.', 'bwing' ) );
	}

	public static function handle_delete_premium() {
		self::guard( 'delete_premium' );
		global $wpdb;
		$wpdb->delete( BWing_DB::table( 'premium' ), array( 'id' => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 ) );
		self::back( 'bwing-premium', __( 'Deleted.', 'bwing' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Subscribers + detections                                            */
	/* ------------------------------------------------------------------ */

	public static function page_subscribers() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . BWing_DB::table( 'subscribers' ) . ' ORDER BY created_at DESC LIMIT 500' ); // phpcs:ignore
		self::header( __( 'Subscribers', 'bwing' ), __( 'Emails collected by the newsletter section.', 'bwing' ) );
		echo '<p><a class="button" href="' . esc_url( self::action_url( 'export_subscribers' ) ) . '">' . esc_html__( 'Export CSV', 'bwing' ) . '</a> ' . self::shortcode_box( '[bwing_newsletter]' ) . '</p>'; // phpcs:ignore
		echo '<table class="widefat striped bwa-table"><thead><tr><th>' . esc_html__( 'Email', 'bwing' ) . '</th><th>' . esc_html__( 'Date', 'bwing' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No subscribers yet.', 'bwing' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( $r->created_at ) . '</td><td><a class="button button-small" href="' . esc_url( self::action_url( 'delete_row', array( 'table' => 'subscribers', 'id' => $r->id ) ) ) . '" data-bwa-confirm="' . esc_attr__( 'Remove this subscriber?', 'bwing' ) . '">' . esc_html__( 'Remove', 'bwing' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	public static function handle_export_subscribers() {
		self::guard( 'export_subscribers' );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT email, created_at FROM ' . BWing_DB::table( 'subscribers' ) . ' ORDER BY created_at DESC', ARRAY_A ); // phpcs:ignore
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bwing-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'created_at' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, $r );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	public static function page_detections() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . BWing_DB::table( 'detections' ) . ' ORDER BY created_at DESC LIMIT 300' ); // phpcs:ignore
		self::header( __( 'Detections', 'bwing' ), __( 'Sites scanned with the theme detector.', 'bwing' ) );
		echo '<p>' . self::shortcode_box( '[bwing_detector]' ) . '</p>'; // phpcs:ignore
		echo '<table class="widefat striped bwa-table"><thead><tr><th>' . esc_html__( 'Domain', 'bwing' ) . '</th><th>' . esc_html__( 'Theme', 'bwing' ) . '</th><th>' . esc_html__( 'Plugins', 'bwing' ) . '</th><th>' . esc_html__( 'Date', 'bwing' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No scans yet.', 'bwing' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$pl = (array) json_decode( (string) $r->plugins, true );
			echo '<tr><td><strong>' . esc_html( $r->domain ) . '</strong><br><small>' . esc_html( $r->title ) . '</small></td><td>' . esc_html( $r->theme_name ? $r->theme_name : $r->theme ) . ( $r->parent_theme ? ' <small>(' . esc_html( $r->parent_theme ) . ')</small>' : '' ) . '</td><td><small>' . esc_html( implode( ', ', $pl ) ) . '</small></td><td>' . esc_html( $r->created_at ) . '</td><td><a class="button button-small" href="' . esc_url( self::action_url( 'delete_row', array( 'table' => 'detections', 'id' => $r->id ) ) ) . '">' . esc_html__( 'Delete', 'bwing' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	public static function handle_delete_row() {
		self::guard( 'delete_row' );
		global $wpdb;
		$table = isset( $_GET['table'] ) && 'detections' === $_GET['table'] ? 'detections' : 'subscribers';
		$wpdb->delete( BWing_DB::table( $table ), array( 'id' => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 ) );
		self::back( 'bwing-' . $table, __( 'Deleted.', 'bwing' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Tools                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_tools() {
		self::header( __( 'Tools & Shortcodes', 'bwing' ) );
		$tools = array(
			'sync'    => array( __( 'Sync now', 'bwing' ), __( 'Fetch today’s rankings from WordPress.org and store a snapshot.', 'bwing' ), '' ),
			'live'    => array( __( 'Switch to live data', 'bwing' ), __( 'Delete demo items + history, then run a fresh live sync.', 'bwing' ), __( 'This deletes all tracked items and history. Continue?', 'bwing' ) ),
			'demo'    => array( __( 'Reinstall demo data', 'bwing' ), __( 'Replace all items with the default demo set and 90 days of sample history.', 'bwing' ), __( 'This replaces all tracked items and history. Continue?', 'bwing' ) ),
			'pages'   => array( __( 'Create missing pages & menus', 'bwing' ), __( 'Recreate any default page (Home, Themes, Plugins, Detector…) that was deleted.', 'bwing' ), '' ),
			'repages' => array( __( 'Restore default page content', 'bwing' ), __( 'Overwrite all default pages with the original shortcode layout.', 'bwing' ), __( 'Your edits to the default pages will be replaced. Continue?', 'bwing' ) ),
			'flush'   => array( __( 'Flush permalinks & cache', 'bwing' ), __( 'Fix 404s on /themes/slug/ pages and clear cached statistics.', 'bwing' ), '' ),
		);
		echo '<div class="bwa-sections">';
		foreach ( $tools as $k => $t ) {
			$url = 'sync' === $k ? admin_url( 'admin.php?page=bwing&bwing_autosync=1' ) : self::action_url( 'tool', array( 'tool' => $k ) );
			echo '<div class="bwa-section"><strong>' . esc_html( $t[0] ) . '</strong><p>' . esc_html( $t[1] ) . '</p><a class="button' . ( 'sync' === $k ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $t[2] ? ' data-bwa-confirm="' . esc_attr( $t[2] ) . '"' : '' ) . '>' . esc_html( $t[0] ) . '</a></div>';
		}
		echo '</div><h2 class="bwa-h2">' . esc_html__( 'Shortcode reference', 'bwing' ) . '</h2><table class="widefat striped bwa-table"><thead><tr><th>' . esc_html__( 'Shortcode', 'bwing' ) . '</th><th>' . esc_html__( 'What it shows', 'bwing' ) . '</th><th>' . esc_html__( 'Settings', 'bwing' ) . '</th></tr></thead><tbody>';
		foreach ( BWing_Shortcodes::map() as $tag => $def ) {
			echo '<tr><td>' . self::shortcode_box( '[' . $tag . ']' ) . '</td><td>' . esc_html( $def[2] ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=bwing-sections&section=' . $def[1] ) ) . '">' . esc_html( BWing_Settings::schema()[ $def[1] ]['label'] ) . '</a></td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
		self::footer();
	}

	private static function health_card() {
		echo '<div class="bwa-card bwa-health" id="bwing-health"><h2><span class="dashicons dashicons-heart"></span> ' . esc_html__( 'Sync health', 'bwing' ) . '</h2><table class="bwa-checks">';
		foreach ( BWing_Sync::health() as $c ) {
			$icon = null === $c[0] ? 'ℹ' : ( $c[0] ? '✓' : '✗' );
			$cls  = null === $c[0] ? 'info' : ( $c[0] ? 'ok' : 'bad' );
			echo '<tr class="is-' . esc_attr( $cls ) . '"><td class="bwa-checks__icon">' . esc_html( $icon ) . '</td><th>' . esc_html( $c[1] ) . '</th><td>' . esc_html( $c[2] ) . ( $c[3] ? '<div class="bwa-checks__fix">' . esc_html( $c[3] ) . '</div>' : '' ) . '</td></tr>';
		}
		echo '<tr class="is-info"><td class="bwa-checks__icon">ℹ</td><th>' . esc_html__( 'Sync button (admin-ajax)', 'bwing' ) . '</th><td><span data-bwa-ajax-check>' . esc_html__( 'checking…', 'bwing' ) . '</span></td></tr>';
		echo '</table><p class="bwa-actions">';
		echo '<a class="button" href="' . esc_url( self::action_url( 'sync_step', array( 'start' => 1 ) ) ) . '">' . esc_html__( 'Run sync without AJAX', 'bwing' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( self::action_url( 'sync_reset' ) ) . '" data-bwa-confirm="' . esc_attr__( 'Cancel the current sync job?', 'bwing' ) . '">' . esc_html__( 'Reset stuck sync', 'bwing' ) . '</a> ';
		echo '<a class="button-link" href="' . esc_url( add_query_arg( 'bwing_recheck', 1 ) ) . '">' . esc_html__( 'Re-check', 'bwing' ) . '</a></p>';
		echo '<details class="bwa-cron"><summary>' . esc_html__( 'Server cron (most reliable on live hosts)', 'bwing' ) . '</summary><p>' . esc_html__( 'In your hosting panel → Cron Jobs, add this command to run every 5 minutes. It continues a running sync, or starts today’s sync if it has not run yet. Keep the URL private.', 'bwing' ) . '</p>';
		echo self::shortcode_box( 'wget -q -O - "' . BWing_Sync::cron_url() . '" >/dev/null 2>&1' ) . '<p class="description">' . esc_html__( 'Or with curl:', 'bwing' ) . '</p>' . self::shortcode_box( 'curl -s "' . BWing_Sync::cron_url() . '" >/dev/null' ); // phpcs:ignore
		echo '<p class="description">' . esc_html__( 'Optional: also add define( \'DISABLE_WP_CRON\', true ); to wp-config.php once the server cron works.', 'bwing' ) . '</p></details></div>';
	}

	/** Non-AJAX sync: runs one batch per page load and refreshes until finished. */
	public static function handle_sync_step() {
		self::guard( 'sync_step' );
		if ( ! empty( $_GET['start'] ) && ! BWing_Sync::job() ) {
			BWing_Sync::start( true );
		}
		$job = BWing_Sync::step( 20 );
		if ( ! empty( $job['finished'] ) ) {
			self::back( 'bwing', implode( ' ', isset( $job['log'] ) ? $job['log'] : array( __( 'Sync finished.', 'bwing' ) ) ) );
		}
		$next = self::action_url( 'sync_step' );
		$pct  = $job['total'] ? round( $job['done'] / $job['total'] * 100 ) : 0;
		nocache_headers();
		echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="1;url=' . esc_attr( $next ) . '"><title>' . esc_html__( 'Syncing…', 'bwing' ) . '</title>';
		echo '<body style="font:16px system-ui;padding:60px;text-align:center;color:#14142b"><h2>' . esc_html__( 'Syncing from WordPress.org…', 'bwing' ) . '</h2>';
		echo '<div style="max-width:420px;margin:20px auto;height:12px;border-radius:9px;background:#eeeaf8;overflow:hidden"><div style="height:100%;width:' . (int) $pct . '%;background:linear-gradient(90deg,#5b3df5,#00c2a8)"></div></div>';
		/* translators: 1 pct 2 done 3 total 4 themes 5 plugins */
		echo '<p>' . esc_html( sprintf( __( '%1$d%% — page %2$d of %3$d · %4$s themes, %5$s plugins', 'bwing' ), $pct, $job['done'], $job['total'], number_format_i18n( $job['counts']['theme'] ), number_format_i18n( $job['counts']['plugin'] ) ) ) . '</p>';
		echo '<p style="color:#6b6b85">' . esc_html__( 'Keep this tab open. If it stops, reload the page to continue.', 'bwing' ) . '</p></body>';
		exit;
	}

	public static function handle_sync_reset() {
		self::guard( 'sync_reset' );
		BWing_Sync::reset();
		self::back( 'bwing', __( 'Sync job reset. You can start a new sync now.', 'bwing' ) );
	}

	public static function handle_sync() {
		self::guard( 'sync' );
		BWing_Sync::start( true );
		$job = BWing_Sync::continue_job();
		$log = ! empty( $job['finished'] ) ? $job['log'] : array( __( 'Sync started — it continues in the background.', 'bwing' ) );
		self::back( isset( $_GET['from'] ) ? 'bwing-tools' : 'bwing', implode( ' ', $log ) );
	}

	/** AJAX: start or advance a sync job (one short batch per request). */
	public static function ajax_sync() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( 'bwing_admin_sync', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bwing' ) ), 403 );
		}
		if ( isset( $_POST['mode'] ) && 'ping' === $_POST['mode'] ) {
			wp_send_json_success( array( 'pong' => true ) );
		}
		if ( isset( $_POST['mode'] ) && 'start' === $_POST['mode'] && ! BWing_Sync::job() ) {
			BWing_Sync::start( true );
		}
		$job = BWing_Sync::step( 8 );
		wp_send_json_success( array(
			'finished' => ! empty( $job['finished'] ),
			'done'     => (int) $job['done'],
			'total'    => (int) $job['total'],
			'themes'   => isset( $job['counts']['theme'] ) ? (int) $job['counts']['theme'] : 0,
			'plugins'  => isset( $job['counts']['plugin'] ) ? (int) $job['counts']['plugin'] : 0,
			'log'      => isset( $job['log'] ) ? $job['log'] : array(),
		) );
	}

	public static function handle_tool() {
		self::guard( 'tool' );
		$tool = isset( $_GET['tool'] ) ? sanitize_key( $_GET['tool'] ) : '';
		switch ( $tool ) {
			case 'live':
				BWing_Seed::wipe_items();
				BWing_Sync::start( true );
				wp_safe_redirect( admin_url( 'admin.php?page=bwing&bwing_autosync=1' ) );
				exit;
				break;
			case 'demo':
				BWing_Seed::run( true );
				$msg = __( 'Demo data installed.', 'bwing' );
				break;
			case 'pages':
				BWing_Pages::create();
				$msg = __( 'Pages and menus checked.', 'bwing' );
				break;
			case 'repages':
				BWing_Pages::create( true );
				$msg = __( 'Default page content restored.', 'bwing' );
				break;
			default:
				BWing_Router::add_rules();
				flush_rewrite_rules();
				BWing_Data::flush_cache();
				$msg = __( 'Permalinks flushed and cache cleared.', 'bwing' );
		}
		self::back( 'bwing-tools', $msg );
	}
}

BWing_Admin::init();
