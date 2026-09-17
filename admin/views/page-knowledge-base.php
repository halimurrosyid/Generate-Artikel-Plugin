<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle Form Submission (Manual & File Upload)
if ( isset( $_POST['aaag_kb_submit'] ) && check_admin_referer( 'aaag_kb_action', 'aaag_kb_nonce' ) ) {
	$name = sanitize_text_field( $_POST['kb_name'] );
	$content = sanitize_textarea_field( wp_unslash( $_POST['kb_content'] ) );
	
	// Check if a file was uploaded alongside
	if ( ! empty( $_FILES['kb_file']['tmp_name'] ) && is_uploaded_file( $_FILES['kb_file']['tmp_name'] ) ) {
		try {
			$file_text = AAAG_Document_Parser::parse_file( $_FILES['kb_file']['tmp_name'], $_FILES['kb_file']['name'] );
			if ( ! empty( $file_text ) ) {
				$content = trim( $content . "\n\n" . $file_text );
			}
			if ( empty( $name ) ) {
				$name = pathinfo( $_FILES['kb_file']['name'], PATHINFO_FILENAME );
				$name = ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
			}
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>Gagal membaca file: ' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	if ( empty( $name ) ) {
		echo '<div class="notice notice-error"><p>Judul/Nama Knowledge Base wajib diisi.</p></div>';
	} elseif ( empty( $content ) ) {
		echo '<div class="notice notice-error"><p>Isi Knowledge Base tidak boleh kosong. Silakan ketik manual atau unggah dokumen.</p></div>';
	} else {
		if ( isset( $_POST['kb_id'] ) && ! empty( $_POST['kb_id'] ) ) {
			AAAG_Knowledge_Base::update( absint( $_POST['kb_id'] ), $name, $content );
			echo '<div class="notice notice-success"><p>Knowledge Base berhasil diperbarui.</p></div>';
		} else {
			AAAG_Knowledge_Base::insert( $name, $content );
			echo '<div class="notice notice-success"><p>Knowledge Base berhasil ditambahkan.</p></div>';
		}
	}
}

if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) && check_admin_referer( 'delete_kb_' . $_GET['id'] ) ) {
	AAAG_Knowledge_Base::delete( absint( $_GET['id'] ) );
	echo '<div class="notice notice-success"><p>Knowledge Base berhasil dihapus.</p></div>';
}

$edit_kb = null;
if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] ) ) {
	$edit_kb = AAAG_Knowledge_Base::get( absint( $_GET['id'] ) );
}

