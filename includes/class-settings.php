<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Settings {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'aaag_options', 'aaag_api_key', 'sanitize_text_field' ); // Anthropic
		register_setting( 'aaag_options', 'aaag_openai_api_key', 'sanitize_text_field' );
		register_setting( 'aaag_options', 'aaag_gemini_api_key', 'sanitize_text_field' );
		register_setting( 'aaag_options', 'aaag_max_tokens', 'absint' );
		register_setting( 'aaag_options', 'aaag_temperature', 'sanitize_text_field' ); // float
		register_setting( 'aaag_options', 'aaag_queue_buffer', 'absint' ); // max future posts limit
		register_setting( 'aaag_options', 'aaag_internal_link_post_types', array(
			'type' => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_array' )
		) );
		register_setting( 'aaag_options', 'aaag_delete_data_uninstall', 'absint' ); // 1 or 0
		
		// Set defaults if not exist
		// aaag_model is now saved per-campaign in DB, no longer a global setting

		if ( false === get_option( 'aaag_max_tokens' ) ) {
			add_option( 'aaag_max_tokens', 8192 );
		}
		if ( false === get_option( 'aaag_temperature' ) ) {
			add_option( 'aaag_temperature', '0.7' );
		}
		if ( false === get_option( 'aaag_queue_buffer' ) ) {
			add_option( 'aaag_queue_buffer', 5 );
		}
		if ( false === get_option( 'aaag_internal_link_post_types' ) ) {
			add_option( 'aaag_internal_link_post_types', array( 'post', 'page' ) );
		}
		if ( false === get_option( 'aaag_delete_data_uninstall' ) ) {
			add_option( 'aaag_delete_data_uninstall', 0 ); // Default false (don't delete)
		}
	}
	
	public static function sanitize_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}
}
