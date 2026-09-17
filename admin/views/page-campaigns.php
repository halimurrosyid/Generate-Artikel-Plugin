<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'ai-auto-article-generator' ) );
}

// Display success message if redirected from campaign creation
if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'created' ) {
	$count = isset($_GET['count']) ? intval($_GET['count']) : 0;
	echo '<div class="notice notice-success"><p>' . $count . ' Judul berhasil dimasukkan ke dalam Campaign.</p></div>';
}

// Handle Actions (Pause, Resume, Delete)
if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && $_GET['action'] !== 'edit' ) {
	$id = absint( $_GET['id'] );
	if ( check_admin_referer( 'campaign_action_' . $id ) ) {
		if ( $_GET['action'] == 'pause' ) {
			AAAG_Campaign::update_status( $id, 'paused' );
			echo '<div class="notice notice-success"><p>Campaign berhasil di-pause.</p></div>';
		} elseif ( $_GET['action'] == 'resume' ) {
			AAAG_Campaign::update_status( $id, 'active' );
			echo '<div class="notice notice-success"><p>Campaign berhasil dilanjutkan (Resume).</p></div>';
		} elseif ( $_GET['action'] == 'delete' ) {
			AAAG_Campaign::delete( $id );
			echo '<div class="notice notice-success"><p>Campaign dan semua antrean di dalamnya berhasil dihapus.</p></div>';
		}
	}
}

// Handle Update Form Submission
if ( isset( $_POST['aaag_campaign_edit_submit'] ) && check_admin_referer( 'campaign_edit_action', 'campaign_edit_nonce' ) ) {
	$id = absint( $_POST['campaign_id'] );
	$author_id = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : 1;
	$generate_seo_meta = isset( $_POST['generate_seo_meta'] ) ? 1 : 0;
	$seo_title_style   = isset( $_POST['seo_title_style'] ) ? sanitize_text_field( $_POST['seo_title_style'] ) : 'dynamic_ctr';
	$seo_title_prompt  = isset( $_POST['seo_title_prompt'] ) ? wp_unslash( $_POST['seo_title_prompt'] ) : '';
	$url_slug_style    = isset( $_POST['url_slug_style'] ) ? sanitize_text_field( $_POST['url_slug_style'] ) : 'default';
	$seo_desc_prompt   = isset( $_POST['seo_desc_prompt'] ) ? wp_unslash( $_POST['seo_desc_prompt'] ) : '';
	$update_data = array(
		'name'              => sanitize_text_field( $_POST['campaign_name'] ),
		'prompt'            => wp_unslash( $_POST['prompt'] ),
		'knowledge_base'    => wp_unslash( $_POST['knowledge_base'] ),
		'ai_model'          => sanitize_text_field( $_POST['ai_model'] ),
		'language'          => isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : 'id',
		'tone'              => isset( $_POST['tone'] ) ? sanitize_text_field( $_POST['tone'] ) : 'informative',
		'pov'               => isset( $_POST['pov'] ) ? sanitize_text_field( $_POST['pov'] ) : 'second_person',
		'author_id'         => $author_id,
		'generate_seo_meta' => $generate_seo_meta,
		'seo_title_style'   => $seo_title_style,
		'seo_title_prompt'  => $seo_title_prompt,
		'url_slug_style'    => $url_slug_style,
		'seo_desc_prompt'   => $seo_desc_prompt
	);
	AAAG_Campaign::update( $id, $update_data );
	
	// Update author on pending/failed jobs
	global $wpdb;
	$jobs_table = AAAG_DB::get_table_name('jobs');
	$wpdb->update( $jobs_table, array( 'author_id' => $author_id ), array( 'campaign_id' => $id, 'status' => 'pending' ) );
	
	$msg = '<p>Campaign berhasil diperbarui. Job selanjutnya akan menggunakan prompt, persona, author, & referensi yang baru.</p>';
	
	// Handle Reschedule
	if ( isset($_POST['reschedule_jobs']) && $_POST['reschedule_jobs'] == '1' ) {
		$schedule_date     = isset( $_POST['schedule_date'] ) ? sanitize_text_field( $_POST['schedule_date'] ) : '';
		$schedule_mode     = isset( $_POST['schedule_mode'] ) ? sanitize_text_field( $_POST['schedule_mode'] ) : 'interval';
		$min_gap           = isset( $_POST['min_gap'] ) ? absint( $_POST['min_gap'] ) : 2;
		$max_gap           = isset( $_POST['max_gap'] ) ? absint( $_POST['max_gap'] ) : 6;
		$gap_unit          = isset( $_POST['gap_unit'] ) ? sanitize_text_field( $_POST['gap_unit'] ) : 'hours';
		$daily_min         = isset( $_POST['daily_min'] ) ? absint( $_POST['daily_min'] ) : 12;
		$daily_max         = isset( $_POST['daily_max'] ) ? absint( $_POST['daily_max'] ) : 14;

		$updated_count = AAAG_Job::reschedule_campaign_jobs( $id, $schedule_date, $schedule_mode, $min_gap, $max_gap, $gap_unit, $daily_min, $daily_max );
		$msg .= '<p><strong>' . intval( $updated_count ) . ' Job berhasil diatur ulang jadwalnya!</strong></p>';
	}
	
	echo '<div class="notice notice-success">' . $msg . '</div>';
}

