<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Post_Creator {
	public static function create_post( $job, $content, $ai_model = '' ) {
		// Attempt to parse JSON response
		$start_pos = strpos($content, '{');
		$end_pos = strrpos($content, '}');
		
		$actual_content = $content;
		$meta_title = '';
		$meta_desc = '';
		$focus_kw = '';
		$tags = array();
		$category_name = '';

		if ($start_pos === false || $end_pos === false || $end_pos <= $start_pos) {
			throw new Exception('Artikel terpotong di tengah jalan (Cut-off) atau format rusak. Artikel dibatalkan agar tidak menjadi sampah. Pastikan Max Tokens diset ke 8192 di Settings.');
		}
		
		if ($start_pos !== false && $end_pos !== false && $end_pos > $start_pos) {
			$json_string = substr($content, $start_pos, $end_pos - $start_pos + 1);
			$data = json_decode($json_string, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				if (!empty($data['content'])) $actual_content = $data['content'];
				if (!empty($data['meta_title'])) $meta_title = sanitize_text_field($data['meta_title']);
				if (!empty($data['meta_description'])) $meta_desc = sanitize_text_field($data['meta_description']);
				if (!empty($data['focus_keyword'])) $focus_kw = sanitize_text_field($data['focus_keyword']);
				if (!empty($data['tags']) && is_array($data['tags'])) {
					$tags = array_map('sanitize_text_field', $data['tags']);
				}
				if (!empty($data['category'])) $category_name = sanitize_text_field($data['category']);
			} else {
				// Fallback jika JSON tidak valid
				if ( preg_match('/"content"\s*:\s*"(.*?)"\s*,\s*"(?:meta_title|meta_description)"/is', $json_string, $matches) ) {
					$actual_content = $matches[1];
					$actual_content = str_replace( '\\"', '"', $actual_content );
				} else {
					$cleaned = preg_replace('/^```[a-z]*\s*/i', '', trim($content));
					$cleaned = preg_replace('/```$/i', '', $cleaned);
					$actual_content = trim($cleaned);
				}
				if ( preg_match('/"meta_title"\s*:\s*"(.*?)"/is', $json_string, $m) ) {
					$meta_title = sanitize_text_field($m[1]);
				}
				if ( preg_match('/"meta_description"\s*:\s*"(.*?)"/is', $json_string, $m) ) {
					$meta_desc = sanitize_text_field($m[1]);
				}
				if ( preg_match('/"focus_keyword"\s*:\s*"(.*?)"/is', $json_string, $m) ) {
					$focus_kw = sanitize_text_field($m[1]);
				}
			}
		}

		// SEO Meta Character Safety Trimming (Failsafe to prevent search engine truncation)
		if ( ! empty( $meta_title ) && mb_strlen( $meta_title ) > 60 ) {
			$meta_title = mb_substr( $meta_title, 0, 58 ) . '..';
		}
		if ( ! empty( $meta_desc ) && mb_strlen( $meta_desc ) > 160 ) {
			$meta_desc = mb_substr( $meta_desc, 0, 157 ) . '...';
		}

		// Clean up literal \n that might be left over if Claude double-escaped
		$actual_content = str_replace(array('\n', '\r', '\\n', '\\r'), "\n", $actual_content);

		// Determine author ID safely
		$author_id = 0;
		if ( isset( $job->author_id ) && absint( $job->author_id ) > 0 ) {
			$author_id = absint( $job->author_id );
		} elseif ( get_current_user_id() ) {
			$author_id = get_current_user_id();
		} else {
			$admin_users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			$author_id   = ! empty( $admin_users ) ? $admin_users[0] : 1;
		}

		// wp_insert_post requires data to be slashed because it calls wp_unslash internally.
		$post_data = wp_slash( array(
			'post_title'   => sanitize_text_field( $job->title ),
			'post_content' => wp_kses_post( $actual_content ), // Secure content
			'post_status'  => sanitize_text_field( $job->post_status ),
			'post_type'    => sanitize_text_field( $job->post_type ),
			'post_author'  => $author_id,
		) );

		if ( $job->post_status === 'future' && ! empty( $job->schedule_time ) ) {
			$post_data['post_date']     = $job->schedule_time;
			$post_data['post_date_gmt'] = get_gmt_from_date( $job->schedule_time );
			$post_data['edit_date']     = true;
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( 'Failed to insert post: ' . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_ai_article_generated', 1 );
		if ( ! empty( $ai_model ) ) {
			update_post_meta( $post_id, '_ai_article_model', sanitize_text_field( $ai_model ) );
		}
		update_post_meta( $post_id, '_ai_article_job_id', $job->id );
		update_post_meta( $post_id, '_ai_article_generated_at', current_time( 'mysql' ) );

		// Set Categories & Tags
		if (!empty($tags)) {
			wp_set_post_terms($post_id, $tags, 'post_tag', false);
		}
		if (!empty($category_name)) {
			$cat_id = get_cat_ID($category_name);
			if ($cat_id <= 0) {
				$cat_id = wp_create_category($category_name);
			}
			if ($cat_id > 0) {
				wp_set_post_categories($post_id, array($cat_id), false);
			}
		}

		// Inject Comprehensive SEO Metadata (Supports Rank Math, Yoast SEO, AIOSEO, SEOPress, The SEO Framework)
		if (!empty($meta_title)) {
			update_post_meta($post_id, 'rank_math_title', $meta_title);
			update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
			update_post_meta($post_id, '_aioseo_title', $meta_title);
			update_post_meta($post_id, '_seopress_titles_title', $meta_title);
			update_post_meta($post_id, '_genesis_title', $meta_title);
		}

		if (!empty($meta_desc)) {
			update_post_meta($post_id, 'rank_math_description', $meta_desc);
			update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
			update_post_meta($post_id, '_aioseo_description', $meta_desc);
			update_post_meta($post_id, '_seopress_titles_desc', $meta_desc);
			update_post_meta($post_id, '_genesis_description', $meta_desc);
		}

		if (!empty($focus_kw)) {
			update_post_meta($post_id, 'rank_math_focus_keyword', $focus_kw);
			update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_kw);
			update_post_meta($post_id, '_aioseo_keywords', $focus_kw);
			update_post_meta($post_id, '_seopress_analysis_target_kw', $focus_kw);
		}

		return $post_id;
	}
}
