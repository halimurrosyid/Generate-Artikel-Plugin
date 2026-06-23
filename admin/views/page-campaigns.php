<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
	$update_data = array(
		'name'           => sanitize_text_field( $_POST['campaign_name'] ),
		'prompt'         => wp_unslash( $_POST['prompt'] ),
		'knowledge_base' => wp_unslash( $_POST['knowledge_base'] ),
		'ai_model'       => sanitize_text_field( $_POST['ai_model'] )
	);
	AAAG_Campaign::update( $id, $update_data );
	
	$msg = '<p>Campaign berhasil diperbarui. Job selanjutnya akan menggunakan prompt & referensi yang baru.</p>';
	
	// Handle Reschedule
	if ( isset($_POST['reschedule_jobs']) && $_POST['reschedule_jobs'] == '1' ) {
		global $wpdb;
		$jobs_table = AAAG_DB::get_table_name('jobs');
		
		$schedule_date     = isset( $_POST['schedule_date'] ) ? sanitize_text_field( $_POST['schedule_date'] ) : ''; // Y-m-d\TH:i
		$schedule_mode     = isset( $_POST['schedule_mode'] ) ? sanitize_text_field( $_POST['schedule_mode'] ) : 'interval';
		$min_gap           = isset( $_POST['min_gap'] ) ? absint( $_POST['min_gap'] ) : 2;
		$max_gap           = isset( $_POST['max_gap'] ) ? absint( $_POST['max_gap'] ) : 6;
		$gap_unit          = isset( $_POST['gap_unit'] ) ? sanitize_text_field( $_POST['gap_unit'] ) : 'hours';
		$daily_min         = isset( $_POST['daily_min'] ) ? absint( $_POST['daily_min'] ) : 12;
		$daily_max         = isset( $_POST['daily_max'] ) ? absint( $_POST['daily_max'] ) : 14;
		
		$current_schedule = null;
		$current_date_ts = null;
		
		if ( $schedule_mode === 'daily' ) {
			$current_date_ts = !empty($schedule_date) ? strtotime(date('Y-m-d', strtotime($schedule_date))) : strtotime(date('Y-m-d'));
		} elseif ( ! empty( $schedule_date ) ) {
			$current_schedule = strtotime( $schedule_date );
		}
		
		// Fetch all pending/failed jobs in order of their ID
		$jobs_to_update = $wpdb->get_results( $wpdb->prepare("SELECT id FROM $jobs_table WHERE campaign_id = %d AND status IN ('pending', 'failed') ORDER BY id ASC", $id) );
		
		$updated_count = 0;
		foreach ( $jobs_to_update as $job ) {
			$job_schedule_time = null;
			if ( $schedule_mode === 'daily' && $current_date_ts ) {
				$random_hour = rand( $daily_min, $daily_max );
				$random_minute = rand( 0, 59 );
				if ( $random_hour === $daily_max ) $random_minute = 0;
				$job_schedule_time = date('Y-m-d', $current_date_ts) . ' ' . sprintf('%02d:%02d:00', $random_hour, $random_minute);
				$current_date_ts += 86400; // Maju 1 hari
			} elseif ( $schedule_mode === 'interval' && $current_schedule ) {
				$job_schedule_time = date( 'Y-m-d H:i:s', $current_schedule );
				$gap_value = rand( $min_gap, $max_gap );
				$multiplier = ( $gap_unit === 'minutes' ) ? 60 : 3600;
				$current_schedule += ( $gap_value * $multiplier );
			}
			
			if ( $job_schedule_time ) {
				$wpdb->update(
					$jobs_table,
					array('schedule_time' => $job_schedule_time),
					array('id' => $job->id),
					array('%s'),
					array('%d')
				);
				$updated_count++;
			}
		}
		
		$msg .= '<p><strong>' . $updated_count . ' Job berhasil diatur ulang jadwalnya!</strong></p>';
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
	<h1>Edit Campaign</h1>
	<p>Mengubah instruksi atau referensi akan mempengaruhi semua antrean yang belum dijalankan (Pending) di dalam Campaign ini.</p>
	<a href="<?php echo admin_url('admin.php?page=aaag-campaigns'); ?>" class="button">&laquo; Kembali ke Daftar</a>
	<hr>
	
	<form method="post" action="<?php echo admin_url('admin.php?page=aaag-campaigns'); ?>">
		<?php wp_nonce_field( 'campaign_edit_action', 'campaign_edit_nonce' ); ?>
		<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $edit_camp->id ); ?>">
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="campaign_name">Nama Campaign</label></th>
				<td>
					<input type="text" name="campaign_name" id="campaign_name" class="regular-text" required value="<?php echo esc_attr( $edit_camp->name ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai_model">Model AI</label></th>
				<td>
					<?php 
					$current_model = isset($edit_camp->ai_model) && !empty($edit_camp->ai_model) ? $edit_camp->ai_model : 'anthropic:claude-3-haiku-20240307';
					?>
					<select name="ai_model" id="ai_model" style="min-width:300px;">
						<optgroup label="Anthropic (Claude)">
							<option value="anthropic:claude-3-haiku-20240307" <?php selected($current_model, 'anthropic:claude-3-haiku-20240307'); ?>>Claude 3 Haiku (Sangat Cepat & Murah)</option>
							<option value="anthropic:claude-3-5-haiku-20241022" <?php selected($current_model, 'anthropic:claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (Terbaru)</option>
							<option value="anthropic:claude-3-5-sonnet-20241022" <?php selected($current_model, 'anthropic:claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
							<option value="anthropic:claude-3-7-sonnet-20250219" <?php selected($current_model, 'anthropic:claude-3-7-sonnet-20250219'); ?>>Claude 3.7 Sonnet (Terbaru & Pintar)</option>
							<option value="anthropic:claude-3-opus-20240229" <?php selected($current_model, 'anthropic:claude-3-opus-20240229'); ?>>Claude 3 Opus (Premium/Mahal)</option>
						</optgroup>
						<optgroup label="OpenAI (ChatGPT)">
							<option value="openai:gpt-4o-mini" <?php selected($current_model, 'openai:gpt-4o-mini'); ?>>GPT-4o Mini (Sangat Cepat & Murah)</option>
							<option value="openai:gpt-4o" <?php selected($current_model, 'openai:gpt-4o'); ?>>GPT-4o (Sangat Pintar)</option>
							<option value="openai:o1-mini" <?php selected($current_model, 'openai:o1-mini'); ?>>o1-mini (Super Logis)</option>
							<option value="openai:o3-mini" <?php selected($current_model, 'openai:o3-mini'); ?>>o3-mini (Terbaru & Pintar)</option>
						</optgroup>
						<optgroup label="Google Gemini">
							<option value="gemini:gemini-1.5-flash" <?php selected($current_model, 'gemini:gemini-1.5-flash'); ?>>Gemini 1.5 Flash (Sangat Murah)</option>
							<option value="gemini:gemini-1.5-pro" <?php selected($current_model, 'gemini:gemini-1.5-pro'); ?>>Gemini 1.5 Pro (Pintar)</option>
							<option value="gemini:gemini-2.0-flash" <?php selected($current_model, 'gemini:gemini-2.0-flash'); ?>>Gemini 2.0 Flash (Terbaru & Cepat)</option>
						</optgroup>
					</select>
					<p class="description">Mengubah model di sini akan berlaku untuk semua antrean (Jobs) di Campaign ini yang belum diproses.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="prompt">AI Prompt (Instruksi)</label></th>
				<td>
					<textarea name="prompt" id="prompt" rows="8" class="large-text" required><?php echo esc_textarea( $edit_camp->prompt ); ?></textarea>
					<p class="description">Variabel wajib: <code>{{title}}</code>, <code>{{min_words}}</code>, <code>{{max_words}}</code>.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="knowledge_base">Knowledge Base / Referensi</label></th>
				<td>
					<textarea name="knowledge_base" id="knowledge_base" rows="8" class="large-text"><?php echo esc_textarea( $edit_camp->knowledge_base ); ?></textarea>
					<p class="description">Teks yang diketik di sini akan dipaksa masuk ke otak AI setiap kali judul dalam Campaign ini diproses.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Reschedule (Atur Ulang Jadwal)</th>
				<td>
					<label style="font-weight:bold; color:#d63638;">
						<input type="checkbox" name="reschedule_jobs" id="reschedule_jobs" value="1"> 
						Ya, saya ingin mengatur ulang jadwal untuk seluruh artikel yang BELUM diterbitkan di Campaign ini.
					</label>
					<p class="description">Jadwal baru di bawah ini hanya akan aktif jika kotak di atas Anda centang.</p>
					
					<div id="wrap_reschedule_options" style="display:none; margin-top:15px; padding:20px; background:#fff; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
						<h3 style="margin-top:0;">Pengaturan Jadwal Baru</h3>
						<table class="form-table" style="margin-top:0;">
							<tr>
								<th scope="row" style="padding-top:0;"><label for="schedule_mode">Metode Penjadwalan:</label></th>
								<td style="padding-top:0;">
									<select name="schedule_mode" id="schedule_mode">
										<option value="daily">1 Artikel Sehari (Tanggal Berurutan, Jam Diacak)</option>
										<option value="interval">Berdasarkan Jarak Waktu (Interval)</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="schedule_date">Mulai Tanggal / Posting Pertama:</label></th>
								<td>
									<input type="datetime-local" name="schedule_date" id="schedule_date" required disabled>
								</td>
							</tr>
							<tr id="wrap_schedule_daily">
								<th scope="row">Rentang Jam Posting (Format 24 Jam):</th>
								<td>
									Mulai dari jam <input type="number" name="daily_min" value="12" min="0" max="23" class="small-text"> s/d jam 
									<input type="number" name="daily_max" value="14" min="0" max="23" class="small-text">
									<p class="description">Artikel akan diterbitkan **setiap hari secara berurutan** (tidak akan ada hari yang bolong/acak). Sistem memposting 1 artikel per hari, jam diacak dalam rentang di atas.</p>
								</td>
							</tr>
							<tr id="wrap_schedule_interval" style="display:none;">
								<th scope="row">Jarak antar posting:</th>
								<td>
									<input type="number" name="min_gap" value="2" min="1" class="small-text"> s/d
									<input type="number" name="max_gap" value="6" min="1" class="small-text">
									<select name="gap_unit">
										<option value="hours">Jam</option>
										<option value="minutes">Menit</option>
									</select>
									<p class="description">Tips: Jarak akan diacak. Jika ingin jarak pasti/tetap, isi angka yang sama: <code>12 s/d 12 Jam</code>.</p>
								</td>
							</tr>
						</table>
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
					});
					</script>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="aaag_campaign_edit_submit" class="button button-primary" value="Simpan Perubahan Campaign">
		</p>
	</form>
</div>
<?php endif; ?>
