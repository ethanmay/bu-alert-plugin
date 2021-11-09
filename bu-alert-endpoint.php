<?php
/**
 * BU Alert Endpoints
 *
 * Defines endpoints to start and stop alerts.
 *
 * @package BU_Alert
 */

namespace BU\Plugins\Alert;

/**
 * Initiates a an alert
 *
 * @param WP_REST_Request $request Parameters from the rest request.
 * @return string Status message.
 */
function start_alert( $request ) {
	global $current_site;

	// Get the site id for the current site.
	$site_ids[] = get_id_for_domain( $current_site->domain );

	// Get site ids for an additional sites from the request parameters.
	$additional_domains  = explode( ',', $request->get_param( 'additionalDomains' ) );
	$additional_site_ids = array_map(
		function( $domain ) {
			return get_id_for_domain( $domain );
		},
		$additional_domains
	);

	// Add the additional site ids, if any.
	$site_ids = array_merge( $site_ids, $additional_site_ids );

	// Filter out any null values.
	$site_ids = array_filter( $site_ids );

	$result = \BU_AlertsPlugin::startAlert(
		format_alert( $request->get_param( 'body' ) ),
		$site_ids
	);

	return $result;
}

/**
 * Format an alert message with markup
 *
 * @param string $body The body of the message to be wrapped in markup.
 * @return string The formatted alert markup
 */
function format_alert( $body ) {
	return sprintf(
		'<div id="bu-alert-emergency" class="nocontent"><div id="bu-alert-emergency-inner"><p><span id="bu-alert-emergency-header">Emergency BU Alert</span> <span id="bu-alert-emergency-message">%s</span></p></div></div>',
		$body
	);
}

/**
 * Get the site id for a specific domain
 *
 * @param string $domain Domain name of a site on the WP network.
 * @return int The id for the domain
 */
function get_id_for_domain( $domain ) {
	global $wpdb;

	$site_id = $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$wpdb->site} WHERE domain = %s", $domain )
	);

	return $site_id;
}

/**
 * Stop an active alert
 *
 * @param WP_REST_Request $request Parameters from the rest request.
 * @return string Status message.
 */
function stop_alert( $request ) {
	global $current_site;
	$site_ids = array( get_id_for_domain( $current_site->domain ) );

	$result = \BU_AlertsPlugin::stopAlert( $site_ids );

	return $result;
}

/**
 * Add REST endpoints.
 */
add_action(
	'rest_api_init',
	function() {
		// Endpoint to initiate an alert.
		register_rest_route(
			'bu-alert/v1',
			'/start/',
			array(
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\start_alert',
				'permission_callback' => function ( $request ) {
					return 'dasfglkdjsghasdf' === $request->get_param( 'token' );
				},
			)
		);

		// Endpoint to end an alert.
		register_rest_route(
			'bu-alert/v1',
			'/stop/',
			array(
				'methods'             => 'POST,GET',
				'callback'            => __NAMESPACE__ . '\stop_alert',
				'permission_callback' => function ( $request ) {
					return 'dasfglkdjsghasdf' === $request->get_param( 'token' );
				},
			)
		);
	}
);
