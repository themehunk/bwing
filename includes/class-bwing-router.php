<?php
/**
 * Pretty URLs for single theme, plugin, premium and author pages.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Router {

	private static $current = null;

	public static function init() {
		self::add_rules();
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'resolve' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function add_rules() {
		$map = array(
			'themes'          => 'theme',
			'plugins'         => 'plugin',
			'premium-themes'  => 'premium-theme',
			'premium-plugins' => 'premium-plugin',
			'authors'         => 'author',
		);
		foreach ( $map as $base => $type ) {
			add_rewrite_rule( '^' . $base . '/([^/]+)/?$', 'index.php?bwing_type=' . $type . '&bwing_slug=$matches[1]', 'top' );
		}
		BWing_SEO::add_rules();
	}

	public static function query_vars( $vars ) {
		$vars[] = 'bwing_type';
		$vars[] = 'bwing_slug';
		return $vars;
	}

	/** Current routed object: array(type, object) or null. */
	public static function current() {
		return self::$current;
	}

	public static function resolve() {
		$type = get_query_var( 'bwing_type' );
		$slug = sanitize_title( get_query_var( 'bwing_slug' ) );
		if ( ! $type || ! $slug ) {
			return;
		}
		$obj = null;
		switch ( $type ) {
			case 'theme':
			case 'plugin':
				$obj = BWing_Data::item( $type, $slug );
				break;
			case 'premium-theme':
				$obj = BWing_Data::premium_item( 'theme', $slug );
				break;
			case 'premium-plugin':
				$obj = BWing_Data::premium_item( 'plugin', $slug );
				break;
			case 'author':
				$items = BWing_Data::author_items( $slug );
				$obj   = $items ? (object) array( 'slug' => $slug, 'name' => $items[0]->author_name ? $items[0]->author_name : $slug, 'items' => $items ) : null;
				break;
		}
		if ( ! $obj ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		self::$current = array( 'type' => $type, 'object' => $obj );
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		bwing_enqueue();
	}

	public static function template( $template ) {
		if ( ! self::$current ) {
			return $template;
		}
		$theme_tpl = locate_template( array( 'bwing/page.php' ) );
		return $theme_tpl ? $theme_tpl : BWING_DIR . 'templates/page.php';
	}

	public static function title( $parts ) {
		if ( self::$current ) {
			$o = self::$current['object'];
			switch ( self::$current['type'] ) {
				case 'theme':
					/* translators: %s name */
					$parts['title'] = sprintf( __( '%s Theme — Stats, Ranking & Install History', 'bwing' ), $o->name );
					break;
				case 'plugin':
					/* translators: %s name */
					$parts['title'] = sprintf( __( '%s Plugin — Stats, Ranking & Install History', 'bwing' ), $o->name );
					break;
				case 'author':
					/* translators: %s name */
					$parts['title'] = sprintf( __( '%s — Themes & Plugins Portfolio', 'bwing' ), $o->name );
					break;
				default:
					/* translators: %s name */
					$parts['title'] = sprintf( __( '%s — Live Sites & Market Share', 'bwing' ), $o->name );
			}
		}
		return $parts;
	}

	public static function body_class( $classes ) {
		if ( self::$current ) {
			$classes[] = 'bwing-single';
			$classes[] = 'bwing-single--' . self::$current['type'];
		}
		return $classes;
	}

	/** Render the routed object's content. */
	public static function render() {
		if ( ! self::$current ) {
			return '';
		}
		$o = self::$current['object'];
		switch ( self::$current['type'] ) {
			case 'theme':
			case 'plugin':
				return BWing_Shortcodes::item( array( 'type' => self::$current['type'], 'slug' => $o->slug ) );
			case 'author':
				return BWing_Shortcodes::author_profile( $o );
			default:
				return BWing_Shortcodes::premium_single( $o );
		}
	}
}