$is_editing = isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] );

if ( $is_editing ) {
	$edit_id = absint( $_GET['id'] );
	$edit_camp = AAAG_Campaign::get( $edit_id );
	if ( ! $edit_camp ) {
		echo '<div class="notice notice-error"><p>Campaign tidak ditemukan.</p></div>';
		$is_editing = false;
	}
}

if ( ! $is_editing ) :
$campaigns = AAAG_Campaign::get_all();
?>
<div class="wrap aaag-wrap">
	<h1>Daftar Campaign</h1>
	<p>Kelola *batch* (grup) pembuatan artikel Anda di sini.</p>
	
	<!-- Dashboard Cards -->
	<div class="aaag-dashboard-cards">
		<?php
		global $wpdb;
		$campaigns_table = AAAG_DB::get_table_name('campaigns');
		$jobs_table = AAAG_DB::get_table_name('jobs');
		
		$total_active = $wpdb->get_var("SELECT COUNT(id) FROM $campaigns_table WHERE status = 'active'");
		$total_articles = $wpdb->get_var("SELECT COUNT(id) FROM $jobs_table WHERE status = 'completed'");
		?>
		<div class="aaag-card">
			<div class="aaag-card-icon"><span class="dashicons dashicons-networking"></span></div>
			<div class="aaag-card-content">
				<h3>Campaign Aktif</h3>
				<p class="aaag-value"><?php echo (int) $total_active; ?></p>
			</div>
		</div>
		<div class="aaag-card">
			<div class="aaag-card-icon"><span class="dashicons dashicons-welcome-write-blog"></span></div>
			<div class="aaag-card-content">
				<h3>Artikel Dibuat</h3>
				<p class="aaag-value"><?php echo (int) $total_articles; ?></p>
			</div>
		</div>
		<div class="aaag-card">
			<div class="aaag-card-icon"><span class="dashicons dashicons-cloud"></span></div>
			<div class="aaag-card-content">
				<h3>Status Mesin (Cron)</h3>
				<p class="aaag-value" style="color: #46b450; font-size: 20px; padding-top: 5px;">Online & Ready</p>
			</div>
		</div>
	</div>
	<!-- End Dashboard Cards -->
	
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>ID</th>
				<th>Nama Campaign</th>
				<th>Status</th>
				<th>Progres</th>
				<th>Tanggal Dibuat</th>
				<th>Aksi</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $campaigns ) ) : ?>
				<tr><td colspan="6">Tidak ada Campaign.</td></tr>
			<?php else : ?>
				<?php foreach ( $campaigns as $camp ) : 
					$stats = AAAG_Campaign::get_stats( $camp->id );
					$status_badge = ($camp->status == 'active') ? 'status-active' : (($camp->status == 'paused') ? 'status-paused' : 'status-draft');
					?>
					<tr>
						<td><?php echo esc_html( $camp->id ); ?></td>
						<td>
							<strong style="font-size:15px; color:#1e293b;"><?php echo esc_html( $camp->name ); ?></strong><br>
							<a href="<?php echo admin_url('admin.php?page=aaag-jobs&campaign_id=' . $camp->id); ?>" style="font-size:12px; text-decoration:none;"><span class="dashicons dashicons-list-view" style="font-size:14px; width:14px; height:14px;"></span> Lihat Detail Jobs</a>
						</td>
						<td><span class="aaag-badge <?php echo $status_badge; ?>"><?php echo esc_html( strtoupper( $camp->status ) ); ?></span></td>
						<td><?php echo $stats['completed'] . ' / ' . $stats['total']; ?> Jobs</td>
						<td><?php echo esc_html( $camp->created_at ); ?></td>
						<td>
							<?php if ( $camp->status == 'active' ) : ?>
								<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-campaigns&action=pause&id=' . $camp->id), 'campaign_action_' . $camp->id ); ?>" class="button"><span class="dashicons dashicons-controls-pause"></span> Pause</a>
							<?php else : ?>
								<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-campaigns&action=resume&id=' . $camp->id), 'campaign_action_' . $camp->id ); ?>" class="button button-primary"><span class="dashicons dashicons-controls-play"></span> Resume</a>
							<?php endif; ?>
							
							<a href="<?php echo admin_url('admin.php?page=aaag-campaigns&action=edit&id=' . $camp->id); ?>" class="button"><span class="dashicons dashicons-edit"></span> Edit</a>
							
							<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-campaigns&action=delete&id=' . $camp->id), 'campaign_action_' . $camp->id ); ?>" class="button button-link-delete aaag-delete-campaign" data-name="<?php echo esc_attr( $camp->name ); ?>"><span class="dashicons dashicons-trash"></span> Hapus</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<?php else : ?>
