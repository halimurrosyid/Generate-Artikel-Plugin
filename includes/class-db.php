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
			ai_model varchar(100) NOT NULL DEFAULT 'anthropic:claude-3-5-haiku-20241022',
			language varchar(50) NOT NULL DEFAULT 'id',
			tone varchar(50) NOT NULL DEFAULT 'informative',
			pov varchar(50) NOT NULL DEFAULT 'second_person',
			author_id bigint(20) unsigned NOT NULL DEFAULT 1,
			generate_seo_meta tinyint(1) NOT NULL DEFAULT 0,
			seo_title_style varchar(50) NOT NULL DEFAULT 'dynamic_ctr',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= "CREATE TABLE " . self::get_table_name('jobs') . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title text NOT NULL,
			template_id bigint(20) unsigned NOT NULL DEFAULT 0,
			knowledge_base_id bigint(20) unsigned DEFAULT 0,
			post_type varchar(50) NOT NULL DEFAULT 'post',
			post_status varchar(20) NOT NULL DEFAULT 'draft',
			author_id bigint(20) unsigned NOT NULL DEFAULT 1,
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
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	public static function upgrade() {
		self::create_tables();

		// Robust verification: Ensure columns exist in case dbDelta failed
		global $wpdb;
		$campaigns_table = self::get_table_name('campaigns');
		$jobs_table      = self::get_table_name('jobs');
		
		$wpdb->suppress_errors();
		$lang_exists        = $wpdb->query( "SELECT language FROM $campaigns_table LIMIT 1" ) !== false;
		$tone_exists        = $wpdb->query( "SELECT tone FROM $campaigns_table LIMIT 1" ) !== false;
		$pov_exists         = $wpdb->query( "SELECT pov FROM $campaigns_table LIMIT 1" ) !== false;
		$camp_author_exists = $wpdb->query( "SELECT author_id FROM $campaigns_table LIMIT 1" ) !== false;
		$job_author_exists  = $wpdb->query( "SELECT author_id FROM $jobs_table LIMIT 1" ) !== false;
		$seo_meta_exists    = $wpdb->query( "SELECT generate_seo_meta FROM $campaigns_table LIMIT 1" ) !== false;
		$seo_style_exists   = $wpdb->query( "SELECT seo_title_style FROM $campaigns_table LIMIT 1" ) !== false;
		$post_type_exists   = $wpdb->query( "SELECT post_type FROM $jobs_table LIMIT 1" ) !== false;
		$post_status_exists = $wpdb->query( "SELECT post_status FROM $jobs_table LIMIT 1" ) !== false;
		$min_words_exists   = $wpdb->query( "SELECT min_words FROM $jobs_table LIMIT 1" ) !== false;
		$max_words_exists   = $wpdb->query( "SELECT max_words FROM $jobs_table LIMIT 1" ) !== false;
		$wpdb->suppress_errors( false );

		if ( ! $lang_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN language varchar(50) NOT NULL DEFAULT 'id'" );
		}
		if ( ! $tone_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN tone varchar(50) NOT NULL DEFAULT 'informative'" );
		}
		if ( ! $pov_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN pov varchar(50) NOT NULL DEFAULT 'second_person'" );
		}
		if ( ! $camp_author_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN author_id bigint(20) unsigned NOT NULL DEFAULT 1" );
		}
		if ( ! $seo_meta_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN generate_seo_meta tinyint(1) NOT NULL DEFAULT 0" );
		}
		if ( ! $seo_style_exists ) {
			$wpdb->query( "ALTER TABLE $campaigns_table ADD COLUMN seo_title_style varchar(50) NOT NULL DEFAULT 'dynamic_ctr'" );
		}
		if ( ! $job_author_exists ) {
			$wpdb->query( "ALTER TABLE $jobs_table ADD COLUMN author_id bigint(20) unsigned NOT NULL DEFAULT 1" );
		}
		if ( ! $post_type_exists ) {
			$wpdb->query( "ALTER TABLE $jobs_table ADD COLUMN post_type varchar(50) NOT NULL DEFAULT 'post'" );
		}
		if ( ! $post_status_exists ) {
			$wpdb->query( "ALTER TABLE $jobs_table ADD COLUMN post_status varchar(20) NOT NULL DEFAULT 'draft'" );
		}
		if ( ! $min_words_exists ) {
			$wpdb->query( "ALTER TABLE $jobs_table ADD COLUMN min_words int(11) NOT NULL DEFAULT 500" );
		}
		if ( ! $max_words_exists ) {
			$wpdb->query( "ALTER TABLE $jobs_table ADD COLUMN max_words int(11) NOT NULL DEFAULT 1000" );
		}

		// Ensure logs table created_at defaults to CURRENT_TIMESTAMP
		$logs_table = self::get_table_name('logs');
		$wpdb->query( "ALTER TABLE $logs_table MODIFY COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL" );
	}
}
