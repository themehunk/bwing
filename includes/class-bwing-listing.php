<?php
/**
 * Listing directory: themes, plugins, blocks, hosting, software and more.
 *
 * Keeps the post type, taxonomies and meta keys of the original BlogWings
 * theme code ("listing", "listing-cate", "listing-tag", bws_* meta) so existing
 * listings keep working, and adds:
 *  - /listing/                      directory with category tabs, search, sort
 *  - /listing-cate/{term}/          the directory filtered by category
 *  - /listing/{slug}/               single listing page
 *  - /listing/author/{author}/      every listing by one author
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Listing {

	const CPT = 'listing';
	const CAT = 'listing-cate';
	const TAG = 'listing-tag';
	const REV = 1;

	/** Meta keys (kept from the theme version). */
	const M_DESC   = 'bws_short_desc';
	const M_DWD    = 'bws_dwdurl';
	const M_DEMO   = 'bws_demourl';
	const M_AUTH   = 'bws_authname';
	const M_AUTHU  = 'bws_authsurl';
	const M_TYPE   = 'bws_freepro_slug';
	const M_ASLUG  = '_bwl_author_slug';

	/** @var array|null Routed author page: slug, name, url. */
	private static $author = null;

	public static function init() {
		self::register();
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'resolve_author' ), 2 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'schema' ) );
		add_shortcode( 'bwing_listings', array( __CLASS__, 'shortcode' ) );
		if ( ! shortcode_exists( 'bwlisting' ) ) {
			add_shortcode( 'bwlisting', array( __CLASS__, 'shortcode_legacy' ) ); // Old theme shortcode.
		}
		// Keep the author slug in sync however the author name is saved (editor, REST, import, OptionTree).
		add_action( 'added_post_meta', array( __CLASS__, 'sync_author_slug' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'sync_author_slug' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'sync_author_slug' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'migrate' ), 99 );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
			add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
			add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		}
	}

	public static function register() {
		global $wp_rewrite;
		// Added before the post type's own rules so /listing/author/x/ wins over the attachment rule.
		add_rewrite_rule( '^listing/author/([^/]+)/?$', 'index.php?bwl_author=$matches[1]', 'top' );
		if ( $wp_rewrite && $wp_rewrite->using_index_permalinks() ) {
			// With /index.php/ permalinks core matches later rules against "index.php/…".
			add_rewrite_rule( '^' . $wp_rewrite->index . '/listing/author/([^/]+)/?$', 'index.php?bwl_author=$matches[1]', 'top' );
		}
		if ( ! post_type_exists( self::CPT ) ) {
			register_post_type( self::CPT, array(
				'labels'        => array(
					'name'               => __( 'Listing', 'bwing' ),
					'singular_name'      => __( 'Listing', 'bwing' ),
					'menu_name'          => __( 'Listing', 'bwing' ),
					'all_items'          => __( 'All Listing', 'bwing' ),
					'add_new'            => __( 'Add New', 'bwing' ),
					'add_new_item'       => __( 'Add New Listing', 'bwing' ),
					'edit_item'          => __( 'Edit Listing', 'bwing' ),
					'view_item'          => __( 'View Listing', 'bwing' ),
					'search_items'       => __( 'Search Listing', 'bwing' ),
					'not_found'          => __( 'Not Found', 'bwing' ),
					'not_found_in_trash' => __( 'Not found in Trash', 'bwing' ),
				),
				'description'   => __( 'Listing and reviews', 'bwing' ),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_position' => 5,
				'menu_icon'     => 'dashicons-list-view',
				'has_archive'   => 'listing',
				'rewrite'       => array( 'slug' => 'listing', 'with_front' => false ),
				'supports'      => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields' ),
				'taxonomies'    => array( self::CAT, self::TAG ),
			) );
		}
		if ( ! taxonomy_exists( self::CAT ) ) {
			register_taxonomy( self::CAT, self::CPT, array(
				'labels'            => array(
					'name'          => __( 'Listing Categories', 'bwing' ),
					'singular_name' => __( 'Listing Category', 'bwing' ),
					'menu_name'     => __( 'Listing Categories', 'bwing' ),
					'add_new_item'  => __( 'Add New Listing Category', 'bwing' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'listing-cate', 'with_front' => false, 'hierarchical' => false ),
			) );
		}
		if ( ! taxonomy_exists( self::TAG ) ) {
			register_taxonomy( self::TAG, self::CPT, array(
				'labels'            => array(
					'name'          => __( 'Listing Tag', 'bwing' ),
					'singular_name' => __( 'Listing Tag', 'bwing' ),
					'menu_name'     => __( 'Listing Tag', 'bwing' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'listing-tag', 'with_front' => false ),
			) );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'bwl_author';
		return $vars;
	}

	/* ------------------------------------------------------------------ */
	/* Data helpers                                                        */
	/* ------------------------------------------------------------------ */

	public static function author_url( $slug ) {
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'bwl_author', $slug, home_url( '/' ) );
		}
		return bwing_url( 'listing/author/' . $slug );
	}

	public static function archive_url() {
		$url = get_post_type_archive_link( self::CPT );
		return $url ? $url : home_url( '/' );
	}

	/** Author name + link of a listing, or null. */
	public static function author( $post ) {
		$name = trim( (string) get_post_meta( $post->ID, self::M_AUTH, true ) );
		if ( '' === $name ) {
			return null;
		}
		return array( 'name' => $name, 'slug' => sanitize_title( $name ), 'site' => (string) get_post_meta( $post->ID, self::M_AUTHU, true ) );
	}

	public static function type_label( $post ) {
		$t = strtolower( trim( (string) get_post_meta( $post->ID, self::M_TYPE, true ) ) );
		return 'pro' === $t ? __( 'Pro', 'bwing' ) : ( 'free' === $t ? __( 'Free', 'bwing' ) : '' );
	}

	/** Short plain-text summary for cards. */
	public static function summary( $post, $words = 22 ) {
		$text = (string) get_post_meta( $post->ID, self::M_DESC, true );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			$text = $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		}
		return wp_trim_words( wp_strip_all_tags( $text ), $words, '…' );
	}

	/** WordPress.org slug + type when the download URL points to the directory. */
	public static function wporg( $post ) {
		$url = (string) get_post_meta( $post->ID, self::M_DWD, true );
		if ( preg_match( '#wordpress\.org/(themes|plugins)/([a-z0-9\-_]+)#i', $url, $m ) ) {
			return array( 'type' => 'themes' === strtolower( $m[1] ) ? 'theme' : 'plugin', 'slug' => sanitize_title( $m[2] ) );
		}
		return null;
	}

	/** Direct .zip download for WordPress.org items, else the saved URL. */
	public static function download_url( $post ) {
		$wp = self::wporg( $post );
		if ( $wp ) {
			return 'https://downloads.wordpress.org/' . $wp['type'] . '/' . $wp['slug'] . '.zip';
		}
		return (string) get_post_meta( $post->ID, self::M_DWD, true );
	}

	private static function primary_term( $post ) {
		$terms = get_the_terms( $post, self::CAT );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}
		// Prefer the deepest (most specific) term.
		usort( $terms, function ( $a, $b ) {
			return count( get_ancestors( $b->term_id, self::CAT ) ) - count( get_ancestors( $a->term_id, self::CAT ) );
		} );
		return $terms[0];
	}

	private static function shot_html( $post, $size = 'medium_large', $class = 'bw-lcard__img' ) {
		if ( has_post_thumbnail( $post ) ) {
			$alt = get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true );
			return get_the_post_thumbnail( $post, $size, array( 'class' => $class, 'alt' => $alt ? $alt : get_the_title( $post ), 'loading' => 'lazy', 'decoding' => 'async' ) );
		}
		return BWing_Format::thumb( array( 'name' => get_the_title( $post ), 'image' => '' ), $class . ' bw-lcard__img--empty' );
	}

	private static function badges( $post, $link = true ) {
		$o    = '';
		$term = self::primary_term( $post );
		if ( $term ) {
			$o .= $link ? '<a class="bw-tool__tag" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>' : '<span class="bw-tool__tag">' . esc_html( $term->name ) . '</span>';
		}
		$type = self::type_label( $post );
		if ( $type ) {
			$key = 'pro' === strtolower( (string) get_post_meta( $post->ID, self::M_TYPE, true ) ) ? 'paid' : 'free';
			$o  .= '<span class="bw-tool__price bw-tool__price--' . $key . '">' . esc_html( $type ) . '</span>';
		}
		return $o;
	}

	public static function card( $post ) {
		$post   = get_post( $post );
		$link   = get_permalink( $post );
		$demo   = (string) get_post_meta( $post->ID, self::M_DEMO, true );
		$author = self::author( $post );
		return '<article class="bw-lcard">'
			. '<a class="bw-lcard__shot" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . self::shot_html( $post ) . '</a>'
			. '<div class="bw-lcard__body"><div class="bw-tool__badges">' . self::badges( $post, false ) . '</div>'
			. '<h3 class="bw-lcard__title"><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>'
			. '<p class="bw-lcard__text">' . esc_html( self::summary( $post ) ) . '</p></div>'
			. '<div class="bw-tool__foot"><span class="bw-lcard__by">' . ( $author ? esc_html__( 'by', 'bwing' ) . ' <a href="' . esc_url( self::author_url( $author['slug'] ) ) . '">' . esc_html( $author['name'] ) . '</a>' : '' ) . '</span>'
			. ( $demo ? '<a class="bw-tool__visit" href="' . esc_url( $demo ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Demo', 'bwing' ) . ' ↗</a>' : '<a class="bw-tool__visit" href="' . esc_url( $link ) . '">' . esc_html__( 'Details', 'bwing' ) . '</a>' )
			. '</div></article>';
	}

	private static function grid( $posts ) {
		return '<div class="bw-listings">' . implode( '', array_map( array( __CLASS__, 'card' ), $posts ) ) . '</div>';
	}

	private static function pager( $page, $pages, $link ) {
		if ( $pages < 2 ) {
			return '';
		}
		$o = '<nav class="bw-pager" aria-label="' . esc_attr__( 'Pages', 'bwing' ) . '">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			if ( $i > 2 && $i < $pages - 1 && abs( $i - $page ) > 2 ) {
				if ( 3 === $i || $pages - 2 === $i ) {
					$o .= '<span class="bw-pager__gap">…</span>';
				}
				continue;
			}
			$o .= $i === $page ? '<span class="bw-pager__cur">' . $i . '</span>' : '<a href="' . esc_url( $link( $i ) ) . '">' . $i . '</a>';
		}
		return $o . '</nav>';
	}

	/* ------------------------------------------------------------------ */
	/* Directory                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array $a title, subtitle, per_page, cate (preselected term slug), tag, pretty (link pills to term archives).
	 */
	public static function directory( $a ) {
		bwing_enqueue();
		$a = wp_parse_args( $a, array(
			'title'    => __( 'Listing', 'bwing' ),
			'subtitle' => __( 'Hand-picked WordPress themes, plugins, blocks, hosting and software — reviewed and rated by the community.', 'bwing' ),
			'per_page' => 18,
			'cate'     => '',
			'tag'      => '',
			'pretty'   => false,
			'base'     => '',
		) );
		// phpcs:disable WordPress.Security.NonceVerification
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$cat  = $a['cate'] ? sanitize_title( $a['cate'] ) : ( isset( $_GET['lcat'] ) ? sanitize_title( wp_unslash( $_GET['lcat'] ) ) : '' );
		$type = isset( $_GET['ltype'] ) ? sanitize_key( $_GET['ltype'] ) : '';
		$sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'newest';
		$page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
		// phpcs:enable
		$base = $a['base'] ? $a['base'] : ( is_singular() ? get_permalink() : self::archive_url() );
		$term = $cat ? get_term_by( 'slug', $cat, self::CAT ) : null;
		$tag  = $a['tag'] ? get_term_by( 'slug', $a['tag'], self::TAG ) : null;

		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $a['per_page'] ),
			'paged'          => $page,
			's'              => $q,
		);
		$tax = array();
		if ( $term ) {
			$tax[] = array( 'taxonomy' => self::CAT, 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => true );
		}
		if ( $tag ) {
			$tax[] = array( 'taxonomy' => self::TAG, 'field' => 'term_id', 'terms' => $tag->term_id );
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( in_array( $type, array( 'free', 'pro' ), true ) ) {
			$args['meta_query'] = array( array( 'key' => self::M_TYPE, 'value' => $type ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( 'az' === $sort ) {
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
		} elseif ( 'popular' === $sort ) {
			$args['orderby'] = array( 'comment_count' => 'DESC', 'date' => 'DESC' );
		} else {
			$sort            = 'newest';
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}
		$query = new WP_Query( $args );

		$keep = function ( $over = array() ) use ( $q, $type, $sort ) {
			return array_filter( array_merge( array( 'q' => $q, 'ltype' => $type, 'sort' => 'newest' === $sort ? '' : $sort ), $over ) );
		};
		$cat_link = function ( $t ) use ( $a, $base, $keep ) {
			if ( $a['pretty'] ) {
				return add_query_arg( $keep(), $t ? get_term_link( $t ) : self::archive_url() );
			}
			return add_query_arg( $keep( array( 'lcat' => $t ? $t->slug : '' ) ), $base );
		};
		$here = $a['pretty'] ? ( $term ? get_term_link( $term ) : ( $tag ? get_term_link( $tag ) : self::archive_url() ) ) : $base;
		$hidden = function ( $pairs ) {
			$h = '';
			foreach ( $pairs as $k => $v ) {
				$h .= $v ? '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' : '';
			}
			return $h;
		};
		$lcat = $a['pretty'] ? '' : $cat;

		// Search.
		$o  = '<form class="bw-finder__search" method="get" action="' . esc_url( $here ) . '" role="search">';
		$o .= '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
		$o .= '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Search themes, plugins, tools…', 'bwing' ) . '" aria-label="' . esc_attr__( 'Search listing', 'bwing' ) . '">';
		$o .= $hidden( array( 'lcat' => $lcat, 'ltype' => $type, 'sort' => 'newest' === $sort ? '' : $sort ) );
		$o .= '<button class="bw-btn bw-btn--primary bw-btn--sm" type="submit">' . esc_html__( 'Search', 'bwing' ) . '</button></form>';

		// Category tabs (top level) + sub-category pills.
		$parents = get_terms( array( 'taxonomy' => self::CAT, 'parent' => 0, 'hide_empty' => false, 'orderby' => 'term_id' ) );
		$parents = is_wp_error( $parents ) ? array() : $parents;
		$active  = $term ? ( $term->parent ? (int) self::top_ancestor( $term ) : (int) $term->term_id ) : 0;
		if ( $parents ) {
			$o .= '<nav class="bw-finder__cats" aria-label="' . esc_attr__( 'Categories', 'bwing' ) . '"><a class="bw-pill' . ( $term ? '' : ' is-active' ) . '" href="' . esc_url( $cat_link( null ) ) . '">' . esc_html__( 'All', 'bwing' ) . '</a>';
			foreach ( $parents as $t ) {
				$o .= '<a class="bw-pill' . ( $active === (int) $t->term_id ? ' is-active' : '' ) . '" href="' . esc_url( $cat_link( $t ) ) . '">' . esc_html( $t->name ) . ( $t->count ? ' <small>' . (int) $t->count . '</small>' : '' ) . '</a>';
			}
			$o .= '</nav>';
			if ( $active ) {
				$subs = get_terms( array( 'taxonomy' => self::CAT, 'parent' => $active, 'hide_empty' => false ) );
				if ( $subs && ! is_wp_error( $subs ) ) {
					$parent = get_term( $active, self::CAT );
					$o     .= '<nav class="bw-finder__cats bw-finder__cats--sub" aria-label="' . esc_attr__( 'Sub-categories', 'bwing' ) . '"><a class="bw-pill bw-pill--sub' . ( (int) $term->term_id === $active ? ' is-active' : '' ) . '" href="' . esc_url( $cat_link( $parent ) ) . '">' . esc_html__( 'All', 'bwing' ) . ' ' . esc_html( $parent->name ) . '</a>';
					foreach ( $subs as $s ) {
						$o .= '<a class="bw-pill bw-pill--sub' . ( (int) $term->term_id === (int) $s->term_id ? ' is-active' : '' ) . '" href="' . esc_url( $cat_link( $s ) ) . '">' . esc_html( $s->name ) . '</a>';
					}
					$o .= '</nav>';
				}
			}
		}

		// Count + filters.
		$o .= '<form class="bw-finder__bar" method="get" action="' . esc_url( $here ) . '">';
		$o .= '<span class="bw-finder__count"><b>' . esc_html( number_format_i18n( $query->found_posts ) ) . '</b> ' . esc_html( _n( 'listing', 'listings', $query->found_posts, 'bwing' ) ) . ( $tag ? ' · #' . esc_html( $tag->name ) : '' ) . '</span>';
		$o .= $hidden( array( 'q' => $q, 'lcat' => $lcat ) );
		$o .= '<label><span class="screen-reader-text">' . esc_html__( 'Type', 'bwing' ) . '</span><select name="ltype" onchange="this.form.submit()"><option value="">' . esc_html__( 'Free & Pro', 'bwing' ) . '</option>';
		foreach ( array( 'free' => __( 'Free only', 'bwing' ), 'pro' => __( 'Pro only', 'bwing' ) ) as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o .= '</select></label><label><span class="screen-reader-text">' . esc_html__( 'Sort', 'bwing' ) . '</span><select name="sort" onchange="this.form.submit()">';
		foreach ( array( 'newest' => __( 'Newest', 'bwing' ), 'popular' => __( 'Most reviewed', 'bwing' ), 'az' => __( 'A–Z', 'bwing' ) ) as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $sort, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o .= '</select></label><noscript><button class="bw-btn bw-btn--ghost bw-btn--sm">' . esc_html__( 'Apply', 'bwing' ) . '</button></noscript></form>';

		if ( $query->have_posts() ) {
			$o .= self::grid( $query->posts );
			$o .= self::pager( $page, (int) $query->max_num_pages, function ( $i ) use ( $here, $keep, $lcat ) {
				return add_query_arg( $keep( array( 'lcat' => $lcat, 'pg' => $i > 1 ? $i : '' ) ), $here );
			} );
		} else {
			$o .= BWing_Render::empty_state( __( 'No listings match your search. Try another category or clear the filters.', 'bwing' ) );
		}

		$title    = $a['title'];
		$subtitle = $a['subtitle'];
		if ( $term && $a['pretty'] ) {
			/* translators: %s category name */
			$title    = sprintf( __( 'Best %s', 'bwing' ), $term->name );
			$subtitle = $term->description ? wp_strip_all_tags( $term->description ) : $subtitle;
		} elseif ( $tag && $a['pretty'] ) {
			/* translators: %s tag name */
			$title = sprintf( __( 'Listing tagged “%s”', 'bwing' ), $tag->name );
		}
		return BWing_Render::wrap( 'listing', BWing_Render::head( $title, $subtitle, 'h1' ) . '<div class="bw-finder">' . $o . '</div>' );
	}

	private static function top_ancestor( $term ) {
		$anc = get_ancestors( $term->term_id, self::CAT, 'taxonomy' );
		return $anc ? end( $anc ) : $term->term_id;
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array(
			'title'    => __( 'Listing', 'bwing' ),
			'subtitle' => __( 'Hand-picked WordPress themes, plugins, blocks, hosting and software — reviewed and rated by the community.', 'bwing' ),
			'per_page' => 18,
			'cate'     => '',
		), $atts, 'bwing_listings' );
		return self::directory( $a );
	}

	/** [bwlisting cate="woocommerce" count="9"] — compact grid, kept from the theme. */
	public static function shortcode_legacy( $atts ) {
		bwing_enqueue();
		$a     = shortcode_atts( array( 'cate' => 'woocommerce', 'count' => 9 ), $atts, 'bwlisting' );
		$posts = get_posts( array(
			'post_type'      => self::CPT,
			'posts_per_page' => max( 1, (int) $a['count'] ),
			'tax_query'      => array( array( 'taxonomy' => self::CAT, 'field' => 'slug', 'terms' => sanitize_title( $a['cate'] ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return $posts ? '<div class="bw-section bw-section--listing">' . self::grid( $posts ) . '</div>' : '';
	}

	/* ------------------------------------------------------------------ */
	/* Single listing                                                      */
	/* ------------------------------------------------------------------ */

	private static function share( $post ) {
		$url   = rawurlencode( get_permalink( $post ) );
		$title = rawurlencode( get_the_title( $post ) );
		$links = array(
			'x'        => array( 'X', 'https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url ),
			'facebook' => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $url ),
			'linkedin' => array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url ),
			'pinterest' => array( 'Pinterest', 'https://pinterest.com/pin/create/button/?url=' . $url . '&description=' . $title ),
		);
		$o = '<div class="bw-share">';
		foreach ( $links as $k => $l ) {
			$o .= '<a class="bw-share__btn bw-share__btn--' . esc_attr( $k ) . '" href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $l[0] ) . '</a>';
		}
		return $o . '</div>';
	}

	public static function single( $post = null ) {
		$post     = get_post( $post );
		$demo     = (string) get_post_meta( $post->ID, self::M_DEMO, true );
		$download = self::download_url( $post );
		$author   = self::author( $post );
		$wporg    = self::wporg( $post );
		$stats    = $wporg && class_exists( 'BWing_Data' ) && BWing_Data::item( $wporg['type'], $wporg['slug'] ) ? BWing_Format::item_url( $wporg['type'], $wporg['slug'] ) : '';
		$rating   = shortcode_exists( 'yasr_visitor_votes' ) ? do_shortcode( '[yasr_visitor_votes size="medium" postid="' . (int) $post->ID . '"]' ) : '';
		$comments = (int) get_comments_number( $post );

		$o  = '<a class="bw-back" href="' . esc_url( self::archive_url() ) . '">← ' . esc_html__( 'All listing', 'bwing' ) . '</a>';
		$o .= '<header class="bw-itemhead bw-itemhead--listing"><div class="bw-itemhead__body"><div class="bw-itemhead__tags">' . self::badges( $post ) . '</div>';
		$o .= '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		$o .= '<p class="bw-itemhead__by">';
		if ( $author ) {
			$o .= esc_html__( 'by', 'bwing' ) . ' <a href="' . esc_url( self::author_url( $author['slug'] ) ) . '">' . esc_html( $author['name'] ) . '</a> · ';
		}
		/* translators: %s date */
		$o .= esc_html( sprintf( __( 'Updated %s', 'bwing' ), get_the_modified_date( '', $post ) ) );
		if ( comments_open( $post ) || $comments ) {
			/* translators: %s number of reviews */
			$o .= ' · <a href="#comments">' . esc_html( sprintf( _n( '%s review', '%s reviews', $comments, 'bwing' ), number_format_i18n( $comments ) ) ) . '</a>';
		}
		$o .= '</p>' . ( $rating ? '<div class="bw-listing__rating">' . $rating . '</div>' : '' );
		$o .= '<div class="bw-itemhead__actions">';
		$o .= $demo ? '<a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( $demo ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Live demo', 'bwing' ) . ' ↗</a>' : '';
		$o .= $download ? '<a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( $download ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $wporg ? __( 'Free download', 'bwing' ) : __( 'Get it', 'bwing' ) ) . '</a>' : '';
		$o .= $stats ? '<a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( $stats ) . '">' . esc_html__( 'Stats & ranking', 'bwing' ) . '</a>' : '';
		$o .= '</div></div></header>';

		// Main column.
		$main = '';
		if ( has_post_thumbnail( $post ) ) {
			$main .= '<figure class="bw-listing__shot">' . ( $demo ? '<a href="' . esc_url( $demo ) . '" target="_blank" rel="noopener nofollow">' : '' ) . self::shot_html( $post, 'large', 'bw-listing__img' ) . ( $demo ? '</a>' : '' ) . '</figure>';
		}
		$desc = (string) get_post_meta( $post->ID, self::M_DESC, true );
		if ( trim( wp_strip_all_tags( $desc ) ) ) {
			$main .= '<section class="bw-panel"><h2>' . esc_html__( 'Overview', 'bwing' ) . '</h2><div class="bw-prose">' . wpautop( wp_kses_post( $desc ) ) . '</div></section>';
		}
		$body = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		if ( trim( wp_strip_all_tags( $body ) ) ) {
			$main .= '<section class="bw-panel"><h2>' . esc_html__( 'Key features', 'bwing' ) . '</h2><div class="bw-prose">' . $body . '</div></section>';
		}
		$tags = get_the_terms( $post, self::TAG );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$main .= '<div class="bw-listing__tags">';
			foreach ( $tags as $t ) {
				$main .= '<a class="bw-pill" href="' . esc_url( get_term_link( $t ) ) . '">#' . esc_html( $t->name ) . '</a>';
			}
			$main .= '</div>';
		}

		// Sidebar.
		$rows = array();
		if ( $author ) {
			$rows[ __( 'Author', 'bwing' ) ] = '<a href="' . esc_url( self::author_url( $author['slug'] ) ) . '">' . esc_html( $author['name'] ) . '</a>';
		}
		$cats = get_the_terms( $post, self::CAT );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$rows[ __( 'Category', 'bwing' ) ] = implode( ', ', array_map( function ( $t ) {
				return '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
			}, $cats ) );
		}
		if ( self::type_label( $post ) ) {
			$rows[ __( 'Price', 'bwing' ) ] = esc_html( self::type_label( $post ) );
		}
		$rows[ __( 'Added', 'bwing' ) ] = esc_html( get_the_date( '', $post ) );
		if ( $author && $author['site'] ) {
			$rows[ __( 'Website', 'bwing' ) ] = '<a href="' . esc_url( $author['site'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( preg_replace( '/^www\./', '', (string) wp_parse_url( $author['site'], PHP_URL_HOST ) ) ) . ' ↗</a>';
		}
		$side = '<section class="bw-panel"><h3>' . esc_html__( 'Details', 'bwing' ) . '</h3><dl class="bw-listing__facts">';
		foreach ( $rows as $k => $v ) {
			$side .= '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>';
		}
		$side .= '</dl>' . ( $demo ? '<a class="bw-btn bw-btn--primary bw-btn--block" href="' . esc_url( $demo ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'View more', 'bwing' ) . ' ↗</a>' : '' ) . '</section>';
		$side .= '<section class="bw-panel"><h3>' . esc_html__( 'Share', 'bwing' ) . '</h3>' . self::share( $post ) . '</section>';
		if ( is_active_sidebar( 'listing' ) ) {
			ob_start();
			dynamic_sidebar( 'listing' );
			$side .= '<div class="bw-listing__widgets">' . ob_get_clean() . '</div>';
		}

		$o .= '<div class="bw-listing"><div class="bw-listing__main">' . $main . '</div><aside class="bw-listing__side">' . $side . '</aside></div>';

		// Related.
		$related = self::related( $post );
		if ( $related ) {
			$o .= BWing_Render::wrap( 'listing-related', BWing_Render::head( __( 'Related listing', 'bwing' ), '', 'h2', $author ? array( 'url' => self::author_url( $author['slug'] ), 'text' => sprintf( /* translators: %s author */ __( 'More by %s →', 'bwing' ), $author['name'] ) ) : array() ) . self::grid( $related ) );
		}
		return '<article class="bw-single bw-single--listing">' . $o . '</article>';
	}

	private static function related( $post, $count = 6 ) {
		$terms = get_the_terms( $post, self::CAT );
		$args  = array(
			'post_type'      => self::CPT,
			'posts_per_page' => $count,
			'post__not_in'   => array( $post->ID ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
			'no_found_rows'  => true,
		);
		if ( $terms && ! is_wp_error( $terms ) ) {
			// Same top-level section (e.g. any theme for a theme), children included.
			$tops              = array_unique( array_map( array( __CLASS__, 'top_ancestor' ), $terms ) );
			$args['tax_query'] = array( array( 'taxonomy' => self::CAT, 'terms' => $tops, 'include_children' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return get_posts( $args );
	}

	/* ------------------------------------------------------------------ */
	/* Author page                                                         */
	/* ------------------------------------------------------------------ */

	public static function resolve_author() {
		$slug = sanitize_title( (string) get_query_var( 'bwl_author' ) );
		if ( ! $slug ) {
			return;
		}
		global $wp_query;
		$first = get_posts( array(
			'post_type'      => self::CPT,
			'posts_per_page' => 1,
			'meta_key'       => self::M_ASLUG, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		if ( ! $first ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		$author       = self::author( $first[0] );
		self::$author = array( 'slug' => $slug, 'name' => $author ? $author['name'] : $slug, 'site' => $author ? $author['site'] : '' );
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		bwing_enqueue();
	}

	public static function author_page() {
		if ( ! self::$author ) {
			return '';
		}
		$au   = self::$author;
		$page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$q    = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 18,
			'paged'          => $page,
			'meta_key'       => self::M_ASLUG, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $au['slug'], // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		// Category breakdown for this author.
		$counts = array();
		foreach ( get_posts( array( 'post_type' => self::CPT, 'posts_per_page' => 500, 'fields' => 'ids', 'meta_key' => self::M_ASLUG, 'meta_value' => $au['slug'], 'no_found_rows' => true ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$t = self::primary_term( get_post( $id ) );
			if ( $t ) {
				$counts[ $t->name ] = isset( $counts[ $t->name ] ) ? $counts[ $t->name ] + 1 : 1;
			}
		}
		arsort( $counts );

		$img  = '';
		$host = $au['site'] ? wp_parse_url( $au['site'], PHP_URL_HOST ) : '';
		if ( $host ) {
			$img = apply_filters( 'bwing_brand_logo_url', 'https://www.google.com/s2/favicons?sz=128&domain=' . rawurlencode( $host ), $host, null );
		}
		$wporg = class_exists( 'BWing_Data' ) && BWing_Data::author_items( $au['slug'] ) ? BWing_Format::author_url( $au['slug'] ) : '';

		$o  = '<a class="bw-back" href="' . esc_url( self::archive_url() ) . '">← ' . esc_html__( 'All listing', 'bwing' ) . '</a>';
		$o .= '<header class="bw-itemhead bw-itemhead--author">' . BWing_Format::avatar( $au['name'], 96, $img ) . '<div class="bw-itemhead__body">';
		$o .= '<h1>' . esc_html( $au['name'] ) . '</h1><p class="bw-itemhead__by">';
		/* translators: %s number */
		$o .= esc_html( sprintf( _n( '%s listing', '%s listings', $q->found_posts, 'bwing' ), number_format_i18n( $q->found_posts ) ) );
		foreach ( array_slice( $counts, 0, 4, true ) as $name => $n ) {
			$o .= ' · ' . esc_html( $name ) . ' <b>' . (int) $n . '</b>';
		}
		$o .= '</p><div class="bw-itemhead__actions">';
		$o .= $au['site'] ? '<a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( $au['site'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Author website', 'bwing' ) . ' ↗</a>' : '';
		$o .= $wporg ? '<a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( $wporg ) . '">' . esc_html__( 'WordPress.org stats', 'bwing' ) . '</a>' : '';
		$o .= '</div></div></header>';

		/* translators: %s author */
		$head = BWing_Render::head( sprintf( __( 'Everything by %s', 'bwing' ), $au['name'] ) );
		$base = self::author_url( $au['slug'] );
		$o   .= BWing_Render::wrap( 'listing', $head . self::grid( $q->posts ) . self::pager( $page, (int) $q->max_num_pages, function ( $i ) use ( $base ) {
			return $i > 1 ? add_query_arg( 'pg', $i, $base ) : $base;
		} ) );
		return '<article class="bw-single bw-single--listing-author">' . $o . '</article>';
	}

	/* ------------------------------------------------------------------ */
	/* Templates, titles, SEO                                              */
	/* ------------------------------------------------------------------ */

	/** Which listing view this request is, or ''. */
	public static function view() {
		if ( self::$author ) {
			return 'author';
		}
		if ( is_singular( self::CPT ) ) {
			return 'single';
		}
		if ( is_post_type_archive( self::CPT ) || is_tax( array( self::CAT, self::TAG ) ) ) {
			return 'archive';
		}
		return '';
	}

	public static function template( $template ) {
		if ( ! self::view() ) {
			return $template;
		}
		bwing_enqueue();
		$theme_tpl = locate_template( array( 'bwing/listing.php' ) );
		return $theme_tpl ? $theme_tpl : BWING_DIR . 'templates/listing.php';
	}

	/** Content for the current listing view (used by templates/listing.php). */
	public static function render() {
		switch ( self::view() ) {
			case 'single':
				return self::single( get_queried_object_id() );
			case 'author':
				return self::author_page();
			case 'archive':
				$obj = get_queried_object();
				$tax = $obj instanceof WP_Term ? $obj : null;
				return self::directory( array(
					'cate'   => $tax && self::CAT === $tax->taxonomy ? $tax->slug : '',
					'tag'    => $tax && self::TAG === $tax->taxonomy ? $tax->slug : '',
					'pretty' => true,
				) );
		}
		return '';
	}

	public static function title( $parts ) {
		if ( self::$author ) {
			/* translators: %s author */
			$parts['title'] = sprintf( __( '%s — Themes, Plugins & Products', 'bwing' ), self::$author['name'] );
		} elseif ( is_post_type_archive( self::CPT ) ) {
			$parts['title'] = __( 'Listing — Best WordPress Themes, Plugins & Tools', 'bwing' );
		}
		return $parts;
	}

	public static function body_class( $classes ) {
		$view = self::view();
		if ( $view ) {
			$classes[] = 'bwing-single';
			$classes[] = 'bwing-listing--' . $view;
		}
		return $classes;
	}

	public static function schema() {
		if ( ! is_singular( self::CPT ) ) {
			return;
		}
		$post   = get_queried_object();
		$author = self::author( $post );
		$term   = self::primary_term( $post );
		$schema = array_filter( array(
			'@context'            => 'https://schema.org',
			'@type'               => 'SoftwareApplication',
			'name'                => get_the_title( $post ),
			'description'         => self::summary( $post, 40 ),
			'url'                 => get_permalink( $post ),
			'image'               => get_the_post_thumbnail_url( $post, 'large' ),
			'applicationCategory' => $term ? $term->name : 'WebApplication',
			'operatingSystem'     => 'Web',
			'author'              => $author ? array_filter( array( '@type' => 'Organization', 'name' => $author['name'], 'url' => $author['site'] ) ) : null,
			'offers'              => 'free' === strtolower( (string) get_post_meta( $post->ID, self::M_TYPE, true ) ) ? array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ) : null,
		) );
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		if ( ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) ) {
			echo '<meta name="description" content="' . esc_attr( wp_html_excerpt( self::summary( $post, 40 ), 158, '…' ) ) . '">' . "\n";
		}
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public static function meta_box() {
		// The old theme registered the same fields through OptionTree; don't show them twice.
		if ( function_exists( 'ot_register_meta_box' ) && function_exists( 'custom_meta_boxes' ) ) {
			return;
		}
		add_meta_box( 'bwl_details', __( 'Listing details', 'bwing' ), array( __CLASS__, 'meta_box_html' ), self::CPT, 'normal', 'high' );
	}

	private static function fields() {
		return array(
			self::M_DESC  => array( __( 'Short description', 'bwing' ), 'textarea', __( 'Shown as the "Overview" on the listing page and on cards.', 'bwing' ) ),
			self::M_DEMO  => array( __( 'Demo URL', 'bwing' ), 'url', '' ),
			self::M_DWD   => array( __( 'Download URL', 'bwing' ), 'url', __( 'A wordpress.org/themes/… or wordpress.org/plugins/… link becomes a direct .zip download and links to its stats page.', 'bwing' ) ),
			self::M_AUTH  => array( __( 'Author name', 'bwing' ), 'text', __( 'Creates the author page at /listing/author/{name}/.', 'bwing' ) ),
			self::M_AUTHU => array( __( 'Author URL', 'bwing' ), 'url', '' ),
			self::M_TYPE  => array( __( 'Free / Pro', 'bwing' ), 'type', '' ),
		);
	}

	public static function meta_box_html( $post ) {
		wp_nonce_field( 'bwl_save', '_bwlnonce' );
		echo '<table class="form-table">';
		foreach ( self::fields() as $key => $f ) {
			$v = (string) get_post_meta( $post->ID, $key, true );
			echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
			if ( 'textarea' === $f[1] ) {
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $v ) . '</textarea>';
			} elseif ( 'type' === $f[1] ) {
				echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"><option value="">—</option>';
				foreach ( array( 'free' => __( 'Free', 'bwing' ), 'pro' => __( 'Pro', 'bwing' ) ) as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( strtolower( $v ), $k, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input class="large-text" type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $v ) . '">';
			}
			echo $f[2] ? '<p class="description">' . esc_html( $f[2] ) . '</p>' : '';
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['_bwlnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bwlnonce'] ), 'bwl_save' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		foreach ( self::fields() as $key => $f ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			switch ( $f[1] ) {
				case 'textarea':
					$val = wp_kses_post( $raw );
					break;
				case 'url':
					$val = esc_url_raw( $raw );
					break;
				case 'type':
					$val = in_array( $raw, array( 'free', 'pro' ), true ) ? $raw : '';
					break;
				default:
					$val = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $key, $val );
		}
	}

	public static function sync_author_slug( $meta_id, $post_id, $meta_key, $value ) {
		if ( self::M_AUTH !== $meta_key || self::CPT !== get_post_type( $post_id ) ) {
			return;
		}
		$name = (string) get_post_meta( $post_id, self::M_AUTH, true );
		if ( '' === trim( $name ) ) {
			delete_post_meta( $post_id, self::M_ASLUG );
		} else {
			update_post_meta( $post_id, self::M_ASLUG, sanitize_title( $name ) );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'title' === $k ) {
				$new['bwl_shot'] = '';
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['bwl_author'] = __( 'Listing author', 'bwing' );
				$new['bwl_type']   = __( 'Free/Pro', 'bwing' );
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'bwl_shot':
				$src = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
				echo $src ? '<img src="' . esc_url( $src ) . '" width="48" height="36" style="border-radius:4px;object-fit:cover" alt="">' : '';
				break;
			case 'bwl_author':
				$a = self::author( get_post( $post_id ) );
				echo $a ? '<a href="' . esc_url( self::author_url( $a['slug'] ) ) . '" target="_blank">' . esc_html( $a['name'] ) . '</a>' : '—';
				break;
			case 'bwl_type':
				echo esc_html( self::type_label( get_post( $post_id ) ) ? self::type_label( get_post( $post_id ) ) : '—' );
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Install / upgrade                                                   */
	/* ------------------------------------------------------------------ */

	/** Categories used by the original listing page (slug => [name, parent]). */
	private static function starter_terms() {
		return array(
			'wp-theme'             => array( __( 'Themes', 'bwing' ), '' ),
			'business'             => array( __( 'Business', 'bwing' ), 'wp-theme' ),
			'shop'                 => array( __( 'Shop', 'bwing' ), 'wp-theme' ),
			'onepage'              => array( __( 'Onepage', 'bwing' ), 'wp-theme' ),
			'wp-plugin'            => array( __( 'Plugins', 'bwing' ), '' ),
			'shop-2'               => array( __( 'Shop', 'bwing' ), 'wp-plugin' ),
			'seo'                  => array( __( 'SEO', 'bwing' ), 'wp-plugin' ),
			'contact'              => array( __( 'Contact', 'bwing' ), 'wp-plugin' ),
			'gutenberg-blocks'     => array( __( 'Gutenberg Blocks', 'bwing' ), '' ),
			'hosting'              => array( __( 'Domain & Hosting', 'bwing' ), '' ),
			'software'             => array( __( 'Software', 'bwing' ), '' ),
			'programming-language' => array( __( 'Programming Language', 'bwing' ), 'software' ),
			'developer-tools'      => array( __( 'Developer Tools', 'bwing' ), 'software' ),
			'others'               => array( __( 'Others', 'bwing' ), 'software' ),
			'chrome-extensions'    => array( __( 'Chrome Extensions', 'bwing' ), '' ),
		);
	}

	public static function seed_terms() {
		self::register();
		$existing = get_terms( array( 'taxonomy' => self::CAT, 'hide_empty' => false, 'fields' => 'ids', 'number' => 1 ) );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return; // The site already has its own categories.
		}
		$ids = array();
		foreach ( self::starter_terms() as $slug => $t ) {
			$parent = $t[1] && ! empty( $ids[ $t[1] ] ) ? $ids[ $t[1] ] : 0;
			$r      = wp_insert_term( $t[0], self::CAT, array( 'slug' => $slug, 'parent' => $parent ) );
			if ( ! is_wp_error( $r ) ) {
				$ids[ $slug ] = (int) $r['term_id'];
			}
		}
	}

	/** Backfill author slugs for listings saved before this version. */
	public static function backfill_authors() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE p.post_type = %s AND m.meta_key = %s AND m.meta_value <> ''",
			self::CPT,
			self::M_AUTH
		) );
		foreach ( $rows as $r ) {
			update_post_meta( (int) $r->post_id, self::M_ASLUG, sanitize_title( $r->meta_value ) );
		}
	}

	/** Adds a "Listing" item (with top-level categories) to the BWing primary menu. */
	public static function add_menu() {
		$menu_id = (int) get_option( 'bwing_menu_primary' );
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
			return;
		}
		$url   = self::archive_url();
		$items = (array) wp_get_nav_menu_items( $menu_id );
		foreach ( $items as $it ) {
			if ( untrailingslashit( $it->url ) === untrailingslashit( $url ) ) {
				return; // Already there.
			}
		}
		$parents = get_terms( array( 'taxonomy' => self::CAT, 'parent' => 0, 'hide_empty' => false, 'number' => 8 ) );
		$parents = is_wp_error( $parents ) ? array() : $parents;
		// Place before "Blog" when it exists.
		$map = get_option( BWing_Pages::OPTION, array() );
		$pos = 0;
		foreach ( $items as $it ) {
			if ( ! empty( $map['blog'] ) && (int) $it->object_id === (int) $map['blog'] && 0 === (int) $it->menu_item_parent ) {
				$pos = (int) $it->menu_order;
			}
		}
		$need = count( $parents ) + 2;
		if ( $pos ) {
			foreach ( $items as $it ) {
				if ( (int) $it->menu_order >= $pos ) {
					wp_update_post( array( 'ID' => $it->ID, 'menu_order' => (int) $it->menu_order + $need ) );
				}
			}
		} else {
			$pos = count( $items ) + 1;
		}
		$add = function ( $title, $link, $parent, $position ) use ( $menu_id ) {
			return wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'     => $title,
				'menu-item-url'       => $link,
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
				'menu-item-position'  => $position,
			) );
		};
		$top = $add( __( 'Listing', 'bwing' ), $url, 0, $pos );
		if ( $top && ! is_wp_error( $top ) && $parents ) {
			$add( __( 'All listing', 'bwing' ), $url, $top, $pos + 1 );
			foreach ( $parents as $i => $t ) {
				$add( $t->name, get_term_link( $t ), $top, $pos + 2 + $i );
			}
		}
	}

	public static function migrate() {
		if ( (int) get_option( 'bwing_listing_rev' ) >= self::REV || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		update_option( 'bwing_listing_rev', self::REV );
		self::seed_terms();
		self::backfill_authors();
		self::add_menu();
		flush_rewrite_rules();
	}
}
