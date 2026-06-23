<?php
/**
 * Plugin Name: AI Auto Article Generator
 * Description: Generates automatic articles using Anthropic Claude API based on provided titles, templates, and knowledge bases.
 * Version: 4.0.4
 * Author: AI Assistant
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'AAAG_PLUGIN_DIR' ) ) {
	define( 'AAAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AAAG_PLUGIN_URL' ) ) {
	define( 'AAAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'AAAG_VERSION' ) ) {
	define( 'AAAG_VERSION', '4.0.4' );
}

// Include core classes
require_once AAAG_PLUGIN_DIR . 'includes/class-logger.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-db.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-activator.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-settings.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-template.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-knowledge-base.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-job.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-campaign.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-queue.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-ai-client.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-post-creator.php';
require_once AAAG_PLUGIN_DIR . 'includes/class-admin-menu.php';

// Register activation hook
register_activation_hook( __FILE__, array( 'AAAG_Activator', 'activate' ) );

// Initialize components
if ( ! function_exists( 'aaag_init_plugin' ) ) {
	function aaag_init_plugin() {
		AAAG_Settings::init();
		AAAG_Admin_Menu::init();
		AAAG_Queue::init();
		
		// DB Upgrade Check
		$installed_ver = get_option( 'aaag_db_version' );
		if ( $installed_ver != AAAG_VERSION ) {
			AAAG_DB::upgrade();
			update_option( 'aaag_db_version', AAAG_VERSION );
		}
	}
}
add_action( 'plugins_loaded', 'aaag_init_plugin' );
