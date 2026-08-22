<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'ai-auto-article-generator' ) );
}

if ( isset( $_POST['aaag_generate_submit'] ) && check_admin_referer( 'aaag_generate_action', 'aaag_generate_nonce' ) ) {
	$campaign_name     = isset( $_POST['campaign_name'] ) ? sanitize_text_field( $_POST['campaign_name'] ) : 'Untitled Campaign';
	$ai_model          = isset( $_POST['ai_model'] ) ? sanitize_text_field( $_POST['ai_model'] ) : 'anthropic:claude-3-5-haiku-20241022';
	$language          = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : 'id';
	$tone              = isset( $_POST['tone'] ) ? sanitize_text_field( $_POST['tone'] ) : 'informative';
	$pov               = isset( $_POST['pov'] ) ? sanitize_text_field( $_POST['pov'] ) : 'second_person';
	$author_id         = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : ( get_current_user_id() ?: 1 );
	$generate_seo_meta = isset( $_POST['generate_seo_meta'] ) ? 1 : 0;
	$seo_title_style   = isset( $_POST['seo_title_style'] ) ? sanitize_text_field( $_POST['seo_title_style'] ) : 'dynamic_ctr';
	$seo_title_prompt  = isset( $_POST['seo_title_prompt'] ) ? wp_unslash( $_POST['seo_title_prompt'] ) : '';
	$seo_desc_prompt   = isset( $_POST['seo_desc_prompt'] ) ? wp_unslash( $_POST['seo_desc_prompt'] ) : '';
	$prompt            = isset( $_POST['prompt'] ) ? wp_unslash( $_POST['prompt'] ) : '';
	$knowledge_base    = isset( $_POST['knowledge_base'] ) ? wp_unslash( $_POST['knowledge_base'] ) : '';
	
	$titles            = isset( $_POST['titles'] ) ? explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['titles'] ) ) ) : array();
	$post_type         = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
	$post_status       = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'draft';
	$min_words         = isset( $_POST['min_words'] ) ? absint( $_POST['min_words'] ) : 500;
	$max_words         = isset( $_POST['max_words'] ) ? absint( $_POST['max_words'] ) : 1000;
	
	$schedule_date     = isset( $_POST['schedule_date'] ) ? sanitize_text_field( $_POST['schedule_date'] ) : ''; // Y-m-d\TH:i
	$schedule_mode     = isset( $_POST['schedule_mode'] ) ? sanitize_text_field( $_POST['schedule_mode'] ) : 'interval';
	$min_gap           = isset( $_POST['min_gap'] ) ? absint( $_POST['min_gap'] ) : 2;
	$max_gap           = isset( $_POST['max_gap'] ) ? absint( $_POST['max_gap'] ) : 6;
	$gap_unit          = isset( $_POST['gap_unit'] ) ? sanitize_text_field( $_POST['gap_unit'] ) : 'hours';
	$daily_min         = isset( $_POST['daily_min'] ) ? absint( $_POST['daily_min'] ) : 12;
	$daily_max         = isset( $_POST['daily_max'] ) ? absint( $_POST['daily_max'] ) : 14;
	
	if ( $min_words > $max_words ) {
		echo '<div class="notice notice-error"><p>Minimal kata tidak boleh lebih besar dari maksimal kata.</p></div>';
	} elseif ( empty( $prompt ) ) {
		echo '<div class="notice notice-error"><p>Silakan isi AI Prompt Konten Artikel.</p></div>';
	} else {
		// Insert Campaign
		$campaign_id = AAAG_Campaign::insert( array(
			'name'              => $campaign_name,
			'prompt'            => $prompt,
			'knowledge_base'    => $knowledge_base,
			'ai_model'          => $ai_model,
			'language'          => $language,
			'tone'              => $tone,
			'pov'               => $pov,
			'author_id'         => $author_id,
			'generate_seo_meta' => $generate_seo_meta,
			'seo_title_style'   => $seo_title_style,
			'seo_title_prompt'  => $seo_title_prompt,
			'seo_desc_prompt'   => $seo_desc_prompt,
			'status'            => 'active'
		) );
		
		$current_schedule = null;
		$current_date_ts = null;
		
		if ( $post_status === 'future' ) {
			if ( $schedule_mode === 'daily' ) {
				// Ambil tanggal mulai, default hari ini
				$current_date_ts = !empty($schedule_date) ? strtotime(date('Y-m-d', strtotime($schedule_date))) : strtotime(date('Y-m-d'));
			} elseif ( ! empty( $schedule_date ) ) {
				$current_schedule = strtotime( $schedule_date );
			}
		}
		
		$jobs_added = 0;
		foreach ( $titles as $title ) {
			$title = trim( $title );
			if ( empty( $title ) ) continue;
			
			$job_schedule_time = null;
			if ( $schedule_mode === 'daily' && $current_date_ts ) {
				// Acak jam dan menit dalam rentang yang ditentukan
				$random_hour = rand( $daily_min, $daily_max );
				$random_minute = rand( 0, 59 );
				if ( $random_hour === $daily_max ) $random_minute = 0; // Jangan melebihi batas jam
				
				$job_schedule_time = date('Y-m-d', $current_date_ts) . ' ' . sprintf('%02d:%02d:00', $random_hour, $random_minute);
				
				// Maju ke hari berikutnya (berurutan)
				$current_date_ts += 86400; // 24 jam
			} elseif ( $schedule_mode === 'interval' && $current_schedule ) {
				$job_schedule_time = date( 'Y-m-d H:i:s', $current_schedule );
				$gap_value = rand( $min_gap, $max_gap );
				$multiplier = ( $gap_unit === 'minutes' ) ? 60 : 3600;
				$current_schedule += ( $gap_value * $multiplier );
			}
			
			AAAG_Job::insert( array(
				'campaign_id'       => $campaign_id,
				'title'             => $title,
				'template_id'       => 0,
				'knowledge_base_id' => 0,
				'post_type'         => $post_type,
				'post_status'       => $post_status,
				'author_id'         => $author_id,
				'min_words'         => $min_words,
				'max_words'         => $max_words,
				'schedule_time'     => $job_schedule_time,
			) );
			$jobs_added++;
		}
		
		// Redirect to avoid duplicate submission on refresh using JS (replace URL to aaag-campaigns)
		echo '<script type="text/javascript">window.location.replace("' . admin_url('admin.php?page=aaag-campaigns&msg=created&count=' . $jobs_added) . '");</script>';
		exit;
	}
}

