<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Activator {
	public static function activate() {
		AAAG_DB::create_tables();
		
		if ( ! wp_next_scheduled( 'aaag_process_queue_hook' ) ) {
			wp_schedule_event( time(), 'aaag_every_five_minutes', 'aaag_process_queue_hook' );
		}
	}
}

add_filter( 'cron_schedules', 'aaag_add_cron_interval' );
if ( ! function_exists( 'aaag_add_cron_interval' ) ) {
	function aaag_add_cron_interval( $schedules ) {
		$schedules['aaag_every_five_minutes'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 Minutes', 'ai-auto-article-generator' ),
		);
		return $schedules;
	}
}
