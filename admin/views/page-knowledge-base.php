<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_POST['aaag_kb_submit'] ) && check_admin_referer( 'aaag_kb_action', 'aaag_kb_nonce' ) ) {
	$name = sanitize_text_field( $_POST['kb_name'] );
	$content = sanitize_textarea_field( wp_unslash( $_POST['kb_content'] ) );
	
	if ( isset( $_POST['kb_id'] ) && ! empty( $_POST['kb_id'] ) ) {
		AAAG_Knowledge_Base::update( absint( $_POST['kb_id'] ), $name, $content );
		echo '<div class="notice notice-success"><p>Knowledge Base diupdate.</p></div>';
	} else {
		AAAG_Knowledge_Base::insert( $name, $content );
		echo '<div class="notice notice-success"><p>Knowledge Base ditambahkan.</p></div>';
	}
}

if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) && check_admin_referer( 'delete_kb_' . $_GET['id'] ) ) {
	AAAG_Knowledge_Base::delete( absint( $_GET['id'] ) );
	echo '<div class="notice notice-success"><p>Knowledge Base dihapus.</p></div>';
}

$edit_kb = null;
if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] ) ) {
	$edit_kb = AAAG_Knowledge_Base::get( absint( $_GET['id'] ) );
}

$knowledge_bases = AAAG_Knowledge_Base::get_all();
?>
<div class="wrap aaag-wrap">
	<h1>Knowledge Base</h1>
	
	<div class="aaag-split">
		<div class="aaag-split-left">
			<h2><?php echo $edit_kb ? 'Edit Knowledge Base' : 'Tambah Knowledge Base Baru'; ?></h2>
			<form method="post" action="?page=aaag-knowledge-base">
				<?php wp_nonce_field( 'aaag_kb_action', 'aaag_kb_nonce' ); ?>
				<?php if ( $edit_kb ) : ?>
					<input type="hidden" name="kb_id" value="<?php echo esc_attr( $edit_kb->id ); ?>">
				<?php endif; ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="kb_name">Judul/Nama KB</label></th>
						<td><input type="text" name="kb_name" id="kb_name" class="regular-text" required value="<?php echo $edit_kb ? esc_attr( $edit_kb->name ) : ''; ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kb_content">Isi Knowledge Base</label></th>
						<td>
							<textarea name="kb_content" id="kb_content" rows="15" class="large-text" required><?php echo $edit_kb ? esc_textarea( $edit_kb->content ) : ''; ?></textarea>
							<p class="description">Teks panjang yang akan disisipkan ke prompt saat digunakan.</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="aaag_kb_submit" class="button button-primary" value="Simpan KB">
					<?php if ( $edit_kb ) : ?>
						<a href="?page=aaag-knowledge-base" class="button">Batal</a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<div class="aaag-split-right">
			<h2>Daftar Knowledge Base</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Nama</th>
						<th>Aksi</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $knowledge_bases ) ) : ?>
						<tr><td colspan="2">Belum ada Knowledge Base.</td></tr>
					<?php else : ?>
						<?php foreach ( $knowledge_bases as $kb ) : ?>
							<tr>
								<td><?php echo esc_html( $kb->name ); ?></td>
								<td>
									<a href="?page=aaag-knowledge-base&action=edit&id=<?php echo $kb->id; ?>" class="button button-small">Edit</a>
									<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-knowledge-base&action=delete&id=' . $kb->id), 'delete_kb_' . $kb->id ); ?>" class="button button-small button-link-delete">Hapus</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
