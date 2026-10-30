<?php
/**
 * Theme / plugin detector and hosting checker.
 * Uses wp_safe_remote_get so private/local addresses are refused.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Detector {

	/** REST namespace => plugin slug. */
	const REST_MAP = array(
		'yoast/v1'            => 'wordpress-seo',
		'wc/v3'               => 'woocommerce',
		'wc/store'            => 'woocommerce',
		'contact-form-7/v1'   => 'contact-form-7',
		'elementor/v1'        => 'elementor',
		'jetpack/v4'          => 'jetpack',
		'rankmath/v1'         => 'seo-by-rank-math',
		'wpforms/v1'          => 'wpforms-lite',
		'litespeed/v1'        => 'litespeed-cache',
		'wordfence/v1'        => 'wordfence',
		'akismet/v1'          => 'akismet',
		'google-site-kit/v1'  => 'google-site-kit',
		'redirection/v1'      => 'redirection',
		'aioseo/v1'           => 'all-in-one-seo-pack',
		'fluentform/v1'       => 'fluentform',
		'wp-rocket/v1'        => 'wp-rocket',
		'ai1wm/v1'            => 'all-in-one-wp-migration',
		'kadence-blocks/v1'   => 'kadence-blocks',
		'wp-statistics/v2'    => 'wp-statistics',
		'mc4wp/v1'            => 'mailchimp-for-wp',
		'complianz/v1'        => 'complianz-gdpr',
		'otter/v1'            => 'otter-blocks',
		'spectra/v1'          => 'ultimate-addons-for-gutenberg',
		'tribe/events/v1'     => 'the-events-calendar',
		'wpml/v1'             => 'wpml',
		'gf/v2'               => 'gravity-forms',
	);

	/** Markup fingerprint => plugin slug. */
	const MARKUP_MAP = array(
		'elementor-kit-'          => 'elementor',
		'Yoast SEO plugin'        => 'wordpress-seo',
		'Rank Math'               => 'seo-by-rank-math',
		'wpcf7'                   => 'contact-form-7',
		'woocommerce-no-js'       => 'woocommerce',
		'Site Kit by Google'      => 'google-site-kit',
		'wpforms-'                => 'wpforms-lite',
		'litespeed'               => 'litespeed-cache',
		'WP Rocket'               => 'wp-rocket',
		'jetpack'                 => 'jetpack',
		'All in One SEO'          => 'all-in-one-seo-pack',
		'Powered by Slider Revolution' => 'slider-revolution',
		'vc_row'                  => 'wpbakery',
		'gform_wrapper'           => 'gravity-forms',
	);

	public static function normalize( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || false === strpos( $parts['host'], '.' ) ) {
			return new WP_Error( 'bwing_url', __( 'Please enter a valid website address.', 'bwing' ) );
		}
		$host = strtolower( preg_replace( '/^www\./i', '', $parts['host'] ) );
		return array(
			'host' => $host,
			'url'  => ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . '/',
		);
	}

	private static function fetch( $url, $method = 'GET' ) {
		$args = array(
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 BWingDetector/1.0',
			'limit_response_size' => 2 * MB_IN_BYTES,
		);
		return 'HEAD' === $method ? wp_safe_remote_head( $url, $args ) : wp_safe_remote_get( $url, $args );
	}

	/**
	 * Detect theme + plugins.
	 *
	 * @param string $input URL.
	 * @return array|WP_Error
	 */
	public static function detect( $input ) {
		$n = self::normalize( $input );
		if ( is_wp_error( $n ) ) {
			return $n;
		}
		$cache_key = 'bwing_detect_' . md5( $n['host'] );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			$cached['cached'] = true;
			return $cached;
		}

		$res = self::fetch( $n['url'] );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'bwing_fetch', sprintf( /* translators: %s error */ __( 'Could not reach the site: %s', 'bwing' ), $res->get_error_message() ) );
		}
		$html    = (string) wp_remote_retrieve_body( $res );
		$headers = wp_remote_retrieve_headers( $res );
		$base    = $n['url'];

		$out = array(
			'domain'  => $n['host'],
			'url'     => $n['url'],
			'title'   => '',
			'is_wp'   => false,
			'theme'   => null,
			'parent'  => null,
			'plugins' => array(),
			'stack'   => self::stack( $headers, $html, $n['host'] ),
			'cached'  => false,
		);

		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			$out['title'] = wp_strip_all_tags( html_entity_decode( trim( $m[1] ), ENT_QUOTES ) );
		}
		$out['is_wp'] = (bool) preg_match( '#/wp-(content|includes)/|wp-json|<meta name="generator" content="WordPress#i', $html );

		// Content path may be customised; detect it from any theme/plugin URL.
		$content = 'wp-content';
		if ( preg_match( '#/([a-z0-9_\-/]+)/themes/[a-z0-9_\-]+/#i', $html, $cm ) && false === strpos( $cm[1], 'wp-content' ) ) {
			$content = trim( $cm[1], '/' );
		}

		// Themes.
		preg_match_all( '#/' . preg_quote( $content, '#' ) . '/themes/([a-z0-9_\-]+)/#i', $html, $tm );
		$themes = array_count_values( array_map( 'strtolower', $tm[1] ) );
		arsort( $themes );
		if ( $themes ) {
			$info  = array();
			$child = null;
			foreach ( array_slice( array_keys( $themes ), 0, 2 ) as $slug ) {
				$info[ $slug ] = self::theme_header( $base . $content . '/themes/' . $slug . '/style.css', $slug );
				if ( ! empty( $info[ $slug ]['template'] ) ) {
					$child = $slug;
				}
			}
			$main          = $child ? $child : key( $themes );
			$out['theme']  = self::enrich( 'theme', $main, $info[ $main ] );
			if ( $child ) {
				$parent        = $info[ $child ]['template'];
				$out['parent'] = self::enrich( 'theme', $parent, isset( $info[ $parent ] ) ? $info[ $parent ] : array( 'name' => $parent ) );
			}
			$out['is_wp'] = true;
		}

		// Plugins from asset paths.
		$plugins = array();
		preg_match_all( '#/' . preg_quote( $content, '#' ) . '/plugins/([a-z0-9_\-]+)/#i', $html, $pm );
		foreach ( array_unique( array_map( 'strtolower', $pm[1] ) ) as $slug ) {
			$plugins[ $slug ] = array( 'asset' );
		}
		// Markup fingerprints.
		foreach ( self::MARKUP_MAP as $needle => $slug ) {
			if ( false !== stripos( $html, $needle ) ) {
				$plugins[ $slug ][] = 'markup';
			}
		}
		// REST namespaces.
		if ( $out['is_wp'] ) {
			$rest = self::fetch( $base . 'wp-json/' );
			if ( ! is_wp_error( $rest ) && 200 === wp_remote_retrieve_response_code( $rest ) ) {
				$json = json_decode( wp_remote_retrieve_body( $rest ), true );
				foreach ( (array) ( isset( $json['namespaces'] ) ? $json['namespaces'] : array() ) as $ns ) {
					if ( isset( self::REST_MAP[ $ns ] ) ) {
						$plugins[ self::REST_MAP[ $ns ] ][] = 'rest';
					}
				}
				if ( ! $out['title'] && ! empty( $json['name'] ) ) {
					$out['title'] = sanitize_text_field( $json['name'] );
				}
			}
		}
		foreach ( $plugins as $slug => $signals ) {
			$p            = self::enrich( 'plugin', $slug, array() );
			$p['signals'] = array_values( array_unique( $signals ) );
			$out['plugins'][] = $p;
		}
		usort( $out['plugins'], function ( $a, $b ) {
			return $b['installs'] <=> $a['installs'];
		} );

		if ( ! $out['is_wp'] ) {
			return new WP_Error( 'bwing_notwp', __( 'This site does not appear to run WordPress (or it hides it well).', 'bwing' ) );
		}

		self::save( $out );
		set_transient( $cache_key, $out, DAY_IN_SECONDS );
		return $out;
	}

	private static function theme_header( $url, $slug ) {
		$res  = self::fetch( $url );
		$info = array( 'name' => $slug, 'version' => '', 'author' => '', 'template' => '' );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return $info;
		}
		$css = substr( (string) wp_remote_retrieve_body( $res ), 0, 8192 );
		foreach ( array( 'name' => 'Theme Name', 'version' => 'Version', 'author' => 'Author', 'template' => 'Template' ) as $k => $h ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $h, '/' ) . ':(.*)$/mi', $css, $m ) ) {
				$info[ $k ] = sanitize_text_field( trim( $m[1] ) );
			}
		}
		return $info;
	}

	/** Attach catalog data (free or premium) to a detected slug. */
	private static function enrich( $type, $slug, $info ) {
		$item = BWing_Data::item( $type, $slug );
		$out  = array(
			'slug'     => $slug,
			'name'     => ! empty( $info['name'] ) ? $info['name'] : ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ),
			'version'  => isset( $info['version'] ) ? $info['version'] : '',
			'author'   => isset( $info['author'] ) ? $info['author'] : '',
			'url'      => '',
			'image'    => '',
			'installs' => 0,
			'pos'      => 0,
			'kind'     => 'custom',
		);
		if ( $item ) {
			$out['name']     = $item->name;
			$out['url']      = BWing_Format::item_url( $type, $slug );
			$out['image']    = $item->image;
			$out['installs'] = (int) $item->active_installs;
			$out['pos']      = (int) $item->pos;
			$out['kind']     = 'free';
			return $out;
		}
		$premium = BWing_Data::premium_item( $type, $slug );
		if ( ! $premium ) {
			// Common aliases (e.g. "Divi" folder is "Divi").
			$premium = BWing_Data::premium_item( $type, strtolower( $slug ) );
		}
		if ( $premium ) {
			$out['name']     = $premium->name;
			$out['url']      = BWing_Format::premium_url( $type, $premium->slug );
			$out['image']    = $premium->image;
			$out['installs'] = (int) $premium->live_sites;
			$out['kind']     = 'premium';
		}
		return $out;
	}

	private static function save( $d ) {
		global $wpdb;
		$wpdb->replace( BWing_DB::table( 'detections' ), array(
			'domain'       => $d['domain'],
			'title'        => mb_substr( $d['title'], 0, 250 ),
			'theme'        => $d['theme'] ? $d['theme']['slug'] : '',
			'theme_name'   => $d['theme'] ? $d['theme']['name'] : '',
			'parent_theme' => $d['parent'] ? $d['parent']['slug'] : '',
			'plugins'      => wp_json_encode( wp_list_pluck( $d['plugins'], 'slug' ) ),
			'stack'        => wp_json_encode( $d['stack'] ),
			'created_at'   => current_time( 'mysql' ),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Stack + hosting                                                     */
	/* ------------------------------------------------------------------ */

	private static function header( $headers, $name ) {
		$v = isset( $headers[ $name ] ) ? $headers[ $name ] : '';
		return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}

	public static function stack( $headers, $html, $host ) {
		$s = array(
			'wordpress' => '',
			'server'    => self::header( $headers, 'server' ),
			'php'       => '',
			'cdn'       => '',
			'host'      => '',
		);
		if ( preg_match( '#<meta name="generator" content="WordPress ([0-9.]+)#i', $html, $m ) ) {
			$s['wordpress'] = $m[1];
		} elseif ( preg_match( '#wp-includes/[^"\']+\?ver=([0-9]\.[0-9.]+)#i', $html, $m ) ) {
			$s['wordpress'] = $m[1];
		}
		$powered = self::header( $headers, 'x-powered-by' );
		if ( preg_match( '#PHP/([0-9.]+)#i', $powered, $m ) ) {
			$s['php'] = $m[1];
		}
		$flat = strtolower( $s['server'] . ' ' . $powered );
		foreach ( $headers as $k => $v ) {
			$flat .= ' ' . strtolower( $k ) . ':' . strtolower( is_array( $v ) ? implode( ',', $v ) : $v );
		}
		$cdn = array(
			'cf-ray'           => 'Cloudflare',
			'x-sucuri-id'      => 'Sucuri',
			'x-fastly'         => 'Fastly',
			'fastly'           => 'Fastly',
			'akamai'           => 'Akamai',
			'x-amz-cf-id'      => 'Amazon CloudFront',
			'bunnycdn'         => 'Bunny CDN',
			'x-qc-pop'         => 'QUIC.cloud',
			'x-ezoic'          => 'Ezoic',
		);
		foreach ( $cdn as $needle => $name ) {
			if ( false !== strpos( $flat, $needle ) ) {
				$s['cdn'] = $name;
				break;
			}
		}
		$hosts = array(
			'x-kinsta-cache' => 'Kinsta',
			'wp engine'      => 'WP Engine',
			'wpe-backend'    => 'WP Engine',
			'x-pantheon'     => 'Pantheon',
			'flywheel'       => 'Flywheel',
			'x-siteground'   => 'SiteGround',
			'sg-optimizer'   => 'SiteGround',
			'x-proxy-cache'  => 'SiteGround',
			'x-hcdn'         => 'Hostinger',
			'hostinger'      => 'Hostinger',
			'x-litespeed'    => 'LiteSpeed server',
			'pressable'      => 'Pressable',
			'x-ac:'          => 'WordPress VIP / WordPress.com',
			'x-nananana'     => 'WordPress.com',
			'cloudways'      => 'Cloudways',
			'x-vercel'       => 'Vercel',
			'netlify'        => 'Netlify',
			'x-github'       => 'GitHub Pages',
		);
		foreach ( $hosts as $needle => $name ) {
			if ( false !== strpos( $flat, $needle ) ) {
				$s['host'] = $name;
				break;
			}
		}
		return $s;
	}

	/** Name server / reverse DNS => provider. */
	const DNS_MAP = array(
		'cloudflare'        => 'Cloudflare',
		'siteground'        => 'SiteGround',
		'bluehost'          => 'Bluehost',
		'hostgator'         => 'HostGator',
		'domaincontrol'     => 'GoDaddy',
		'secureserver'      => 'GoDaddy',
		'dns-parking'       => 'Hostinger',
		'hostinger'         => 'Hostinger',
		'kinsta'            => 'Kinsta',
		'wpengine'          => 'WP Engine',
		'a2hosting'         => 'A2 Hosting',
		'dreamhost'         => 'DreamHost',
		'registrar-servers' => 'Namecheap',
		'awsdns'            => 'Amazon Web Services',
		'amazonaws'         => 'Amazon Web Services',
		'googledomains'     => 'Google',
		'googleusercontent' => 'Google Cloud',
		'digitalocean'      => 'DigitalOcean',
		'linode'            => 'Akamai / Linode',
		'vultr'             => 'Vultr',
		'hetzner'           => 'Hetzner',
		'ovh'               => 'OVHcloud',
		'wordpress.com'     => 'WordPress.com',
		'automattic'        => 'Automattic',
		'inmotionhosting'   => 'InMotion Hosting',
		'ionos'             => 'IONOS',
		'ui-dns'            => 'IONOS',
		'nsone'             => 'NS1',
		'azure'             => 'Microsoft Azure',
		'vercel'            => 'Vercel',
		'netlify'           => 'Netlify',
	);

	public static function hosting( $input ) {
		$n = self::normalize( $input );
		if ( is_wp_error( $n ) ) {
			return $n;
		}
		$cache_key = 'bwing_host_' . md5( $n['host'] );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}
		$ip = gethostbyname( $n['host'] );
		if ( $ip === $n['host'] ) {
			return new WP_Error( 'bwing_dns', __( 'That domain does not resolve.', 'bwing' ) );
		}
		$ns = array();
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $n['host'], DNS_NS ); // phpcs:ignore
			foreach ( (array) $records as $r ) {
				if ( ! empty( $r['target'] ) ) {
					$ns[] = strtolower( $r['target'] );
				}
			}
		}
		$rdns    = gethostbyaddr( $ip );
		$res     = self::fetch( $n['url'] );
		$headers = is_wp_error( $res ) ? array() : wp_remote_retrieve_headers( $res );
		$html    = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$stack   = self::stack( $headers, $html, $n['host'] );

		$provider = $stack['host'];
		$dns_by   = '';
		foreach ( self::DNS_MAP as $needle => $name ) {
			if ( ! $provider && $rdns && false !== stripos( $rdns, $needle ) ) {
				$provider = $name;
			}
			foreach ( $ns as $server ) {
				if ( ! $dns_by && false !== strpos( $server, $needle ) ) {
					$dns_by = $name;
				}
			}
		}
		$out = array(
			'domain'      => $n['host'],
			'ip'          => $ip,
			'reverse_dns' => $rdns && $rdns !== $ip ? $rdns : '',
			'nameservers' => $ns,
			'dns_by'      => $dns_by,
			'provider'    => $provider ? $provider : ( 'Cloudflare' === $stack['cdn'] ? __( 'Hidden behind Cloudflare', 'bwing' ) : __( 'Unknown', 'bwing' ) ),
			'server'      => $stack['server'],
			'cdn'         => $stack['cdn'],
			'php'         => $stack['php'],
			'wordpress'   => $stack['wordpress'],
		);
		set_transient( $cache_key, $out, DAY_IN_SECONDS );
		return $out;
	}
}