// Display success message if redirected
if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'created' ) {
	$count = isset($_GET['count']) ? intval($_GET['count']) : 0;
	echo '<div class="notice notice-success"><p>' . $count . ' Judul berhasil dimasukkan ke dalam Campaign.</p></div>';
}

// Fetch post types that have a UI
$raw_post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
$post_types = array();
$excluded_types = array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'aaag_template', 'aaag_kb' );
foreach ( $raw_post_types as $pt ) {
	if ( ! in_array( $pt->name, $excluded_types ) ) {
		$post_types[] = $pt;
	}
}

$default_prompt = "Tulislah artikel SEO yang sangat lengkap, mendalam, dan menarik tentang: {{title}}.\n\nPanjang artikel harus di antara {{min_words}} hingga {{max_words}} kata.\n\nGunakan format HTML (H2, H3, ul, li). Gunakan bahasa Indonesia yang luwes dan natural.\n\nFokuskan pada intent pembaca dan berikan solusi nyata.";

?>
<div class="wrap aaag-wrap">
	<h1>Buat Campaign Artikel</h1>
	<p class="description">Semua pengaturan untuk satu grup antrean (Campaign) diatur di halaman ini.</p>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'aaag_generate_action', 'aaag_generate_nonce' ); ?>
		
		<div class="aaag-dashboard-grid">
			<!-- Left Column: Main Settings (Card) -->
			<div class="aaag-main-card">
				<div class="aaag-card-header">
					<h2><span class="dashicons dashicons-admin-generic"></span> Pengaturan Campaign & AI</h2>
				</div>
				<div class="aaag-card-body">
					<div class="aaag-form-row" style="margin-bottom: 24px;">
						<div class="aaag-form-col">
							<label for="campaign_name" class="aaag-label">Nama Campaign</label>
							<input type="text" name="campaign_name" id="campaign_name" class="regular-text aaag-input-full" required placeholder="Contoh: Batch Artikel Wisata Bali">
							<p class="aaag-help-text">Gunakan nama yang mudah dikenali untuk mengelompokkan artikel Anda.</p>
						</div>
						<div class="aaag-form-col">
							<label for="ai_model" class="aaag-label">Model AI Utama</label>
							<select name="ai_model" id="ai_model" class="aaag-select-full">
								<?php
								$anthropic_verified = get_option( 'aaag_verified_anthropic_models', array() );
								$openai_verified    = get_option( 'aaag_verified_openai_models', array() );
								$gemini_verified    = get_option( 'aaag_verified_gemini_models', array() );
								$current_model      = 'anthropic:claude-3-5-haiku-20241022';
								
								$anthropic_names = array(
									'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet (Model Flagship Terbaru Anthropic)',
									'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet / Sonnet 5 (Paling Populer & Cerdas)',
									'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku / Haiku 4.5 (Tercepat, Biaya Terendah, Volume Tinggi)',
									'claude-3-haiku-20240307'    => 'Claude 3 Haiku (Ekonomis & Ringan)',
									'claude-3-opus-20240229'     => 'Claude 3 Opus (Riset Mendalam)',
								);

								$openai_names = array(
									'gpt-4.5-preview'    => 'GPT-4.5 Preview (Model Terbesar OpenAI)',
									'gpt-4o'             => 'GPT-4o (Sangat Pintar & Komprehensif)',
									'gpt-4o-mini'        => 'GPT-4o Mini (Sangat Cepat & Murah)',
									'chatgpt-4o-latest'  => 'ChatGPT-4o Latest (Versi Terbaru ChatGPT)',
									'o3-mini'            => 'o3-mini (Model Penalaran / Reasoning Cepat)',
									'o1-mini'            => 'o1-mini (Penalaran Logis)',
									'o1'                 => 'o1 (Penalaran Mendalam / Deep Thinking)'
								);

								$gemini_names = array(
									'gemini-2.5-flash'      => 'Gemini 2.5 Flash (Generasi Terbaru & Sangat Cepat)',
									'gemini-2.5-pro'        => 'Gemini 2.5 Pro (Sangat Pintar & Analitis)',
									'gemini-2.0-flash'      => 'Gemini 2.0 Flash (Cepat & Stabil)',
									'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (Super Hemat & Ringan)',
									'gemini-1.5-flash'      => 'Gemini 1.5 Flash (Stabil & Teruji)',
									'gemini-1.5-pro'        => 'Gemini 1.5 Pro (Konteks Panjang)'
								);

								$has_any = false;
								$anthropic_key = get_option( 'aaag_api_key' );
								$openai_key    = get_option( 'aaag_openai_api_key' );
								$gemini_key    = get_option( 'aaag_gemini_api_key' );
								
								if ( ! empty( $anthropic_verified ) && ! empty( $anthropic_key ) ) :
									$has_any = true;
								?>
								<optgroup label="Anthropic (Claude)">
									<?php foreach ( $anthropic_verified as $model ) : 
										$label = isset($anthropic_names[$model]) ? $anthropic_names[$model] : $model;
										$val = "anthropic:" . $model;
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected($current_model, $val); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php 
								endif;
								
								if ( ! empty( $openai_verified ) && ! empty( $openai_key ) ) :
									$has_any = true;
								?>
								<optgroup label="OpenAI (ChatGPT)">
									<?php foreach ( $openai_verified as $model ) : 
										$label = isset($openai_names[$model]) ? $openai_names[$model] : $model;
										$val = "openai:" . $model;
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected($current_model, $val); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php 
								endif;
								
								if ( ! empty( $gemini_verified ) && ! empty( $gemini_key ) ) :
									$has_any = true;
								?>
								<optgroup label="Google Gemini">
									<?php foreach ( $gemini_verified as $model ) : 
										$label = isset($gemini_names[$model]) ? $gemini_names[$model] : $model;
										$val = "gemini:" . $model;
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected($current_model, $val); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php 
								endif;
								
								if ( ! $has_any ) :
								?>
								<option value="">-- Silakan isi API Key & jalankan "Test Connection" di menu Settings --</option>
								<?php endif; ?>
							</select>
							<p class="aaag-help-text">Model AI yang akan menulis artikel.</p>
						</div>
					</div>

					<div class="aaag-form-row" style="margin-bottom: 24px;">
						<div class="aaag-form-col">
							<label for="language" class="aaag-label">Bahasa Artikel</label>
							<select name="language" id="language" class="aaag-select-full">
								<option value="id" selected>🇮🇩 Bahasa Indonesia</option>
								<option value="en">🇬🇧 English</option>
								<option value="ms">🇲🇾 Bahasa Melayu</option>
								<option value="es">🇪🇸 Spanish (Español)</option>
								<option value="de">🇩🇪 German (Deutsch)</option>
								<option value="fr">🇫🇷 French (Français)</option>
								<option value="ar">🇸🇦 Arabic (العربية)</option>
								<option value="ja">🇯🇵 Japanese (日本語)</option>
							</select>
							<p class="aaag-help-text">Bahasa utama yang digunakan artikel.</p>
						</div>
						<div class="aaag-form-col">
							<label for="tone" class="aaag-label">Gaya Penulisan (Tone)</label>
							<select name="tone" id="tone" class="aaag-select-full">
								<option value="informative" selected>Informatif & Edukatif</option>
								<option value="casual">Kasual & Ramah (Mengobrol)</option>
								<option value="professional">Profesional & Berwibawa</option>
								<option value="journalistic">Jurnalistik & Berita</option>
								<option value="storytelling">Storytelling & Naratif</option>
								<option value="persuasive">Persuasif & Copywriting Promosi</option>
							</select>
							<p class="aaag-help-text">Nada dan karakter penyampaian tulisan.</p>
						</div>
						<div class="aaag-form-col">
							<label for="pov" class="aaag-label">Gaya Sapaan Pembaca</label>
							<select name="pov" id="pov" class="aaag-select-full">
								<option value="second_person" selected>Menyapa Pembaca ("Anda" / "Kamu")</option>
								<option value="first_person">Sudut Pandang Penulis ("Saya" / "Kami")</option>
								<option value="third_person">Netral & Objektif (Formal / Berita)</option>
							</select>
							<p class="aaag-help-text">Cara penulis menyapa audiens di artikel.</p>
						</div>
					</div>

					<!-- 3. AI Prompt Konten Artikel (Emerald Card) -->
					<div class="aaag-form-group" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--aaag-radius-md); padding: 20px; margin-bottom: 24px;">
						<label for="prompt" class="aaag-label" style="color: #166534; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 6px;">
							<span>📝 1. AI Prompt Konten Artikel (Instruksi Isi Postingan)</span>
						</label>
						<p class="aaag-help-text" style="color: #15803d; margin-top: 2px; margin-bottom: 12px;">
							Prompt ini digunakan AI untuk menulis <strong>keseluruhan isi artikel (body text)</strong>, struktur sub-heading (H2, H3), format list, dan pembahasan lengkap.
						</p>
						<textarea name="prompt" id="prompt" rows="7" class="large-text aaag-textarea-full" style="background: #ffffff; font-size: 13px;" required><?php echo esc_textarea( $default_prompt ); ?></textarea>
						
						<div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
							<p class="aaag-help-text" style="margin: 0; color: #166534;">Klik variabel untuk menyisipkan: 
								<button type="button" class="button button-small aaag-chip-var" data-target="prompt" data-var="{{title}}">{{title}}</button>
								<button type="button" class="button button-small aaag-chip-var" data-target="prompt" data-var="{{min_words}}">{{min_words}}</button>
								<button type="button" class="button button-small aaag-chip-var" data-target="prompt" data-var="{{max_words}}">{{max_words}}</button>
								<button type="button" class="button button-small aaag-chip-var" data-target="prompt" data-var="{{site_name}}">{{site_name}}</button>
								<button type="button" class="button button-small aaag-chip-var" data-target="prompt" data-var="{{current_year}}">{{current_year}}</button>
							</p>
							<span style="font-size: 11px; color: #15803d; font-weight: 600;">Wajib: {{title}}, {{min_words}}, {{max_words}}</span>
						</div>
					</div>

					<!-- 4. Knowledge Base (Slate Card) -->
					<div class="aaag-form-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--aaag-radius-md); padding: 20px; margin-bottom: 24px;">
						<label for="knowledge_base" class="aaag-label" style="font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
							<span>📚 Knowledge Base / Referensi Tambahan (Opsional)</span>
						</label>
						<p class="aaag-help-text" style="margin-top: 2px; margin-bottom: 12px; color: #64748b;">
							AI akan membaca teks ini sebagai referensi mutlak saat menulis seluruh artikel dalam Campaign ini.
						</p>
						<textarea name="knowledge_base" id="knowledge_base" rows="4" class="large-text aaag-textarea-full" style="background: #ffffff; font-size: 13px;" placeholder="Masukkan referensi data, spesifikasi harga, aturan khusus, atau fakta yang wajib dimuat AI..."></textarea>
					</div>

					<!-- SEO Metadata Integration Box (Sky Card) -->
					<?php
					$default_seo_title_prompt = "Buat Meta Title SEO yang memikat klik (CTR tinggi), mengandung keyword utama {{title}}, dan dibatasi 50-60 karakter.";
					$default_seo_desc_prompt  = "Buat Meta Deskripsi persuasif (120-155 karakter) yang merangkum solusi artikel {{title}} di {{site_name}} diakhiri Call to Action.";
					?>
					<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: var(--aaag-radius-md); padding: 20px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
							<label style="font-weight: 700; color: #0f172a; font-size: 14px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="generate_seo_meta" id="generate_seo_meta" value="1" checked style="margin: 0; width: 18px; height: 18px;">
								<span>🚀 2. AI Prompt & Optimasi SEO (Meta Title & Meta Deskripsi)</span>
							</label>
						</div>
						<p class="aaag-help-text" style="margin-top: 0; margin-bottom: 15px; color: #475569;">
							Otomatis menyuntikkan Meta Title, Meta Description, dan Focus Keyword ke <strong>Rank Math, Yoast SEO, All in One SEO, SEOPress,</strong> & <strong>The SEO Framework</strong>.
						</p>

						<div id="seo_settings_fields" style="display: block; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
							
							<!-- Meta Title Section -->
							<div class="aaag-form-group" style="margin-bottom: 20px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
									<label for="seo_title_prompt" class="aaag-label" style="font-size: 13px; font-weight: 600; color: #1e293b; margin: 0;">
										🎯 AI Prompt Khusus Meta Title (Judul Google)
									</label>
									<!-- Quick Presets for Title -->
									<div style="display: flex; gap: 4px; align-items: center;">
										<span style="font-size: 11px; color: #64748b; margin-right: 4px;">Pilih Template:</span>
										<button type="button" class="button button-small aaag-preset-btn" data-target="seo_title_prompt" data-text="Buat Meta Title SEO yang memikat klik (CTR tinggi), mengandung keyword utama {{title}}, dan dibatasi 50-60 karakter.">⚡ CTR Booster</button>
										<button type="button" class="button button-small aaag-preset-btn" data-target="seo_title_prompt" data-text="Buat Meta Title bentuk pertanyaan/trik menarik tentang {{title}} disertai tahun {{current_year}} (Maksimal 58 karakter).">📈 Power Words + <?php echo current_time('Y'); ?></button>
										<button type="button" class="button button-small aaag-preset-btn" data-target="seo_title_prompt" data-text="Format judul standar bersih: {{title}} | {{site_name}} (Panjang 50-60 karakter).">🏷️ Standar</button>
									</div>
								</div>

								<textarea name="seo_title_prompt" id="seo_title_prompt" rows="2" class="aaag-textarea-full" style="background: #ffffff;"><?php echo esc_textarea( $default_seo_title_prompt ); ?></textarea>
								
								<div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
									<p class="aaag-help-text" style="margin: 0;">Klik variabel untuk menyisipkan: 
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_title_prompt" data-var="{{title}}">{{title}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_title_prompt" data-var="{{site_name}}">{{site_name}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_title_prompt" data-var="{{current_year}}">{{current_year}}</button>
									</p>
									<span style="font-size: 11px; color: #0284c7; font-weight: 600;">📏 Batas Google: 50–60 Karakter</span>
								</div>
							</div>

							<!-- Meta Description Section -->
							<div class="aaag-form-group" style="margin-bottom: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
									<label for="seo_desc_prompt" class="aaag-label" style="font-size: 13px; font-weight: 600; color: #1e293b; margin: 0;">
										📄 AI Prompt Khusus Meta Description (Deskripsi Cuplikan Google)
									</label>
									<!-- Quick Presets for Description -->
									<div style="display: flex; gap: 4px; align-items: center;">
										<span style="font-size: 11px; color: #64748b; margin-right: 4px;">Pilih Template:</span>
										<button type="button" class="button button-small aaag-preset-btn" data-target="seo_desc_prompt" data-text="Buat Meta Deskripsi persuasif (120-155 karakter) yang merangkum solusi artikel {{title}} di {{site_name}} diakhiri Call to Action.">🎯 Hook + Solusi + CTA</button>
										<button type="button" class="button button-small aaag-preset-btn" data-target="seo_desc_prompt" data-text="Sebutkan masalah utama pembaca tentang {{title}}, tawarkan manfaat terbaik dari artikel ini, dan ajak untuk membaca sekarang (120-155 karakter).">💡 Problem + Benefit</button>
									</div>
								</div>

								<textarea name="seo_desc_prompt" id="seo_desc_prompt" rows="2" class="aaag-textarea-full" style="background: #ffffff;"><?php echo esc_textarea( $default_seo_desc_prompt ); ?></textarea>
								
								<div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
									<p class="aaag-help-text" style="margin: 0;">Klik variabel untuk menyisipkan: 
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{title}}">{{title}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{site_name}}">{{site_name}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{current_year}}">{{current_year}}</button>
									</p>
									<span style="font-size: 11px; color: #0284c7; font-weight: 600;">📏 Batas Google: 120–155 Karakter</span>
								</div>
							</div>

						</div>
					</div>
				</div>
			</div>

			<!-- Right Column: Settings & Actions (Card) -->
			<div class="aaag-sidebar-card">
				<div class="aaag-card-header">
					<h2><span class="dashicons dashicons-admin-post"></span> Format Post & Jadwal</h2>
				</div>
				<div class="aaag-card-body">
					<div class="aaag-form-row" style="margin-bottom: 20px;">
						<div class="aaag-form-col">
							<label for="post_type" class="aaag-label">Post Type Tujuan</label>
							<select name="post_type" id="post_type" class="aaag-select-full">
								<?php foreach ( $post_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="aaag-form-col">
							<label for="author_id" class="aaag-label">Penulis (Author)</label>
							<select name="author_id" id="author_id" class="aaag-select-full">
								<?php
								$wp_authors = get_users( array( 'who' => 'authors', 'fields' => array( 'ID', 'display_name' ) ) );
								if ( empty( $wp_authors ) ) {
									$wp_authors = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
								}
								$current_uid = get_current_user_id();
								foreach ( $wp_authors as $u ) :
								?>
									<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $current_uid, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="aaag-form-row" style="margin-bottom: 20px;">
						<div class="aaag-form-col">
							<label for="post_status" class="aaag-label">Status Artikel</label>
							<select name="post_status" id="post_status" class="aaag-select-full">
								<option value="draft">Draft</option>
								<option value="pending">Pending</option>
								<option value="publish">Publish</option>
								<option value="future">Schedule (Posting Terjadwal)</option>
							</select>
						</div>
					</div>

					<div class="aaag-form-row" style="margin-bottom: 20px;">
						<div class="aaag-form-col">
							<label for="min_words" class="aaag-label">Min Kata</label>
							<input type="number" name="min_words" id="min_words" value="500" min="100" class="aaag-input-full">
						</div>
						<div class="aaag-form-col">
							<label for="max_words" class="aaag-label">Max Kata</label>
							<input type="number" name="max_words" id="max_words" value="1000" min="100" class="aaag-input-full">
						</div>
					</div>

					<div id="schedule_options" class="aaag-schedule-box" style="display:none; margin-bottom: 20px;">
						<div class="aaag-form-group" style="margin-bottom: 16px;">
							<label for="schedule_mode" class="aaag-label">Metode Penjadwalan</label>
							<select name="schedule_mode" id="schedule_mode" class="aaag-select-full">
								<option value="daily">1 Artikel Sehari (Jam Diacak)</option>
								<option value="interval">Berdasarkan Jarak Waktu (Interval)</option>
							</select>
						</div>

						<div class="aaag-form-group" style="margin-bottom: 16px;">
							<label for="schedule_date" class="aaag-label">Mulai Tanggal / Posting Pertama</label>
							<input type="datetime-local" name="schedule_date" id="schedule_date" class="aaag-input-full" style="width: 100%; box-sizing: border-box;">
						</div>
						
						<div id="wrap_schedule_daily">
							<div class="aaag-form-row">
								<div class="aaag-form-col">
									<label class="aaag-label">Min Jam (24h)</label>
									<input type="number" name="daily_min" value="12" min="0" max="23" class="aaag-input-full">
								</div>
								<div class="aaag-form-col">
									<label class="aaag-label">Max Jam (24h)</label>
									<input type="number" name="daily_max" value="14" min="0" max="23" class="aaag-input-full">
								</div>
							</div>
							<p class="aaag-help-text">Sistem memposting 1 artikel per hari, jam diacak dalam rentang di atas.</p>
						</div>

						<div id="wrap_schedule_interval" style="display:none;">
							<div class="aaag-form-row" style="margin-bottom: 12px;">
								<div class="aaag-form-col">
									<label class="aaag-label">Min Jarak</label>
									<input type="number" name="min_gap" value="2" min="1" class="aaag-input-full">
								</div>
								<div class="aaag-form-col">
									<label class="aaag-label">Max Jarak</label>
									<input type="number" name="max_gap" value="6" min="1" class="aaag-input-full">
								</div>
							</div>
							<div class="aaag-form-group">
								<label class="aaag-label">Satuan Jarak</label>
								<select name="gap_unit" class="aaag-select-full">
									<option value="hours">Jam</option>
									<option value="minutes">Menit</option>
								</select>
							</div>
							<p class="aaag-help-text">Jarak antar posting akan diacak dalam rentang ini.</p>
						</div>
					</div>

					<div class="aaag-form-group" style="margin-top: 25px;">
						<label for="titles" class="aaag-label">Daftar Judul Artikel</label>
						<textarea name="titles" id="titles" rows="8" class="large-text aaag-textarea-full" required placeholder="Masukkan satu judul per baris..."></textarea>
						<p class="aaag-help-text">Setiap baris akan menjadi 1 artikel dalam Campaign ini.</p>
						<div id="token_estimation" style="margin-top: 10px; font-weight: bold; color: #0073aa;">Estimasi Token per Artikel: 0 token</div>
					</div>

					<div class="aaag-submit-box" style="margin-top: 30px;">
						<input type="submit" name="aaag_generate_submit" class="button button-primary aaag-btn-submit-full" value="Buat Campaign & Mulai Antrean">
					</div>
				</div>
			</div>
	</form>

	<script>
	jQuery(document).ready(function($) {
		$('#generate_seo_meta').on('change', function() {
			if ($(this).is(':checked')) {
				$('#seo_settings_fields').slideDown(200);
			} else {
				$('#seo_settings_fields').slideUp(200);
			}
		});
	});
	</script>
</div>
