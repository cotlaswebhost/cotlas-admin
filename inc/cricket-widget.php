<?php
/**
 * Cricket Widget Module
 *
 * Displays live/upcoming/previous cricket matches and series points tables
 * using the CricAPI (cricapi.com) free tier.
 *
 * Shortcodes:
 *   [cricket_widget]        — Tabbed match widget (LIVE / PREVIOUS / UPCOMING)
 *   [cricket_points_table]  — Series points table (any series, auto-detects current)
 *   [ipl_points_table]      — Alias for cricket_points_table (backward compat)
 *
 * @package CotlasAdmin
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail if the module is disabled.
if ( ! get_option( 'cotlas_cricket_enabled' ) ) {
	return;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * DATABASE CACHE
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Ensure the cricket cache table exists.
 */
function cotlas_cricket_ensure_cache_table() {
	global $wpdb;
	$table   = $wpdb->prefix . 'cotlas_cricket_cache';
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
		cache_key varchar(191) NOT NULL,
		cache_value longtext NOT NULL,
		cache_expires datetime NOT NULL,
		PRIMARY KEY (cache_key)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Get a cached API response. Returns data or false if expired/missing.
 */
function cotlas_cricket_cache_get( $key ) {
	global $wpdb;
	$table = $wpdb->prefix . 'cotlas_cricket_cache';
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT cache_value, cache_expires FROM {$table} WHERE cache_key = %s",
		$key
	) );
	if ( ! $row ) {
		return false;
	}
	if ( current_time( 'mysql', true ) > $row->cache_expires ) {
		$wpdb->delete( $table, array( 'cache_key' => $key ) );
		return false;
	}
	return json_decode( $row->cache_value, true );
}

/**
 * Store an API response in the cache.
 */
function cotlas_cricket_cache_set( $key, $data, $ttl_seconds ) {
	global $wpdb;
	$table   = $wpdb->prefix . 'cotlas_cricket_cache';
	$expires = gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds );
	$wpdb->replace( $table, array(
		'cache_key'     => $key,
		'cache_value'   => wp_json_encode( $data ),
		'cache_expires' => $expires,
	), array( '%s', '%s', '%s' ) );

	// Periodically clean expired entries (once per hour).
	if ( ! wp_next_scheduled( 'cotlas_cricket_cache_cleanup' ) ) {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'cotlas_cricket_cache_cleanup' );
	}
}

/**
 * Delete expired cache entries.
 */
function cotlas_cricket_cache_cleanup() {
	global $wpdb;
	$table = $wpdb->prefix . 'cotlas_cricket_cache';
	$wpdb->query( "DELETE FROM {$table} WHERE cache_expires < UTC_TIMESTAMP()" );
}
add_action( 'cotlas_cricket_cache_cleanup', 'cotlas_cricket_cache_cleanup' );

/* ═══════════════════════════════════════════════════════════════════════════
 * SERIES & COUNTRIES CACHING
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Fetch and cache the series list from the API. Stores in DB for 24 hours.
 * TODO: Uncomment when CricAPI provides a way to get points table series IDs.
 *       Currently the /v1/series endpoint doesn't include tournament series
 *       that have points data (e.g. TNPL, Ranji Trophy).
 *
 * Each series entry has: id, name, startDate, endDate, odi, t20, test, squad, match.
 * Also auto-cleans expired series from admin settings.
 */
/*
function cotlas_cricket_fetch_series_list( $force = false ) {
	$cached = cotlas_cricket_cache_get( 'cric_series_list_all' );
	if ( false !== $cached && ! $force ) {
		return $cached;
	}

	$all_series = array();
	$offset     = 0;
	$limit      = 25;

	// Paginate through all series (max 5 pages to stay within API budget).
	for ( $page = 0; $page < 5; $page++ ) {
		$data = cotlas_cricket_api( 'series', array( 'offset' => $offset, 'limit' => $limit ), 86400 );
		if ( empty( $data ) ) {
			break;
		}
		foreach ( $data as $s ) {
			$all_series[] = array(
				'id'        => isset( $s['id'] ) ? $s['id'] : '',
				'name'      => isset( $s['name'] ) ? $s['name'] : '',
				'startDate' => isset( $s['startDate'] ) ? $s['startDate'] : '',
				'endDate'   => isset( $s['endDate'] ) ? $s['endDate'] : '',
				'odi'       => isset( $s['odi'] ) ? (int) $s['odi'] : 0,
				't20'       => isset( $s['t20'] ) ? (int) $s['t20'] : 0,
				'test'      => isset( $s['test'] ) ? (int) $s['test'] : 0,
			);
		}
		$offset += $limit;
		if ( count( $data ) < $limit ) {
			break;
		}
	}

	// Cache for 24 hours.
	cotlas_cricket_cache_set( 'cric_series_list_all', $all_series, 86400 );

	// Auto-clean expired series from admin settings.
	cotlas_cricket_clean_expired_series( $all_series );

	return $all_series;
}
*/

/**
 * Remove expired series IDs from the admin settings.
 * TODO: Uncomment when cotlas_cricket_fetch_series_list is enabled.
 */
/*
function cotlas_cricket_clean_expired_series( $series_list ) {
	$raw = get_option( 'cotlas_cricket_series_ids', '' );
	if ( empty( $raw ) ) {
		return;
	}
	$admin_ids   = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	$valid_ids   = array();
	$today       = gmdate( 'Y-m-d' );
	$series_dates = array();

	foreach ( $series_list as $s ) {
		if ( $s['id'] ) {
			$series_dates[ $s['id'] ] = $s['endDate'];
		}
	}

	foreach ( $admin_ids as $sid ) {
		$end = isset( $series_dates[ $sid ] ) ? $series_dates[ $sid ] : '';
		if ( ! $end || $end >= $today ) {
			$valid_ids[] = $sid;
		}
	}

	if ( count( $valid_ids ) !== count( $admin_ids ) ) {
		update_option( 'cotlas_cricket_series_ids', implode( "\n", $valid_ids ) );
	}
}
*/

