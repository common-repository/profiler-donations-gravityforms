<?php

if(!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

add_action('plugins_loaded', function() {
	if(!class_exists('WC_Integration') ) {
		return;
	}

	// Include our integration class.
	require_once('integration.php');

	// Register the integration.
	add_filter('woocommerce_integrations', function($integrations) {
		$integrations[] = 'WC_Integration_Profiler';
		return $integrations;
	});
});