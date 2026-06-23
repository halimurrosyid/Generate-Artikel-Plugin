<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_DB {
	public static function get_table_name( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'ai_article_' . $table;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . self::get_table_name('campaigns') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			prompt text NOT NULL,
			knowledge_base longtext NOT NULL,
			ai_model varchar(100) NOT NULL DEFAULT 'anthropic:claude-3-5-haiku-latest',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= "CREATE TABLE " . self::get_table_name('jobs') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title text NOT NULL,
			template_id bigint(20) unsigned NOT NULL,
			knowledge_base_id bigint(20) unsigned DEFAULT 0,
			post_type varchar(50) NOT NULL DEFAULT 'post',
			post_status varchar(20) NOT NULL DEFAULT 'draft',
			min_words int(11) NOT NULL DEFAULT 500,
			max_words int(11) NOT NULL DEFAULT 1000,
			schedule_time datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			locked_at datetime DEFAULT NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= "CREATE TABLE " . self::get_table_name('templates') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			prompt text NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= "CREATE TABLE " . self::get_table_name('knowledge_base') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			content longtext NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= "CREATE TABLE " . self::get_table_name('logs') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned DEFAULT NULL,
			message text NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	public static function upgrade() {
		self::create_tables();
	}
}
