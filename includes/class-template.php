<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Template {
	public static function get_all() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('templates');
		return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY name ASC" );
	}

	public static function get( $id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('templates');
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
	}

	public static function insert( $name, $prompt ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('templates');
		return $wpdb->insert(
			$table_name,
			array(
				'name'   => $name,
				'prompt' => $prompt,
			),
			array( '%s', '%s' )
		);
	}

	public static function update( $id, $name, $prompt ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('templates');
		return $wpdb->update(
			$table_name,
			array(
				'name'   => $name,
				'prompt' => $prompt,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('templates');
		return $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
	}
}
