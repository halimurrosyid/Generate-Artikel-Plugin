<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Campaign {
	public static function insert( $data ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'campaigns' );
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'campaigns' );
		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'campaigns' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	public static function get_all() {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'campaigns' );
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );
	}

	public static function update_status( $id, $status ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'campaigns' );
		return $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$campaigns_table = AAAG_DB::get_table_name( 'campaigns' );
		$jobs_table = AAAG_DB::get_table_name( 'jobs' );
		
		// Delete all jobs in this campaign
		$wpdb->delete( $jobs_table, array( 'campaign_id' => $id ) );
		// Delete the campaign
		return $wpdb->delete( $campaigns_table, array( 'id' => $id ) );
	}

	public static function get_stats( $id ) {
		global $wpdb;
		$jobs_table = AAAG_DB::get_table_name( 'jobs' );
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $jobs_table WHERE campaign_id = %d", $id ) );
		$completed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $jobs_table WHERE campaign_id = %d AND status = 'completed'", $id ) );
		return array(
			'total' => (int) $total,
			'completed' => (int) $completed
		);
	}
}
