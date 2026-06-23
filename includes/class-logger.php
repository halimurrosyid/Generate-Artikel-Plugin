<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Logger {
	public static function log( $message, $job_id = null ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		
		// Ensure API key is never logged
		$api_key = get_option( 'aaag_api_key' );
		if ( ! empty( $api_key ) ) {
			$message = str_replace( $api_key, '***API_KEY_HIDDEN***', $message );
		}
		
		if ( defined('AI_ARTICLE_ANTHROPIC_API_KEY') ) {
			$message = str_replace( AI_ARTICLE_ANTHROPIC_API_KEY, '***API_KEY_HIDDEN***', $message );
		}

		$wpdb->insert(
			$table_name,
			array(
				'job_id'  => $job_id,
				'message' => $message,
			),
			array( '%d', '%s' )
		);
	}

	public static function get_logs( $limit = 50 ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d", $limit ) );
	}
	
	public static function clear_logs() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		$wpdb->query( "TRUNCATE TABLE $table_name" );
	}
}
