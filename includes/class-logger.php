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
				'job_id'     => $job_id,
				'message'    => $message,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	public static function get_logs( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
	}

	public static function get_total_logs() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
	}
	
	public static function clear_logs() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		$wpdb->query( "DELETE FROM $table_name" );
	}

	public static function clear_old_logs( $days = 7 ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('logs');
		$cutoff = date( 'Y-m-d H:i:s', time() - ( $days * 24 * 60 * 60 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE created_at < %s", $cutoff ) );
	}
}
