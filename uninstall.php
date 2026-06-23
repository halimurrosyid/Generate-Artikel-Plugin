<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'aaag_delete_data_uninstall', 0 );

if ( $delete_data ) {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'ai_article_jobs',
		$wpdb->prefix . 'ai_article_templates',
		$wpdb->prefix . 'ai_article_knowledge_base',
		$wpdb->prefix . 'ai_article_logs'
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}

	delete_option( 'aaag_api_key' );
	delete_option( 'aaag_model' );
	delete_option( 'aaag_max_tokens' );
	delete_option( 'aaag_temperature' );
	delete_option( 'aaag_delete_data_uninstall' );
}

wp_clear_scheduled_hook( 'aaag_process_queue_hook' );
