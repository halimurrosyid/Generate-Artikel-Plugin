<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'aaag_delete_data_uninstall', 0 );

if ( $delete_data ) {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'ai_article_campaigns',
		$wpdb->prefix . 'ai_article_jobs',
		$wpdb->prefix . 'ai_article_templates',
		$wpdb->prefix . 'ai_article_knowledge_base',
		$wpdb->prefix . 'ai_article_logs'
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}

	$options = array(
		'aaag_api_key',
		'aaag_openai_api_key',
		'aaag_gemini_api_key',
		'aaag_model',
		'aaag_max_tokens',
		'aaag_temperature',
		'aaag_queue_buffer',
		'aaag_internal_link_post_types',
		'aaag_max_internal_links',
		'aaag_delete_data_uninstall',
		'aaag_anthropic_connected',
		'aaag_openai_connected',
		'aaag_gemini_connected',
		'aaag_verified_anthropic_models',
		'aaag_verified_openai_models',
		'aaag_verified_gemini_models',
		'aaag_db_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

wp_clear_scheduled_hook( 'aaag_process_queue_hook' );

