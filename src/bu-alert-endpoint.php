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
 * @param \WP_REST_Request $request Parameters from the rest request.
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

	// Type can be set to 'announcement', but default to 'emergency'.
	$type = ( 'announcement' === $request->get_param( 'type' ) ) ? 'announcement' : 'emergency';

	// Look up the alert in the Everbridge API to try to match it to an incident ID.
	$open_incidents = get_eb_open_incidents();

	$matching_incidents = array_filter(
		$open_incidents,
		function( $incident ) use ( $request ) {
			// Check for matching title and body of the open incident.
			return $incident['title'] === $request->get_param( 'title' ) && $incident['body'] === $request->get_param( 'body' );
		}
	);

	$incident_id = ( $matching_incidents ) ? $matching_incidents[0]['id'] : 0;

	$result = \BU_AlertsPlugin::startAlert(
		$request->get_param( 'body' ),
		$site_ids,
		$type,
		$incident_id
	);

	// Rebuild the homepage.
	if ( function_exists( '\BU\Themes\R_Editorial\BU_Homepage\bu_homepage_trigger_action' ) ) {
		\BU\Themes\R_Editorial\BU_Homepage\bu_homepage_trigger_action();
	}

	return $result;
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

	// Rebuild the homepage.
	if ( function_exists( '\BU\Themes\R_Editorial\BU_Homepage\bu_homepage_trigger_action' ) ) {
		\BU\Themes\R_Editorial\BU_Homepage\bu_homepage_trigger_action();
	}

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
					return \BU_ALERT_API_TOKEN === $request->get_param( 'token' );
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
					return \BU_ALERT_API_TOKEN === $request->get_param( 'token' );
				},
			)
		);
	}
);
