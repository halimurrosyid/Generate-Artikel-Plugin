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
				'campaign_id'       => isset($data['campaign_id']) ? absint($data['campaign_id']) : 0,
				'title'             => $data['title'],
				'template_id'       => isset($data['template_id']) ? absint($data['template_id']) : 0,
				'knowledge_base_id' => isset($data['knowledge_base_id']) ? absint($data['knowledge_base_id']) : 0,
				'post_type'         => $data['post_type'],
				'post_status'       => $data['post_status'],
				'author_id'         => isset($data['author_id']) ? absint($data['author_id']) : 1,
				'min_words'         => $data['min_words'],
				'max_words'         => $data['max_words'],
				'schedule_time'     => isset($data['schedule_time']) ? $data['schedule_time'] : null,
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		
		return $wpdb->insert_id;
	}

	public static function get_all( $limit = 50, $offset = 0, $campaign_id = 0, $status = '' ) {
		global $wpdb;
		$table = AAAG_DB::get_table_name( 'jobs' );
		$where_clauses = array();
		
		if ( $campaign_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "campaign_id = %d", $campaign_id );
		}
		if ( ! empty( $status ) ) {
			$where_clauses[] = $wpdb->prepare( "status = %s", $status );
		}
		
		$where = "";
		if ( ! empty( $where_clauses ) ) {
			$where = " WHERE " . implode( " AND ", $where_clauses );
		}
		
		// Sort: Active/Pending/Failed processing queue at the top (with closest schedule time first), completed/history at the bottom (newest first)
		$order_by = "ORDER BY 
			CASE 
				WHEN status = 'processing' THEN 1 
				WHEN status = 'pending' THEN 2 
				WHEN status = 'failed' AND attempts < 3 THEN 3 
				ELSE 4 
			END ASC,
			CASE 
				WHEN status IN ('pending', 'processing', 'failed') THEN 
					CASE WHEN schedule_time IS NULL THEN 0 ELSE 1 END
				ELSE 1
			END ASC,
			CASE 
				WHEN status IN ('pending', 'processing', 'failed') THEN schedule_time 
				ELSE NULL 
			END ASC,
			id DESC";
			
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where $order_by LIMIT %d OFFSET %d", $limit, $offset ) );
	}
	
	public static function count_all( $campaign_id = 0, $status = '' ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		$where_clauses = array();
		
		if ( $campaign_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "campaign_id = %d", $campaign_id );
		}
		if ( ! empty( $status ) ) {
			$where_clauses[] = $wpdb->prepare( "status = %s", $status );
		}
		
		$where = "";
		if ( ! empty( $where_clauses ) ) {
			$where = " WHERE " . implode( " AND ", $where_clauses );
		}
		
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where" );
	}

	public static function update_status( $id, $status, $error_message = null ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		$data = array( 'status' => $status );
		$format = array( '%s' );
		
		if ( $status === 'processing' ) {
			$data['locked_at'] = current_time( 'mysql' );
			$format[] = '%s';
		} elseif ( $status === 'completed' || $status === 'failed' || $status === 'skipped' ) {
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

	public static function delete_multiple( $ids ) {
		if ( empty( $ids ) || ! is_array( $ids ) ) return 0;
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		$escaped_ids = implode( ',', array_map( 'absint', $ids ) );
		return $wpdb->query( "DELETE FROM $table_name WHERE id IN ($escaped_ids)" );
	}

	public static function reset_multiple( $ids ) {
		if ( empty( $ids ) || ! is_array( $ids ) ) return 0;
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		$escaped_ids = implode( ',', array_map( 'absint', $ids ) );
		return $wpdb->query( "UPDATE $table_name SET status = 'pending', attempts = 0, error_message = NULL WHERE id IN ($escaped_ids)" );
	}

	public static function reset_failed( $campaign_id = 0 ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		$where = " WHERE status = 'failed'";
		if ( $campaign_id > 0 ) {
			$where .= $wpdb->prepare( " AND campaign_id = %d", $campaign_id );
		}
		return $wpdb->query( "UPDATE $table_name SET status = 'pending', attempts = 0, error_message = NULL $where" );
	}

	public static function reschedule_campaign_jobs( $campaign_id, $start_date = '', $schedule_mode = 'interval', $min_gap = 2, $max_gap = 6, $gap_unit = 'hours', $daily_min = 12, $daily_max = 14 ) {
		global $wpdb;
		$jobs_table = AAAG_DB::get_table_name('jobs');

		$current_schedule = null;
		$current_date_ts = null;

		if ( empty( $start_date ) ) {
			$start_date = current_time( 'Y-m-d H:i:s' );
		}

		if ( $schedule_mode === 'daily' ) {
			$current_date_ts = strtotime( date( 'Y-m-d', strtotime( $start_date ) ) );
		} else {
			$current_schedule = strtotime( $start_date );
		}

		$where = "status IN ('pending', 'failed')";
		if ( $campaign_id > 0 ) {
			$where .= $wpdb->prepare( " AND campaign_id = %d", $campaign_id );
		}

		$jobs_to_update = $wpdb->get_results( "SELECT id FROM $jobs_table WHERE $where ORDER BY id ASC" );

		$updated_count = 0;
		foreach ( $jobs_to_update as $job ) {
			$job_schedule_time = null;
			if ( $schedule_mode === 'daily' && $current_date_ts ) {
				$random_hour = rand( $daily_min, $daily_max );
				$random_minute = rand( 0, 59 );
				if ( $random_hour === $daily_max ) $random_minute = 0;
				$job_schedule_time = date('Y-m-d', $current_date_ts) . ' ' . sprintf('%02d:%02d:00', $random_hour, $random_minute);
				$current_date_ts += 86400;
			} else {
				$job_schedule_time = date( 'Y-m-d H:i:s', $current_schedule );
				$gap_value = rand( $min_gap, $max_gap );
				$multiplier = ( $gap_unit === 'minutes' ) ? 60 : 3600;
				$current_schedule += ( $gap_value * $multiplier );
			}

			if ( $job_schedule_time ) {
				$wpdb->update(
					$jobs_table,
					array( 'schedule_time' => $job_schedule_time ),
					array( 'id' => $job->id ),
					array( '%s' ),
					array( '%d' )
				);
				$updated_count++;
			}
		}

		return $updated_count;
	}
}
