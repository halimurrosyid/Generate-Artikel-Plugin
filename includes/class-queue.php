<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Queue {
	public static function init() {
		add_action( 'aaag_process_queue_hook', array( __CLASS__, 'process_queue' ) );
	}

	public static function process_queue() {
		@set_time_limit( 300 );
		global $wpdb;
		
		// Auto-clean logs older than 7 days to keep database lightweight
		AAAG_Logger::clear_old_logs( 7 );

		// Self-healing: Auto-publish any missed schedule future posts
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->posts SET post_status = 'publish' WHERE post_status = 'future' AND post_date <= %s",
			current_time( 'mysql' )
		) );

		$table_name = AAAG_DB::get_table_name('jobs');
		
		$fifteen_mins_ago = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 15 * 60 ) );
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
		
		// Select top candidate ID
		$candidate = $wpdb->get_row( "SELECT id FROM $table_name WHERE campaign_id IN ($in_clause) AND (status = 'pending' OR (status = 'failed' AND attempts < 3)) ORDER BY CASE WHEN schedule_time IS NULL THEN 0 ELSE 1 END ASC, schedule_time ASC, id ASC LIMIT 1" );

		if ( ! $candidate ) {
			return; 
		}

		// Atomically lock the row to prevent concurrent race condition duplicate runs
		$current_time = current_time( 'mysql' );
		$locked = $wpdb->query( $wpdb->prepare(
			"UPDATE $table_name SET status = 'processing', locked_at = %s WHERE id = %d AND (status = 'pending' OR (status = 'failed' AND attempts < 3))",
			$current_time,
			$candidate->id
		) );

		if ( ! $locked ) {
			return; // Row was grabbed by another thread/request
		}

		// Fetch the full locked job details
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $candidate->id ) );
		if ( ! $job ) {
			return;
		}

		self::execute_job( $job );
	}

	public static function process_job_manual( $job_id ) {
		@set_time_limit( 300 );
		global $wpdb;
		$table_name = AAAG_DB::get_table_name('jobs');
		
		// Atomically lock the row
		$current_time = current_time( 'mysql' );
		$locked = $wpdb->query( $wpdb->prepare(
			"UPDATE $table_name SET status = 'processing', locked_at = %s WHERE id = %d AND status IN ('pending', 'failed', 'skipped')",
			$current_time,
			$job_id
		) );

		if ( ! $locked ) {
			return false;
		}

		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $job_id ) );
		if ( ! $job ) return false;
		
		self::execute_job( $job );
		return true;
	}

	private static function execute_job( $job ) {
		try {
			AAAG_Logger::log( "Starting job ID: {$job->id} for title: {$job->title}", $job->id );
			
			// Pengecekan apakah judul artikel sudah ada sebelumnya di WordPress (berdasarkan post_type target)
			global $wpdb;
			$post_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s AND post_status IN ('publish', 'future', 'draft', 'pending', 'private') LIMIT 1",
				$job->title,
				$job->post_type
			) );

			if ( $post_exists ) {
				AAAG_Job::update_status( $job->id, 'skipped', 'Skipped: Judul artikel sudah terbit atau terdaftar di WordPress (Duplicate).' );
				AAAG_Logger::log( "Job ID {$job->id} di-skip karena judul duplikat pada post type '{$job->post_type}': {$job->title}", $job->id );
				return;
			}
			
			$campaign = AAAG_Campaign::get( $job->campaign_id );
			if ( ! $campaign ) {
				throw new Exception( "Campaign not found." );
			}
			
			$prompt_text = $campaign->prompt;
			$knowledge_base_content = $campaign->knowledge_base;

			$prompt = self::compile_prompt( $prompt_text, $job, $knowledge_base_content, $campaign );
			
			$ai_model_str = isset($campaign->ai_model) && !empty($campaign->ai_model) ? $campaign->ai_model : 'anthropic:claude-3-5-haiku-20241022';
			$content = AAAG_AI_Client::generate_content( $prompt, $ai_model_str );
			
			$post_id = AAAG_Post_Creator::create_post( $job, $content, $ai_model_str );
			
			AAAG_Job::update_status( $job->id, 'completed', '' );
			AAAG_Logger::log( "Job completed. Created post ID: $post_id", $job->id );
			
		} catch ( Exception $e ) {
			AAAG_Job::update_status( $job->id, 'failed', $e->getMessage() );
			AAAG_Logger::log( "Job failed: " . $e->getMessage(), $job->id );
		}
	}
	
	private static function compile_prompt( $prompt, $job, $kb_content, $campaign = null ) {
		$replacements = array(
			'{{title}}'          => $job->title,
			'{{min_words}}'      => $job->min_words,
			'{{max_words}}'      => $job->max_words,
			'{{site_name}}'      => get_bloginfo( 'name' ),
			'{{current_date}}'   => current_time( 'Y-m-d' ),
		);
		$compiled = strtr( $prompt, $replacements );

		// Inject Persona: Language, Tone of Voice, and POV Guidelines
		$lang_map = array(
			'id' => 'Bahasa Indonesia (Natural, EYD sesuai standar penulisan artikel web modern)',
			'en' => 'English (Fluent, natural, and modern standard English)',
			'ms' => 'Bahasa Melayu (Baku dan mesra pembaca)',
			'es' => 'Spanish (Español fluido y natural)',
			'de' => 'German (Deutsch)',
			'fr' => 'French (Français)',
			'ar' => 'Arabic (العربية)',
			'ja' => 'Japanese (日本語)'
		);

		$tone_map = array(
			'informative'  => 'Informatif, mendalam, kaya data, terstruktur dan mudah dipahami',
			'professional' => 'Profesional, berwibawa, formal, dan kredibel',
			'casual'       => 'Kasual, ramah, santai, komunikatif, dan mengalir seperti mengobrol dengan kawan',
			'journalistic' => 'Jurnalistik, berbasis fakta, objektif, padat, dan investigatif',
			'storytelling' => 'Storytelling naratif, memikat emosi pembaca dengan alur cerita yang hidup',
			'persuasive'   => 'Persuasif, copywriting menjual, memikat rasa penasaran, dan mendorong tindakan (Call to Action)'
		);

		$pov_map = array(
			'second_person' => 'Orang Kedua (Sapa pembaca dengan sapaan "Anda" atau "Kamu" secara akrab dan konsisten)',
			'first_person'  => 'Orang Pertama (Gunakan sudut pandang "Saya" atau "Kami" sebagai pakar/praktisi berpengalaman)',
			'third_person'  => 'Orang Ketiga (Sudut pandang netral / objektif tanpa sapaan personal)'
		);

		$lang_code = ( $campaign && ! empty( $campaign->language ) ) ? $campaign->language : 'id';
		$tone_code = ( $campaign && ! empty( $campaign->tone ) ) ? $campaign->tone : 'informative';
		$pov_code  = ( $campaign && ! empty( $campaign->pov ) ) ? $campaign->pov : 'second_person';

		$lang_desc = isset( $lang_map[$lang_code] ) ? $lang_map[$lang_code] : 'Bahasa Indonesia';
		$tone_desc = isset( $tone_map[$tone_code] ) ? $tone_map[$tone_code] : 'Informatif dan edukatif';
		$pov_desc  = isset( $pov_map[$pov_code] ) ? $pov_map[$pov_code] : 'Orang Kedua';

		$persona_instruction = "\n\n--- PEDOMAN PENULISAN & PERSONA ---\n";
		$persona_instruction .= "- Bahasa Wajib: " . $lang_desc . "\n";
		$persona_instruction .= "- Gaya Bahasa / Tone: " . $tone_desc . "\n";
		$persona_instruction .= "- Sudut Pandang (POV): " . $pov_desc . "\n";
		$persona_instruction .= "-----------------------------------\n";

		$compiled .= $persona_instruction;
		
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
		
		// Batasi maksimal rekomendasi terbaik berdasarkan pengaturan (default: 5) agar hemat token dan seimbang
		$max_internal_links = (int) get_option( 'aaag_max_internal_links', 5 );
		$related_posts = array_slice( $related_posts, 0, $max_internal_links );
		
		$links_text = "";
		foreach($related_posts as $p) {
			$links_text .= "- " . $p->post_title . " (URL: " . get_permalink($p->ID) . ")\n";
		}
		
		$categories = get_categories( array( 'hide_empty' => false ) );
		$cats_text = "";
		foreach($categories as $c) {
			$cats_text .= "- " . $c->name . "\n";
		}
		
		$seo_enabled = ( $campaign && isset( $campaign->generate_seo_meta ) && intval( $campaign->generate_seo_meta ) === 1 );
		
		$seo_replacements = array(
			'{{title}}'        => $job->title,
			'{{site_name}}'    => get_bloginfo( 'name' ),
			'{{current_year}}' => current_time( 'Y' ),
		);

		if ( ! empty( $campaign->seo_title_prompt ) ) {
			$seo_title_rule = strtr( $campaign->seo_title_prompt, $seo_replacements ) . " (STRICT RULE: Length must be strictly 50-60 characters, never exceed 60 chars).";
		} else {
			$seo_style   = ( $campaign && ! empty( $campaign->seo_title_style ) ) ? $campaign->seo_title_style : 'dynamic_ctr';
			$seo_title_rule = "Create a unique, compelling, click-worthy SEO Meta Title strictly between 50 and 60 characters (never exceed 60 chars). It must include the primary keyword and be optimized for maximum search CTR.";
			if ( $seo_style === 'power_words' ) {
				$seo_title_rule = "Create an engaging SEO Meta Title strictly between 50 and 60 characters that includes power words and current year (" . current_time('Y') . ").";
			} elseif ( $seo_style === 'standard' ) {
				$seo_title_rule = "Create a clean standard SEO Meta Title strictly between 50 and 60 characters following the pattern: [Title] | [Brand/Site Name].";
			}
		}

		if ( ! empty( $campaign->seo_desc_prompt ) ) {
			$seo_desc_rule = strtr( $campaign->seo_desc_prompt, $seo_replacements ) . " (STRICT RULE: Length must be strictly 120-155 characters, must not exceed 160 chars).";
		} else {
			$seo_desc_rule = "A compelling SEO meta description strictly between 120 and 155 characters (MUST NOT exceed 160 chars) summarizing the article with a natural call to action";
		}

		$advanced_instruction = "\n\n--- ADVANCED INSTRUCTIONS ---\n";
		$advanced_instruction .= "You must output your response ONLY as a raw valid JSON object without any markdown formatting, no code blocks, and no extra text. Do not wrap it in ```json. The JSON must have the following exact keys:\n";
		$advanced_instruction .= "{\n";
		$advanced_instruction .= '  "content": "Your full article HTML content here. If relevant, naturally insert hyperlinks (<a> tags) to these recent articles where contextually appropriate:\n' . $links_text . '", ' . "\n";
		if ( $seo_enabled ) {
			$advanced_instruction .= '  "meta_title": "' . $seo_title_rule . '", ' . "\n";
			$advanced_instruction .= '  "meta_description": "' . $seo_desc_rule . '", ' . "\n";
			$advanced_instruction .= '  "focus_keyword": "The primary SEO focus keyword of this article (2-4 words)", ' . "\n";
		}
		$advanced_instruction .= '  "tags": ["tag1", "tag2", "tag3"], ' . "\n";
		$advanced_instruction .= '  "category": "Select ONE most relevant category from this list: \n' . $cats_text . '"' . "\n";
		$advanced_instruction .= "}\n\n";
		$advanced_instruction .= "CRITICAL SEO GUIDELINES:\n";
		$advanced_instruction .= "- If meta_title is generated, ensure character length is 50-60 chars.\n";
		$advanced_instruction .= "- If meta_description is generated, ensure character length is 120-155 chars.\n";
		$advanced_instruction .= "- Complete the JSON object perfectly and close with } at the very end.\n";
		
		return $compiled . $advanced_instruction;
	}
}