$knowledge_bases = AAAG_Knowledge_Base::get_all();
?>
<div class="wrap aaag-wrap">
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; background: #ffffff; padding: 20px 24px; border-radius: var(--aaag-radius-lg); border: 1px solid var(--aaag-border); box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
		<div>
			<h1 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 24px;">
				<span>🧠 Knowledge Base & Referensi AI</span>
			</h1>
			<p class="description" style="margin-top: 4px; margin-bottom: 0;">Tambahkan data pengetahuan secara manual atau unggah berkas (PDF, DOCX, XLSX, CSV, TXT) untuk memperkaya wawasan AI.</p>
		</div>
	</div>

	<div class="aaag-dashboard-grid" style="grid-template-columns: 1fr 1fr;">
		<!-- Form Column -->
		<div class="aaag-main-card">
			<div class="aaag-card-header">
				<h2><span class="dashicons dashicons-edit"></span> <?php echo $edit_kb ? 'Edit Knowledge Base' : 'Tambah Knowledge Base Baru'; ?></h2>
			</div>
			<div class="aaag-card-body">
				<form method="post" action="?page=aaag-knowledge-base" enctype="multipart/form-data">
					<?php wp_nonce_field( 'aaag_kb_action', 'aaag_kb_nonce' ); ?>
					<?php if ( $edit_kb ) : ?>
						<input type="hidden" name="kb_id" value="<?php echo esc_attr( $edit_kb->id ); ?>">
					<?php endif; ?>

					<div class="aaag-form-group">
						<label for="kb_name" class="aaag-label">Judul / Nama Knowledge Base</label>
						<input type="text" name="kb_name" id="kb_name" class="aaag-input-full" placeholder="Contoh: Panduan Produk & Layanan 2026" required value="<?php echo $edit_kb ? esc_attr( $edit_kb->name ) : ''; ?>">
					</div>

					<!-- Upload Document Box -->
					<div class="aaag-form-group" style="background: #f8fafc; border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; text-align: center; position: relative;">
						<div style="margin-bottom: 10px;">
							<span class="dashicons dashicons-cloud-upload" style="font-size: 36px; width: 36px; height: 36px; color: var(--aaag-primary);"></span>
						</div>
						<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: #1e293b;">Unggah Berkas Dokumen (Opsional)</h4>
						<p style="margin: 0 0 12px 0; font-size: 12px; color: #64748b;">Mendukung format: <strong>PDF, DOCX (Word), XLSX / CSV (Excel), TXT, MD</strong></p>
						
						<input type="file" name="kb_file" id="kb_file" accept=".pdf,.docx,.doc,.xlsx,.csv,.txt,.md,.json" style="display: none;">
						<button type="button" class="button" id="btn_select_kb_file" style="background: #ffffff; border-color: #94a3b8; font-weight: 600;">📁 Pilih File Dokumen</button>

						<div id="kb_file_info" style="display: none; margin-top: 12px; padding: 10px; background: #e0f2fe; border-radius: 6px; color: #0369a1; font-size: 12px; font-weight: 600;">
							<span id="kb_file_name"></span> (<span id="kb_file_size"></span>)
							<button type="button" id="btn_parse_kb_file" class="button button-primary button-small" style="margin-left: 10px;">⚡ Ekstrak Teks Sekarang</button>
						</div>
					</div>

					<div class="aaag-form-group">
						<label for="kb_content" class="aaag-label">Isi Teks Knowledge Base</label>
						<textarea name="kb_content" id="kb_content" rows="14" class="aaag-textarea-full" placeholder="Ketik teks secara manual atau ekstrak langsung dari berkas dokumen di atas..." required><?php echo $edit_kb ? esc_textarea( $edit_kb->content ) : ''; ?></textarea>
						<p class="aaag-help-text">Teks di atas akan otomatis disematkan ke instruksi AI (*Smart RAG*) saat Campaign dijalankan.</p>
					</div>

					<div style="display: flex; gap: 10px; align-items: center; margin-top: 20px;">
						<input type="submit" name="aaag_kb_submit" class="button button-primary" value="💾 Simpan Knowledge Base">
						<?php if ( $edit_kb ) : ?>
							<a href="?page=aaag-knowledge-base" class="button">Batal</a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>

		<!-- List Column -->
		<div class="aaag-sidebar-card">
			<div class="aaag-card-header">
				<h2><span class="dashicons dashicons-list-view"></span> Daftar Knowledge Base Tersimpan</h2>
			</div>
			<div class="aaag-card-body" style="padding: 0;">
				<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
					<thead>
						<tr>
							<th style="padding-left: 20px;">Nama / Judul KB</th>
							<th style="width: 130px; text-align: right; padding-right: 20px;">Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $knowledge_bases ) ) : ?>
							<tr><td colspan="2" style="padding: 20px; text-align: center; color: #64748b;">Belum ada Knowledge Base yang tersimpan.</td></tr>
						<?php else : ?>
							<?php foreach ( $knowledge_bases as $kb ) : ?>
								<tr>
									<td style="padding-left: 20px;">
										<strong style="color: #0f172a;"><?php echo esc_html( $kb->name ); ?></strong>
										<div style="font-size: 11px; color: #64748b; margin-top: 3px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
											<?php echo esc_html( mb_substr( strip_tags( $kb->content ), 0, 80 ) ) . '...'; ?>
										</div>
									</td>
									<td style="text-align: right; padding-right: 20px; white-space: nowrap;">
										<a href="?page=aaag-knowledge-base&action=edit&id=<?php echo $kb->id; ?>" class="button button-small"><span class="dashicons dashicons-edit" style="font-size: 14px; margin-top: 2px;"></span> Edit</a>
										<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-knowledge-base&action=delete&id=' . $kb->id), 'delete_kb_' . $kb->id ); ?>" class="button button-small button-link-delete" onclick="return confirm('Hapus Knowledge Base ini?');"><span class="dashicons dashicons-trash" style="font-size: 14px; margin-top: 2px;"></span> Hapus</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#btn_select_kb_file').on('click', function(e) {
		e.preventDefault();
		$('#kb_file').click();
	});

	$('#kb_file').on('change', function() {
		var file = this.files[0];
		if (file) {
			$('#kb_file_name').text(file.name);
			$('#kb_file_size').text(formatBytes(file.size));
			$('#kb_file_info').slideDown(200);

			if (!$('#kb_name').val()) {
				var cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, " ");
				cleanName = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
				$('#kb_name').val(cleanName);
			}
		}
	});

	$('#btn_parse_kb_file').on('click', function(e) {
		e.preventDefault();
		var fileInput = $('#kb_file')[0];
		if (!fileInput.files.length) return;

		var $btn = $(this);
		var originalText = $btn.text();
		$btn.prop('disabled', true).text('Memproses & Ekstrak...');

		var formData = new FormData();
		formData.append('action', 'aaag_parse_document');
		formData.append('nonce', aaagAjax.nonce);
		formData.append('kb_file', fileInput.files[0]);

		$.ajax({
			url: aaagAjax.ajaxurl,
			type: 'POST',
			data: formData,
			contentType: false,
			processData: false,
			success: function(response) {
				$btn.prop('disabled', false).text(originalText);
				if (response.success) {
					var currentVal = $('#kb_content').val();
					var newText = response.data.text;
					if (currentVal.trim().length > 0) {
						$('#kb_content').val(currentVal + "\n\n" + newText);
					} else {
						$('#kb_content').val(newText);
					}
					if (response.data.title && !$('#kb_name').val()) {
						$('#kb_name').val(response.data.title);
					}
					Swal.fire('Berhasil Ekstrak!', 'Teks dari file ' + response.data.name + ' berhasil dimasukkan ke form.', 'success');
				} else {
					Swal.fire('Gagal Ekstrak', response.data, 'error');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text(originalText);
				Swal.fire('Error', 'Gagal memproses file pada server.', 'error');
			}
		});
	});

	function formatBytes(bytes, decimals = 2) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const dm = decimals < 0 ? 0 : decimals;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
	}
});
</script>
