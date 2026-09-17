<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'ai-auto-article-generator' ) );
}

// Handle Single Delete
if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['job_id'] ) && check_admin_referer( 'delete_job_' . $_GET['job_id'] ) ) {
	AAAG_Job::delete( absint( $_GET['job_id'] ) );
	echo '<div class="notice notice-success"><p>Job berhasil dihapus.</p></div>';
}

// Handle Reset All Failed in Campaign/Global
if ( isset( $_GET['action'] ) && $_GET['action'] == 'reset_all_failed' ) {
	$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
	if ( check_admin_referer( 'reset_all_failed_action' ) ) {
		$reset_count = AAAG_Job::reset_failed( $campaign_id );
		echo '<div class="notice notice-success"><p>' . intval( $reset_count ) . ' job yang gagal berhasil di-reset menjadi pending.</p></div>';
	}
}

// Handle Quick Reschedule for Campaign
if ( isset( $_POST['aaag_quick_reschedule_submit'] ) && check_admin_referer( 'quick_reschedule_action', 'quick_reschedule_nonce' ) ) {
	$resched_campaign_id = isset( $_POST['resched_campaign_id'] ) ? absint( $_POST['resched_campaign_id'] ) : 0;
	$start_date          = isset( $_POST['resched_start_date'] ) ? sanitize_text_field( $_POST['resched_start_date'] ) : '';
	$schedule_mode       = isset( $_POST['resched_mode'] ) ? sanitize_text_field( $_POST['resched_mode'] ) : 'interval';
	$min_gap             = isset( $_POST['resched_min_gap'] ) ? absint( $_POST['resched_min_gap'] ) : 8;
	$max_gap             = isset( $_POST['resched_max_gap'] ) ? absint( $_POST['resched_max_gap'] ) : 9;
	$gap_unit            = isset( $_POST['resched_gap_unit'] ) ? sanitize_text_field( $_POST['resched_gap_unit'] ) : 'hours';

	$updated_count = AAAG_Job::reschedule_campaign_jobs( $resched_campaign_id, $start_date, $schedule_mode, $min_gap, $max_gap, $gap_unit );
	echo '<div class="notice notice-success"><p>Berhasil menjadwalkan ulang ' . intval( $updated_count ) . ' artikel pending mulai dari ' . esc_html( date( 'd-m-Y H:i', strtotime( $start_date ) ) ) . '.</p></div>';
}

// Handle Bulk Actions
if ( isset( $_POST['aaag_bulk_action_submit'] ) && check_admin_referer( 'aaag_bulk_jobs_nonce', 'aaag_bulk_jobs_nonce_field' ) ) {
	$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
	$selected_jobs = isset( $_POST['selected_jobs'] ) && is_array( $_POST['selected_jobs'] ) ? array_map( 'absint', $_POST['selected_jobs'] ) : array();
	
	if ( empty( $selected_jobs ) ) {
		echo '<div class="notice notice-warning"><p>Tidak ada job yang dipilih.</p></div>';
	} elseif ( $bulk_action === 'delete' ) {
		$deleted = AAAG_Job::delete_multiple( $selected_jobs );
		echo '<div class="notice notice-success"><p>' . intval( $deleted ) . ' job berhasil dihapus.</p></div>';
	} elseif ( $bulk_action === 'reset' ) {
		$reset = AAAG_Job::reset_multiple( $selected_jobs );
		echo '<div class="notice notice-success"><p>' . intval( $reset ) . ' job berhasil di-reset menjadi Pending.</p></div>';
	} elseif ( $bulk_action === 'run_now' ) {
		$processed = 0;
		foreach ( $selected_jobs as $jid ) {
			if ( AAAG_Queue::process_job_manual( $jid ) ) {
				$processed++;
			}
		}
		echo '<div class="notice notice-success"><p>' . intval( $processed ) . ' job berhasil dijalankan.</p></div>';
	}
}

$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

$per_page    = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset      = ( $current_page - 1 ) * $per_page;