/**
 * Fetch and cache the countries list from the API. Stores in DB for 30 days.
 */
function cotlas_cricket_fetch_countries( $force = false ) {
	$cached = cotlas_cricket_cache_get( 'cric_countries_list' );
	if ( false !== $cached && ! $force ) {
		return $cached;
	}

	$data = cotlas_cricket_api( 'countries', array( 'offset' => 0 ), 2592000 ); // 30 days.
	$countries = array();
	foreach ( $data as $c ) {
		$countries[] = array(
			'id'   => isset( $c['id'] ) ? $c['id'] : '',
			'name' => isset( $c['name'] ) ? $c['name'] : '',
		);
	}

	cotlas_cricket_cache_set( 'cric_countries_list', $countries, 2592000 );
	return $countries;
}

/**
 * Get admin-configured country names for match filtering.
 * Returns array of lowercase country names.
 */
function cotlas_cricket_get_filter_countries() {
	$raw = get_option( 'cotlas_cricket_filter_countries', '' );
	if ( empty( $raw ) ) {
		// Default to India if not configured.
		return array( 'india' );
	}
	return array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $raw ) ) ) );
}

/**
 * Check if a match relates to any of the configured countries.
 * Matches against team names, series name, and known team keywords.
 */
function cotlas_cricket_match_filter( $match ) {
	$countries = cotlas_cricket_get_filter_countries();
	if ( empty( $countries ) || in_array( 'all', $countries, true ) ) {
		return true;
	}

	$t1     = strtolower( isset( $match['t1'] ) ? $match['t1'] : '' );
	$t2     = strtolower( isset( $match['t2'] ) ? $match['t2'] : '' );
	$series = strtolower( str_replace( "\n", ' ', isset( $match['series'] ) ? $match['series'] : '' ) );

	// Known franchise/league keywords per country.
	$country_keywords = array(
		'india'     => array( 'indian premier league', 'ipl', 'india', 'rajasthan royals', 'mumbai indians', 'chennai super kings', 'kolkata knight riders', 'sunrisers hyderabad', 'delhi capitals', 'royal challengers', 'punjab kings', 'lucknow super giants', 'gujarat titans' ),
		'australia' => array( 'australia', 'big bash', 'bbl', 'perth scorchers', 'melbourne stars', 'sydney sixers' ),
		'england'   => array( 'england', 'county championship', 'the hundred', 'surrey', 'middlesex', 'yorkshire', 'lancashire' ),
		'pakistan'   => array( 'pakistan', 'psl', 'lahore', 'karachi', 'islamabad', 'multan', 'quetta', 'peshawar' ),
	);

	foreach ( $countries as $country ) {
		// Direct match: country name in team or series.
		if ( false !== strpos( $t1, $country ) || false !== strpos( $t2, $country ) || false !== strpos( $series, $country ) ) {
			return true;
		}
		// Keyword match: franchise/league names.
		if ( isset( $country_keywords[ $country ] ) ) {
			foreach ( $country_keywords[ $country ] as $kw ) {
				if ( false !== strpos( $t1, $kw ) || false !== strpos( $t2, $kw ) || false !== strpos( $series, $kw ) ) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Get smart cache TTL based on match state.
 * Live: 1 hour, Upcoming/Previous: 24 hours.
 */
function cotlas_cricket_get_cache_ttl() {
	// Check if any match is currently live (cached state).
	$live_check = cotlas_cricket_cache_get( 'cric_has_live_match' );
	if ( true === $live_check ) {
		return 3600; // 1 hour — live match in progress.
	}
	return 86400; // 24 hours — no live matches.
}

/**
 * Schedule daily midnight refresh for series list and countries.
 */
function cotlas_cricket_schedule_cron() {
	if ( ! wp_next_scheduled( 'cotlas_cricket_daily_refresh' ) ) {
		// Schedule at midnight UTC.
		$midnight = strtotime( 'tomorrow midnight UTC' );
		wp_schedule_event( $midnight, 'daily', 'cotlas_cricket_daily_refresh' );
	}
}
add_action( 'init', 'cotlas_cricket_schedule_cron' );

/**
 * Daily midnight refresh callback.
 */
function cotlas_cricket_daily_refresh() {
	// cotlas_cricket_fetch_series_list( true ); // TODO: Enable when CricAPI supports points table series list.
	cotlas_cricket_fetch_countries( true );
}
add_action( 'cotlas_cricket_daily_refresh', 'cotlas_cricket_daily_refresh' );

/**
 * When series IDs are saved, immediately fetch fresh points data for each.
 */
function cotlas_cricket_on_series_ids_update( $old, $new ) {
	if ( $old === $new ) {
		return;
	}
	$lines = array_filter( array_map( 'trim', explode( "\n", $new ) ) );
	foreach ( $lines as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$sid   = $parts[0];
		if ( empty( $sid ) ) {
			continue;
		}
		// Force-refresh points data for each series.
		cotlas_cricket_api( 'series_points', array( 'id' => $sid ), 14400 );
	}
}
add_action( 'update_option_cotlas_cricket_series_ids', 'cotlas_cricket_on_series_ids_update', 10, 2 );

/* ═══════════════════════════════════════════════════════════════════════════
 * API KEY ROTATION
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Get all configured API keys (main + rotation).
 * Returns array of non-empty keys.
 */
function cotlas_cricket_get_api_keys() {
	$keys = array();
	$main = get_option( 'cotlas_cricapi_key', '' );
	if ( $main ) {
		$keys[] = $main;
	}
	$extra = get_option( 'cotlas_cricapi_keys_extra', '' );
	if ( $extra ) {
		foreach ( explode( "\n", $extra ) as $k ) {
			$k = trim( $k );
			if ( $k && ! in_array( $k, $keys, true ) ) {
				$keys[] = $k;
			}
		}
	}
	return $keys;
}

/**
 * Pick the best API key (least used today). Tracks hits per key in a transient.
 * Returns array( 'key' => string, 'index' => int ).
 */
function cotlas_cricket_pick_api_key() {
	$keys = cotlas_cricket_get_api_keys();
	if ( empty( $keys ) ) {
		return false;
	}
	if ( 1 === count( $keys ) ) {
		return array( 'key' => $keys[0], 'index' => 0 );
	}

	$usage = get_transient( 'cotlas_cricapi_key_usage' );
	if ( ! is_array( $usage ) ) {
		$usage = array();
	}

	// Reset daily: store the date and clear if it's a new day.
	$today = gmdate( 'Y-m-d' );
	if ( isset( $usage['_date'] ) && $usage['_date'] !== $today ) {
		$usage = array( '_date' => $today );
	}
	$usage['_date'] = $today;

	// Find the key with the least hits.
	$best_idx = 0;
	$best_hits = PHP_INT_MAX;
	foreach ( $keys as $i => $key ) {
		$short = substr( md5( $key ), 0, 8 );
		$hits  = isset( $usage[ $short ] ) ? (int) $usage[ $short ] : 0;
		if ( $hits < $best_hits ) {
			$best_hits = $hits;
			$best_idx  = $i;
		}
	}

	set_transient( 'cotlas_cricapi_key_usage', $usage, DAY_IN_SECONDS );
	return array( 'key' => $keys[ $best_idx ], 'index' => $best_idx );
}

/**
 * Increment the hit counter for a given API key.
 */
function cotlas_cricket_track_api_hit( $api_key ) {
	$usage = get_transient( 'cotlas_cricapi_key_usage' );
	if ( ! is_array( $usage ) ) {
		$usage = array( '_date' => gmdate( 'Y-m-d' ) );
	}
	$short         = substr( md5( $api_key ), 0, 8 );
	$usage[ $short ] = isset( $usage[ $short ] ) ? (int) $usage[ $short ] + 1 : 1;
	set_transient( 'cotlas_cricapi_key_usage', $usage, DAY_IN_SECONDS );
}

/**
 * Fetch with cache and key rotation. Returns decoded data array or empty array.
 * @param string $endpoint  API endpoint path (e.g. 'cricScore').
 * @param array  $params    Query params (apikey is added automatically).
 * @param int    $ttl       Cache TTL in seconds.
 */
function cotlas_cricket_api( $endpoint, $params = array(), $ttl = 900 ) {
	// Check DB cache first.
	$cache_key = 'cric_' . $endpoint . '_' . md5( wp_json_encode( $params ) );
	$cached    = cotlas_cricket_cache_get( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// Pick the best API key.
	$picked = cotlas_cricket_pick_api_key();
	if ( ! $picked ) {
		return array();
	}

	$params['apikey'] = $picked['key'];
	$url = add_query_arg( $params, 'https://api.cricapi.com/v1/' . $endpoint );
	$resp = wp_remote_get( $url, array( 'timeout' => 8 ) );

	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	// Track the API hit.
	cotlas_cricket_track_api_hit( $picked['key'] );

	$data = array();
	if ( ! empty( $body['data'] ) && is_array( $body['data'] ) ) {
		$data = $body['data'];
	}

	// Cache the result.
	$cache_ttl = ! empty( $data ) ? $ttl : 60;
	cotlas_cricket_cache_set( $cache_key, $data, $cache_ttl );

	return $data;
}

/**
 * Fetch cricket matches. Uses cricScore as primary, enriches with currentMatches.
 */
function cotlas_fetch_cricket_matches( $api_key_unused = '', $cache_seconds = 0 ) {
	if ( $cache_seconds <= 0 ) {
		$cache_seconds = cotlas_cricket_get_cache_ttl();
	}
	$matches = array();

	// Primary: cricScore (has live, result, AND fixture/upcoming).
	$cric_data = cotlas_cricket_api( 'cricScore', array(), $cache_seconds );
	foreach ( $cric_data as $m ) {
		$matches[] = cotlas_normalize_cricscore_match( $m );
	}

	if ( empty( $matches ) ) {
		return $matches;
	}

	// Check for live matches and update the live flag.
	$has_live = false;
	foreach ( $matches as $m ) {
		if ( 'live' === $m['ms'] ) {
			$has_live = true;
			break;
		}
	}
	cotlas_cricket_cache_set( 'cric_has_live_match', $has_live, 7200 );

	// Enrich with currentMatches (adds venue, series_id, better scores).
	$enrich = array();
	$cm_data = cotlas_cricket_api( 'currentMatches', array( 'offset' => 0 ), $cache_seconds );
	foreach ( $cm_data as $m ) {
		$enrich[ $m['id'] ] = $m;
	}

	if ( ! empty( $enrich ) ) {
		foreach ( $matches as &$m ) {
			$mid = isset( $m['id'] ) ? $m['id'] : '';
			if ( $mid && isset( $enrich[ $mid ] ) ) {
				$cm = $enrich[ $mid ];
				if ( ! empty( $cm['venue'] ) ) {
					$m['venue'] = $cm['venue'];
				}
				if ( ! empty( $cm['series_id'] ) ) {
					$m['series_id'] = $cm['series_id'];
				}
				if ( ! empty( $cm['score'] ) && is_array( $cm['score'] ) ) {
					$t1_lower = strtolower( $m['t1'] );
					$t2_lower = strtolower( $m['t2'] );
					$t1s = '';
					$t2s = '';
					foreach ( $cm['score'] as $s ) {
						$inning = isset( $s['inning'] ) ? strtolower( $s['inning'] ) : '';
						$r = isset( $s['r'] ) ? (int) $s['r'] : 0;
						$w = isset( $s['w'] ) ? (int) $s['w'] : 0;
						$o = isset( $s['o'] ) ? $s['o'] : '';
						$score_str = $r . '/' . $w . ( $o ? ' (' . $o . ')' : '' );
						if ( $t1_lower && false !== strpos( $inning, $t1_lower ) ) {
							$t1s = $score_str;
						} elseif ( $t2_lower && false !== strpos( $inning, $t2_lower ) ) {
							$t2s = $score_str;
						} elseif ( ! $t1s ) {
							$t1s = $score_str;
						} else {
							$t2s = $score_str;
						}
					}
					if ( $t1s ) { $m['t1s'] = $t1s; }
					if ( $t2s ) { $m['t2s'] = $t2s; }
				}
			}
		}
		unset( $m );
	}

	return $matches;
}

/**
 * Normalize a currentMatches entry to the common format.
 */
function cotlas_normalize_current_match( $m ) {
	$started = ! empty( $m['matchStarted'] );
	$ended   = ! empty( $m['matchEnded'] );
	$status  = isset( $m['status'] ) ? trim( $m['status'] ) : '';

	// Determine ms (match state).
	if ( $ended ) {
		$ms = 'result';
	} elseif ( $started && ! preg_match( '/^match starts at/i', $status ) ) {
		$ms = 'live';
	} else {
		$ms = 'fixture';
	}

	// Extract team info.
	$team_info = isset( $m['teamInfo'] ) && is_array( $m['teamInfo'] ) ? $m['teamInfo'] : array();
	$teams     = isset( $m['teams'] ) && is_array( $m['teams'] ) ? $m['teams'] : array();

	$t1 = isset( $team_info[0]['name'] ) ? $team_info[0]['name'] : ( isset( $teams[0] ) ? $teams[0] : '' );
	$t2 = isset( $team_info[1]['name'] ) ? $team_info[1]['name'] : ( isset( $teams[1] ) ? $teams[1] : '' );
	$t1img = isset( $team_info[0]['img'] ) ? $team_info[0]['img'] : '';
	$t2img = isset( $team_info[1]['img'] ) ? $team_info[1]['img'] : '';

	// Build score strings from innings data.
	$t1s = '';
	$t2s = '';
	$scores = isset( $m['score'] ) && is_array( $m['score'] ) ? $m['score'] : array();
	foreach ( $scores as $s ) {
		$inning = isset( $s['inning'] ) ? strtolower( $s['inning'] ) : '';
		$r      = isset( $s['r'] ) ? (int) $s['r'] : 0;
		$w      = isset( $s['w'] ) ? (int) $s['w'] : 0;
		$o      = isset( $s['o'] ) ? $s['o'] : '';
		$score_str = $r . '/' . $w . ( $o ? ' (' . $o . ')' : '' );

		$t1_lower = strtolower( $t1 );
		$t2_lower = strtolower( $t2 );
		if ( $t1_lower && false !== strpos( $inning, $t1_lower ) ) {
			$t1s = $score_str;
		} elseif ( $t2_lower && false !== strpos( $inning, $t2_lower ) ) {
			$t2s = $score_str;
		} elseif ( ! $t1s ) {
			$t1s = $score_str;
		} else {
			$t2s = $score_str;
		}
	}

	return array(
		'ms'         => $ms,
		't1'         => $t1,
		't2'         => $t2,
		't1s'        => $t1s,
		't2s'        => $t2s,
		't1img'      => $t1img,
		't2img'      => $t2img,
		'series'     => isset( $m['name'] ) ? $m['name'] : '',
		'matchType'  => isset( $m['matchType'] ) ? $m['matchType'] : '',
		'status'     => $status,
		'dateTimeGMT' => isset( $m['dateTimeGMT'] ) ? $m['dateTimeGMT'] : '',
		'venue'      => isset( $m['venue'] ) ? $m['venue'] : '',
		'series_id'  => isset( $m['series_id'] ) ? $m['series_id'] : '',
	);
}

/**
 * Normalize a cricScore entry to the common format.
 */
function cotlas_normalize_cricscore_match( $m ) {
	return array(
		'id'         => isset( $m['id'] ) ? $m['id'] : '',
		'ms'         => isset( $m['ms'] ) ? $m['ms'] : 'fixture',
		't1'         => cotlas_clean_team_name( isset( $m['t1'] ) ? $m['t1'] : '' ),
		't2'         => cotlas_clean_team_name( isset( $m['t2'] ) ? $m['t2'] : '' ),
		't1s'        => isset( $m['t1s'] ) ? trim( $m['t1s'] ) : '',
		't2s'        => isset( $m['t2s'] ) ? trim( $m['t2s'] ) : '',
		't1img'      => isset( $m['t1img'] ) ? $m['t1img'] : '',
		't2img'      => isset( $m['t2img'] ) ? $m['t2img'] : '',
		'series'     => isset( $m['series'] ) ? $m['series'] : '',
		'matchType'  => isset( $m['matchType'] ) ? $m['matchType'] : '',
		'status'     => isset( $m['status'] ) ? $m['status'] : '',
		'dateTimeGMT' => isset( $m['dateTimeGMT'] ) ? $m['dateTimeGMT'] : '',
		'venue'      => '',
		'series_id'  => '',
	);
}

/**
 * Fetch series points table from cache/API.
 */
function cotlas_fetch_series_points( $series_id, $cache_seconds = 21600 ) {
	return cotlas_cricket_api( 'series_points', array( 'id' => $series_id ), $cache_seconds );
}

/**
 * Auto-detect the most relevant series ID that has points table data.
 * Checks admin-configured series first, then tries auto-detection.
 * Returns array( 'series_id' => '...', 'series_name' => '...' ) or false.
 */
function cotlas_get_current_series_id() {
	// Check admin-configured series IDs first.
	$admin_series = cotlas_get_admin_series_ids();
	if ( ! empty( $admin_series ) ) {
		if ( count( $admin_series ) === 1 ) {
			// Single series: always use it.
			return $admin_series[0];
		}
		// Multiple series: rotate every 2 hours.
		$idx = (int) ( floor( time() / 7200 ) % count( $admin_series ) );
		return $admin_series[ $idx ];
	}

	// Auto-detect: collect series IDs from currentMatches and cricScore.
	$series_map = array();

	$cm_data = cotlas_cricket_api( 'currentMatches', array( 'offset' => 0 ), 3600 );
	foreach ( $cm_data as $m ) {
		$sid = isset( $m['series_id'] ) ? $m['series_id'] : '';
		if ( $sid ) {
			$series_map[ $sid ] = isset( $series_map[ $sid ] ) ? $series_map[ $sid ] + 1 : 1;
		}
	}

	$cric_series_names = array();
	$cric_data = cotlas_cricket_api( 'cricScore', array(), 3600 );
	foreach ( $cric_data as $m ) {
		$sn = isset( $m['series'] ) ? trim( $m['series'] ) : '';
		if ( $sn && ! in_array( $sn, $cric_series_names, true ) ) {
			$cric_series_names[] = $sn;
		}
	}

	if ( empty( $series_map ) && empty( $cric_series_names ) ) {
		return false;
	}

	uasort( $series_map, function ( $a, $b ) { return $b - $a; } );

	// Try each series from currentMatches — validate it has points data.
	foreach ( array_keys( $series_map ) as $sid ) {
		$pts = cotlas_cricket_api( 'series_points', array( 'id' => $sid ), 21600 );
		if ( ! empty( $pts ) && is_array( $pts ) ) {
			$series_name = '';
			$info = cotlas_cricket_api( 'series_info', array( 'id' => $sid ), 86400 );
			if ( ! empty( $info['name'] ) ) {
				$series_name = $info['name'];
			} elseif ( is_array( $info ) && ! empty( $info ) ) {
				$series_name = isset( $info[0]['name'] ) ? $info[0]['name'] : '';
			}
			return array( 'series_id' => $sid, 'series_name' => $series_name );
		}
	}

	// Search by series names from cricScore.
	foreach ( $cric_series_names as $name ) {
		$search = cotlas_cricket_api( 'series', array( 'search' => $name, 'offset' => 0 ), 86400 );
		if ( ! empty( $search ) && is_array( $search ) ) {
			foreach ( $search as $s ) {
				$sid = isset( $s['id'] ) ? $s['id'] : '';
				if ( ! $sid ) { continue; }
				$pts = cotlas_cricket_api( 'series_points', array( 'id' => $sid ), 21600 );
				if ( ! empty( $pts ) && is_array( $pts ) ) {
					return array( 'series_id' => $sid, 'series_name' => isset( $s['name'] ) ? $s['name'] : $name );
				}
			}
		}
	}

	return false;
}

/**
 * Get admin-configured series IDs with names.
 * Returns array of array( 'series_id' => '...', 'series_name' => '...' ).
 */
function cotlas_get_admin_series_ids() {
	$raw = get_option( 'cotlas_cricket_series_ids', '' );
	if ( empty( $raw ) ) {
		return array();
	}
	$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	$result = array();
	foreach ( $lines as $line ) {
		// Support "series_id | Title" format.
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$sid   = $parts[0];
		$name  = isset( $parts[1] ) && $parts[1] ? $parts[1] : '';

		if ( empty( $sid ) ) {
			continue;
		}

		// If no manual title, try to get from API.
		if ( ! $name ) {
			$info = cotlas_cricket_api( 'series_info', array( 'id' => $sid ), 86400 );
			if ( ! empty( $info['name'] ) ) {
				$name = $info['name'];
			} elseif ( is_array( $info ) && ! empty( $info ) ) {
				$name = isset( $info[0]['name'] ) ? $info[0]['name'] : '';
			}
		}
		$result[] = array( 'series_id' => $sid, 'series_name' => $name );
	}
	return $result;
}

/**
 * Returns true if a match is India / IPL related.
 */
function cotlas_is_india_ipl_match( $match ) {
	$t1     = strtolower( isset( $match['t1'] ) ? $match['t1'] : '' );
	$t2     = strtolower( isset( $match['t2'] ) ? $match['t2'] : '' );
	$series = strtolower( str_replace( "\n", ' ', isset( $match['series'] ) ? $match['series'] : '' ) );

	$keywords = array(
		'indian premier league', 'ipl', 'india',
		'rajasthan royals', 'mumbai indians', 'chennai super kings',
		'kolkata knight riders', 'sunrisers hyderabad', 'delhi capitals',
		'royal challengers', 'punjab kings', 'lucknow super giants',
		'gujarat titans',
	);

	foreach ( $keywords as $kw ) {
		if ( false !== strpos( $t1, $kw )
		  || false !== strpos( $t2, $kw )
		  || false !== strpos( $series, $kw ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Strip the [SHORT] abbreviation from CricScore team names.
 */
function cotlas_clean_team_name( $name ) {
	return trim( preg_replace( '/\s*\[.*?\]\s*$/', '', (string) $name ) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * CRICKET WIDGET SHORTCODE — [cricket_widget]
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Render a single match card. Expects normalized format.
 */
function cotlas_render_cricscore_card( $match ) {
	$ms         = isset( $match['ms'] ) ? $match['ms'] : 'fixture';
	$status     = isset( $match['status'] ) ? trim( $match['status'] ) : '';
	$match_type = isset( $match['matchType'] ) ? strtoupper( trim( $match['matchType'] ) ) : '';
	$dt_gmt     = isset( $match['dateTimeGMT'] ) ? $match['dateTimeGMT'] : '';
	$series_raw = isset( $match['series'] ) ? $match['series'] : '';
	$venue      = isset( $match['venue'] ) ? $match['venue'] : '';
	$t1         = isset( $match['t1'] ) ? $match['t1'] : '';
	$t2         = isset( $match['t2'] ) ? $match['t2'] : '';
	$t1s        = isset( $match['t1s'] ) ? trim( $match['t1s'] ) : '';
	$t2s        = isset( $match['t2s'] ) ? trim( $match['t2s'] ) : '';
	$t1img      = isset( $match['t1img'] ) ? $match['t1img'] : '';
	$t2img      = isset( $match['t2img'] ) ? $match['t2img'] : '';

	$series = trim( str_replace( "\n", ' ', $series_raw ) );

	$datetime_ist = '';
	if ( $dt_gmt ) {
		$ts = strtotime( $dt_gmt );
		if ( $ts ) {
			$datetime_ist = gmdate( 'j M Y, g:i A', $ts + 19800 ) . ' IST';
		}
	}

	$show_status = $status && ! preg_match( '/^match starts at/i', $status );

	ob_start();
	?>
	<div class="ccw-card">
		<div class="ccw-card__top">
			<div class="ccw-card__badges">
				<?php if ( 'live' === $ms ) : ?>
					<span class="ccw-badge ccw-badge--live"><span class="ccw-badge__dot"></span>LIVE</span>
				<?php elseif ( 'result' === $ms ) : ?>
					<span class="ccw-badge ccw-badge--result">RESULT</span>
				<?php else : ?>
					<span class="ccw-badge ccw-badge--upcoming">UPCOMING</span>
				<?php endif; ?>
				<?php if ( $match_type ) : ?>
					<span class="ccw-badge ccw-badge--type"><?php echo esc_html( $match_type ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $series ) : ?>
				<div class="ccw-card__series"><?php echo esc_html( $series ); ?></div>
			<?php endif; ?>
		</div>
		<div class="ccw-card__teams">
			<div class="ccw-card__team">
				<?php if ( $t1img ) : ?>
					<img src="<?php echo esc_url( $t1img ); ?>" alt="<?php echo esc_attr( $t1 ); ?>" class="ccw-card__logo" width="32" height="32" loading="lazy" />
				<?php endif; ?>
				<span class="ccw-card__team-name"><?php echo esc_html( $t1 ); ?></span>
				<?php if ( $t1s ) : ?>
					<span class="ccw-card__score"><?php echo esc_html( $t1s ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ccw-card__vs">vs</div>
			<div class="ccw-card__team">
				<?php if ( $t2img ) : ?>
					<img src="<?php echo esc_url( $t2img ); ?>" alt="<?php echo esc_attr( $t2 ); ?>" class="ccw-card__logo" width="32" height="32" loading="lazy" />
				<?php endif; ?>
				<span class="ccw-card__team-name"><?php echo esc_html( $t2 ); ?></span>
				<?php if ( $t2s ) : ?>
					<span class="ccw-card__score"><?php echo esc_html( $t2s ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<div class="ccw-card__footer">
			<?php if ( $show_status ) : ?>
				<div class="ccw-card__status"><?php echo esc_html( $status ); ?></div>
			<?php endif; ?>
			<?php if ( $venue ) : ?>
				<div class="ccw-card__venue"><?php echo esc_html( $venue ); ?></div>
			<?php endif; ?>
			<?php if ( $datetime_ist ) : ?>
				<div class="ccw-card__datetime"><?php echo esc_html( $datetime_ist ); ?></div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Shortcode: [cricket_widget]
 */
function cotlas_cricket_widget_shortcode( $atts ) {
	$admin_filter = strtolower( trim( get_option( 'cotlas_cricket_filter_countries', 'India' ) ) );
	$is_all = $admin_filter === 'all' || $admin_filter === '';

	$atts = shortcode_atts( array(
		'title'      => 'क्रिकेट',
		'cache'      => 900,
		'apikey'     => '',
		'link'       => '',
		'link_label' => 'Full Schedule',
		'filter'     => $is_all ? 'all' : 'country',
		'limit'      => 5,
		'matches'    => '',  // Backward compat: alias for limit.
	), $atts, 'cricket_widget' );

	// Support legacy "matches" attribute as alias for "limit".
	if ( '' !== $atts['matches'] ) {
		$atts['limit'] = (int) $atts['matches'];
	}

	$api_key = sanitize_text_field( $atts['apikey'] );
	if ( empty( $api_key ) ) {
		$api_key = get_option( 'cotlas_cricapi_key', '' );
	}

	if ( empty( $api_key ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:red;font-size:0.85em;">[cricket_widget] — Please add your CricAPI key in <strong>Cotlas Admin → GenerateBlocks Tags → Cricket</strong>.</p>';
		}
		return '';
	}

	$all = cotlas_fetch_cricket_matches( '', absint( $atts['cache'] ) );

	if ( empty( $all ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:red;font-size:0.85em;">[cricket_widget] — CricAPI returned no data. Check your API key in <strong>Cotlas Admin → GenerateBlocks Tags → Cricket</strong>. Test your key at <a href="https://www.cricapi.com/" target="_blank" rel="noopener">cricapi.com</a>.</p>';
		}
		return '';
	}

	$total = count( $all );

	if ( 'all' !== sanitize_text_field( $atts['filter'] ) ) {
		$all = array_values( array_filter( $all, 'cotlas_cricket_match_filter' ) );
	}

	if ( empty( $all ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:orange;font-size:0.85em;">[cricket_widget] — No matches found after filter="' . esc_attr( $atts['filter'] ) . '" (from ' . $total . ' total matches). Try <code>filter="all"</code> to see all matches.</p>';
		}
		return '';
	}

	$limit = max( 1, (int) $atts['limit'] );

	$live     = array();
	$results  = array();
	$upcoming = array();

	foreach ( $all as $m ) {
		$ms = isset( $m['ms'] ) ? $m['ms'] : 'fixture';
		if ( 'live' === $ms ) {
			$live[] = $m;
		} elseif ( 'result' === $ms ) {
			$results[] = $m;
		} else {
			$upcoming[] = $m;
		}
	}

	usort( $results, function ( $a, $b ) {
		return strtotime( isset( $b['dateTimeGMT'] ) ? $b['dateTimeGMT'] : '' )
		     - strtotime( isset( $a['dateTimeGMT'] ) ? $a['dateTimeGMT'] : '' );
	} );
	usort( $upcoming, function ( $a, $b ) {
		return strtotime( isset( $a['dateTimeGMT'] ) ? $a['dateTimeGMT'] : '' )
		     - strtotime( isset( $b['dateTimeGMT'] ) ? $b['dateTimeGMT'] : '' );
	} );

	$live     = array_slice( $live,     0, $limit );
	$results  = array_slice( $results,  0, $limit );
	$upcoming = array_slice( $upcoming, 0, $limit );

	$first = ! empty( $live ) ? 'live' : ( ! empty( $upcoming ) ? 'next' : 'prev' );

	$uid        = 'ccw-' . wp_rand( 1000, 9999 );
	$title      = esc_html( $atts['title'] );
	$sched_url  = esc_url( $atts['link'] );
	$sched_label = esc_html( $atts['link_label'] );

	ob_start();
	?>
	<div class="cotlas-cricket-widget" id="<?php echo esc_attr( $uid ); ?>">
		<div class="ccw-header">
			<h3 class="ccw-header__title">
				🏏 <?php echo $title; ?>
				<?php if ( $sched_url ) : ?>
					<a href="<?php echo $sched_url; ?>" class="ccw-header__link" target="_blank" rel="noopener"><?php echo $sched_label; ?> ›</a>
				<?php endif; ?>
			</h3>
		</div>
		<div class="ccw-tabs" role="tablist">
			<button class="ccw-tab <?php echo 'live' === $first ? 'ccw-tab--active' : ''; ?>"
					role="tab" aria-selected="<?php echo 'live' === $first ? 'true' : 'false'; ?>"
					data-target="<?php echo esc_attr( $uid ); ?>-live">
				<?php if ( ! empty( $live ) ) : ?><span class="ccw-live-dot"></span><?php endif; ?>LIVE<?php if ( ! empty( $live ) ) : ?>&nbsp;<span class="ccw-tab-count"><?php echo count( $live ); ?></span><?php endif; ?>
			</button>
			<button class="ccw-tab <?php echo 'prev' === $first ? 'ccw-tab--active' : ''; ?>"
					role="tab" aria-selected="<?php echo 'prev' === $first ? 'true' : 'false'; ?>"
					data-target="<?php echo esc_attr( $uid ); ?>-prev">PREVIOUS</button>
			<button class="ccw-tab <?php echo 'next' === $first ? 'ccw-tab--active' : ''; ?>"
					role="tab" aria-selected="<?php echo 'next' === $first ? 'true' : 'false'; ?>"
					data-target="<?php echo esc_attr( $uid ); ?>-next">UPCOMING</button>
		</div>
		<div class="ccw-panels">
			<?php
			$ccw_panel_data = array(
				'live' => array( 'matches' => $live,     'empty' => 'No live matches right now.' ),
				'prev' => array( 'matches' => $results,  'empty' => 'No recent results found.' ),
				'next' => array( 'matches' => $upcoming, 'empty' => 'No upcoming matches scheduled.' ),
			);
			foreach ( $ccw_panel_data as $pkey => $pdata ) :
				$phidden = $pkey !== $first ? ' ccw-panel--hidden' : '';
			?>
			<div class="ccw-panel<?php echo $phidden; ?>" id="<?php echo esc_attr( $uid . '-' . $pkey ); ?>" role="tabpanel">
				<?php if ( ! empty( $pdata['matches'] ) ) : ?>
					<div class="ccw-carousel" data-total="<?php echo count( $pdata['matches'] ); ?>">
						<div class="ccw-carousel__track">
							<?php foreach ( $pdata['matches'] as $m ) { echo cotlas_render_cricscore_card( $m ); } ?>
						</div>
						<?php if ( count( $pdata['matches'] ) > 1 ) : ?>
						<div class="ccw-carousel__dots">
							<?php for ( $di = 0; $di < count( $pdata['matches'] ); $di++ ) : ?>
								<span class="ccw-dot<?php echo 0 === $di ? ' ccw-dot--active' : ''; ?>"></span>
							<?php endfor; ?>
						</div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<p class="ccw-empty"><?php echo esc_html( $pdata['empty'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'cricket_widget', 'cotlas_cricket_widget_shortcode' );

/* ═══════════════════════════════════════════════════════════════════════════
 * POINTS TABLE SHORTCODE — [cricket_points_table]
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Shortcode: [cricket_points_table]
 *
 * Atts:
 *   title      Widget heading. Default: "Points Table" (auto-set to series name if available)
 *   series_id  CricAPI series ID. Auto-detected from current matches if omitted.
 *   cache      Cache in seconds. Default: 21600 (6 h)
 *   limit      Max rows to show. Default: 10
 *   link       Optional URL for heading arrow link.
 */
function cotlas_points_table_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'title'     => '',
		'series_id' => '',
		'cache'     => 21600,
		'limit'     => 10,
		'link'      => '',
	), $atts, 'cricket_points_table' );

	$api_key = get_option( 'cotlas_cricapi_key', '' );
	if ( empty( $api_key ) && empty( cotlas_cricket_get_api_keys() ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:red;font-size:0.85em;">[cricket_points_table] — Add CricAPI key in <strong>Cotlas Admin → GenerateBlocks Tags → Cricket</strong>.</p>';
		}
		return '';
	}

	// Auto-detect series if not provided or if placeholder was used.
	$series_id   = sanitize_text_field( $atts['series_id'] );
	$series_name = '';

	if ( empty( $series_id ) || '...' === $series_id || strlen( $series_id ) < 10 ) {
		$detected = cotlas_get_current_series_id();
		if ( ! $detected ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p style="color:orange;font-size:0.85em;">[cricket_points_table] — No active series found. Add series IDs in <strong>Cotlas Admin → GenerateBlocks Tags → Cricket</strong> or provide a <code>series_id</code> attribute.</p>';
			}
			return '';
		}
		$series_id   = $detected['series_id'];
		$series_name = isset( $detected['series_name'] ) ? $detected['series_name'] : '';
	}

	$rows = cotlas_fetch_series_points( $series_id, absint( $atts['cache'] ) );

	if ( empty( $rows ) ) {
		return '<p class="ipl-pts__empty">Points table not available right now.</p>';
	}

	foreach ( $rows as &$r ) {
		$r['pts'] = ( (int) $r['wins'] ) * 2 + ( (int) $r['ties'] ) + ( (int) $r['nr'] );
	}
	unset( $r );
	usort( $rows, function ( $a, $b ) {
		if ( $b['pts'] !== $a['pts'] ) {
			return $b['pts'] - $a['pts'];
		}
		return $b['wins'] - $a['wins'];
	} );

	$rows = array_slice( $rows, 0, (int) $atts['limit'] );

	// Title: use explicit att, then auto-detected series name, then fallback.
	$title      = $atts['title'] ? esc_html( $atts['title'] ) : ( $series_name ? esc_html( $series_name ) : 'Points Table' );
	$link       = esc_url( $atts['link'] );
	$show_name  = $series_name && $atts['title'] && $atts['title'] !== $series_name;

	ob_start();
	?>
	<div class="ipl-pts">
		<div class="ipl-pts__header">
			<div>
				<span class="ipl-pts__title"><?php echo $title; ?></span>
				<?php if ( $show_name ) : ?>
					<div class="ipl-pts__series"><?php echo esc_html( $series_name ); ?></div>
				<?php endif; ?>
			</div>
			<?php if ( $link ) : ?>
				<a href="<?php echo $link; ?>" class="ipl-pts__more" target="_blank" rel="noopener">→</a>
			<?php endif; ?>
		</div>
		<div class="ipl-pts__table-wrap">
			<table class="ipl-pts__table">
				<thead>
					<tr>
						<th class="ipl-pts__th ipl-pts__th--team">TEAM</th>
						<th class="ipl-pts__th">P</th>
						<th class="ipl-pts__th">W</th>
						<th class="ipl-pts__th">L</th>
						<th class="ipl-pts__th">NR</th>
						<th class="ipl-pts__th ipl-pts__th--pts">PTS</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $i => $row ) :
					$display = ! empty( $row['teamname'] ) ? esc_html( $row['teamname'] ) : esc_html( isset( $row['shortname'] ) ? $row['shortname'] : '' );
					$img     = esc_url( isset( $row['img'] ) ? $row['img'] : '' );
					$top4    = $i < 4;
				?>
					<tr class="ipl-pts__row<?php echo $top4 ? ' ipl-pts__row--top4' : ''; ?>">
						<td class="ipl-pts__td ipl-pts__td--team">
							<span class="ipl-pts__pos"><?php echo $i + 1; ?></span>
							<?php if ( $img ) : ?>
								<span class="ipl-pts__logo-wrap"><img src="<?php echo $img; ?>" alt="<?php echo $display; ?>" class="ipl-pts__logo" width="24" height="24" loading="lazy" /></span>
							<?php endif; ?>
							<span class="ipl-pts__name"><?php echo $display; ?></span>
						</td>
						<td class="ipl-pts__td"><?php echo (int) $row['matches']; ?></td>
						<td class="ipl-pts__td"><?php echo (int) $row['wins']; ?></td>
						<td class="ipl-pts__td"><?php echo (int) $row['loss']; ?></td>
						<td class="ipl-pts__td"><?php echo (int) $row['nr']; ?></td>
						<td class="ipl-pts__td ipl-pts__td--pts"><?php echo (int) $row['pts']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'cricket_points_table', 'cotlas_points_table_shortcode' );
add_shortcode( 'ipl_points_table', 'cotlas_points_table_shortcode' );

/* ═══════════════════════════════════════════════════════════════════════════
 * FRONTEND STYLES & SCRIPTS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_cricket_widget_enqueue() {
	if ( ! is_singular() && ! is_front_page() && ! is_home() && ! is_archive() ) {
		return;
	}

	global $post;
	if ( $post && ( has_shortcode( $post->post_content, 'cricket_widget' ) || has_shortcode( $post->post_content, 'cricket_points_table' ) || has_shortcode( $post->post_content, 'ipl_points_table' ) ) ) {
		$dir = plugin_dir_path( __DIR__ );
		$url = plugin_dir_url( __DIR__ );
		wp_enqueue_style( 'cotlas-cricket-widget', $url . 'assets/css/cricket-widget.css', array(), filemtime( $dir . 'assets/css/cricket-widget.css' ) );
		wp_enqueue_script( 'cotlas-cricket-widget', $url . 'assets/js/cricket-widget.js', array(), filemtime( $dir . 'assets/js/cricket-widget.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'cotlas_cricket_widget_enqueue' );