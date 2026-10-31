<?php
/**
 * AI tools directory: custom post type, product-finder page, single tool pages
 * and front-end submissions (saved as pending for review).
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Tools {

	const CPT = 'bwing_tool';
	const TAX = 'bwing_tool_cat';

	/** @var WP_Error|null Errors from the last submission, shown by the form. */
	private static $errors = null;

	public static function init() {
		self::register();
		add_action( 'template_redirect', array( __CLASS__, 'handle_submit' ), 5 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'schema' ) );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
			add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
			add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		}
	}

	public static function register() {
		register_post_type( self::CPT, array(
			'labels'       => array(
				'name'          => __( 'AI Tools', 'bwing' ),
				'singular_name' => __( 'AI Tool', 'bwing' ),
				'add_new_item'  => __( 'Add AI tool', 'bwing' ),
				'edit_item'     => __( 'Edit AI tool', 'bwing' ),
				'all_items'     => __( 'AI Tools', 'bwing' ),
				'search_items'  => __( 'Search AI tools', 'bwing' ),
			),
			'public'       => true,
			'show_in_menu' => 'bwing',
			'show_in_rest' => true,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'ai-tools', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'thumbnail', 'author' ),
			'menu_icon'    => 'dashicons-superhero',
		) );
		register_taxonomy( self::TAX, self::CPT, array(
			'labels'             => array(
				'name'          => __( 'Tool categories', 'bwing' ),
				'singular_name' => __( 'Tool category', 'bwing' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'hierarchical'       => true,
			'rewrite'            => false,
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	public static function pricing_options() {
		return array(
			'free'     => __( 'Free', 'bwing' ),
			'freemium' => __( 'Freemium', 'bwing' ),
			'trial'    => __( 'Free trial', 'bwing' ),
			'paid'     => __( 'Paid', 'bwing' ),
		);
	}

	public static function logo( $post ) {
		$post = get_post( $post );
		if ( has_post_thumbnail( $post ) ) {
			return get_the_post_thumbnail_url( $post, 'thumbnail' );
		}
		$logo = get_post_meta( $post->ID, '_bwt_logo', true );
		if ( $logo ) {
			return $logo;
		}
		$host = wp_parse_url( (string) get_post_meta( $post->ID, '_bwt_url', true ), PHP_URL_HOST );
		return $host ? apply_filters( 'bwing_brand_logo_url', 'https://www.google.com/s2/favicons?sz=128&domain=' . rawurlencode( $host ), $host, $post ) : '';
	}

	private static function logo_html( $post, $class = 'bw-tool__logo' ) {
		$src = self::logo( $post );
		if ( $src ) {
			return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $src ) . '" alt="" loading="lazy" decoding="async">';
		}
		return BWing_Format::thumb( array( 'name' => get_the_title( $post ), 'image' => '' ), $class );
	}

	private static function badges( $post ) {
		$o     = '';
		$terms = get_the_terms( $post, self::TAX );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$o .= '<span class="bw-tool__tag">' . esc_html( $terms[0]->name ) . '</span>';
		}
		$price = get_post_meta( $post->ID, '_bwt_pricing', true );
		$opts  = self::pricing_options();
		if ( isset( $opts[ $price ] ) ) {
			$o .= '<span class="bw-tool__price bw-tool__price--' . esc_attr( $price ) . '">' . esc_html( $opts[ $price ] ) . '</span>';
		}
		return $o;
	}

	public static function card( $post ) {
		$featured = get_post_meta( $post->ID, '_bwt_featured', true );
		$url      = get_post_meta( $post->ID, '_bwt_url', true );
		return '<article class="bw-tool' . ( $featured ? ' is-featured' : '' ) . '">'
			. '<a class="bw-tool__main" href="' . esc_url( get_permalink( $post ) ) . '">'
			. '<span class="bw-tool__top">' . self::logo_html( $post ) . ( $featured ? '<span class="bw-tool__star">★ ' . esc_html__( 'Featured', 'bwing' ) . '</span>' : '' ) . '</span>'
			. '<strong class="bw-tool__name">' . esc_html( get_the_title( $post ) ) . '</strong>'
			. '<span class="bw-tool__tagline">' . esc_html( get_post_meta( $post->ID, '_bwt_tagline', true ) ) . '</span></a>'
			. '<div class="bw-tool__foot"><span class="bw-tool__badges">' . self::badges( $post ) . '</span>'
			. ( $url ? '<a class="bw-tool__visit" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Visit', 'bwing' ) . ' ↗</a>' : '' )
			. '</div></article>';
	}

	/* ------------------------------------------------------------------ */
	/* Directory (product finder)                                          */
	/* ------------------------------------------------------------------ */

	public static function directory( $a ) {
		$q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$cat     = isset( $_GET['tool_cat'] ) ? sanitize_title( wp_unslash( $_GET['tool_cat'] ) ) : ''; // phpcs:ignore
		$pricing = isset( $_GET['pricing'] ) ? sanitize_key( $_GET['pricing'] ) : ''; // phpcs:ignore
		$sort    = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'featured'; // phpcs:ignore
		$page    = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore
		$base    = get_permalink();

		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $a['per_page'] ),
			'paged'          => $page,
			's'              => $q,
		);
		if ( $cat ) {
			$args['tax_query'] = array( array( 'taxonomy' => self::TAX, 'field' => 'slug', 'terms' => $cat ) ); // phpcs:ignore
		}
		if ( isset( self::pricing_options()[ $pricing ] ) ) {
			$args['meta_query'] = array( array( 'key' => '_bwt_pricing', 'value' => $pricing ) ); // phpcs:ignore
		}
		if ( 'az' === $sort ) {
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
		} elseif ( 'newest' === $sort ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} else {
			$args['meta_key'] = '_bwt_featured'; // phpcs:ignore
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'title' => 'ASC' );
		}
		$query = new WP_Query( $args );

		// Search + filters.
		$link = function ( $over ) use ( $base, $q, $cat, $pricing, $sort ) {
			$params = array_filter( array_merge( array( 'q' => $q, 'tool_cat' => $cat, 'pricing' => $pricing, 'sort' => 'featured' === $sort ? '' : $sort ), $over ) );
			return add_query_arg( $params, $base );
		};
		$o  = '<form class="bw-finder__search" method="get" action="' . esc_url( $base ) . '" role="search">';
		$o .= '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
		$o .= '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '" aria-label="' . esc_attr( $a['placeholder'] ) . '">';
		foreach ( array( 'tool_cat' => $cat, 'pricing' => $pricing ) as $k => $v ) {
			$o .= $v ? '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' : '';
		}
		$o .= '<button class="bw-btn bw-btn--primary bw-btn--sm" type="submit">' . esc_html__( 'Search', 'bwing' ) . '</button></form>';

		$terms = get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => true ) );
		$o    .= '<nav class="bw-finder__cats" aria-label="' . esc_attr__( 'Categories', 'bwing' ) . '"><a class="bw-pill' . ( $cat ? '' : ' is-active' ) . '" href="' . esc_url( $link( array( 'tool_cat' => '' ) ) ) . '">' . esc_html__( 'All', 'bwing' ) . '</a>';
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$o .= '<a class="bw-pill' . ( $cat === $t->slug ? ' is-active' : '' ) . '" href="' . esc_url( $link( array( 'tool_cat' => $t->slug ) ) ) . '">' . esc_html( $t->name ) . ' <small>' . (int) $t->count . '</small></a>';
		}
		$o .= '</nav>';

		$o .= '<form class="bw-finder__bar" method="get" action="' . esc_url( $base ) . '">';
		$o .= '<span class="bw-finder__count"><b>' . esc_html( number_format_i18n( $query->found_posts ) ) . '</b> ' . esc_html( _n( 'tool', 'tools', $query->found_posts, 'bwing' ) ) . '</span>';
		foreach ( array( 'q' => $q, 'tool_cat' => $cat ) as $k => $v ) {
			$o .= $v ? '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' : '';
		}
		$o .= '<label><span class="screen-reader-text">' . esc_html__( 'Pricing', 'bwing' ) . '</span><select name="pricing" onchange="this.form.submit()"><option value="">' . esc_html__( 'Any pricing', 'bwing' ) . '</option>';
		foreach ( self::pricing_options() as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $pricing, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o .= '</select></label><label><span class="screen-reader-text">' . esc_html__( 'Sort', 'bwing' ) . '</span><select name="sort" onchange="this.form.submit()">';
		foreach ( array( 'featured' => __( 'Featured first', 'bwing' ), 'newest' => __( 'Newest', 'bwing' ), 'az' => __( 'A–Z', 'bwing' ) ) as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $sort, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o .= '</select></label><noscript><button class="bw-btn bw-btn--ghost bw-btn--sm">' . esc_html__( 'Apply', 'bwing' ) . '</button></noscript>';
		$o .= '<a class="bw-btn bw-btn--primary bw-btn--sm bw-finder__submit" href="' . esc_url( bwing_url( 'submit-tool' ) ) . '">+ ' . esc_html__( 'Submit a tool', 'bwing' ) . '</a></form>';

		if ( $query->have_posts() ) {
			$o .= '<div class="bw-tools">';
			foreach ( $query->posts as $post ) {
				$o .= self::card( $post );
			}
			$o .= '</div>';
			if ( $query->max_num_pages > 1 ) {
				$o .= '<nav class="bw-pager">';
				for ( $i = 1; $i <= $query->max_num_pages; $i++ ) {
					$o .= $i === $page ? '<span class="bw-pager__cur">' . $i . '</span>' : '<a href="' . esc_url( add_query_arg( 'pg', $i, $link( array() ) ) ) . '">' . $i . '</a>';
				}
				$o .= '</nav>';
			}
		} else {
			$o .= BWing_Render::empty_state( __( 'No tools match your search. Try another category or submit the tool you were looking for.', 'bwing' ) );
		}
		$o .= self::submit_cta();
		return BWing_Render::wrap( 'tools', BWing_Render::head( $a['title'], $a['subtitle'], 'h1' ) . '<div class="bw-finder">' . $o . '</div>' );
	}

	private static function submit_cta() {
		return '<a class="bw-callout bw-callout--soft bw-finder__cta" href="' . esc_url( bwing_url( 'submit-tool' ) ) . '"><span>＋</span><div><strong>' . esc_html__( 'Built an AI tool?', 'bwing' ) . '</strong> ' . esc_html__( 'Submit it for free — every listing is reviewed before it goes live.', 'bwing' ) . '</div><em>' . esc_html__( 'Submit a tool →', 'bwing' ) . '</em></a>';
	}

	/* ------------------------------------------------------------------ */
	/* Single tool page                                                    */
	/* ------------------------------------------------------------------ */

	public static function template( $template ) {
		if ( ! is_singular( self::CPT ) ) {
			return $template;
		}
		bwing_enqueue();
		$theme_tpl = locate_template( array( 'bwing/tool.php' ) );
		return $theme_tpl ? $theme_tpl : BWING_DIR . 'templates/tool.php';
	}

	public static function single( $post = null ) {
		$post = get_post( $post );
		$url  = get_post_meta( $post->ID, '_bwt_url', true );
		$o    = '<a class="bw-back" href="' . esc_url( bwing_url( 'ai-tools' ) ) . '">← ' . esc_html__( 'All AI tools', 'bwing' ) . '</a>';
		$o   .= '<header class="bw-itemhead bw-itemhead--tool">' . self::logo_html( $post, 'bw-itemhead__img bw-thumb--logo' ) . '<div class="bw-itemhead__body"><div class="bw-itemhead__tags">' . self::badges( $post ) . '</div>';
		$o   .= '<h1>' . esc_html( get_the_title( $post ) ) . '</h1><p class="bw-itemhead__by">' . esc_html( get_post_meta( $post->ID, '_bwt_tagline', true ) ) . '</p>';
		$o   .= $url ? '<div class="bw-itemhead__actions"><a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Visit website', 'bwing' ) . ' ↗</a></div>' : '';
		$o   .= '</div></header>';
		$body = apply_filters( 'the_content', $post->post_content );
		if ( trim( wp_strip_all_tags( $body ) ) ) {
			$o .= '<section class="bw-panel"><h2>' . esc_html__( 'About', 'bwing' ) . '</h2><div class="bw-prose">' . $body . '</div></section>';
		}

		$terms = get_the_terms( $post, self::TAX );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$similar = get_posts( array(
				'post_type'      => self::CPT,
				'posts_per_page' => 6,
				'post__not_in'   => array( $post->ID ), // phpcs:ignore
				'tax_query'      => array( array( 'taxonomy' => self::TAX, 'terms' => wp_list_pluck( $terms, 'term_id' ) ) ), // phpcs:ignore
				'orderby'        => 'rand',
			) );
			if ( $similar ) {
				/* translators: %s category */
				$o .= BWing_Render::wrap( 'similar', BWing_Render::head( sprintf( __( 'More %s tools', 'bwing' ), $terms[0]->name ) ) . '<div class="bw-tools">' . implode( '', array_map( array( __CLASS__, 'card' ), $similar ) ) . '</div>' );
			}
		}
		$o .= self::submit_cta();
		return '<article class="bw-single bw-single--tool">' . $o . '</article>';
	}

	public static function schema() {
		if ( ! is_singular( self::CPT ) ) {
			return;
		}
		$post   = get_queried_object();
		$terms  = get_the_terms( $post, self::TAX );
		$schema = array_filter( array(
			'@context'            => 'https://schema.org',
			'@type'               => 'SoftwareApplication',
			'name'                => get_the_title( $post ),
			'description'         => get_post_meta( $post->ID, '_bwt_tagline', true ),
			'url'                 => get_permalink( $post ),
			'sameAs'              => get_post_meta( $post->ID, '_bwt_url', true ),
			'image'               => self::logo( $post ),
			'applicationCategory' => $terms && ! is_wp_error( $terms ) ? $terms[0]->name : 'AI tool',
			'operatingSystem'     => 'Web',
			'offers'              => in_array( get_post_meta( $post->ID, '_bwt_pricing', true ), array( 'free', 'freemium' ), true ) ? array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ) : null,
		) );
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		if ( ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) ) {
			echo '<meta name="description" content="' . esc_attr( wp_html_excerpt( get_the_title( $post ) . ' – ' . get_post_meta( $post->ID, '_bwt_tagline', true ), 158, '…' ) ) . '">' . "\n";
		}
	}

	/* ------------------------------------------------------------------ */
	/* Submission                                                          */
	/* ------------------------------------------------------------------ */

	public static function submit_form( $a ) {
		if ( ! is_user_logged_in() ) {
			$here = get_permalink();
			return BWing_Render::wrap( 'submit-tool', BWing_Render::head( $a['submit_title'], $a['submit_subtitle'], 'h1' )
				. '<div class="bw-auth bw-auth--gate"><p>' . esc_html__( 'Log in or create a free account to submit a tool and follow its review status.', 'bwing' ) . '</p>'
				. '<div class="bw-auth__actions"><a class="bw-btn bw-btn--primary" href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( $here ), bwing_url( 'login' ) ) ) . '">' . esc_html__( 'Log in', 'bwing' ) . '</a>'
				. '<a class="bw-btn bw-btn--ghost" href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( $here ), bwing_url( 'register' ) ) ) . '">' . esc_html__( 'Create account', 'bwing' ) . '</a></div></div>' );
		}
		$v    = self::$errors ? wp_unslash( $_POST ) : array(); // phpcs:ignore
		$val  = function ( $k ) use ( $v ) {
			return isset( $v[ $k ] ) ? (string) $v[ $k ] : '';
		};
		$o    = '';
		if ( isset( $_GET['submitted'] ) ) { // phpcs:ignore
			$o .= '<div class="bw-notice bw-notice--ok">' . esc_html__( 'Thanks! Your tool was submitted and is waiting for review. You can follow its status on your account page.', 'bwing' ) . ' <a href="' . esc_url( bwing_url( 'account' ) ) . '">' . esc_html__( 'My account →', 'bwing' ) . '</a></div>';
		}
		$o .= BWing_Account::errors_html( self::$errors );
		$o .= '<form class="bw-form" method="post" action="' . esc_url( get_permalink() ) . '">' . wp_nonce_field( 'bwing_submit_tool', '_bwnonce', true, false ) . '<input type="hidden" name="bwing_action" value="submit_tool">';
		$o .= '<div class="bw-form__hp" aria-hidden="true"><label>Website 2<input type="text" name="website2" tabindex="-1" autocomplete="off"></label></div>';
		$o .= '<div class="bw-form__row"><label>' . esc_html__( 'Tool name', 'bwing' ) . ' *<input type="text" name="tool_name" maxlength="80" required value="' . esc_attr( $val( 'tool_name' ) ) . '"></label>';
		$o .= '<label>' . esc_html__( 'Website URL', 'bwing' ) . ' *<input type="url" name="tool_url" required placeholder="https://" value="' . esc_attr( $val( 'tool_url' ) ) . '"></label></div>';
		$o .= '<label>' . esc_html__( 'Tagline', 'bwing' ) . ' *<input type="text" name="tool_tagline" maxlength="140" required placeholder="' . esc_attr__( 'One sentence: what does it do?', 'bwing' ) . '" value="' . esc_attr( $val( 'tool_tagline' ) ) . '"></label>';
		$o .= '<div class="bw-form__row"><label>' . esc_html__( 'Category', 'bwing' ) . ' *<select name="tool_cat" required><option value="">' . esc_html__( 'Choose…', 'bwing' ) . '</option>';
		foreach ( get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => false ) ) as $t ) {
			$o .= '<option value="' . (int) $t->term_id . '"' . selected( $val( 'tool_cat' ), (string) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		$o .= '</select></label><label>' . esc_html__( 'Pricing', 'bwing' ) . ' *<select name="tool_pricing" required>';
		foreach ( self::pricing_options() as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $val( 'tool_pricing' ), $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o .= '</select></label></div>';
		$o .= '<label>' . esc_html__( 'Description', 'bwing' ) . '<textarea name="tool_desc" rows="6" maxlength="3000" placeholder="' . esc_attr__( 'Key features, who it is for, what makes it different.', 'bwing' ) . '">' . esc_textarea( $val( 'tool_desc' ) ) . '</textarea></label>';
		$o .= '<label>' . esc_html__( 'Logo URL (optional)', 'bwing' ) . '<input type="url" name="tool_logo" placeholder="https://…/logo.png" value="' . esc_attr( $val( 'tool_logo' ) ) . '"><small>' . esc_html__( 'Leave empty to use the website icon.', 'bwing' ) . '</small></label>';
		$o .= '<p class="bw-form__note">' . esc_html__( 'Every submission is reviewed before it is published. Listings are free.', 'bwing' ) . '</p>';
		$o .= '<button class="bw-btn bw-btn--primary" type="submit">' . esc_html__( 'Submit for review', 'bwing' ) . '</button></form>';
		return BWing_Render::wrap( 'submit-tool', BWing_Render::head( $a['submit_title'], $a['submit_subtitle'], 'h1' ) . '<div class="bw-auth bw-auth--wide">' . $o . '</div>' );
	}

	public static function handle_submit() {
		if ( empty( $_POST['bwing_action'] ) || 'submit_tool' !== $_POST['bwing_action'] ) { // phpcs:ignore
			return;
		}
		self::$errors = new WP_Error();
		if ( ! is_user_logged_in() || ! isset( $_POST['_bwnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bwnonce'] ), 'bwing_submit_tool' ) ) {
			self::$errors->add( 'auth', __( 'Your session expired. Please reload the page and try again.', 'bwing' ) );
			return;
		}
		if ( ! empty( $_POST['website2'] ) ) {
			wp_safe_redirect( add_query_arg( 'submitted', 1, wp_get_referer() ) ); // Bot: pretend success.
			exit;
		}
		$user    = get_current_user_id();
		$name    = sanitize_text_field( wp_unslash( $_POST['tool_name'] ?? '' ) );
		$url     = esc_url_raw( wp_unslash( $_POST['tool_url'] ?? '' ), array( 'http', 'https' ) );
		$tagline = sanitize_text_field( wp_unslash( $_POST['tool_tagline'] ?? '' ) );
		$desc    = sanitize_textarea_field( wp_unslash( $_POST['tool_desc'] ?? '' ) );
		$cat     = absint( $_POST['tool_cat'] ?? 0 );
		$pricing = sanitize_key( $_POST['tool_pricing'] ?? '' );
		$logo    = esc_url_raw( wp_unslash( $_POST['tool_logo'] ?? '' ), array( 'http', 'https' ) );

		if ( mb_strlen( $name ) < 2 ) {
			self::$errors->add( 'name', __( 'Please enter the tool name.', 'bwing' ) );
		}
		$host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
		if ( ! $host ) {
			self::$errors->add( 'url', __( 'Please enter a valid website URL.', 'bwing' ) );
		}
		if ( mb_strlen( $tagline ) < 10 ) {
			self::$errors->add( 'tagline', __( 'Please add a short tagline (at least 10 characters).', 'bwing' ) );
		}
		if ( ! $cat || ! term_exists( $cat, self::TAX ) ) {
			self::$errors->add( 'cat', __( 'Please choose a category.', 'bwing' ) );
		}
		if ( ! isset( self::pricing_options()[ $pricing ] ) ) {
			self::$errors->add( 'pricing', __( 'Please choose a pricing model.', 'bwing' ) );
		}
		if ( $host && self::find_by_host( $host ) ) {
			self::$errors->add( 'dupe', __( 'This tool is already listed or waiting for review.', 'bwing' ) );
		}
		$recent = get_posts( array( 'post_type' => self::CPT, 'author' => $user, 'post_status' => 'any', 'date_query' => array( array( 'after' => '1 day ago' ) ), 'fields' => 'ids', 'posts_per_page' => 6 ) );
		if ( count( $recent ) >= 5 && ! current_user_can( 'edit_others_posts' ) ) {
			self::$errors->add( 'limit', __( 'You can submit up to 5 tools per day. Please try again tomorrow.', 'bwing' ) );
		}
		if ( self::$errors->has_errors() ) {
			return;
		}

		$id = wp_insert_post( array(
			'post_type'    => self::CPT,
			'post_status'  => 'pending',
			'post_title'   => $name,
			'post_excerpt' => $tagline,
			'post_content' => $desc ? wpautop( esc_html( $desc ) ) : '',
			'post_author'  => $user,
		), true );
		if ( is_wp_error( $id ) ) {
			self::$errors->add( 'save', __( 'Could not save your submission. Please try again.', 'bwing' ) );
			return;
		}
		wp_set_object_terms( $id, array( $cat ), self::TAX );
		update_post_meta( $id, '_bwt_url', $url );
		update_post_meta( $id, '_bwt_tagline', $tagline );
		update_post_meta( $id, '_bwt_pricing', $pricing );
		update_post_meta( $id, '_bwt_logo', $logo );
		update_post_meta( $id, '_bwt_featured', 0 );

		if ( BWing_Settings::get( 'tools', 'notify', 1 ) ) {
			/* translators: %s tool name */
			wp_mail( get_option( 'admin_email' ), sprintf( __( 'New AI tool submitted: %s', 'bwing' ), $name ), sprintf( "%s\n%s\n\n%s\n\n%s", $name, $url, $tagline, admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
		}
		wp_safe_redirect( add_query_arg( 'submitted', 1, get_permalink() ) );
		exit;
	}

	private static function find_by_host( $host ) {
		global $wpdb;
		$host = preg_replace( '/^www\./', '', strtolower( $host ) );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_bwt_url'
			 WHERE p.post_type = %s AND p.post_status IN ('publish','pending','draft','future') AND (m.meta_value LIKE %s OR m.meta_value LIKE %s) LIMIT 1",
			self::CPT, '%://' . $wpdb->esc_like( $host ) . '%', '%://www.' . $wpdb->esc_like( $host ) . '%'
		) );
	}

	/** The current user's submissions (for the account page). */
	public static function user_tools( $user_id ) {
		return get_posts( array( 'post_type' => self::CPT, 'author' => $user_id, 'post_status' => array( 'publish', 'pending', 'draft', 'trash' ), 'posts_per_page' => 50 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public static function meta_box() {
		add_meta_box( 'bwt_details', __( 'Tool details', 'bwing' ), array( __CLASS__, 'meta_box_html' ), self::CPT, 'normal', 'high' );
	}

	public static function meta_box_html( $post ) {
		wp_nonce_field( 'bwt_save', '_bwtnonce' );
		$f = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, $k, true );
		};
		echo '<table class="form-table"><tr><th><label for="bwt_url">' . esc_html__( 'Website URL', 'bwing' ) . '</label></th><td><input class="large-text" id="bwt_url" type="url" name="bwt_url" value="' . esc_attr( $f( '_bwt_url' ) ) . '"></td></tr>';
		echo '<tr><th><label for="bwt_tagline">' . esc_html__( 'Tagline', 'bwing' ) . '</label></th><td><input class="large-text" id="bwt_tagline" type="text" name="bwt_tagline" maxlength="140" value="' . esc_attr( $f( '_bwt_tagline' ) ) . '"></td></tr>';
		echo '<tr><th><label for="bwt_pricing">' . esc_html__( 'Pricing', 'bwing' ) . '</label></th><td><select id="bwt_pricing" name="bwt_pricing">';
		foreach ( self::pricing_options() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f( '_bwt_pricing' ), $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="bwt_logo">' . esc_html__( 'Logo URL', 'bwing' ) . '</label></th><td><input class="large-text" id="bwt_logo" type="url" name="bwt_logo" value="' . esc_attr( $f( '_bwt_logo' ) ) . '"><p class="description">' . esc_html__( 'Optional. The featured image wins; empty uses the website icon.', 'bwing' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Featured', 'bwing' ) . '</th><td><label><input type="checkbox" name="bwt_featured" value="1"' . checked( $f( '_bwt_featured' ), 1, false ) . '> ' . esc_html__( 'Show first in the directory with a Featured badge', 'bwing' ) . '</label></td></tr></table>';
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['_bwtnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bwtnonce'] ), 'bwt_save' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_bwt_url', esc_url_raw( wp_unslash( $_POST['bwt_url'] ?? '' ) ) );
		$tagline = sanitize_text_field( wp_unslash( $_POST['bwt_tagline'] ?? '' ) );
		update_post_meta( $post_id, '_bwt_tagline', $tagline );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $tagline ), array( 'ID' => $post_id ) ); // Searchable; no save_post loop.
		clean_post_cache( $post_id );
		$p = sanitize_key( $_POST['bwt_pricing'] ?? '' );
		update_post_meta( $post_id, '_bwt_pricing', isset( self::pricing_options()[ $p ] ) ? $p : 'freemium' );
		update_post_meta( $post_id, '_bwt_logo', esc_url_raw( wp_unslash( $_POST['bwt_logo'] ?? '' ) ) );
		update_post_meta( $post_id, '_bwt_featured', empty( $_POST['bwt_featured'] ) ? 0 : 1 );
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'title' === $k ) {
				$new['bwt_logo'] = '';
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['bwt_url']     = __( 'Website', 'bwing' );
				$new['bwt_pricing'] = __( 'Pricing', 'bwing' );
				$new['bwt_feat']    = '★';
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'bwt_logo':
				$src = self::logo( $post_id );
				echo $src ? '<img src="' . esc_url( $src ) . '" width="32" height="32" style="border-radius:6px;object-fit:contain" alt="">' : '';
				break;
			case 'bwt_url':
				$u = get_post_meta( $post_id, '_bwt_url', true );
				echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $u, PHP_URL_HOST ) ) . '</a>' : '—';
				break;
			case 'bwt_pricing':
				$p = get_post_meta( $post_id, '_bwt_pricing', true );
				echo esc_html( self::pricing_options()[ $p ] ?? '—' );
				break;
			case 'bwt_feat':
				echo get_post_meta( $post_id, '_bwt_featured', true ) ? '★' : '';
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Starter content                                                     */
	/* ------------------------------------------------------------------ */

	public static function categories() {
		return array(
			'chat'         => __( 'Chat & Assistants', 'bwing' ),
			'writing'      => __( 'Writing', 'bwing' ),
			'image'        => __( 'Image', 'bwing' ),
			'video'        => __( 'Video', 'bwing' ),
			'audio'        => __( 'Audio & Voice', 'bwing' ),
			'code'         => __( 'Code', 'bwing' ),
			'productivity' => __( 'Productivity', 'bwing' ),
			'research'     => __( 'Search & Research', 'bwing' ),
			'design'       => __( 'Design', 'bwing' ),
			'marketing'    => __( 'Marketing', 'bwing' ),
		);
	}

	/** Columns: name, url, tagline, category, pricing, featured. */
	private static function starter_tools() {
		return array(
			array( 'ChatGPT', 'https://chatgpt.com/', 'Conversational AI assistant from OpenAI for writing, analysis, coding and more.', 'chat', 'freemium', 1 ),
			array( 'Claude', 'https://claude.ai/', 'AI assistant from Anthropic for writing, analysis, coding and working with long documents.', 'chat', 'freemium', 1 ),
			array( 'Gemini', 'https://gemini.google.com/', "Google's AI assistant, connected to Search and Google apps.", 'chat', 'freemium', 1 ),
			array( 'Microsoft Copilot', 'https://copilot.microsoft.com/', "Microsoft's AI companion for chat, web answers and creating images.", 'chat', 'freemium', 0 ),
			array( 'Perplexity', 'https://www.perplexity.ai/', 'AI answer engine that searches the web and cites its sources.', 'research', 'freemium', 1 ),
			array( 'Midjourney', 'https://www.midjourney.com/', 'Generates high-quality images and artwork from text prompts.', 'image', 'paid', 1 ),
			array( 'Adobe Firefly', 'https://firefly.adobe.com/', "Adobe's generative AI for images, text effects and design.", 'image', 'freemium', 0 ),
			array( 'Leonardo.Ai', 'https://leonardo.ai/', 'AI image and video generation for creators, marketers and game art.', 'image', 'freemium', 0 ),
			array( 'Canva', 'https://www.canva.com/', 'Design platform with built-in AI tools for images, text and presentations.', 'design', 'freemium', 0 ),
			array( 'Runway', 'https://runwayml.com/', 'AI video generation and editing tools for filmmakers and creators.', 'video', 'freemium', 1 ),
			array( 'HeyGen', 'https://www.heygen.com/', 'Create videos with AI avatars, voices and video translation.', 'video', 'freemium', 0 ),
			array( 'Descript', 'https://www.descript.com/', 'Edit video and podcasts by editing the transcript, with AI voice tools.', 'video', 'freemium', 0 ),
			array( 'ElevenLabs', 'https://elevenlabs.io/', 'Realistic AI voices for text to speech, dubbing and voice cloning.', 'audio', 'freemium', 1 ),
			array( 'Suno', 'https://suno.com/', 'Create full songs with vocals and instruments from a text prompt.', 'audio', 'freemium', 0 ),
			array( 'GitHub Copilot', 'https://github.com/features/copilot', 'AI pair programmer that suggests code and answers questions in your editor.', 'code', 'freemium', 1 ),
			array( 'Cursor', 'https://cursor.com/', 'AI-first code editor for writing, editing and understanding code.', 'code', 'freemium', 0 ),
			array( 'Lovable', 'https://lovable.dev/', 'Build web apps by describing what you want in plain language.', 'code', 'freemium', 0 ),
			array( 'Hugging Face', 'https://huggingface.co/', 'Open platform for AI models, datasets and demo apps.', 'code', 'free', 0 ),
			array( 'Grammarly', 'https://www.grammarly.com/', 'AI writing assistant for grammar, clarity and tone.', 'writing', 'freemium', 0 ),
			array( 'DeepL', 'https://www.deepl.com/', 'AI translation and writing assistant for over 30 languages.', 'writing', 'freemium', 0 ),
			array( 'Jasper', 'https://www.jasper.ai/', 'AI content platform for marketing teams and brand voice.', 'marketing', 'trial', 0 ),
			array( 'Otter.ai', 'https://otter.ai/', 'AI meeting assistant with live transcription and summaries.', 'productivity', 'freemium', 0 ),
			array( 'Gamma', 'https://gamma.app/', 'Create presentations, documents and web pages with AI.', 'productivity', 'freemium', 0 ),
		);
	}

	public static function seed() {
		if ( get_option( 'bwing_tools_seeded' ) ) {
			return;
		}
		self::register();
		$ids = array();
		foreach ( self::categories() as $slug => $name ) {
			$t            = term_exists( $slug, self::TAX );
			$t            = $t ? $t : wp_insert_term( $name, self::TAX, array( 'slug' => $slug ) );
			$ids[ $slug ] = is_wp_error( $t ) ? 0 : (int) $t['term_id'];
		}
		foreach ( self::starter_tools() as $i => $r ) {
			if ( self::find_by_host( wp_parse_url( $r[1], PHP_URL_HOST ) ) ) {
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $r[0],
				'post_excerpt' => $r[2],
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 60 - $i ) . ' minutes', current_time( 'timestamp' ) ) ), // phpcs:ignore
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			if ( $ids[ $r[3] ] ) {
				wp_set_object_terms( $id, array( $ids[ $r[3] ] ), self::TAX );
			}
			update_post_meta( $id, '_bwt_url', $r[1] );
			update_post_meta( $id, '_bwt_tagline', $r[2] );
			update_post_meta( $id, '_bwt_pricing', $r[4] );
			update_post_meta( $id, '_bwt_featured', $r[5] );
		}
		update_option( 'bwing_tools_seeded', 1 );
	}
}
