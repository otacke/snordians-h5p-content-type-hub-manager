<?php
/**
 * Plugin Name: Sustainum H5P Content Type Hub Manager
 * Plugin URI: https://github.com/otacke/sustainum-h5p-content-type-hub-manager
 * Text Domain: sustainum-h5p-content-type-hub-manager
 * Description: Manage the H5P Content Type Hub.
 * Version: 1.0.3
 * Author: Sustainum, Oliver Tacke (SNORDIAN)
 * Author URI: https://snordian.de
 * License: MIT
 *
 * @package sustainum-h5p-content-type-hub-manager
 */

namespace Sustainum\H5PContentTypeHubManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

// In theory, the scheduling should work without this, but in practice it does not.
define( 'ALTERNATE_WP_CRON', true );

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-capabilities.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-contenttypehubconnector.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-main.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-options.php';

/**
 * Main plugin class.
 *
 * @return object NDLAH5PCARETAKER
 */
function init() {
	if ( ! is_admin() ) {
		return;
	}

	return new Main();
}

/**
 * Handle plugin activation.
 */
function on_activation() {
	Options::set_defaults();
	Capabilities::add_capabilities();
}

/**
 * Handle plugin deactivation.
 */
function on_deactivation() {
	$timestamp = wp_next_scheduled( 'h5p_content_hub_manager_update_libraries' );
	wp_unschedule_event( $timestamp, 'h5p_content_hub_manager_update_libraries' );
}

/**
 * Handle plugin uninstallation.
 */
function on_uninstall() {
	Options::delete_options();
	Capabilities::remove_capabilities();
	Main::update_endpoint_in_h5p_core( Options::get_default_endpoint_url_base() );

	$timestamp = wp_next_scheduled( 'h5p_content_hub_manager_update_libraries' );
	wp_unschedule_event( $timestamp, 'h5p_content_hub_manager_update_libraries' );
}

/**
 * Load the text domain for internationalization.
 */
function shcthb_load_plugin_textdomain() {
	load_plugin_textdomain(
		'sustainum-h5p-content-type-hub-manager',
		false,
		plugin_basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages'
	);
}

register_activation_hook( __FILE__, 'Sustainum\H5PContentTypeHubManager\on_activation' );
register_deactivation_hook( __FILE__, 'Sustainum\H5PContentTypeHubManager\on_deactivation' );
register_uninstall_hook( __FILE__, 'Sustainum\H5PContentTypeHubManager\on_uninstall' );

add_action( 'plugins_loaded', 'Sustainum\H5PContentTypeHubManager\shcthb_load_plugin_textdomain' );
add_action( 'init', 'Sustainum\H5PContentTypeHubManager\init' );
