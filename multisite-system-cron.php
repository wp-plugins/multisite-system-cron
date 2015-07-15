<?php
/*
Plugin Name: MultiSite System Cron
Plugin URI: http://blogestudio.com
Description: Linux crontab requests for multisite installs instead of WP-Cron.
Version: 1.0
Author: Pau Iglesias, Blogestudio
License: GPLv2 or later
Network: true
Text Domain: msscron
Domain Path: /languages
*/

// Avoid script calls via plugin URL
if (!function_exists('add_action'))
	die;

// Check XMLRPC context
if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
	return;

// Check admin area
if (is_admin()) {
	
	// Only main blog
	if (is_main_site()) {
	
		// Network admin area
		if (function_exists('is_network_admin') && is_network_admin()) {
			require_once(dirname(__FILE__).'/multisite-system-cron-admin.php');
			BE_MSSC_Admin::init();
		}
		
		// Activation hook
		register_activation_hook(__FILE__, 'be_mssc_activation');
		function be_mssc_activation() {
			require_once(dirname(__FILE__).'/multisite-system-cron-admin.php');
			BE_MSSC_Admin::activation();
		}
	}

// WP cron script
} elseif (defined('DOING_CRON') && DOING_CRON) {
	
	// Custom CRON calls
	require_once(dirname(__FILE__).'/multisite-system-cron-request.php');
	BE_MSSC_Request::cron();
}