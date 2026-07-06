<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_POST['clear_logs'] ) && check_admin_referer( 'clear_logs_action' ) ) {
	AAAG_Logger::clear_logs();
	echo '<div class="notice notice-success"><p>Semua logs berhasil dibersihkan.</p></div>';
}

if ( isset( $_POST['clear_old_logs'] ) && check_admin_referer( 'clear_logs_action' ) ) {
	AAAG_Logger::clear_old_logs( 7 );
	echo '<div class="notice notice-success"><p>Logs yang berumur lebih dari 7 hari berhasil dibersihkan.</p></div>';
}

$per_page = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

$logs = AAAG_Logger::get_logs( $per_page, $offset );
$total_logs = AAAG_Logger::get_total_logs();
$total_pages = ceil( $total_logs / $per_page );
?>
<div class="wrap aaag-wrap">
	<h1>Logs Eksekusi</h1>
	
	<div class="aaag-tablenav" style="display: flex; justify-content: space-between; align-items: center; margin: 15px 0; flex-wrap: wrap; gap: 10px;">
		<div style="display: flex; gap: 10px; align-items: center;">
			<form method="post" action="" style="margin: 0;">
				<?php wp_nonce_field( 'clear_logs_action' ); ?>
				<input type="submit" name="clear_logs" class="button" style="background: #dc3545; color: #fff; border-color: #dc3545;" value="Hapus Semua Log" onclick="return confirm('Apakah Anda yakin ingin menghapus SEMUA logs?');">
			</form>
			<form method="post" action="" style="margin: 0;">
				<?php wp_nonce_field( 'clear_logs_action' ); ?>
				<input type="submit" name="clear_old_logs" class="button" style="background: #e2e8f0; color: #475569; border-color: #cbd5e1;" value="Hapus Log > 7 Hari" onclick="return confirm('Hapus semua log yang berumur lebih dari 7 hari?');">
			</form>
			<span style="font-size: 12px; color: #64748b; font-style: italic;">*Sistem juga menghapus otomatis log > 7 hari di background agar database tetap ringan.</span>
		</div>
		
		<?php if ( $total_pages > 1 ) : ?>
			<div class="aaag-pagination">
				<span class="displaying-num" style="margin-right: 10px; color: #666; font-size: 13px;"><?php printf( '%d items', $total_logs ); ?></span>
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

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 200px;">Waktu</th>
				<th style="width: 100px;">Job ID</th>
				<th>Pesan Log</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="3">Tidak ada log.</td></tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $log->created_at ); ?></strong></td>
						<td><code><?php echo esc_html( $log->job_id ? $log->job_id : '-' ); ?></code></td>
						<td><?php echo esc_html( $log->message ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

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