<div class="wrap aaag-wrap">
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; background: #ffffff; padding: 20px 24px; border-radius: var(--aaag-radius-lg); border: 1px solid var(--aaag-border); box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
		<div>
			<h1 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 24px;">
				<span>Edit Campaign: <?php echo esc_html( $edit_camp->name ); ?></span>
				<span class="aaag-badge status-<?php echo ( $edit_camp->status == 'active' ) ? 'active' : 'paused'; ?>" style="font-size: 11px;">
					<?php echo ( $edit_camp->status == 'active' ) ? '🟢 Aktif' : '⏸️ Dijeda'; ?>
				</span>
			</h1>
			<p class="description" style="margin-top: 4px; margin-bottom: 0;">Mengubah instruksi atau referensi akan mempengaruhi semua antrean yang belum dijalankan (Pending) di Campaign ini.</p>
		</div>
		<div>
			<a href="<?php echo admin_url('admin.php?page=aaag-campaigns'); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px !important; font-weight: 600;">
				<span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; margin: 0;"></span> Kembali ke Daftar
			</a>
		</div>
	</div>
	
	<form method="post" action="<?php echo admin_url('admin.php?page=aaag-campaigns'); ?>">
		<?php wp_nonce_field( 'campaign_edit_action', 'campaign_edit_nonce' ); ?>
		<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $edit_camp->id ); ?>">
		
		<div class="aaag-dashboard-grid">
			<!-- Left Column: Main Settings (Card) -->
			<div class="aaag-main-card">
				<div class="aaag-card-header">
					<h2><span class="dashicons dashicons-admin-generic"></span> Konfigurasi Campaign & Otak AI</h2>
				</div>
				<div class="aaag-card-body">
					
					<!-- 1. Identitas & Model AI -->
					<div class="aaag-form-row" style="margin-bottom: 20px;">
						<div class="aaag-form-col">
							<label for="campaign_name" class="aaag-label">Nama Campaign</label>
							<input type="text" name="campaign_name" id="campaign_name" class="aaag-input-full" required value="<?php echo esc_attr( $edit_camp->name ); ?>">
						</div>
						<div class="aaag-form-col">
							<label for="ai_model" class="aaag-label">Model AI</label>
							<?php 
							$current_model = isset($edit_camp->ai_model) && !empty($edit_camp->ai_model) ? $edit_camp->ai_model : 'anthropic:claude-haiku-4-5';
							?>
							<select name="ai_model" id="ai_model" class="aaag-select-full">
								<?php
								$anthropic_connected = get_option( 'aaag_anthropic_connected', 0 );
								$openai_connected    = get_option( 'aaag_openai_connected', 0 );
								$gemini_connected    = get_option( 'aaag_gemini_connected', 0 );
								
								$anthropic_names = array(
									'claude-haiku-4-5'           => 'Claude 4.5 Haiku (Sangat Cepat & Murah)',
									'claude-sonnet-4-6'          => 'Claude 4.6 Sonnet (Terbaru & Pintar)',
									'claude-fable-5'             => 'Claude 5 Fable (Premium)',
									'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet (Flagship 2026)',
									'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
									'claude-3-haiku-20240307'    => 'Claude 3 Haiku (Ekonomis)',
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
								
								if ( ! empty( get_option( 'aaag_api_key' ) ) ) :
									$has_any = true;
								?>
								<optgroup label="Anthropic (Claude)">
									<?php foreach ( $anthropic_names as $model => $label ) :
										$val = "anthropic:" . $model;
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected($current_model, $val); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php 
								endif;
								
								if ( ! empty( get_option( 'aaag_openai_api_key' ) ) ) :
									$has_any = true;
								?>
								<optgroup label="OpenAI (ChatGPT)">
									<?php foreach ( $openai_names as $model => $label ) :
										$val = "openai:" . $model;
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected($current_model, $val); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php 
								endif;
								
								if ( ! empty( get_option( 'aaag_gemini_api_key' ) ) ) :
									$has_any = true;
								?>
								<optgroup label="Google Gemini">
									<?php foreach ( $gemini_names as $model => $label ) :
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
							<p class="aaag-help-text">Model AI yang digunakan untuk campaign ini.</p>
						</div>
					</div>

					<!-- 2. Persona & Bahasa -->
					<div class="aaag-form-row" style="margin-bottom: 24px;">
						<div class="aaag-form-col">
							<label for="language" class="aaag-label">Bahasa Artikel</label>
							<?php $curr_lang = isset($edit_camp->language) ? $edit_camp->language : 'id'; ?>
							<select name="language" id="language" class="aaag-select-full">
								<option value="id" <?php selected($curr_lang, 'id'); ?>>🇮🇩 Bahasa Indonesia</option>
								<option value="en" <?php selected($curr_lang, 'en'); ?>>🇬🇧 English</option>
								<option value="ms" <?php selected($curr_lang, 'ms'); ?>>🇲🇾 Bahasa Melayu</option>
								<option value="es" <?php selected($curr_lang, 'es'); ?>>🇪🇸 Spanish (Español)</option>
								<option value="de" <?php selected($curr_lang, 'de'); ?>>🇩🇪 German (Deutsch)</option>
								<option value="fr" <?php selected($curr_lang, 'fr'); ?>>🇫🇷 French (Français)</option>
								<option value="ar" <?php selected($curr_lang, 'ar'); ?>>🇸🇦 Arabic (العربية)</option>
								<option value="ja" <?php selected($curr_lang, 'ja'); ?>>🇯🇵 Japanese (日本語)</option>
							</select>
							<p class="aaag-help-text">Bahasa utama yang digunakan artikel.</p>
						</div>
						<div class="aaag-form-col">
							<label for="tone" class="aaag-label">Gaya Penulisan (Tone)</label>
							<?php $curr_tone = isset($edit_camp->tone) ? $edit_camp->tone : 'informative'; ?>
							<select name="tone" id="tone" class="aaag-select-full">
								<option value="informative" <?php selected($curr_tone, 'informative'); ?>>Informatif & Edukatif</option>
								<option value="casual" <?php selected($curr_tone, 'casual'); ?>>Kasual & Ramah (Mengobrol)</option>
								<option value="professional" <?php selected($curr_tone, 'professional'); ?>>Profesional & Berwibawa</option>
								<option value="journalistic" <?php selected($curr_tone, 'journalistic'); ?>>Jurnalistik & Berita</option>
								<option value="storytelling" <?php selected($curr_tone, 'storytelling'); ?>>Storytelling & Naratif</option>
								<option value="persuasive" <?php selected($curr_tone, 'persuasive'); ?>>Persuasif & Copywriting Promosi</option>
							</select>
							<p class="aaag-help-text">Nada dan karakter penyampaian tulisan.</p>
						</div>
						<div class="aaag-form-col">
							<label for="pov" class="aaag-label">Gaya Sapaan Pembaca</label>
							<?php $curr_pov = isset($edit_camp->pov) ? $edit_camp->pov : 'second_person'; ?>
							<select name="pov" id="pov" class="aaag-select-full">
								<option value="second_person" <?php selected($curr_pov, 'second_person'); ?>>Menyapa Pembaca ("Anda" / "Kamu")</option>
								<option value="first_person" <?php selected($curr_pov, 'first_person'); ?>>Sudut Pandang Penulis ("Saya" / "Kami")</option>
								<option value="third_person" <?php selected($curr_pov, 'third_person'); ?>>Netral & Objektif (Formal / Berita)</option>
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
						<textarea name="prompt" id="prompt" rows="8" class="aaag-textarea-full" style="background: #ffffff; font-size: 13px;" required><?php echo esc_textarea( $edit_camp->prompt ); ?></textarea>
						
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
							Teks yang diketik di sini akan dipaksa masuk ke otak AI setiap kali judul dalam Campaign ini diproses.
						</p>
						<textarea name="knowledge_base" id="knowledge_base" rows="5" class="aaag-textarea-full" style="background: #ffffff; font-size: 13px;" placeholder="Masukkan referensi data, spesifikasi harga, aturan khusus, atau fakta yang wajib dimuat AI..."><?php echo esc_textarea( $edit_camp->knowledge_base ); ?></textarea>
					</div>

					<!-- 5. SEO Metadata Integration Box (Sky Card) -->
					<?php 
					$camp_seo_enabled = ( isset($edit_camp->generate_seo_meta) && intval($edit_camp->generate_seo_meta) === 1 ); 
					$default_title_p  = "Buat Meta Title SEO yang memikat klik (CTR tinggi), mengandung keyword utama {{title}}, dan dibatasi 50-60 karakter.";
					$default_desc_p   = "Buat Meta Deskripsi persuasif (120-155 karakter) yang merangkum solusi artikel {{title}} di {{site_name}} diakhiri Call to Action.";
					$camp_title_prompt = ( ! empty($edit_camp->seo_title_prompt) ) ? $edit_camp->seo_title_prompt : $default_title_p;
					$camp_desc_prompt  = ( ! empty($edit_camp->seo_desc_prompt) ) ? $edit_camp->seo_desc_prompt : $default_desc_p;
					?>
					<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: var(--aaag-radius-md); padding: 20px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
							<label style="font-weight: 700; color: #0f172a; font-size: 14px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="generate_seo_meta" id="generate_seo_meta_edit" value="1" <?php checked($camp_seo_enabled, true); ?> style="margin: 0; width: 18px; height: 18px;">
								<span>🚀 2. AI Prompt & Optimasi SEO (Meta Title & Meta Deskripsi)</span>
							</label>
						</div>
						<p class="aaag-help-text" style="margin-top: 0; margin-bottom: 15px; color: #475569;">
							Otomatis menyuntikkan Meta Title, Meta Description, dan Focus Keyword ke <strong>Rank Math, Yoast SEO, All in One SEO, SEOPress,</strong> & <strong>The SEO Framework</strong>.
						</p>

						<div id="seo_settings_fields_edit" style="display: <?php echo $camp_seo_enabled ? 'block' : 'none'; ?>; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
							
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

								<textarea name="seo_title_prompt" id="seo_title_prompt" rows="2" class="aaag-textarea-full" style="background: #ffffff;"><?php echo esc_textarea( $camp_title_prompt ); ?></textarea>
								
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

								<textarea name="seo_desc_prompt" id="seo_desc_prompt" rows="2" class="aaag-textarea-full" style="background: #ffffff;"><?php echo esc_textarea( $camp_desc_prompt ); ?></textarea>
								
								<div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
									<p class="aaag-help-text" style="margin: 0;">Klik variabel untuk menyisipkan: 
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{title}}">{{title}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{site_name}}">{{site_name}}</button>
										<button type="button" class="button button-small aaag-chip-var" data-target="seo_desc_prompt" data-var="{{current_year}}">{{current_year}}</button>
									</p>
									<span style="font-size: 11px; color: #0284c7; font-weight: 600;">📏 Batas Google: 120–155 Karakter</span>
								</div>
							</div>
							
							<!-- URL Slug Section -->
							<?php
							$camp_url_slug = isset( $edit_camp->url_slug_style ) && ! empty( $edit_camp->url_slug_style ) ? $edit_camp->url_slug_style : 'default';
							?>
							<div class="aaag-form-group" style="margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
								<label for="url_slug_style" class="aaag-label" style="font-size: 13px; font-weight: 600; color: #1e293b; margin: 0 0 6px 0; display: block;">
									🔗 Format URL (Slug) Artikel
								</label>
								<select name="url_slug_style" id="url_slug_style" class="aaag-select-full" style="background: #ffffff;">
									<option value="default" <?php selected($camp_url_slug, 'default'); ?>>Default WordPress (Mengikuti Judul Artikel Asli)</option>
									<option value="focus_keyword" <?php selected($camp_url_slug, 'focus_keyword'); ?>>Sesuai Focus Keyword (Sangat SEO Friendly, Padat & Ringkas)</option>
									<option value="meta_title" <?php selected($camp_url_slug, 'meta_title'); ?>>Sesuai Judul Meta SEO (Bervariasi)</option>
									<option value="random" <?php selected($camp_url_slug, 'random'); ?>>Random Alphanumeric (Contoh: x8k2m9)</option>
								</select>
								<p class="aaag-help-text" style="margin-top: 6px;">Tentukan bagaimana URL artikel akan dibentuk agar tidak terlihat seragam atau templated.</p>
							</div>

						</div>
					</div>
				</div>
			</div>

			<!-- Right Column: Reschedule & Actions (Card) -->
			<div class="aaag-sidebar-card">
				<div class="aaag-card-header">
					<h2><span class="dashicons dashicons-admin-post"></span> Penulis & Jadwal</h2>
				</div>
				<div class="aaag-card-body">
					<div class="aaag-form-group" style="margin-bottom: 20px;">
						<label for="author_id" class="aaag-label">Penulis Artikel (Author)</label>
						<select name="author_id" id="author_id" class="aaag-select-full">
							<?php
							$wp_authors = get_users( array( 'who' => 'authors', 'fields' => array( 'ID', 'display_name' ) ) );
							if ( empty( $wp_authors ) ) {
								$wp_authors = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
							}
							$curr_author = isset($edit_camp->author_id) ? absint($edit_camp->author_id) : get_current_user_id();
							foreach ( $wp_authors as $u ) :
							?>
								<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $curr_author, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="aaag-help-text">Penulis yang akan disematkan pada artikel yang belum di-generate di Campaign ini.</p>
					</div>

					<div class="aaag-form-group" style="background:#fff3f2; border: 1px solid #fee2e2; padding: 20px; border-radius: var(--aaag-radius-md);">
						<label style="font-weight:bold; color:#d63638; display:flex; align-items:start; gap: 8px;">
							<input type="checkbox" name="reschedule_jobs" id="reschedule_jobs" value="1" style="margin-top: 3px;"> 
							<span>Ya, saya ingin mengatur ulang jadwal untuk seluruh artikel yang BELUM diterbitkan di Campaign ini.</span>
						</label>
						<p class="aaag-help-text" style="color: #991b1b; margin-top:10px;">Jadwal baru di bawah ini hanya akan aktif jika kotak di atas Anda centang.</p>
					</div>
					
					<div id="wrap_reschedule_options" class="aaag-schedule-box" style="display:none; margin-top:18px;">
						<h3 style="margin-top:0; font-size:14px; border-bottom:1px solid #e2e8f0; padding-bottom:8px; color:var(--aaag-primary); font-weight:700;">Pengaturan Jadwal Baru</h3>
						
						<div class="aaag-form-group" style="margin-bottom: 16px;">
							<label for="schedule_mode" class="aaag-label">Metode Penjadwalan</label>
							<select name="schedule_mode" id="schedule_mode" class="aaag-select-full">
								<option value="daily">1 Artikel Sehari (Jam Diacak)</option>
								<option value="interval">Berdasarkan Jarak Waktu (Interval)</option>
							</select>
						</div>

						<div class="aaag-form-group" style="margin-bottom: 16px;">
							<label for="schedule_date" class="aaag-label">Mulai Tanggal / Posting Pertama</label>
							<input type="datetime-local" name="schedule_date" id="schedule_date" class="aaag-input-full" style="width: 100%; box-sizing: border-box;" required disabled>
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
							<p class="aaag-help-text">Jarak akan diacak dalam rentang di atas.</p>
						</div>
					</div>
					
					<script>
					jQuery(document).ready(function($) {
						$('#reschedule_jobs').on('change', function() {
							if ($(this).is(':checked')) {
								$('#wrap_reschedule_options').show();
								$('#schedule_date').prop('disabled', false);
							} else {
								$('#wrap_reschedule_options').hide();
								$('#schedule_date').prop('disabled', true);
							}
						});
						
						$('#schedule_mode').on('change', function() {
							if ($(this).val() === 'daily') {
								$('#wrap_schedule_daily').show();
								$('#wrap_schedule_interval').hide();
							} else {
								$('#wrap_schedule_daily').hide();
								$('#wrap_schedule_interval').show();
							}
						}).trigger('change');

						$('#generate_seo_meta_edit').on('change', function() {
							if ($(this).is(':checked')) {
								$('#seo_settings_fields_edit').slideDown(200);
							} else {
								$('#seo_settings_fields_edit').slideUp(200);
							}
						});
					});
					</script>
					
					<div class="aaag-submit-box" style="margin-top: 24px;">
						<input type="submit" name="aaag_campaign_edit_submit" class="button button-primary aaag-btn-submit-full" value="💾 Simpan Perubahan Campaign">
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
<?php endif; ?>