$total_jobs  = AAAG_Job::count_all( $campaign_id, $status_filter );
$total_pages = ceil( $total_jobs / $per_page );
$jobs        = AAAG_Job::get_all( $per_page, $offset, $campaign_id, $status_filter );

// Status counts for tabs
$count_all        = AAAG_Job::count_all( $campaign_id, '' );
$count_pending    = AAAG_Job::count_all( $campaign_id, 'pending' );
$count_processing = AAAG_Job::count_all( $campaign_id, 'processing' );
$count_completed  = AAAG_Job::count_all( $campaign_id, 'completed' );
$count_failed     = AAAG_Job::count_all( $campaign_id, 'failed' );
$count_skipped    = AAAG_Job::count_all( $campaign_id, 'skipped' );
?>
<div class="wrap aaag-wrap">
	<h1>Daftar Job Antrean <?php echo $campaign_id > 0 ? '(Campaign #' . $campaign_id . ')' : ''; ?></h1>
	<p>Proses WP-Cron berjalan setiap 5 menit dan memproses 1 job berstatus "pending". Anda juga bisa mengelola antrean massal di bawah ini.</p>
	
	<!-- Status Filter Tabs -->
	<ul class="subsubsub" style="margin-bottom: 15px;">
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">Semua <span class="count">(<?php echo $count_all; ?>)</span></a> |</li>
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs&status=pending' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">Pending <span class="count">(<?php echo $count_pending; ?>)</span></a> |</li>
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs&status=processing' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">Processing <span class="count">(<?php echo $count_processing; ?>)</span></a> |</li>
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs&status=completed' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">Completed <span class="count">(<?php echo $count_completed; ?>)</span></a> |</li>
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs&status=failed' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo $status_filter === 'failed' ? 'current' : ''; ?>">Failed <span class="count">(<?php echo $count_failed; ?>)</span></a> |</li>
		<li><a href="<?php echo admin_url('admin.php?page=aaag-jobs&status=skipped' . ($campaign_id ? '&campaign_id=' . $campaign_id : '')); ?>" class="<?php echo $status_filter === 'skipped' ? 'current' : ''; ?>">Skipped <span class="count">(<?php echo $count_skipped; ?>)</span></a></li>
	</ul>
	<div class="clear"></div>

	<form method="post" action="">
		<?php wp_nonce_field( 'aaag_bulk_jobs_nonce', 'aaag_bulk_jobs_nonce_field' ); ?>

		<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
				<select name="bulk_action" style="max-width: 200px;">
					<option value="">-- Tindakan Massal --</option>
					<option value="run_now">⚡ Jalankan Terpilih (Run Now)</option>
					<option value="reset">🔄 Reset Terpilih ke Pending</option>
					<option value="delete">🗑️ Hapus Terpilih</option>
				</select>
				<input type="submit" name="aaag_bulk_action_submit" class="button button-primary" value="Terapkan" onclick="return confirm('Apakah Anda yakin ingin menerapkan tindakan massal pada item terpilih?');">

				<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-jobs&action=reset_all_failed&campaign_id=' . $campaign_id), 'reset_all_failed_action' ); ?>" class="button button-secondary" style="background: #e2e8f0; color: #475569; border-color: #cbd5e1; margin-left: 10px;" onclick="return confirm('Reset semua job yang gagal menjadi pending?');">Reset Semua Gagal</a>

				<button type="button" id="btn_toggle_quick_reschedule" class="button button-secondary" style="background: #e0f2fe; color: #0369a1; border-color: #bae6fd; margin-left: 5px;">
					📅 Jadwalkan Ulang (Reschedule)
				</button>
				
				<?php if ( $campaign_id > 0 ) : ?>
					<a href="<?php echo admin_url('admin.php?page=aaag-jobs'); ?>" class="button">&laquo; Tampilkan Semua Campaign</a>
				<?php endif; ?>
			</div>
			
			<?php if ( $total_pages > 1 ) : ?>
				<div class="aaag-pagination">
					<span class="displaying-num" style="margin-right: 10px; color: #666; font-size: 13px;"><?php printf( '%d items', $total_jobs ); ?></span>
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo; Prev',
						'next_text' => 'Next &raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					) );
					?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Panel Quick Reschedule -->
		<div id="panel_quick_reschedule" style="display: none; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
			<h3 style="margin-top: 0; margin-bottom: 8px; font-size: 15px; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
				<span>📅 Penjadwalan Ulang Artikel (Reschedule Queue)</span>
			</h3>
			<p style="margin-top: 0; margin-bottom: 16px; font-size: 13px; color: #475569;">
				Sistem akan menghitung ulang dan memajukan jadwal artikel <strong>Pending / Failed</strong> di Campaign <?php echo $campaign_id > 0 ? '#' . $campaign_id : ''; ?> mulai dari waktu terdekat hari ini secara rapi.
			</p>
			
			<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
				<?php if ( empty( $campaign_id ) ) : ?>
					<div style="flex: 1; min-width: 200px;">
						<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Pilih Campaign Target</label>
						<select name="resched_campaign_id" required style="width: 100%; border-radius: 4px;">
							<option value="0">⚡ Semua Campaign (Reschedule All)</option>
							<?php
							$all_camps = AAAG_Campaign::get_all();
							foreach ( $all_camps as $c ) {
								echo '<option value="' . esc_attr($c->id) . '">' . esc_html($c->name) . ' (#' . $c->id . ')</option>';
							}
							?>
						</select>
					</div>
				<?php else : ?>
					<input type="hidden" name="resched_campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
				<?php endif; ?>

				<div style="flex: 1; min-width: 220px;">
					<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Mulai Tanggal & Jam Pertama</label>
					<input type="datetime-local" name="resched_start_date" value="<?php echo esc_attr( current_time( 'Y-m-d\TH:i' ) ); ?>" required style="width: 100%; border-radius: 4px;">
				</div>

				<div style="flex: 1; min-width: 180px;">
					<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Metode Penjadwalan</label>
					<select name="resched_mode" id="quick_resched_mode" style="width: 100%; border-radius: 4px;">
						<option value="interval">Berdasarkan Jarak (Interval)</option>
						<option value="daily">1 Artikel Sehari (Jam Diacak)</option>
					</select>
				</div>

				<div id="quick_resched_interval_wrap" style="display: flex; gap: 8px; flex: 2; min-width: 280px;">
					<div style="flex: 1;">
						<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Min Jarak</label>
						<input type="number" name="resched_min_gap" value="8" min="1" style="width: 100%; border-radius: 4px;">
					</div>
					<div style="flex: 1;">
						<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Max Jarak</label>
						<input type="number" name="resched_max_gap" value="9" min="1" style="width: 100%; border-radius: 4px;">
					</div>
					<div style="flex: 1;">
						<label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #334155;">Satuan</label>
						<select name="resched_gap_unit" style="width: 100%; border-radius: 4px;">
							<option value="hours">Jam</option>
							<option value="minutes">Menit</option>
						</select>
					</div>
				</div>

				<div style="margin-top: 10px;">
					<?php wp_nonce_field( 'quick_reschedule_action', 'quick_reschedule_nonce' ); ?>
					<input type="submit" name="aaag_quick_reschedule_submit" class="button button-primary" value="🚀 Terapkan Reschedule Sekarang" onclick="return confirm('Apakah Anda yakin ingin mengatur ulang jadwal artikel pending mulai tanggal yang dipilih?');">
				</div>
			</div>
		</div>
		
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column" style="width: 38px; padding: 18px 10px;">
						<input type="checkbox" id="cb-select-all-top">
					</td>
					<th style="width: 60px;">ID</th>
					<th>Judul Artikel</th>
					<th>Status</th>
					<th>Jadwal Post</th>
					<th>Attempts</th>
					<th>Error</th>
					<th>Aksi</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $jobs ) ) : ?>
					<tr><td colspan="8">Tidak ada job yang sesuai filter.</td></tr>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<th scope="row" class="check-column" style="padding: 18px 10px;">
								<input type="checkbox" name="selected_jobs[]" value="<?php echo esc_attr( $job->id ); ?>" class="aaag-job-checkbox">
							</th>
							<td><?php echo esc_html( $job->id ); ?></td>
							<td>
								<strong><?php echo esc_html( $job->title ); ?></strong>
								<?php if ( $job->campaign_id > 0 ) : ?>
									<div style="font-size: 11px; color: #64748b; margin-top: 3px;">Campaign #<?php echo esc_html( $job->campaign_id ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<?php 
								$status_class = 'status-draft';
								if ($job->status == 'completed') $status_class = 'status-active';
								if ($job->status == 'failed') $status_class = 'status-error';
								if ($job->status == 'skipped') $status_class = 'status-skipped';
								if ($job->status == 'processing') $status_class = 'status-paused';
								if ($job->status == 'pending') $status_class = 'status-draft';
								?>
								<span class="aaag-badge <?php echo $status_class; ?>"><?php echo esc_html( strtoupper( $job->status ) ); ?></span>
							</td>
							<td><?php echo esc_html( $job->schedule_time ? $job->schedule_time : '-' ); ?></td>
							<td><?php echo esc_html( $job->attempts ); ?></td>
							<td><?php echo esc_html( $job->error_message ? $job->error_message : '-' ); ?></td>
							<td style="white-space: nowrap; width: 220px;">
								<div style="display: flex; gap: 6px; align-items: center;">
									<?php if ( in_array( $job->status, array('pending', 'failed', 'skipped') ) ) : ?>
										<button type="button" class="button aaag-run-job-btn" data-id="<?php echo esc_attr( $job->id ); ?>" style="margin: 0;">Run Now</button>
									<?php endif; ?>
									<?php 
									if ( $job->status === 'completed' ) {
										global $wpdb;
										$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ai_article_job_id' AND meta_value = %d LIMIT 1", $job->id ) );
										if ( $post_id ) {
											echo '<a href="' . get_edit_post_link( $post_id ) . '" class="button" target="_blank" style="margin: 0;">Edit</a>';
											$preview_url = get_permalink( $post_id );
											$post_status = get_post_status( $post_id );
											if ( in_array( $post_status, array( 'draft', 'pending', 'future' ) ) ) {
												$preview_url = set_url_scheme( get_permalink( $post_id ) );
												$preview_url = add_query_arg( 'preview', 'true', $preview_url );
											}
											echo '<a href="' . esc_url( $preview_url ) . '" class="button button-primary" target="_blank" style="margin: 0;">Preview</a>';
										}
									}
									?>
									<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-jobs&action=delete&job_id=' . $job->id), 'delete_job_' . $job->id ); ?>" class="button button-link-delete aaag-delete-job" data-id="<?php echo esc_attr( $job->id ); ?>" style="margin: 0;"><span class="dashicons dashicons-trash"></span> Hapus</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</form>

	<script>
	jQuery(document).ready(function($) {
		$('#cb-select-all-top').on('change', function() {
			$('.aaag-job-checkbox').prop('checked', $(this).is(':checked'));
		});

		$('#btn_toggle_quick_reschedule').on('click', function(e) {
			e.preventDefault();
			$('#panel_quick_reschedule').slideToggle(200);
		});

		$('#quick_resched_mode').on('change', function() {
			if ($(this).val() === 'daily') {
				$('#quick_resched_interval_wrap').hide();
			} else {
				$('#quick_resched_interval_wrap').show();
			}
		});
	});
	</script>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="aaag-tablenav" style="display: flex; justify-content: flex-end; margin-top: 15px;">
			<div class="aaag-pagination">
				<?php
				echo paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo; Prev',
					'next_text' => 'Next &raquo;',
					'total'     => $total_pages,
					'current'   => $current_page,
				) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

