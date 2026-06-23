<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['job_id'] ) && check_admin_referer( 'delete_job_' . $_GET['job_id'] ) ) {
	AAAG_Job::delete( absint( $_GET['job_id'] ) );
	echo '<div class="notice notice-success"><p>Job berhasil dihapus.</p></div>';
}

$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
$jobs = AAAG_Job::get_all( 100, 0, $campaign_id );
?>
<div class="wrap aaag-wrap">
	<h1>Daftar Job Antrean</h1>
	<p>Proses WP-Cron berjalan setiap 5 menit dan memproses 1 job berstatus "pending". Anda juga bisa menjalankan secara manual.</p>
	
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>ID</th>
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
				<tr><td colspan="7">Tidak ada job.</td></tr>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( $job->id ); ?></td>
						<td><?php echo esc_html( $job->title ); ?></td>
						<td>
							<?php 
							$status_class = 'status-draft';
							if ($job->status == 'completed') $status_class = 'status-active';
							if ($job->status == 'failed') $status_class = 'status-error';
							if ($job->status == 'processing') $status_class = 'status-paused';
							if ($job->status == 'pending') $status_class = 'status-draft';
							?>
							<span class="aaag-badge <?php echo $status_class; ?>"><?php echo esc_html( strtoupper( $job->status ) ); ?></span>
						</td>
						<td><?php echo esc_html( $job->schedule_time ? $job->schedule_time : '-' ); ?></td>
						<td><?php echo esc_html( $job->attempts ); ?></td>
						<td><?php echo esc_html( $job->error_message ? $job->error_message : '-' ); ?></td>
						<td>
							<?php if ( in_array( $job->status, array('pending', 'failed') ) ) : ?>
								<button class="button aaag-run-job-btn" data-id="<?php echo esc_attr( $job->id ); ?>">Run Now</button>
							<?php endif; ?>
							<?php 
							if ( $job->status === 'completed' ) {
								global $wpdb;
								$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ai_article_job_id' AND meta_value = %d LIMIT 1", $job->id ) );
								if ( $post_id ) {
									echo '<a href="' . get_edit_post_link( $post_id ) . '" class="button" target="_blank">Edit</a> ';
									$preview_url = get_permalink( $post_id );
									$post_status = get_post_status( $post_id );
									if ( in_array( $post_status, array( 'draft', 'pending', 'future' ) ) ) {
										// Generate a preview link that works even if not published
										$preview_url = set_url_scheme( get_permalink( $post_id ) );
										$preview_url = add_query_arg( 'preview', 'true', $preview_url );
									}
									echo '<a href="' . esc_url( $preview_url ) . '" class="button button-primary" target="_blank">Preview</a> ';
								}
							}
							?>
							<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-jobs&action=delete&job_id=' . $job->id), 'delete_job_' . $job->id ); ?>" class="button button-link-delete aaag-delete-job" data-id="<?php echo esc_attr( $job->id ); ?>"><span class="dashicons dashicons-trash"></span> Hapus</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
