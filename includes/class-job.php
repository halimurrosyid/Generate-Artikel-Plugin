<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Job {
	public static function insert( $data ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		$wpdb->insert(
			$table_name,
			array(
				'campaign_id'       => isset($data['campaign_id']) ? $data['campaign_id'] : 0,
				'title'             => $data['title'],
				'template_id'       => $data['template_id'],
				'knowledge_base_id' => isset($data['knowledge_base_id']) ? $data['knowledge_base_id'] : 0,
				'post_type'         => $data['post_type'],
				'post_status'       => $data['post_status'],
				'min_words'         => $data['min_words'],
				'max_words'         => $data['max_words'],
				'schedule_time'     => isset($data['schedule_time']) ? $data['schedule_time'] : null,
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		
		return $wpdb->insert_id;
	}

	public static function get_all( $limit = 50, $offset = 0, $campaign_id = 0 ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'jobs' );
		$where = "";
		if ( $campaign_id > 0 ) {
			$where = $wpdb->prepare( " WHERE campaign_id = %d ", $campaign_id );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
	}
	
	public static function count_all() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
	}

	public static function update_status( $id, $status, $error_message = null ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		$data = array( 'status' => $status );
		$format = array( '%s' );
		
		if ( $status === 'processing' ) {
			$data['locked_at'] = current_time( 'mysql' );
			$format[] = '%s';
		} elseif ( $status === 'completed' || $status === 'failed' ) {
			$data['locked_at'] = null;
			$format[] = '%s';
		}

		if ( $error_message !== null ) {
			$data['error_message'] = $error_message;
			$format[] = '%s';
		}
		
		if ( $status === 'failed' ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d", $id ) );
		}

		$wpdb->update(
			$table_name,
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);
	}
	
	public static function delete( $id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		return $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
	}
}
