<?php
/*
 Plugin Name: Log Viewer
 Plugin URI: https://github.com/blessingefkt/log-viewer-for-wp
 Description: This plugin provides an easy way to view log files directly in the admin panel.
 Author: blessingefkt
 Author URI: https://www.blessingeffect.dev/
 Tag: 1.0.0-stable
 Version: 1.0.0
 Timestamp: 2020.10.30
 */

if (!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}


if (!is_admin()) {
	return;
}

require 'helper.inc';
require 'class.plugin.php';

$ciLogViewer = new ciLogViewer();
