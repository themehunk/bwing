<?php
/**
 * Read layer: rankings, movement, authors, statistics.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Data {

	private static $date_cache = array();

	/* ------------------------------------------------------------------ */
	/* Snapshot dates                                                      */
	/* ------------------------------------------------------------------ */

	public static function latest_date() {
		return self::date_on_or_before( current_time( 'Y-m-d' ) );
	}

	/** Closest snapshot date on or before $date. */
	public static function date_on_or_before( $date ) {
		if ( isset( self::$date_cache[ $date ] ) ) {
			return self::$date_cache[ $date ];
		}
		global $wpdb;
		$t = BWing_DB::table( 'snapshots' );
		$d = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(snap_date) FROM {$t} WHERE snap_date <= %s", $date ) );
		self::$date_cache[ $date ] = $d;
		return $d;
	}

	public static function past_date( $days ) {
		$latest = self::latest_date();
		if ( ! $latest ) {
			return null;
		}
		$target = gmdate( 'Y-m-d', strtotime( $latest . ' -' . absint( $days ) . ' days' ) );
		$d      = self::date_on_or_before( $target );
		return ( $d && $d !== $latest ) ? $d : null;
	}

	public static function snapshot_days() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT snap_date) FROM ' . BWing_DB::table( 'snapshots' ) );
	}

	/** Map item_id => pos at a past window. */
	public static function positions_at( $ids, $days ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		$d   = self::past_date( $days );
		if ( ! $ids || ! $d ) {
			return array();
		}
		$t    = BWing_DB::table( 'snapshots' );
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, pos, active_installs, downloaded FROM {$t} WHERE snap_date = %s AND item_id IN ({$in})", $d ) ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->item_id ] = $r;
		}
		return $out;
	}

	/**
	 * Attach dl1/dl7/dl30 download counts (today, last 7 days, last 30 days) from the
	 * difference in cumulative downloads between snapshots. null = not enough history.
	 */
	public static function attach_downloads( $rows ) {
		if ( ! $rows ) {
			return $rows;
		}
		$ids = wp_list_pluck( $rows, 'id' );
		foreach ( array( 1, 7, 30 ) as $w ) {
			$past = self::positions_at( $ids, $w );
			$key  = 'dl' . $w;
			foreach ( $rows as $row ) {
				$diff      = isset( $past[ $row->id ] ) && $past[ $row->id ]->downloaded > 0 ? (int) $row->downloaded - (int) $past[ $row->id ]->downloaded : -1;
				$row->$key = $diff >= 0 ? $diff : null; // Negative = history from another data source (e.g. demo).
			}
		}
		return $rows;
	}

	/** Attach d1/d7/d30 deltas to rows (null = new, false = no data). */
	public static function attach_trends( $rows, $windows = array( 1, 7, 30 ) ) {
		if ( ! $rows ) {
			return $rows;
		}
		$ids = wp_list_pluck( $rows, 'id' );
		foreach ( $windows as $w ) {
			$has  = (bool) self::past_date( $w );
			$past = self::positions_at( $ids, $w );
			foreach ( $rows as $row ) {
				$key = 'd' . $w;
				if ( ! $has ) {
					$row->$key = 0;
				} elseif ( isset( $past[ $row->id ] ) && $past[ $row->id ]->pos > 0 ) {
					$row->$key = (int) $past[ $row->id ]->pos - (int) $row->pos;
				} else {
					$row->$key = null;
				}
			}
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ */
	/* Items                                                               */
	/* ------------------------------------------------------------------ */

	public static function count_items( $type = '' ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		if ( $type ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE type = %s", $type ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
	}

	public static function top_items( $type = 'theme', $limit = 20 ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND pos > 0 ORDER BY pos ASC LIMIT %d", $type, $limit ) );
	}

	public static function item( $type, $slug ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND slug = %s", $type, $slug ) );
	}

	public static function item_by_id( $id ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
	}

	/**
	 * Paged, filterable ranking.
	 *
	 * @param array $args type, page, per_page, sort, s, min, kind (block|classic).
	 * @return array rows, total, pages.
	 */
	public static function ranking( $args ) {
		global $wpdb;
		$a = wp_parse_args( $args, array(
			'type'     => 'theme',
			'page'     => 1,
			'per_page' => 50,
			'sort'     => 'popular',
			's'        => '',
			'min'      => 0,
			'kind'     => '',
		) );
		$t     = BWing_DB::table( 'items' );
		$where = $wpdb->prepare( 'WHERE type = %s', $a['type'] );
		if ( $a['s'] ) {
			$like   = '%' . $wpdb->esc_like( $a['s'] ) . '%';
			$where .= $wpdb->prepare( ' AND (name LIKE %s OR slug LIKE %s OR author LIKE %s)', $like, $like, $like );
		}
		if ( $a['min'] ) {
			$where .= $wpdb->prepare( ' AND active_installs >= %d', $a['min'] );
		}
		if ( 'block' === $a['kind'] ) {
			$where .= ' AND is_block = 1';
		} elseif ( 'classic' === $a['kind'] ) {
			$where .= ' AND is_block = 0';
		}
		$orders = array(
			'popular'   => 'pos = 0, pos ASC',
			'installs'  => 'active_installs DESC, pos ASC',
			'downloads' => 'downloaded DESC',
			'rating'    => 'rating DESC, num_ratings DESC',
			'newest'    => 'added DESC',
			'updated'   => 'last_updated DESC',
		);
		$order = isset( $orders[ $a['sort'] ] ) ? $orders[ $a['sort'] ] : $orders['popular'];
		$per   = max( 1, (int) $a['per_page'] );
		$page  = max( 1, (int) $a['page'] );
		$off   = ( $page - 1 ) * $per;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$where}" ); // phpcs:ignore
		$rows  = $wpdb->get_results( "SELECT * FROM {$t} {$where} ORDER BY {$order} LIMIT {$off}, {$per}" ); // phpcs:ignore
		return array(
			'rows'  => self::attach_trends( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
			'page'  => $page,
			'per'   => $per,
		);
	}

	public static function history( $item_id, $days = 0 ) {
		global $wpdb;
		$t = BWing_DB::table( 'snapshots' );
		if ( $days ) {
			$from = gmdate( 'Y-m-d', strtotime( '-' . absint( $days ) . ' days' ) );
			return $wpdb->get_results( $wpdb->prepare( "SELECT snap_date, pos, active_installs, downloaded FROM {$t} WHERE item_id = %d AND snap_date >= %s ORDER BY snap_date ASC", $item_id, $from ) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT snap_date, pos, active_installs, downloaded FROM {$t} WHERE item_id = %d ORDER BY snap_date ASC", $item_id ) );
	}

	public static function same_author( $item, $limit = 6 ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE author = %s AND id <> %d ORDER BY active_installs DESC LIMIT %d", $item->author, $item->id, $limit ) );
	}

	public static function similar( $item, $limit = 6 ) {
		global $wpdb;
		$t    = BWing_DB::table( 'items' );
		$tags = array_slice( array_filter( array_map( 'trim', explode( ',', (string) $item->tags ) ) ), 0, 3 );
		$cond = '';
		foreach ( $tags as $tag ) {
			$cond .= $wpdb->prepare( ' OR tags LIKE %s', '%' . $wpdb->esc_like( $tag ) . '%' );
		}
		$cond = $cond ? 'AND (0 ' . $cond . ')' : '';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND id <> %d AND author <> %s {$cond} ORDER BY active_installs DESC LIMIT %d", $item->type, $item->id, $item->author, $limit ) ); // phpcs:ignore
	}

	public static function fastest( $limit = 30 ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = 'theme' AND perf_mobile > 0 ORDER BY perf_mobile DESC, perf_desktop DESC LIMIT %d", $limit ) );
	}

	public static function search( $q, $limit = 8 ) {
		global $wpdb;
		$t    = BWing_DB::table( 'items' );
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, type, slug, name, author, image, active_installs, pos FROM {$t} WHERE name LIKE %s OR slug LIKE %s ORDER BY active_installs DESC LIMIT %d", $like, $like, $limit ) );
	}

	/* ------------------------------------------------------------------ */
	/* Movement                                                            */
	/* ------------------------------------------------------------------ */

	public static function movers( $type, $days = 7, $min = 10000, $limit = 4, $dir = 'up' ) {
		global $wpdb;
		$past = self::past_date( $days );
		if ( ! $past ) {
			return array();
		}
		$i     = BWing_DB::table( 'items' );
		$s     = BWing_DB::table( 'snapshots' );
		$order = 'up' === $dir ? 'DESC' : 'ASC';
		$cmp   = 'up' === $dir ? '> 0' : '< 0';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, (s.pos - i.pos) AS delta FROM {$i} i INNER JOIN {$s} s ON s.item_id = i.id AND s.snap_date = %s
			 WHERE i.type = %s AND i.pos > 0 AND s.pos > 0 AND i.active_installs >= %d AND (s.pos - i.pos) {$cmp}
			 ORDER BY delta {$order} LIMIT %d",
			$past, $type, $min, $limit
		) ); // phpcs:ignore
	}

	/**
	 * Featured items for one column: pinned authors / items first, then random
	 * picks from the top-ranked pool that stay the same for the whole day.
	 *
	 * @param string $type theme|plugin.
	 * @param array  $a    Featured section settings.
	 * @return object[] Rows with ->pinned set.
	 */
	public static function featured( $type, $a ) {
		global $wpdb;
		$t       = BWing_DB::table( 'items' );
		$count   = max( 1, (int) $a['count'] );
		$seed    = absint( crc32( current_time( 'Y-m-d' ) . $type ) ) % 2147483647;
		$authors = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['pinned_authors'] ) ) );
		$slugs   = array();
		foreach ( BWing_Settings::rows( $a['pinned_items'] ) as $r ) {
			if ( isset( $r[1] ) && $type === strtolower( $r[0] ) ) {
				$slugs[] = sanitize_title( $r[1] );
			}
		}

		$pinned = array();
		if ( $authors || $slugs ) {
			$cond = array();
			$args = array( $type );
			if ( $authors ) {
				$cond[] = 'author IN (' . implode( ',', array_fill( 0, count( $authors ), '%s' ) ) . ')';
				$args   = array_merge( $args, $authors );
			}
			if ( $slugs ) {
				$cond[] = 'slug IN (' . implode( ',', array_fill( 0, count( $slugs ), '%s' ) ) . ')';
				$args   = array_merge( $args, $slugs );
			}
			$pinned = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND (" . implode( ' OR ', $cond ) . ') ORDER BY active_installs DESC', $args ) ); // phpcs:ignore
			$room   = min( $count, (int) ( 'theme' === $type ? $a['pinned_themes'] : $a['pinned_plugins'] ) );
			if ( count( $pinned ) > $room ) {
				// More pinned items than slots: rotate them daily, best-known first.
				$keep   = array_slice( $pinned, 0, 1 );
				$rest   = array_slice( $pinned, 1 );
				mt_srand( $seed );
				shuffle( $rest );
				mt_srand();
				$pinned = $room > 0 ? array_merge( $keep, array_slice( $rest, 0, $room - 1 ) ) : array();
			}
			foreach ( $pinned as $p ) {
				$p->pinned = true;
			}
		}

		$need = $count - count( $pinned );
		$pick = array();
		if ( $need > 0 ) {
			$skip = $pinned ? ' AND id NOT IN (' . implode( ',', array_map( 'absint', wp_list_pluck( $pinned, 'id' ) ) ) . ')' : '';
			$pick = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$t} WHERE type = %s AND pos BETWEEN 1 AND %d AND active_installs >= %d{$skip} ORDER BY RAND(%d) LIMIT %d",
				$type, max( $need, (int) $a['random_pool'] ), (int) $a['random_min'], $seed, $need
			) ); // phpcs:ignore
			foreach ( $pick as $p ) {
				$p->pinned = false;
			}
		}
		return array_merge( $pinned, $pick );
	}

	public static function bracket( $n ) {
		foreach ( BWing_Format::BRACKETS as $b ) {
			if ( $n >= $b ) {
				return $b;
			}
		}
		return 0;
	}

	/** Notable events: bracket crossings, top-10 entries/exits, anomalies. */
	public static function notable( $days = 30, $limit = 9 ) {
		global $wpdb;
		$past = self::past_date( $days );
		if ( ! $past ) {
			return array();
		}
		$i    = BWing_DB::table( 'items' );
		$s    = BWing_DB::table( 'snapshots' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, s.pos AS old_pos, s.active_installs AS old_installs FROM {$i} i INNER JOIN {$s} s ON s.item_id = i.id AND s.snap_date = %s WHERE i.pos > 0",
			$past
		) );
		$events = array();
		foreach ( $rows as $r ) {
			$new_b = self::bracket( (int) $r->active_installs );
			$old_b = self::bracket( (int) $r->old_installs );
			if ( $r->old_installs > 0 && $r->active_installs < $r->old_installs * 0.6 ) {
				$events[] = array(
					'item'  => $r,
					'kind'  => 'anomaly',
					'score' => 1000 + $r->old_installs,
					'text'  => sprintf( /* translators: 1 old 2 new */ __( 'Installs dropped from %1$s to %2$s — possible data correction.', 'bwing' ), BWing_Format::installs( $r->old_installs ), BWing_Format::installs( $r->active_installs ) ),
				);
			} elseif ( $new_b > $old_b && $new_b >= 10000 ) {
				$events[] = array(
					'item'  => $r,
					'kind'  => 'bracket',
					'score' => $new_b,
					'text'  => sprintf( /* translators: 1 old 2 new */ __( 'Crossed into %2$s active installs (was %1$s).', 'bwing' ), BWing_Format::installs( $old_b ), BWing_Format::installs( $new_b ) ),
				);
			}
			if ( $r->pos <= 10 && $r->old_pos > 10 ) {
				$events[] = array(
					'item'  => $r,
					'kind'  => 'top10',
					'score' => 5000000 - $r->pos,
					'text'  => sprintf( /* translators: 1 old 2 new */ __( 'Entered the top 10: #%1$d → #%2$d.', 'bwing' ), $r->old_pos, $r->pos ),
				);
			} elseif ( $r->pos > 10 && $r->old_pos <= 10 && $r->old_pos > 0 ) {
				$events[] = array(
					'item'  => $r,
					'kind'  => 'top10out',
					'score' => 4000000,
					'text'  => sprintf( /* translators: 1 old 2 new */ __( 'Dropped out of the top 10: #%1$d → #%2$d.', 'bwing' ), $r->old_pos, $r->pos ),
				);
			}
		}
		usort( $events, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );
		return array_slice( $events, 0, $limit );
	}

	/* ------------------------------------------------------------------ */
	/* Authors                                                             */
	/* ------------------------------------------------------------------ */

	public static function authors( $args = array() ) {
		global $wpdb;
		$a = wp_parse_args( $args, array(
			'type'     => 'all',
			'page'     => 1,
			'per_page' => 50,
			'sort'     => 'total',
			'order'    => 'desc',
			's'        => '',
		) );
		$i     = BWing_DB::table( 'items' );
		$where = "WHERE author <> ''";
		if ( 'theme' === $a['type'] || 'plugin' === $a['type'] ) {
			$where .= $wpdb->prepare( ' AND type = %s', $a['type'] );
		}
		if ( $a['s'] ) {
			$like   = '%' . $wpdb->esc_like( $a['s'] ) . '%';
			$where .= $wpdb->prepare( ' AND (author LIKE %s OR author_name LIKE %s)', $like, $like );
		}
		$sorts = array( 'total', 'plugins', 'themes', 'plugin_installs', 'theme_installs' );
		$sort  = in_array( $a['sort'], $sorts, true ) ? $a['sort'] : 'total';
		$order = 'asc' === strtolower( $a['order'] ) ? 'ASC' : 'DESC';
		$per   = max( 1, (int) $a['per_page'] );
		$off   = ( max( 1, (int) $a['page'] ) - 1 ) * $per;

		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT author) FROM {$i} {$where}" ); // phpcs:ignore
		$rows  = $wpdb->get_results( "SELECT author, MAX(author_name) AS author_name, MAX(author_avatar) AS author_avatar, MAX(author_url) AS author_url,
				SUM(type='plugin') AS plugins, SUM(type='theme') AS themes,
				SUM(IF(type='plugin', active_installs, 0)) AS plugin_installs,
				SUM(IF(type='theme', active_installs, 0)) AS theme_installs,
				SUM(active_installs) AS total, GROUP_CONCAT(id) AS ids
			FROM {$i} {$where} GROUP BY author ORDER BY {$sort} {$order} LIMIT {$off}, {$per}" ); // phpcs:ignore

		// 30-day trend.
		$past = self::past_date( 30 );
		if ( $past && $rows ) {
			$s = BWing_DB::table( 'snapshots' );
			foreach ( $rows as $r ) {
				$ids      = implode( ',', array_map( 'absint', explode( ',', $r->ids ) ) );
				$old      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(active_installs) FROM {$s} WHERE snap_date = %s AND item_id IN ({$ids})", $past ) ); // phpcs:ignore
				$r->trend = $old ? (int) $r->total - $old : 0;
			}
		} else {
			foreach ( $rows as $r ) {
				$r->trend = 0;
			}
		}
		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
			'page'  => max( 1, (int) $a['page'] ),
			'per'   => $per,
		);
	}

	public static function author_items( $author ) {
		global $wpdb;
		$t = BWing_DB::table( 'items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE author = %s ORDER BY active_installs DESC", $author ) );
	}

	/* ------------------------------------------------------------------ */
	/* Premium                                                             */
	/* ------------------------------------------------------------------ */

	public static function premium( $type = 'theme', $limit = 20, $offset = 0 ) {
		global $wpdb;
		$t = BWing_DB::table( 'premium' );
		return self::premium_logos( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s ORDER BY live_sites DESC, sort_order ASC LIMIT %d, %d", $type, $offset, $limit ) ) );
	}

	public static function premium_item( $type, $slug ) {
		global $wpdb;
		$t = BWing_DB::table( 'premium' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s AND slug = %s", $type, $slug ) );
		return $row ? self::premium_logos( array( $row ) )[0] : null;
	}

	/**
	 * Fill in a brand logo for premium rows without an uploaded image: the free
	 * WordPress.org version's icon when there is one, else the vendor site's icon.
	 */
	public static function premium_logos( $rows ) {
		global $wpdb;
		$need = array();
		foreach ( $rows as $r ) {
			if ( ! $r->image && $r->free_slug ) {
				$need[ $r->type ][] = $r->free_slug;
			}
		}
		$free = array();
		foreach ( $need as $type => $slugs ) {
			$t   = BWing_DB::table( 'items' );
			$in  = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$res = $wpdb->get_results( $wpdb->prepare( "SELECT slug, image FROM {$t} WHERE type = %s AND slug IN ({$in})", array_merge( array( $type ), $slugs ) ) ); // phpcs:ignore
			foreach ( $res as $f ) {
				$free[ $type . ':' . $f->slug ] = $f->image;
			}
		}
		foreach ( $rows as $r ) {
			if ( $r->image ) {
				continue;
			}
			// A theme screenshot is not a logo, so only plugins borrow the free version's image.
			if ( 'plugin' === $r->type && ! empty( $free[ $r->type . ':' . $r->free_slug ] ) ) {
				$r->image = $free[ $r->type . ':' . $r->free_slug ];
			} elseif ( $r->url ) {
				$host = wp_parse_url( $r->url, PHP_URL_HOST );
				$r->image = $host ? apply_filters( 'bwing_brand_logo_url', 'https://www.google.com/s2/favicons?sz=128&domain=' . rawurlencode( $host ), $host, $r ) : '';
			}
			$r->auto_logo = true;
		}
		return $rows;
	}

	public static function premium_rank( $p ) {
		global $wpdb;
		$t = BWing_DB::table( 'premium' );
		return 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE type = %s AND live_sites > %d", $p->type, $p->live_sites ) );
	}

	/* ------------------------------------------------------------------ */
	/* Detector                                                            */
	/* ------------------------------------------------------------------ */

	public static function recent_detections( $limit = 8 ) {
		global $wpdb;
		$t = BWing_DB::table( 'detections' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY created_at DESC LIMIT %d", $limit ) );
	}

	public static function detections_for( $type, $slug, $limit = 6 ) {
		global $wpdb;
		$t = BWing_DB::table( 'detections' );
		if ( 'theme' === $type ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE theme = %s OR parent_theme = %s ORDER BY created_at DESC LIMIT %d", $slug, $slug, $limit ) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE plugins LIKE %s ORDER BY created_at DESC LIMIT %d", '%"' . $wpdb->esc_like( $slug ) . '"%', $limit ) );
	}

	/** Most detected themes or plugins: slug => count. */
	public static function most_detected( $type = 'theme', $limit = 20 ) {
		global $wpdb;
		$t      = BWing_DB::table( 'detections' );
		$counts = array();
		if ( 'theme' === $type ) {
			$rows = $wpdb->get_results( "SELECT IF(parent_theme <> '', parent_theme, theme) AS slug, COUNT(*) AS c FROM {$t} WHERE theme <> '' GROUP BY slug ORDER BY c DESC LIMIT " . absint( $limit ) ); // phpcs:ignore
			foreach ( $rows as $r ) {
				$counts[ $r->slug ] = (int) $r->c;
			}
		} else {
			foreach ( $wpdb->get_col( "SELECT plugins FROM {$t}" ) as $json ) { // phpcs:ignore
				$list = json_decode( (string) $json, true );
				foreach ( (array) $list as $p ) {
					$slug = is_array( $p ) ? $p['slug'] : $p;
					$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
				}
			}
			arsort( $counts );
			$counts = array_slice( $counts, 0, $limit, true );
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore
		return array( 'counts' => $counts, 'total' => $total );
	}

	/* ------------------------------------------------------------------ */
	/* Statistics                                                          */
	/* ------------------------------------------------------------------ */

	public static function stats( $type = 'theme' ) {
		global $wpdb;
		$key    = 'bwing_stats_' . $type . '_' . self::latest_date();
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$t = BWing_DB::table( 'items' );
		$w = $wpdb->prepare( 'WHERE type = %s', $type );

		$s                 = array();
		$s['count']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$w}" ); // phpcs:ignore
		$s['installs']     = (int) $wpdb->get_var( "SELECT SUM(active_installs) FROM {$t} {$w}" ); // phpcs:ignore
		$s['downloads']    = (int) $wpdb->get_var( "SELECT SUM(downloaded) FROM {$t} {$w}" ); // phpcs:ignore
		$s['authors']      = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT author) FROM {$t} {$w}" ); // phpcs:ignore
		$s['avg_rating']   = BWing_Format::stars( (float) $wpdb->get_var( "SELECT AVG(rating) FROM {$t} {$w} AND num_ratings > 0" ) ); // phpcs:ignore
		$s['avg_installs'] = $s['count'] ? (int) round( $s['installs'] / $s['count'] ) : 0;
		$s['block']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$w} AND is_block = 1" ); // phpcs:ignore
		$s['classic']      = $s['count'] - $s['block'];

		// Diversity: 1 - share of installs held by top 10.
		$top = array();
		foreach ( array( 10, 20, 50, 100 ) as $n ) {
			$inst      = (int) $wpdb->get_var( "SELECT SUM(active_installs) FROM (SELECT active_installs FROM {$t} {$w} ORDER BY active_installs DESC LIMIT {$n}) x" ); // phpcs:ignore
			$dl        = (int) $wpdb->get_var( "SELECT SUM(downloaded) FROM (SELECT downloaded FROM {$t} {$w} ORDER BY active_installs DESC LIMIT {$n}) x" ); // phpcs:ignore
			$top[ $n ] = array(
				'installs'  => $s['installs'] ? round( $inst / $s['installs'] * 100, 1 ) : 0,
				'downloads' => $s['downloads'] ? round( $dl / $s['downloads'] * 100, 1 ) : 0,
			);
		}
		$s['concentration'] = $top;
		$s['diversity']     = round( 100 - $top[10]['installs'] );

		// Excluding default "Twenty" themes.
		if ( 'theme' === $type ) {
			$wx               = $w . " AND slug NOT LIKE 'twenty%'";
			$s['ex_installs']  = (int) $wpdb->get_var( "SELECT SUM(active_installs) FROM {$t} {$wx}" ); // phpcs:ignore
			$s['ex_downloads'] = (int) $wpdb->get_var( "SELECT SUM(downloaded) FROM {$t} {$wx}" ); // phpcs:ignore
			$s['ex_count']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$wx}" ); // phpcs:ignore
		}

		// Histogram.
		$bands     = array( 0, 100, 1000, 10000, 100000, 1000000 );
		$labels    = array( '<100', '100+', '1K+', '10K+', '100K+', '1M+' );
		$histogram = array();
		foreach ( $bands as $k => $min ) {
			$max                       = isset( $bands[ $k + 1 ] ) ? $bands[ $k + 1 ] : PHP_INT_MAX;
			$histogram[ $labels[ $k ] ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} {$w} AND active_installs >= %d AND active_installs < %d", $min, $max ) ); // phpcs:ignore
		}
		$s['histogram'] = $histogram;

		// Ratings.
		$s['best']  = $wpdb->get_results( "SELECT * FROM {$t} {$w} AND num_ratings >= 20 ORDER BY rating DESC, num_ratings DESC LIMIT 5" ); // phpcs:ignore
		$s['worst'] = $wpdb->get_results( "SELECT * FROM {$t} {$w} AND num_ratings >= 10 ORDER BY rating ASC LIMIT 10" ); // phpcs:ignore

		// Abandonment.
		$aband = array();
		foreach ( array( 1, 2, 5, 10 ) as $y ) {
			$cut         = gmdate( 'Y-m-d', strtotime( '-' . $y . ' years' ) );
			$row         = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) c, SUM(active_installs) i FROM {$t} {$w} AND last_updated IS NOT NULL AND last_updated < %s", $cut ) ); // phpcs:ignore
			$aband[ $y ] = array(
				'count'    => (int) $row->c,
				'pct'      => $s['count'] ? round( $row->c / $s['count'] * 100, 1 ) : 0,
				'installs' => (int) $row->i,
			);
		}
		$s['abandoned']     = $aband;
		$s['abandoned_top'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} {$w} AND last_updated < %s ORDER BY active_installs DESC LIMIT 10", gmdate( 'Y-m-d', strtotime( '-2 years' ) ) ) ); // phpcs:ignore

		// Age bands.
		$ages = array();
		foreach ( array( array( 0, 1, '< 1 year' ), array( 1, 3, '1–3 years' ), array( 3, 5, '3–5 years' ), array( 5, 10, '5–10 years' ), array( 10, 100, '10+ years' ) ) as $band ) {
			$from                 = gmdate( 'Y-m-d', strtotime( '-' . $band[1] . ' years' ) );
			$to                   = gmdate( 'Y-m-d', strtotime( '-' . $band[0] . ' years' ) );
			$ages[ $band[2] ]     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} {$w} AND added > %s AND added <= %s", $from, $to ) ); // phpcs:ignore
		}
		$s['ages'] = $ages;

		// New per month (last 12).
		$months = array();
		for ( $m = 11; $m >= 0; $m-- ) {
			$start = gmdate( 'Y-m-01', strtotime( "-{$m} months", strtotime( gmdate( 'Y-m-01' ) ) ) );
			$end   = gmdate( 'Y-m-t', strtotime( $start ) );
			$months[ gmdate( 'M', strtotime( $start ) ) ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} {$w} AND added BETWEEN %s AND %s", $start, $end ) ); // phpcs:ignore
		}
		$s['months'] = $months;

		$s['top_by_count']    = $wpdb->get_results( "SELECT author, MAX(author_name) author_name, COUNT(*) c FROM {$t} {$w} AND author <> '' GROUP BY author ORDER BY c DESC LIMIT 10" ); // phpcs:ignore
		$s['top_by_installs'] = $wpdb->get_results( "SELECT author, MAX(author_name) author_name, SUM(active_installs) c FROM {$t} {$w} AND author <> '' GROUP BY author ORDER BY c DESC LIMIT 10" ); // phpcs:ignore

		set_transient( $key, $s, DAY_IN_SECONDS );
		return $s;
	}

	public static function hero_counts() {
		return array(
			'themes'  => self::count_items( 'theme' ),
			'plugins' => self::count_items( 'plugin' ),
			'days'    => self::snapshot_days(),
			'authors' => (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(DISTINCT author) FROM ' . BWing_DB::table( 'items' ) ),
		);
	}

	public static function flush_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bwing\_%' OR option_name LIKE '\_transient\_timeout\_bwing\_%'" );
		self::$date_cache = array();
	}
}
