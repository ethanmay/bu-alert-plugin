<?php

$bu_alert_campus_map = array();

function bu_alert_init_campus_map()
{
	global $bu_alert_campus_map, $current_site;

	$bu_init = BU_Config::get_instance();
	$bu_wp_config = $bu_init->get_config_for_server(BU_INSTALL_TYPE);

	switch ($bu_wp_config->environment)
	{
		case 'prod':
			$bu_alert_campus_map['crc'] = array('www.bu.edu', 'management.bu.edu');
			$bu_alert_campus_map['bumc'] = array('www.bumc.bu.edu');
			break;

		case 'test':
			$bu_alert_campus_map['crc'] = array('www-test.bu.edu', 'wwwv-test.bu.edu');
			$bu_alert_campus_map['bumc'] = array('www-test.bumc.bu.edu');
			break;

		case 'devl':
		default:
			$bu_alert_campus_map['crc'] = array('www-devl.bu.edu', 'wwwv-devl.bu.edu');
			$bu_alert_campus_map['bumc'] = array('www-devl.bumc.bu.edu');
	}

	if (isset($current_site))
	{
		$bu_alert_campus_map['debug'] = array($current_site->domain);
	}
}
bu_alert_init_campus_map();

function bu_alert_get_campus_site_ids($campus)
{
	global $wpdb, $bu_alert_campus_map;

	$domains = (isset($bu_alert_campus_map[$campus]))
		? $bu_alert_campus_map[$campus]
		: array();

	$site_ids = array();
	foreach ($domains as $domain)
	{
		$site_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->site} WHERE domain = %s", $domain )
		);

		if ($site_id)
		{
			$site_ids[] = $site_id;
		}
		else
		{
			error_log('bu_alert_get_campus_site_ids cannot find site ID for domain ' . $domain);
		}
	}

	return $site_ids;
}
