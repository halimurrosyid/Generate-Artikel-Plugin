<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Admin_Menu {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aaag_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_aaag_run_job', array( __CLASS__, 'ajax_run_job' ) );
	}

	public static function add_menu_pages() {
		add_menu_page(
			'AI Auto Article',
			'AI Auto Article',
			'manage_options',
			'aaag-generate',
			array( __CLASS__, 'render_page_generate' ),
			'dashicons-media-text',
			30
		);

		add_submenu_page(
			'aaag-generate',
			'Generate Artikel',
			'Generate Artikel',
			'manage_options',
			'aaag-generate',
			array( __CLASS__, 'render_page_generate' )
		);

		add_submenu_page(
			'aaag-generate',
			'Daftar Campaign',
			'Daftar Campaign',
			'manage_options',
			'aaag-campaigns',
			array( __CLASS__, 'render_page_campaigns' )
		);

		add_submenu_page(
			'aaag-generate',
			'Daftar Job',
			'Daftar Job',
			'manage_options',
			'aaag-jobs',
			array( __CLASS__, 'render_page_jobs' )
		);

		add_submenu_page(
			'aaag-generate',
			'Settings',
			'Settings',
			'manage_options',
			'aaag-settings',
			array( __CLASS__, 'render_page_settings' )
		);

		add_submenu_page(
			'aaag-generate',
			'Logs',
			'Logs',
			'manage_options',
			'aaag-logs',
			array( __CLASS__, 'render_page_logs' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'aaag-' ) === false ) {
			return;
		}

		// SweetAlert2
		wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true );

		wp_enqueue_style( 'aaag-admin-css', AAAG_PLUGIN_URL . 'admin/assets/admin.css', array(), AAAG_VERSION );
		wp_enqueue_script( 'aaag-admin-js', AAAG_PLUGIN_URL . 'admin/assets/admin.js', array( 'jquery' ), AAAG_VERSION, true );
		
		wp_localize_script( 'aaag-admin-js', 'aaagAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aaag_ajax_nonce' )
		) );
	}

	public static function render_page_generate() {
		require_once AAAG_PLUGIN_DIR . 'admin/views/page-generate.php';
	}

	public static function render_page_jobs() {
		require_once AAAG_PLUGIN_DIR . 'admin/views/page-jobs.php';
	}

	public static function render_page_campaigns() {
		require_once AAAG_PLUGIN_DIR . 'admin/views/page-campaigns.php';
	}

	public static function render_page_settings() {
		require_once AAAG_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	public static function render_page_logs() {
		require_once AAAG_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	public static function ajax_test_connection() {
		check_ajax_referer( 'aaag_ajax_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$result = AAAG_AI_Client::test_anthropic_connection();
		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
	
	public static function ajax_run_job() {
		check_ajax_referer( 'aaag_ajax_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		if ( ! $job_id ) {
			wp_send_json_error( 'Invalid Job ID' );
		}
		
		$success = AAAG_Queue::process_job_manual( $job_id );
		if ( $success ) {
			wp_send_json_success( 'Job processed successfully.' );
		} else {
			wp_send_json_error( 'Job failed to process or is already processing/completed.' );
		}
	}
}
