<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Queue {
	public static function init() {
		add_action( 'aaag_process_queue_hook', array( __CLASS__, 'process_queue' ) );
	}

	public static function process_queue() {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		$fifteen_mins_ago = gmdate( 'Y-m-d H:i:s', time() - ( 15 * 60 ) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table_name SET status = 'failed', error_message = 'Job stuck processing for over 15 minutes', locked_at = NULL WHERE status = 'processing' AND locked_at < %s",
			$fifteen_mins_ago
		) );

		// 2. Get one pending or eligible failed job to process, ONLY if campaign is active and buffer is not full
		$campaigns_table = AAAG_DB::get_table_name('campaigns');
		
		$max_buffer = (int) get_option( 'aaag_queue_buffer', 5 );
		$active_campaigns = $wpdb->get_col( "SELECT id FROM $campaigns_table WHERE status = 'active'" );
		$valid_campaign_ids = array();
		
		foreach ( $active_campaigns as $cid ) {
			// Hitung artikel yang sudah selesai di-generate (completed) tetapi jadwal tayangnya masih di masa depan
			$future_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE campaign_id = %d AND status = 'completed' AND schedule_time > %s", $cid, current_time( 'mysql' ) ) );
			if ( $future_count < $max_buffer ) {
				$valid_campaign_ids[] = $cid;
			}
		}
		
		if ( empty( $valid_campaign_ids ) ) {
			return; // Semua campaign aktif sudah memenuhi batas buffer artikel masa depan
		}
		
		$in_clause = implode( ',', array_map( 'intval', $valid_campaign_ids ) );
		$job = $wpdb->get_row( "SELECT * FROM $table_name WHERE campaign_id IN ($in_clause) AND (status = 'pending' OR (status = 'failed' AND attempts < 3)) ORDER BY id ASC LIMIT 1" );

		if ( ! $job ) {
			return; 
		}

		AAAG_Job::update_status( $job->id, 'processing' );
		self::execute_job( $job );
	}

	public static function process_job_manual( $job_id ) {
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $job_id ) );
		if ( ! $job || $job->status === 'processing' || $job->status === 'completed' ) return false;
		
		AAAG_Job::update_status( $job->id, 'processing' );
		self::execute_job( $job );
		return true;
	}

	private static function execute_job( $job ) {
		try {
			AAAG_Logger::log( "Starting job ID: {$job->id} for title: {$job->title}", $job->id );
			
			$campaign = AAAG_Campaign::get( $job->campaign_id );
			if ( ! $campaign ) {
				throw new Exception( "Campaign not found." );
			}
			
			$prompt_text = $campaign->prompt;
			$knowledge_base_content = $campaign->knowledge_base;

			$prompt = self::compile_prompt( $prompt_text, $job, $knowledge_base_content );
			
			$ai_model_str = isset($campaign->ai_model) && !empty($campaign->ai_model) ? $campaign->ai_model : 'anthropic:claude-sonnet-4-6';
			$content = AAAG_AI_Client::generate_content( $prompt, $ai_model_str );
			
			$post_id = AAAG_Post_Creator::create_post( $job, $content );
			
			AAAG_Job::update_status( $job->id, 'completed', '' );
			AAAG_Logger::log( "Job completed. Created post ID: $post_id", $job->id );
			
		} catch ( Exception $e ) {
			AAAG_Job::update_status( $job->id, 'failed', $e->getMessage() );
			AAAG_Logger::log( "Job failed: " . $e->getMessage(), $job->id );
		}
	}
	
	private static function compile_prompt( $prompt, $job, $kb_content ) {
		$replacements = array(
			'{{title}}'          => $job->title,
			'{{min_words}}'      => $job->min_words,
			'{{max_words}}'      => $job->max_words,
			'{{site_name}}'      => get_bloginfo( 'name' ),
			'{{current_date}}'   => current_time( 'Y-m-d' ),
		);
		$compiled = strtr( $prompt, $replacements );
		
		if (!empty($kb_content) && strpos($compiled, '{{knowledge_base}}') === false) {
			$compiled .= "\n\n--- REFERENSI / KNOWLEDGE BASE ---\nHarap baca dan gunakan informasi berikut ini secara ekstensif dalam artikel Anda:\n" . $kb_content . "\n----------------------------------\n";
		}
		// Also replace it if it was manually typed
		$compiled = str_replace('{{knowledge_base}}', $kb_content, $compiled);
		
		// Smart RAG Internal Linking (Lintas Post Type & Pencarian Konteks)
		$target_post_types = get_option( 'aaag_internal_link_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $target_post_types ) || empty( $target_post_types ) ) {
			$target_post_types = array( 'post' );
		}
		
		// Langkah 1: Pencarian akurat berdasarkan keseluruhan judul
		$related_posts = get_posts( array(
			's'              => $job->title,
			'post_type'      => $target_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 15
		) );
		
		// Langkah 2: Jika kurang dari 5 hasil, pecah kata kunci (buang stop words)
		if ( count( $related_posts ) < 5 ) {
			$stopwords = array('di','ke','dari','yang','dan','untuk','dengan','adalah','pada','dalam','ini','itu','atau','oleh');
			$words = explode( ' ', strtolower( $job->title ) );
			$keywords = array_diff( $words, $stopwords );
			$search_term = implode( ' ', $keywords );
			
			$related_posts2 = get_posts( array(
				's'              => $search_term,
				'post_type'      => $target_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 15
			) );
			
			// Gabungkan tanpa duplikat
			$merged = array_merge( $related_posts, $related_posts2 );
			$related_posts = array();
			$ids = array();
			foreach ( $merged as $rp ) {
				if ( ! in_array( $rp->ID, $ids ) ) {
					$ids[] = $rp->ID;
					$related_posts[] = $rp;
				}
			}
		}
		
		// Langkah 3: Jika masih kurang dari 3 hasil (misal web baru), gunakan artikel terbaru (Fallback)
		if ( count( $related_posts ) < 3 ) {
			$fallback = get_posts( array(
				'post_type'      => $target_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC'
			) );
			
			$merged = array_merge( $related_posts, $fallback );
			$related_posts = array();
			$ids = array();
			foreach ( $merged as $rp ) {
				if ( ! in_array( $rp->ID, $ids ) ) {
					$ids[] = $rp->ID;
					$related_posts[] = $rp;
				}
			}
		}
		
		// Batasi maksimal 15 rekomendasi terbaik agar hemat token
		$related_posts = array_slice( $related_posts, 0, 15 );
		
		$links_text = "";
		foreach($related_posts as $p) {
			$links_text .= "- " . $p->post_title . " (URL: " . get_permalink($p->ID) . ")\n";
		}
		
		$categories = get_categories( array( 'hide_empty' => false ) );
		$cats_text = "";
		foreach($categories as $c) {
			$cats_text .= "- " . $c->name . "\n";
		}
		
		$advanced_instruction = "\n\n--- ADVANCED INSTRUCTIONS ---\n";
		$advanced_instruction .= "You must output your response ONLY as a raw valid JSON object without any markdown formatting, no code blocks, and no extra text. Do not wrap it in ```json. The JSON must have the following exact keys:\n";
		$advanced_instruction .= "{\n";
		$advanced_instruction .= '  "content": "Your full article HTML content here. If relevant, naturally insert hyperlinks (<a> tags) to these recent articles where contextually appropriate:\n' . $links_text . '", ' . "\n";
		$advanced_instruction .= '  "meta_description": "A compelling SEO meta description under 160 characters", ' . "\n";
		$advanced_instruction .= '  "focus_keyword": "The primary SEO focus keyword of this article", ' . "\n";
		$advanced_instruction .= '  "tags": ["tag1", "tag2", "tag3"], ' . "\n";
		$advanced_instruction .= '  "category": "Select ONE most relevant category from this list: \n' . $cats_text . '"' . "\n";
		$advanced_instruction .= "}\n\n";
		$advanced_instruction .= "CRITICAL: You must write the entire article and complete the JSON object perfectly. Do not let your response get cut off. Make sure you close the JSON object with } at the very end.\n";
		
		return $compiled . $advanced_instruction;
	}
}
