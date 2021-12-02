<?php
/**
 * BU Alert
 *
 * Main functions.
 *
 * @package BU_Alert
 */

namespace BU\Plugins\Alert;

// Define constants for the alert option key.
const SITE_OPT_ALERT        = 'bu-active-alert';
const SITE_OPT_ANNOUNCEMENT = 'bu-active-announcement';

/**
 * Get all open alerts for the entire WP multi-site network
 *
 * Directly queries the site_meta table for the entire network
 * and returns any open alerts on any site.  Also processes the
 * result object.  We can't use native get_option functions, because
 * we want to scan ALL sites on the network.
 *
 * @return array An array of site options for open alerts.
 */
function get_all_open_alerts() {
	global $wpdb;

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->sitemeta} WHERE meta_key = %s OR meta_key = %s",
			SITE_OPT_ALERT,
			SITE_OPT_ANNOUNCEMENT
		)
	);

	// Parse raw results and return an associative array.
	return array_map(
		function ( $option ) {
			return array(
				'meta_id'    => $option->meta_id,
				'site_id'    => $option->site_id,
				'meta_key'   => $option->meta_key,
				'meta_value' => unserialize( $option->meta_value ),
			);
		},
		$results
	);
}
