<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap aaag-wrap">
	<h1>Settings AI Auto Article</h1>
	
	<form method="post" action="options.php">
		<?php
		settings_fields( 'aaag_options' );
		do_settings_sections( 'aaag_options' );
		?>
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="aaag_api_key">Anthropic API Key (Claude)</label></th>
				<td>
					<input type="password" name="aaag_api_key" id="aaag_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'aaag_api_key' ) ); ?>">
					<p class="description">Diperlukan jika Anda memilih model Anthropic Claude.</p>
					<button type="button" id="aaag_test_connection" class="button aaag-test-conn-btn" data-provider="anthropic">Test Anthropic Connection</button>
					<span id="aaag_test_result_anthropic"></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aaag_openai_api_key">OpenAI API Key (ChatGPT)</label></th>
				<td>
					<input type="password" name="aaag_openai_api_key" id="aaag_openai_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'aaag_openai_api_key' ) ); ?>">
					<p class="description">Diperlukan jika Anda memilih model OpenAI (GPT-4o, dsb).</p>
					<button type="button" class="button aaag-test-conn-btn" data-provider="openai">Test OpenAI Connection</button>
					<span id="aaag_test_result_openai"></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aaag_gemini_api_key">Google Gemini API Key</label></th>
				<td>
					<input type="password" name="aaag_gemini_api_key" id="aaag_gemini_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'aaag_gemini_api_key' ) ); ?>">
					<p class="description">Diperlukan jika Anda memilih model Google Gemini.</p>
					<button type="button" class="button aaag-test-conn-btn" data-provider="gemini">Test Gemini Connection</button>
					<span id="aaag_test_result_gemini"></span>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="aaag_max_tokens">Max Tokens</label></th>
				<td>
					<input type="number" name="aaag_max_tokens" id="aaag_max_tokens" style="width: 100px;" value="<?php echo esc_attr( get_option( 'aaag_max_tokens', 8192 ) ); ?>">
					<p class="description">Batas maksimal teks yang bisa dikeluarkan oleh AI. Biarkan di angka 8192 (batas tertinggi Claude) agar artikel panjang tidak terpotong di tengah jalan.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aaag_temperature">Temperature</label></th>
				<td>
					<?php $current_temp = strval( get_option( 'aaag_temperature', '0.7' ) ); ?>
					<select name="aaag_temperature" id="aaag_temperature" style="min-width: 250px;">
						<option value="0.2" <?php selected( $current_temp, '0.2' ); ?>>0.2 (Sangat Kaku & Faktual)</option>
						<option value="0.5" <?php selected( $current_temp, '0.5' ); ?>>0.5 (Cukup Terstruktur)</option>
						<option value="0.7" <?php selected( $current_temp, '0.7' ); ?>>0.7 (Normal / Standar Artikel)</option>
						<option value="0.9" <?php selected( $current_temp, '0.9' ); ?>>0.9 (Sangat Kreatif & Luwes)</option>
					</select>
					<p class="description">Mengontrol tingkat kreativitas AI.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aaag_queue_buffer">Batas Buffer Antrean (Artikel Terjadwal)</label></th>
				<td>
					<input type="number" name="aaag_queue_buffer" id="aaag_queue_buffer" style="width: 100px;" value="<?php echo esc_attr( get_option( 'aaag_queue_buffer', 5 ) ); ?>">
					<p class="description">Maksimal jumlah artikel berstatus <strong>Schedule (Terjadwal)</strong> di masa depan yang boleh di-generate oleh AI dalam satu waktu untuk tiap Campaign. Jika batas ini tercapai, mesin akan menunggu sampai artikel tersebut terbit sebelum memproses sisa antrean lainnya (Sangat hemat token!).</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Tipe Post Target (Internal Link)</th>
				<td>
					<?php 
					$saved_types = get_option( 'aaag_internal_link_post_types', array( 'post', 'page' ) );
					if ( ! is_array( $saved_types ) ) $saved_types = array();
					
					$raw_post_types = get_post_types( array( 'public' => true ), 'objects' );
					$excluded_types = array( 'attachment', 'wp_block' );
					foreach ( $raw_post_types as $pt ) {
						if ( in_array( $pt->name, $excluded_types ) ) continue;
						$checked = in_array( $pt->name, $saved_types ) ? 'checked' : '';
						echo '<label style="margin-right: 15px;"><input type="checkbox" name="aaag_internal_link_post_types[]" value="' . esc_attr( $pt->name ) . '" ' . $checked . '> ' . esc_html( $pt->label ) . '</label>';
					}
					?>
					<p class="description">Pilih tipe post apa saja yang akan dicari oleh "Smart RAG" untuk dijadikan rekomendasi Internal Link di dalam artikel AI Anda.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aaag_delete_data_uninstall">Hapus Data Saat Uninstall?</label></th>
				<td>
					<select name="aaag_delete_data_uninstall" id="aaag_delete_data_uninstall">
						<option value="0" <?php selected( get_option( 'aaag_delete_data_uninstall', 0 ), 0 ); ?>>False (Biarkan data tabel plugin)</option>
						<option value="1" <?php selected( get_option( 'aaag_delete_data_uninstall', 0 ), 1 ); ?>>True (Hapus semua data tabel plugin)</option>
					</select>
					<p class="description">Data artikel yang di-generate tidak akan dihapus dari wp_posts.</p>
				</td>
			</tr>
		</table>
		
		<?php submit_button(); ?>
	</form>
</div>
