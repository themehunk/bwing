<?php
/**
 * Daily sync from the public WordPress.org API.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Sync {

	const THEMES_API  = 'https://api.wordpress.org/themes/info/1.2/';
	const PLUGINS_API = 'https://api.wordpress.org/plugins/info/1.2/';

	const JOB      = 'bwing_sync_job';
	const PER_PAGE = 100;

	/**
	 * Cron entry point: start a job and work through it in time-boxed batches,
	 * scheduling a continuation event until every page is fetched.
	 */
	public static function run_all() {
		self::start();
		return self::continue_job();
	}

	public static function continue_job() {
		$job = self::step( 25 );
		if ( empty( $job['finished'] ) ) {
			wp_schedule_single_event( time() + 20, 'bwing_sync_continue' );
		}
		return $job;
	}

	public static function job() {
		$job = get_option( self::JOB );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Create (or resume) a sync job: one queue entry per API page.
	 *
	 * @param bool $force Restart even if a recent job is still running.
	 */
	public static function start( $force = false ) {
		$job = self::job();
		if ( $job && ! $force && strtotime( $job['started'] ) > strtotime( current_time( 'mysql' ) ) - 2 * HOUR_IN_SECONDS ) {
			return $job;
		}
		$queue = array();
		$max   = array(
			'theme'  => max( 1, (int) BWing_Settings::get( 'general', 'sync_theme_pages', 50 ) ),
			'plugin' => max( 1, (int) BWing_Settings::get( 'general', 'sync_plugin_pages', 20 ) ),
		);
		foreach ( $max as $type => $pages ) {
			for ( $p = 1; $p <= $pages; $p++ ) {
				$queue[] = array( $type, $p );
			}
		}
		// Featured companies: fetch all their items, even ones outside the ranked pages.
		foreach ( self::pinned_authors() as $author ) {
			$queue[] = array( 'author:theme', $author );
			$queue[] = array( 'author:plugin', $author );
		}
		$job = array(
			'started' => current_time( 'mysql' ),
			'date'    => current_time( 'Y-m-d' ),
			'queue'   => $queue,
			'total'   => count( $queue ),
			'done'    => 0,
			'counts'  => array( 'theme' => 0, 'plugin' => 0 ),
			'errors'  => array(),
		);
		update_option( self::JOB, $job, false );
		return $job;
	}

	/**
	 * Process queued pages for up to $seconds.
	 *
	 * @return array Job state; 'finished' => true when complete.
	 */
	public static function step( $seconds = 20 ) {
		$job = self::job();
		if ( ! $job ) {
			return array( 'finished' => true, 'total' => 0, 'done' => 0, 'counts' => array() );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( $seconds + 60 ); // phpcs:ignore
		}
		$until = microtime( true ) + $seconds;
		while ( $job['queue'] && microtime( true ) < $until ) {
			list( $type, $page ) = array_shift( $job['queue'] );
			$job['done']++;
			if ( 0 === strpos( $type, 'author:' ) ) {
				$job = self::sync_author( $job, substr( $type, 7 ), $page );
				continue;
			}
			$batch = 'theme' === $type ? self::fetch_themes( $page ) : self::fetch_plugins( $page );
			if ( is_wp_error( $batch ) ) {
				$job['errors'][] = ucfirst( $type ) . ' page ' . $page . ': ' . $batch->get_error_message();
				if ( 1 === $page ) {
					$job = self::drop_type( $job, $type ); // API unreachable for this type.
				}
				continue;
			}
			if ( ! $batch ) {
				$job = self::drop_type( $job, $type ); // Past the last page.
				continue;
			}
			foreach ( $batch as $i => $row ) {
				$row['type'] = $type;
				$row['pos']  = ( $page - 1 ) * self::PER_PAGE + $i + 1;
				$id          = self::upsert( $row );
				if ( $id ) {
					$job['counts'][ $type ]++;
					self::snapshot( $id, $job['date'], $row['pos'], $row['active_installs'], $row['downloaded'] );
				}
			}
			$job['updated'] = time();
			update_option( self::JOB, $job, false );
		}
		if ( ! $job['queue'] ) {
			return self::finish( $job );
		}
		update_option( self::JOB, $job, false );
		$job['finished'] = false;
		return $job;
	}

	/** WordPress.org usernames set under Sections → Featured. */
	public static function pinned_authors() {
		return array_slice( array_filter( array_map( 'sanitize_title', explode( ',', (string) BWing_Settings::get( 'featured', 'pinned_authors', '' ) ) ) ), 0, 10 );
	}

	/**
	 * Store every theme or plugin by one author. Items already ranked in this run are
	 * left alone; the rest are kept unranked (pos 0) so they can still be featured.
	 */
	private static function sync_author( $job, $type, $author ) {
		global $wpdb;
		$batch = 'theme' === $type ? self::fetch_themes( 1, $author ) : self::fetch_plugins( 1, $author );
		if ( is_wp_error( $batch ) ) {
			$job['errors'][] = ucfirst( $type ) . 's by ' . $author . ': ' . $batch->get_error_message();
			return $job;
		}
		$t = BWing_DB::table( 'items' );
		foreach ( $batch as $row ) {
			$row['type'] = $type;
			$existing    = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at FROM {$t} WHERE type = %s AND slug = %s", $type, $row['slug'] ) );
			if ( $existing && $existing->updated_at && $existing->updated_at >= $job['started'] ) {
				continue; // Ranked in this run.
			}
			$row['pos']        = 0;
			$row['updated_at'] = current_time( 'mysql' );
			if ( $existing ) {
				$wpdb->update( $t, $row, array( 'id' => $existing->id ) );
				$id = (int) $existing->id;
			} else {
				$row['peak_pos']      = 0;
				$row['first_tracked'] = current_time( 'Y-m-d' );
				$wpdb->insert( $t, $row );
				$id = (int) $wpdb->insert_id;
			}
			if ( $id ) {
				self::snapshot( $id, $job['date'], 0, $row['active_installs'], $row['downloaded'] );
			}
		}
		$job['updated'] = time();
		return $job;
	}

	/** Cancel a running or stuck job. */
	public static function reset() {
		delete_option( self::JOB );
		wp_clear_scheduled_hook( 'bwing_sync_continue' );
	}

	/** Secret key for the server-cron URL (?bwing_cron=KEY). */
	public static function cron_key() {
		$key = get_option( 'bwing_cron_key' );
		if ( ! $key ) {
			$key = wp_generate_password( 24, false );
			update_option( 'bwing_cron_key', $key, false );
		}
		return $key;
	}

	public static function cron_url() {
		return add_query_arg( 'bwing_cron', self::cron_key(), home_url( '/' ) );
	}

	/**
	 * Server cron entry: continues a running job, or starts today's sync if it has not run yet.
	 * Hooked on init; prints a one-line status and exits.
	 */
	public static function maybe_server_cron() {
		if ( empty( $_GET['bwing_cron'] ) || ! hash_equals( self::cron_key(), (string) wp_unslash( $_GET['bwing_cron'] ) ) ) { // phpcs:ignore
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		if ( ! self::job() ) {
			if ( BWing_Data::latest_date() === current_time( 'Y-m-d' ) && empty( $_GET['force'] ) ) { // phpcs:ignore
				echo 'BWing: already synced today.';
				exit;
			}
			self::start( true );
		}
		$job = self::step( 25 );
		echo ! empty( $job['finished'] ) ? 'BWing: sync finished. ' . implode( ' ', $job['log'] ) : 'BWing: page ' . (int) $job['done'] . ' of ' . (int) $job['total'] . ' — call again to continue.';
		exit;
	}

	/**
	 * Diagnostics shown on the dashboard.
	 *
	 * @return array[] [ ok(bool|null), label, detail, fix ].
	 */
	public static function health() {
		$out = array();

		// Outgoing HTTP to WordPress.org.
		$api = get_transient( 'bwing_health_api' );
		if ( false === $api ) {
			$t   = microtime( true );
			$r   = wp_remote_get( self::THEMES_API . '?action=query_themes&request[per_page]=1', array( 'timeout' => 15 ) );
			$api = is_wp_error( $r ) ? 'ERR ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $r ) . ' in ' . round( microtime( true ) - $t, 1 ) . 's';
			set_transient( 'bwing_health_api', $api, 5 * MINUTE_IN_SECONDS );
		}
		$api_ok = 0 === strpos( $api, 'HTTP 200' );
		$out[]  = array( $api_ok, __( 'Connection to api.wordpress.org', 'bwing' ), $api, $api_ok ? '' : __( 'Your host or a firewall blocks outgoing requests. Ask your host to allow outbound HTTPS to api.wordpress.org (port 443). If wp-config.php defines WP_HTTP_BLOCK_EXTERNAL, add api.wordpress.org to WP_ACCESSIBLE_HOSTS.', 'bwing' ) );

		// WP-Cron.
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next     = wp_next_scheduled( 'bwing_daily_sync' );
		if ( ! $next ) {
			wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'bwing_daily_sync' );
			$next = wp_next_scheduled( 'bwing_daily_sync' );
		}
		$overdue = $next && $next < time() - HOUR_IN_SECONDS;
		$cron_ok = ! $disabled && ! $overdue;
		$detail  = $disabled ? __( 'DISABLE_WP_CRON is true in wp-config.php.', 'bwing' ) : ( $overdue ? __( 'The daily event is overdue — WP-Cron is not running.', 'bwing' ) : sprintf( /* translators: %s date */ __( 'Next run %s', 'bwing' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ) ) ) );
		$out[]   = array( $cron_ok, __( 'Daily schedule (WP-Cron)', 'bwing' ), $detail, $cron_ok ? '' : __( 'Add a server cron job (hosting panel → Cron Jobs) that opens the cron URL below every 5 minutes.', 'bwing' ) );

		// Job state.
		$job = self::job();
		if ( $job ) {
			$last  = isset( $job['updated'] ) ? $job['updated'] : strtotime( get_gmt_from_date( $job['started'] ) );
			$stuck = $last < time() - 15 * MINUTE_IN_SECONDS;
			/* translators: 1 done 2 total */
			$out[] = array( ! $stuck, __( 'Current sync job', 'bwing' ), sprintf( __( 'Page %1$d of %2$d', 'bwing' ), $job['done'], $job['total'] ) . ( $stuck ? ' — ' . __( 'no progress for 15+ minutes', 'bwing' ) : '' ), $stuck ? __( 'Click “Reset stuck sync”, then “Run sync without AJAX”.', 'bwing' ) : '' );
		}

		// Freshness.
		$latest = BWing_Data::latest_date();
		$fresh  = $latest && $latest >= gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) );
		$out[]  = array( $fresh, __( 'Latest snapshot', 'bwing' ), $latest ? $latest : __( 'none yet', 'bwing' ), $fresh ? '' : __( 'Run a sync now.', 'bwing' ) );

		// PHP limits.
		$max   = (int) ini_get( 'max_execution_time' );
		$out[] = array( null, __( 'PHP time limit', 'bwing' ), $max ? $max . 's' : __( 'unlimited', 'bwing' ), '' );

		// Last errors.
		$last = get_option( 'bwing_last_sync' );
		if ( $last && ! empty( $last['log'] ) ) {
			$errs = array_filter( $last['log'], function ( $l ) {
				return false !== strpos( $l, 'page' );
			} );
			if ( $errs ) {
				$out[] = array( false, __( 'Errors in last sync', 'bwing' ), implode( ' · ', $errs ), __( 'Usually temporary — run the sync again.', 'bwing' ) );
			}
		}
		return $out;
	}

	private static function drop_type( $job, $type ) {
		$before       = count( $job['queue'] );
		$job['queue'] = array_values( array_filter( $job['queue'], function ( $q ) use ( $type ) {
			return $q[0] !== $type;
		} ) );
		$job['done'] += $before - count( $job['queue'] );
		return $job;
	}

	private static function finish( $job ) {
		global $wpdb;
		$items = BWing_DB::table( 'items' );
		$log   = array();
		foreach ( $job['counts'] as $type => $n ) {
			if ( $n ) {
				// Anything not seen in this run has dropped out of the tracked set.
				$wpdb->query( $wpdb->prepare( "UPDATE {$items} SET pos = 0 WHERE type = %s AND (updated_at IS NULL OR updated_at < %s)", $type, $job['started'] ) ); // phpcs:ignore
			}
			/* translators: 1 count 2 type */
			$log[] = sprintf( __( 'Synced %1$s %2$ss.', 'bwing' ), number_format_i18n( $n ), $type );
		}
		foreach ( array_slice( $job['errors'], 0, 5 ) as $e ) {
			$log[] = $e;
		}
		delete_option( self::JOB );
		BWing_Data::flush_cache();
		update_option( 'bwing_last_sync', array( 'time' => current_time( 'mysql' ), 'log' => $log ) );
		$job['finished'] = true;
		$job['log']      = $log;
		return $job;
	}

	public static function upsert( $row ) {
		global $wpdb;
		$t        = BWing_DB::table( 'items' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, peak_pos, first_tracked, perf_mobile FROM {$t} WHERE type = %s AND slug = %s", $row['type'], $row['slug'] ) );

		$row['updated_at'] = current_time( 'mysql' );
		if ( $existing ) {
			$row['peak_pos'] = ( $existing->peak_pos && $existing->peak_pos < $row['pos'] ) ? $existing->peak_pos : $row['pos'];
			$wpdb->update( $t, $row, array( 'id' => $existing->id ) );
			return (int) $existing->id;
		}
		$row['peak_pos']      = $row['pos'];
		$row['first_tracked'] = current_time( 'Y-m-d' );
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function snapshot( $item_id, $date, $pos, $installs, $downloads ) {
		global $wpdb;
		$t = BWing_DB::table( 'snapshots' );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$t} (item_id, snap_date, pos, active_installs, downloaded) VALUES (%d, %s, %d, %d, %d)
			 ON DUPLICATE KEY UPDATE pos = VALUES(pos), active_installs = VALUES(active_installs), downloaded = VALUES(downloaded)",
			$item_id, $date, $pos, $installs, $downloads
		) );
	}

	private static function request( $url, $args ) {
		$res = wp_remote_get( add_query_arg( $args, $url ), array( 'timeout' => 30 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'bwing_http', 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : new WP_Error( 'bwing_json', 'Invalid JSON' );
	}

	public static function fetch_themes( $page, $author = '' ) {
		$fields = array();
		foreach ( array( 'active_installs', 'downloaded', 'last_updated', 'creation_time', 'tags', 'requires', 'requires_php', 'description', 'extended_author', 'rating', 'num_ratings', 'homepage', 'screenshot_url' ) as $f ) {
			$fields[ $f ] = 1;
		}
		$data = self::request( self::THEMES_API, array(
			'action'  => 'query_themes',
			'request' => $author ? array( 'author' => $author, 'per_page' => 100, 'page' => $page, 'fields' => $fields ) : array( 'browse' => 'popular', 'per_page' => 100, 'page' => $page, 'fields' => $fields ),
		) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( isset( $data['themes'] ) ? $data['themes'] : array() ) as $th ) {
			$tags   = isset( $th['tags'] ) ? (array) $th['tags'] : array();
			$author = isset( $th['author'] ) && is_array( $th['author'] ) ? $th['author'] : array( 'user_nicename' => (string) ( isset( $th['author'] ) ? $th['author'] : '' ) );
			$shot   = isset( $th['screenshot_url'] ) ? $th['screenshot_url'] : '';
			if ( $shot && 0 === strpos( $shot, '//' ) ) {
				$shot = 'https:' . $shot;
			}
			$out[] = array(
				'slug'            => sanitize_title( $th['slug'] ),
				'name'            => sanitize_text_field( html_entity_decode( $th['name'] ) ),
				'author'          => sanitize_title( isset( $author['user_nicename'] ) ? $author['user_nicename'] : '' ),
				'author_name'     => sanitize_text_field( isset( $author['display_name'] ) ? $author['display_name'] : ( isset( $author['user_nicename'] ) ? $author['user_nicename'] : '' ) ),
				'author_avatar'   => self::avatar_url( isset( $author['avatar'] ) ? $author['avatar'] : '' ),
				'author_url'      => self::clean_url( isset( $author['author_url'] ) ? $author['author_url'] : '' ),
				'version'         => sanitize_text_field( isset( $th['version'] ) ? $th['version'] : '' ),
				'rating'          => (float) ( isset( $th['rating'] ) ? $th['rating'] : 0 ),
				'num_ratings'     => (int) ( isset( $th['num_ratings'] ) ? $th['num_ratings'] : 0 ),
				'active_installs' => (int) ( isset( $th['active_installs'] ) ? $th['active_installs'] : 0 ),
				'downloaded'      => (int) ( isset( $th['downloaded'] ) ? $th['downloaded'] : 0 ),
				'is_block'        => ( isset( $tags['full-site-editing'] ) || in_array( 'full-site-editing', $tags, true ) ) ? 1 : 0,
				'image'           => esc_url_raw( $shot ),
				'homepage'        => esc_url_raw( isset( $th['homepage'] ) ? $th['homepage'] : '' ),
				'preview_url'     => esc_url_raw( isset( $th['preview_url'] ) ? $th['preview_url'] : '' ),
				'description'     => wp_kses_post( isset( $th['description'] ) ? $th['description'] : '' ),
				'tags'            => implode( ',', array_map( 'sanitize_text_field', array_keys( $tags ) === range( 0, count( $tags ) - 1 ) ? $tags : array_keys( $tags ) ) ),
				'requires'        => sanitize_text_field( isset( $th['requires'] ) && $th['requires'] ? $th['requires'] : '' ),
				'requires_php'    => sanitize_text_field( isset( $th['requires_php'] ) && $th['requires_php'] ? $th['requires_php'] : '' ),
				'last_updated'    => self::to_date( isset( $th['last_updated'] ) ? $th['last_updated'] : '' ),
				'added'           => self::to_date( isset( $th['creation_time'] ) ? $th['creation_time'] : '' ),
			);
		}
		return $out;
	}

	public static function fetch_plugins( $page, $author = '' ) {
		$data = self::request( self::PLUGINS_API, array(
			'action'  => 'query_plugins',
			'request' => array(
				$author ? 'author' : 'browse' => $author ? $author : 'popular',
				'per_page' => 100,
				'page'     => $page,
				'fields'   => array( 'icons' => 1, 'active_installs' => 1, 'short_description' => 1, 'contributors' => 1, 'description' => 0, 'sections' => 0, 'versions' => 0, 'banners' => 0 ),
			),
		) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( isset( $data['plugins'] ) ? $data['plugins'] : array() ) as $p ) {
			$profile = isset( $p['author_profile'] ) ? $p['author_profile'] : '';
			$author  = $profile ? basename( untrailingslashit( $profile ) ) : '';
			$icons   = isset( $p['icons'] ) ? (array) $p['icons'] : array();
			$icon    = isset( $icons['2x'] ) ? $icons['2x'] : ( isset( $icons['1x'] ) ? $icons['1x'] : ( isset( $icons['svg'] ) ? $icons['svg'] : '' ) );
			$tags    = isset( $p['tags'] ) ? (array) $p['tags'] : array();
			$people  = isset( $p['contributors'] ) ? (array) $p['contributors'] : array();
			$person  = array();
			// Only the author's own photo: other contributors (team accounts, co-devs) are a different person.
			foreach ( $people as $user => $c ) {
				$c_profile = is_array( $c ) && isset( $c['profile'] ) ? untrailingslashit( $c['profile'] ) : '';
				if ( $author && ( sanitize_title( $user ) === sanitize_title( $author ) || ( $profile && untrailingslashit( $profile ) === $c_profile ) ) ) {
					$person = $c;
					break;
				}
			}
			$site    = isset( $p['author'] ) && preg_match( '#href=["\']([^"\']+)#', $p['author'], $m ) ? $m[1] : '';
			$out[]   = array(
				'slug'            => sanitize_title( $p['slug'] ),
				'name'            => sanitize_text_field( html_entity_decode( wp_strip_all_tags( $p['name'] ) ) ),
				'author'          => sanitize_title( $author ),
				'author_name'     => sanitize_text_field( html_entity_decode( wp_strip_all_tags( isset( $p['author'] ) ? $p['author'] : '' ) ) ),
				'author_avatar'   => self::avatar_url( is_array( $person ) && isset( $person['avatar'] ) ? $person['avatar'] : '' ),
				'author_url'      => self::clean_url( $site ),
				'version'         => sanitize_text_field( isset( $p['version'] ) ? $p['version'] : '' ),
				'rating'          => (float) ( isset( $p['rating'] ) ? $p['rating'] : 0 ),
				'num_ratings'     => (int) ( isset( $p['num_ratings'] ) ? $p['num_ratings'] : 0 ),
				'active_installs' => (int) ( isset( $p['active_installs'] ) ? $p['active_installs'] : 0 ),
				'downloaded'      => (int) ( isset( $p['downloaded'] ) ? $p['downloaded'] : 0 ),
				'is_block'        => 0,
				'image'           => esc_url_raw( $icon ),
				'homepage'        => esc_url_raw( isset( $p['homepage'] ) ? $p['homepage'] : '' ),
				'description'     => sanitize_text_field( isset( $p['short_description'] ) ? $p['short_description'] : '' ),
				'tags'            => implode( ',', array_map( 'sanitize_text_field', array_values( $tags ) ) ),
				'requires'        => sanitize_text_field( isset( $p['requires'] ) && $p['requires'] ? $p['requires'] : '' ),
				'requires_php'    => sanitize_text_field( isset( $p['requires_php'] ) && $p['requires_php'] ? $p['requires_php'] : '' ),
				'last_updated'    => self::to_date( isset( $p['last_updated'] ) ? $p['last_updated'] : '' ),
				'added'           => self::to_date( isset( $p['added'] ) ? $p['added'] : '' ),
			);
		}
		return $out;
	}

	/** Gravatar at 128px. */
	private static function avatar_url( $url ) {
		return $url ? esc_url_raw( preg_replace( '/([?&])s=\d+/', '$1s=128', $url ) ) : '';
	}

	/** Author website without tracking parameters. */
	private static function clean_url( $url ) {
		$url = esc_url_raw( (string) $url );
		return $url ? strtok( $url, '?#' ) : '';
	}

	private static function to_date( $value ) {
		if ( ! $value ) {
			return null;
		}
		$value = preg_replace( '/\s*(am|pm)\s*GMT/i', '$1', (string) $value );
		$ts    = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}
}
