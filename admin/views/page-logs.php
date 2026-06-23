<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_POST['clear_logs'] ) && check_admin_referer( 'clear_logs_action' ) ) {
	AAAG_Logger::clear_logs();
	echo '<div class="notice notice-success"><p>Logs berhasil dibersihkan.</p></div>';
}

$logs = AAAG_Logger::get_logs( 100 );
?>
<div class="wrap aaag-wrap">
	<h1>Logs Eksekusi</h1>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'clear_logs_action' ); ?>
		<p><input type="submit" name="clear_logs" id="aaag-clear-logs-btn" class="button" value="Bersihkan Logs"></p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>Waktu</th>
				<th>Job ID</th>
				<th>Pesan Log</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="3">Tidak ada log.</td></tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( $log->job_id ? $log->job_id : '-' ); ?></td>
						<td><?php echo esc_html( $log->message ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
