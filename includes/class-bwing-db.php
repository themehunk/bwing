<?php
/**
 * Database tables.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_DB {

	const DB_VERSION = '1.1.0';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'bwing_' . $name;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bwing_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		$items     = self::table( 'items' );
		$snapshots = self::table( 'snapshots' );
		$premium   = self::table( 'premium' );
		$detect    = self::table( 'detections' );
		$subs      = self::table( 'subscribers' );

		dbDelta( "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(10) NOT NULL DEFAULT 'theme',
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			author varchar(191) NOT NULL DEFAULT '',
			author_name varchar(255) NOT NULL DEFAULT '',
			author_avatar varchar(500) NOT NULL DEFAULT '',
			author_url varchar(500) NOT NULL DEFAULT '',
			version varchar(50) NOT NULL DEFAULT '',
			rating float NOT NULL DEFAULT 0,
			num_ratings int(11) NOT NULL DEFAULT 0,
			active_installs bigint(20) NOT NULL DEFAULT 0,
			downloaded bigint(20) NOT NULL DEFAULT 0,
			pos int(11) NOT NULL DEFAULT 0,
			peak_pos int(11) NOT NULL DEFAULT 0,
			is_block tinyint(1) NOT NULL DEFAULT 0,
			image varchar(500) NOT NULL DEFAULT '',
			homepage varchar(500) NOT NULL DEFAULT '',
			preview_url varchar(500) NOT NULL DEFAULT '',
			description text NULL,
			tags text NULL,
			requires varchar(20) NOT NULL DEFAULT '',
			requires_php varchar(20) NOT NULL DEFAULT '',
			last_updated date NULL,
			added date NULL,
			live_sites bigint(20) NOT NULL DEFAULT 0,
			perf_mobile tinyint(3) NOT NULL DEFAULT 0,
			perf_desktop tinyint(3) NOT NULL DEFAULT 0,
			lcp float NOT NULL DEFAULT 0,
			fcp float NOT NULL DEFAULT 0,
			tbt int(11) NOT NULL DEFAULT 0,
			cls float NOT NULL DEFAULT 0,
			page_weight int(11) NOT NULL DEFAULT 0,
			first_tracked date NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY type_slug (type,slug),
			KEY type_pos (type,pos),
			KEY author (author)
		) {$c};" );

		dbDelta( "CREATE TABLE {$snapshots} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NOT NULL,
			snap_date date NOT NULL,
			pos int(11) NOT NULL DEFAULT 0,
			active_installs bigint(20) NOT NULL DEFAULT 0,
			downloaded bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY item_date (item_id,snap_date),
			KEY snap_date (snap_date)
		) {$c};" );

		dbDelta( "CREATE TABLE {$premium} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(10) NOT NULL DEFAULT 'theme',
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			vendor varchar(255) NOT NULL DEFAULT '',
			price varchar(50) NOT NULL DEFAULT '',
			live_sites bigint(20) NOT NULL DEFAULT 0,
			prev_live_sites bigint(20) NOT NULL DEFAULT 0,
			url varchar(500) NOT NULL DEFAULT '',
			image varchar(500) NOT NULL DEFAULT '',
			description text NULL,
			free_slug varchar(191) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY type_slug (type,slug)
		) {$c};" );

		dbDelta( "CREATE TABLE {$detect} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain varchar(191) NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			theme varchar(191) NOT NULL DEFAULT '',
			theme_name varchar(255) NOT NULL DEFAULT '',
			parent_theme varchar(191) NOT NULL DEFAULT '',
			plugins longtext NULL,
			stack longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain (domain),
			KEY theme (theme)
		) {$c};" );

		dbDelta( "CREATE TABLE {$subs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL,
			source varchar(50) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) {$c};" );

		update_option( 'bwing_db_version', self::DB_VERSION );
	}
}
