<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Knowledge_Base {
	public static function get_all() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('knowledge_base');
		return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY name ASC" );
	}

	public static function get( $id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('knowledge_base');
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
	}

	public static function insert( $name, $content ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('knowledge_base');
		return $wpdb->insert(
			$table_name,
			array(
				'name'    => $name,
				'content' => $content,
			),
			array( '%s', '%s' )
		);
	}

	public static function update( $id, $name, $content ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('knowledge_base');
		return $wpdb->update(
			$table_name,
			array(
				'name'    => $name,
				'content' => $content,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('knowledge_base');
		return $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
	}
}
