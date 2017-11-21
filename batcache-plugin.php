<?php

// NB: This file will not have access to most WP resources when it is loaded.

require_once 'alert-file.php';

function bu_alert_batcache_plugin()
{
	$host = $_SERVER['HTTP_HOST'];
	$alert_file = new BU_AlertFile($host);

	if ($alert = $alert_file->getActiveAlert($host))
	{
		global $batcache;
		$batcache->unique['active'] = $alert_file->getTimestamp();
	}
}
bu_alert_batcache_plugin();
