<?php
/**
 * Front-end AJAX: live search, detector, hosting checker, newsletter.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Ajax {

	public static function init() {
		foreach ( array( 'search', 'detect', 'hosting', 'subscribe' ) as $action ) {
			add_action( 'wp_ajax_bwing_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_bwing_' . $action, array( __CLASS__, $action ) );
		}
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'bwing_front', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired — please reload the page.', 'bwing' ) ), 403 );
		}
	}

	/** Simple per-IP rate limit. */
	private static function throttle( $bucket, $max ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'bwing_rl_' . $bucket . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again in a few minutes.', 'bwing' ) ), 429 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	}

	public static function search() {
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( mb_strlen( $q ) < 2 ) {
			wp_send_json_success( array() );
		}
		$out = array();
		foreach ( BWing_Data::search( $q, 8 ) as $r ) {
			$out[] = array(
				'type'     => $r->type,
				'name'     => $r->name,
				'url'      => BWing_Format::item_url( $r->type, $r->slug ),
				'image'    => $r->image,
				'meta'     => ( 'theme' === $r->type ? __( 'Theme', 'bwing' ) : __( 'Plugin', 'bwing' ) ) . ' · #' . (int) $r->pos . ' · ' . BWing_Format::installs( $r->active_installs ),
			);
		}
		foreach ( BWing_Data::authors( array( 's' => $q, 'per_page' => 3 ) )['rows'] as $a ) {
			$out[] = array(
				'type'  => 'author',
				'name'  => $a->author_name ? $a->author_name : $a->author,
				'url'   => BWing_Format::author_url( $a->author ),
				'image' => '',
				'meta'  => __( 'Author', 'bwing' ) . ' · ' . BWing_Format::short( $a->total, true ),
			);
		}
		wp_send_json_success( $out );
	}

	public static function detect() {
		self::verify();
		self::throttle( 'detect', 30 );
		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$res = BWing_Detector::detect( $url );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'html' => self::detect_html( $res ) ) );
	}

	private static function detect_html( $r ) {
		$row = function ( $p, $label ) {
			$img  = $p['image'] ? '<img class="bw-thumb bw-thumb--sm" src="' . esc_url( $p['image'] ) . '" alt="">' : BWing_Format::thumb( array( 'name' => $p['name'], 'image' => '' ), 'bw-thumb bw-thumb--sm' );
			$name = $p['url'] ? '<a href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['name'] ) . '</a>' : esc_html( $p['name'] );
			$meta = array();
			if ( 'free' === $p['kind'] ) {
				$meta[] = '#' . (int) $p['pos'] . ' · ' . BWing_Format::installs( $p['installs'] );
			} elseif ( 'premium' === $p['kind'] ) {
				$meta[] = __( 'Premium', 'bwing' ) . ' · ' . BWing_Format::short( $p['installs'] ) . ' ' . __( 'live sites', 'bwing' );
			} else {
				$meta[] = __( 'Custom / unlisted', 'bwing' );
			}
			if ( ! empty( $p['version'] ) ) {
				$meta[] = 'v' . $p['version'];
			}
			if ( ! empty( $p['signals'] ) ) {
				$meta[] = implode( ', ', $p['signals'] );
			}
			return '<div class="bw-found">' . $img . '<div><small>' . esc_html( $label ) . '</small><strong>' . $name . '</strong><span>' . esc_html( implode( ' · ', $meta ) ) . '</span></div></div>';
		};
		$o = '<div class="bw-result"><div class="bw-result__head"><img src="' . esc_url( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $r['domain'] ) ) . '" alt=""><div><strong>' . esc_html( $r['title'] ? $r['title'] : $r['domain'] ) . '</strong><small>' . esc_html( $r['domain'] ) . ( $r['cached'] ? ' · ' . esc_html__( 'cached result', 'bwing' ) : '' ) . '</small></div></div>';
		$o .= '<div class="bw-result__grid"><div><h4>' . esc_html__( 'Theme', 'bwing' ) . '</h4>';
		$o .= $r['theme'] ? $row( $r['theme'], $r['parent'] ? __( 'Child theme', 'bwing' ) : __( 'Active theme', 'bwing' ) ) : '<p class="bw-muted">' . esc_html__( 'Theme could not be identified.', 'bwing' ) . '</p>';
		$o .= $r['parent'] ? $row( $r['parent'], __( 'Parent theme', 'bwing' ) ) : '';
		$o .= '<h4>' . esc_html__( 'Stack', 'bwing' ) . '</h4><dl class="bw-details">';
		foreach ( array( 'wordpress' => 'WordPress', 'server' => __( 'Server', 'bwing' ), 'php' => 'PHP', 'cdn' => 'CDN', 'host' => __( 'Hosting', 'bwing' ) ) as $k => $label ) {
			$o .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $r['stack'][ $k ] ? $r['stack'][ $k ] : '—' ) . '</dd>';
		}
		/* translators: %d count */
		$o .= '</dl></div><div><h4>' . esc_html( sprintf( _n( '%d plugin detected', '%d plugins detected', count( $r['plugins'] ), 'bwing' ), count( $r['plugins'] ) ) ) . '</h4>';
		foreach ( $r['plugins'] as $p ) {
			$o .= $row( $p, __( 'Plugin', 'bwing' ) );
		}
		if ( ! $r['plugins'] ) {
			$o .= '<p class="bw-muted">' . esc_html__( 'No plugins visible on the home page.', 'bwing' ) . '</p>';
		}
		return $o . '</div></div></div>';
	}

	public static function hosting() {
		self::verify();
		self::throttle( 'hosting', 30 );
		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$r   = BWing_Detector::hosting( $url );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		$o  = '<div class="bw-result"><div class="bw-result__head"><img src="' . esc_url( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $r['domain'] ) ) . '" alt=""><div><small>' . esc_html__( 'Hosted by', 'bwing' ) . '</small><strong class="bw-result__big">' . esc_html( $r['provider'] ) . '</strong><small>' . esc_html( $r['domain'] ) . '</small></div></div><dl class="bw-details bw-details--wide">';
		$rows = array(
			__( 'IP address', 'bwing' )   => $r['ip'],
			__( 'Reverse DNS', 'bwing' )  => $r['reverse_dns'],
			__( 'DNS provider', 'bwing' ) => $r['dns_by'],
			__( 'Name servers', 'bwing' ) => implode( ', ', $r['nameservers'] ),
			__( 'Web server', 'bwing' )   => $r['server'],
			'CDN'                         => $r['cdn'],
			'PHP'                         => $r['php'],
			'WordPress'                   => $r['wordpress'],
		);
		foreach ( $rows as $k => $v ) {
			$o .= '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ? $v : '—' ) . '</dd>';
		}
		$o .= '</dl></div>';
		wp_send_json_success( array( 'html' => $o ) );
	}

	public static function subscribe() {
		self::verify();
		self::throttle( 'sub', 10 );
		if ( ! empty( $_POST['website'] ) ) { // Honeypot.
			wp_send_json_success();
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'bwing' ) ) );
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . BWing_DB::table( 'subscribers' ) . ' (email, source, created_at) VALUES (%s, %s, %s)', $email, 'site', current_time( 'mysql' ) ) ); // phpcs:ignore
		do_action( 'bwing_subscribed', $email );
		wp_send_json_success();
	}
}
