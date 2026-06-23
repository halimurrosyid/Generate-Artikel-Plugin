<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_POST['aaag_generate_submit'] ) && check_admin_referer( 'aaag_generate_action', 'aaag_generate_nonce' ) ) {
	$campaign_name     = isset( $_POST['campaign_name'] ) ? sanitize_text_field( $_POST['campaign_name'] ) : 'Untitled Campaign';
	$ai_model          = isset( $_POST['ai_model'] ) ? sanitize_text_field( $_POST['ai_model'] ) : 'anthropic:claude-sonnet-4-6';
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
		echo '<div class="notice notice-error"><p>Silakan isi AI Prompt (Instruksi).</p></div>';
	} else {
		// Insert Campaign
		$campaign_id = AAAG_Campaign::insert( array(
			'name'           => $campaign_name,
			'prompt'         => $prompt,
			'knowledge_base' => $knowledge_base,
			'ai_model'       => $ai_model,
			'status'         => 'active'
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
				'template_id'       => 0, // Legacy
				'knowledge_base_id' => 0, // Legacy
				'post_type'         => $post_type,
				'post_status'       => $post_status,
				'min_words'         => $min_words,
				'max_words'         => $max_words,
				'schedule_time'     => $job_schedule_time,
			) );
			$jobs_added++;
		}
		
		// Redirect to avoid duplicate submission on refresh
		wp_redirect( admin_url('admin.php?page=aaag-jobs&campaign_id=' . $campaign_id . '&msg=created&count=' . $jobs_added) );
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
	<p>Semua pengaturan untuk satu grup antrean (Campaign) diatur di halaman ini.</p>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'aaag_generate_action', 'aaag_generate_nonce' ); ?>
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="campaign_name">Nama Campaign</label></th>
				<td>
					<input type="text" name="campaign_name" id="campaign_name" class="regular-text" required placeholder="Contoh: Batch Artikel Wisata Bali">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai_model">Model AI</label></th>
				<td>
					<select name="ai_model" id="ai_model" style="min-width:300px;">
						<?php
						$anthropic_connected = get_option( 'aaag_anthropic_connected', 0 );
						$openai_connected    = get_option( 'aaag_openai_connected', 0 );
						$gemini_connected    = get_option( 'aaag_gemini_connected', 0 );
						$has_any = false;
						
						if ( $anthropic_connected && ! empty( get_option( 'aaag_api_key' ) ) ) :
							$has_any = true;
						?>
						<optgroup label="Anthropic (Claude)">
							<option value="anthropic:claude-sonnet-4-6" selected>Claude 4.6 Sonnet (Terverifikasi & Rekomendasi Akun Anda)</option>
							<option value="anthropic:claude-3-5-sonnet-latest">Claude 3.5 Sonnet (Latest Alias)</option>
							<option value="anthropic:claude-3-5-haiku-latest">Claude 3.5 Haiku (Latest Alias)</option>
							<option value="anthropic:claude-haiku-4-5">Claude 4.5 Haiku (Sangat Cepat)</option>
							<option value="anthropic:claude-3-7-sonnet-20250219">Claude 3.7 Sonnet</option>
							<option value="anthropic:claude-3-opus-latest">Claude 3 Opus (Premium)</option>
						</optgroup>
						<?php 
						endif;
						
						if ( $openai_connected && ! empty( get_option( 'aaag_openai_api_key' ) ) ) :
							$has_any = true;
						?>
						<optgroup label="OpenAI (ChatGPT)">
							<option value="openai:gpt-4o-mini" selected>GPT-4o Mini (Sangat Cepat & Murah)</option>
							<option value="openai:gpt-4o">GPT-4o (Sangat Pintar)</option>
							<option value="openai:o1-mini">o1-mini (Super Logis)</option>
							<option value="openai:o3-mini">o3-mini (Terbaru & Pintar)</option>
						</optgroup>
						<?php 
						endif;
						
						if ( $gemini_connected && ! empty( get_option( 'aaag_gemini_api_key' ) ) ) :
							$has_any = true;
						?>
						<optgroup label="Google Gemini">
							<option value="gemini:gemini-3.5-flash">Gemini 3.5 Flash (Terbaru & Cepat)</option>
							<option value="gemini:gemini-3.1-pro">Gemini 3.1 Pro (Pintar)</option>
							<option value="gemini:gemini-1.5-flash">Gemini 1.5 Flash (Sangat Murah)</option>
							<option value="gemini:gemini-1.5-pro">Gemini 1.5 Pro</option>
						</optgroup>
						<?php 
						endif;
						
						if ( ! $has_any ) :
						?>
						<option value="">-- Silakan isi API Key & jalankan "Test Connection" di menu Settings --</option>
						<?php endif; ?>
					</select>
					<p class="description">Pilih model yang akan menulis artikel di Campaign ini. Pastikan Anda sudah memasukkan API Key yang sesuai di halaman Settings.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="prompt">AI Prompt (Instruksi)</label></th>
				<td>
					<textarea name="prompt" id="prompt" rows="6" class="large-text" required><?php echo esc_textarea( $default_prompt ); ?></textarea>
					<p class="description">Variabel wajib: <code>{{title}}</code>, <code>{{min_words}}</code>, <code>{{max_words}}</code>. Opsional: <code>{{site_name}}</code>, <code>{{current_date}}</code>.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="knowledge_base">Knowledge Base (Opsional)</label></th>
				<td>
					<textarea name="knowledge_base" id="knowledge_base" rows="4" class="large-text" placeholder="Masukkan referensi tambahan, data spesifik, atau aturan khusus di sini..."></textarea>
					<p class="description">AI akan membaca teks ini sebagai referensi mutlak saat menulis seluruh artikel dalam Campaign ini. Anda <strong>TIDAK PERLU</strong> memasukkan kode apapun ke dalam prompt, sistem akan menyuntikkannya otomatis.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="post_type">Post Type Tujuan</label></th>
				<td>
					<select name="post_type" id="post_type">
						<?php foreach ( $post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">Jumlah Kata (Per Artikel)</th>
				<td>
					<input type="number" name="min_words" id="min_words" value="500" min="100" class="small-text"> s/d
					<input type="number" name="max_words" id="max_words" value="1000" min="100" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="post_status">Status Artikel</label></th>
				<td>
					<select name="post_status" id="post_status">
						<option value="draft">Draft</option>
						<option value="pending">Pending</option>
						<option value="publish">Publish</option>
						<option value="future">Schedule (Posting Terjadwal)</option>
					</select>
				</td>
			</tr>
			<tr id="schedule_options" style="display:none;">
				<th scope="row">Jadwal Auto Posting</th>
				<td>
					<p>
						<label for="schedule_mode">Metode Penjadwalan:</label><br>
						<select name="schedule_mode" id="schedule_mode" style="margin-bottom: 15px;">
							<option value="daily">1 Artikel Sehari (Tanggal Berurutan, Jam Diacak)</option>
							<option value="interval">Berdasarkan Jarak Waktu (Interval)</option>
						</select>
					</p>
					
					<p>
						<label for="schedule_date">Mulai Tanggal / Posting Pertama:</label><br>
						<input type="datetime-local" name="schedule_date" id="schedule_date">
					</p>
					
					<div id="wrap_schedule_daily">
						<p>
							Rentang Jam Posting (Format 24 Jam):<br>
							Mulai dari jam <input type="number" name="daily_min" value="12" min="0" max="23" class="small-text"> s/d jam 
							<input type="number" name="daily_max" value="14" min="0" max="23" class="small-text">
						</p>
						<p class="description">Artikel akan diterbitkan **setiap hari secara berurutan** (tidak akan ada hari yang bolong/acak). Sistem akan memposting 1 artikel per hari, namun jam terbitnya akan diacak di antara rentang waktu di atas agar terlihat natural.</p>
					</div>

					<div id="wrap_schedule_interval" style="display:none;">
						<p>
							Jarak antar posting: 
							<input type="number" name="min_gap" value="2" min="1" class="small-text"> s/d
							<input type="number" name="max_gap" value="6" min="1" class="small-text">
							<select name="gap_unit">
								<option value="hours">Jam</option>
								<option value="minutes">Menit</option>
							</select>
						</p>
						<p class="description">Tips: Jarak waktu akan diacak. Jika Anda ingin jarak waktu yang <strong>pasti/tetap</strong> (misal persis 12 jam), silakan isi angka yang sama: <code>12 s/d 12 Jam</code>.</p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="titles">Daftar Judul Artikel</label></th>
				<td>
					<textarea name="titles" id="titles" rows="10" class="large-text" required placeholder="Masukkan satu judul per baris..."></textarea>
					<p class="description">Setiap baris akan menjadi 1 artikel dalam Campaign ini.</p>
					<div id="token_estimation" style="margin-top: 10px; font-weight: bold; color: #0073aa;">Estimasi Token per Artikel: 0 token</div>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="aaag_generate_submit" class="button button-primary" value="Buat Campaign & Mulai Antrean">
		</p>
	</form>
</div>
