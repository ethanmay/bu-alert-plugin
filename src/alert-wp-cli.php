<?php
/**
 * WP CLI commands for working with alert states
 *
 * @package BU_Alert
 */

namespace BU\Plugins\Alert;

/**
 * Very simple command that just grabs all the open alerts and formats them as a table
 *
 * @since 3.0.0
 *
 * @return void
 */
function list_alerts() {
	$alert_options = get_all_open_alerts();

	// Parse alert options for display.
	$alerts = array_map(
		function( $alert ) {
			return array(
				'site_id'     => $alert['site_id'],
				'meta_key'    => $alert['meta_key'],
				'msg'         => $alert['meta_value']['msg'],
				'started_on'  => $alert['meta_value']['started_on'],
				'incident_id' => $alert['meta_value']['incident_id'],
			);
		},
		$alert_options
	);

	\WP_CLI\Utils\format_items( 'table', $alerts, array( 'site_id', 'meta_key', 'msg', 'started_on', 'incident_id' ) );
}

\WP_CLI::add_command( 'alert list', __NAMESPACE__ . '\list_alerts' );

/**
 * Removes alerts that have been closed in Everbridge
 *
 * First gets all of the open alerts in WordPress. If there aren't any, it exits.
 * Then it gets all of the open incidents in Everbridge.  It compares the two lists by incident ID
 * (which was recorded when the alert was initiated).  If there are WordPress alerts without matching
 * open incidents in Everbridge, they must have been closed so it removes them.
 *
 * @since 3.0.0
 *
 * @return void
 */
function expire_everbridge_alerts() {
	global $wp_object_cache;

	// Get all of the current alert options from the WP multisite network.
	$open_alert_options = get_all_open_alerts();

	// If there are no open alerts in WordPress, don't bother checking with Everbridge.
	if ( ! count( $open_alert_options ) > 0 ) {
		\WP_CLI::success( 'No WordPress alerts found' );
		die();
	}

	// Get the open incident ids from Everbridge.
	$open_incident_ids = array_map(
		function ( $incident ) {
			return $incident['id'];
		},
		get_eb_open_incidents()
	);

	// Check for alerts with incident ids that are no longer active in Everbridge.
	$expired_incidents = array_filter(
		$open_alert_options,
		function ( $alert ) use ( $open_incident_ids ) {
			// Check for a valid incident id, and see if it is in the array of open incidents.
			return $alert['meta_value']['incident_id'] && ! in_array( $alert['meta_value']['incident_id'], $open_incident_ids, true );
		}
	);

	// Remove the the expired alerts.
	$removed_alerts = array_map(
		function ( $alert ) {
			remove_alert_option( $alert );
			return "{$alert['site_id']}: {$alert['meta_key']}";
		},
		$expired_incidents
	);

	// If the expired incidents array wasn't empty, then flush the cache.
	if ( $expired_incidents ) {
		$flush_result = $wp_object_cache->flush( 0 );

		// Log result and exit.
		\WP_CLI::success( "Expired alerts removed, cache flush result was {$flush_result}" );
		array_map(
			function( $removed_alert ) {
				\WP_CLI::log( "Removed alert {$removed_alert}" );
			},
			$removed_alerts
		);
		return;
	}

	\WP_CLI::success( 'No expired events found.' );
}

\WP_CLI::add_command( 'alert expire', __NAMESPACE__ . '\expire_everbridge_alerts' );
