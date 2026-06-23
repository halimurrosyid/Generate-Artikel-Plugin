<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_POST['aaag_template_submit'] ) && check_admin_referer( 'aaag_template_action', 'aaag_template_nonce' ) ) {
	$name = sanitize_text_field( $_POST['template_name'] );
	$prompt = sanitize_textarea_field( wp_unslash( $_POST['template_prompt'] ) );
	
	if ( isset( $_POST['template_id'] ) && ! empty( $_POST['template_id'] ) ) {
		AAAG_Template::update( absint( $_POST['template_id'] ), $name, $prompt );
		echo '<div class="notice notice-success"><p>Template diupdate.</p></div>';
	} else {
		AAAG_Template::insert( $name, $prompt );
		echo '<div class="notice notice-success"><p>Template ditambahkan.</p></div>';
	}
}

if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) && check_admin_referer( 'delete_template_' . $_GET['id'] ) ) {
	AAAG_Template::delete( absint( $_GET['id'] ) );
	echo '<div class="notice notice-success"><p>Template dihapus.</p></div>';
}

$edit_template = null;
if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] ) ) {
	$edit_template = AAAG_Template::get( absint( $_GET['id'] ) );
}

$templates = AAAG_Template::get_all();
?>
<div class="wrap aaag-wrap">
	<h1>Prompt Template</h1>
	
	<div class="aaag-split">
		<div class="aaag-split-left">
			<h2><?php echo $edit_template ? 'Edit Template' : 'Tambah Template Baru'; ?></h2>
			<form method="post" action="?page=aaag-templates">
				<?php wp_nonce_field( 'aaag_template_action', 'aaag_template_nonce' ); ?>
				<?php if ( $edit_template ) : ?>
					<input type="hidden" name="template_id" value="<?php echo esc_attr( $edit_template->id ); ?>">
				<?php endif; ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="template_name">Nama Template</label></th>
						<td><input type="text" name="template_name" id="template_name" class="regular-text" required value="<?php echo $edit_template ? esc_attr( $edit_template->name ) : ''; ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="template_prompt">Prompt</label></th>
						<td>
							<textarea name="template_prompt" id="template_prompt" rows="10" class="large-text" required><?php echo $edit_template ? esc_textarea( $edit_template->prompt ) : ''; ?></textarea>
							<p class="description">
								Placeholder yang didukung:<br>
								<code>{{title}}</code>, <code>{{min_words}}</code>, <code>{{max_words}}</code>, <code>{{knowledge_base}}</code>, <code>{{site_name}}</code>, <code>{{current_date}}</code>
							</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="aaag_template_submit" class="button button-primary" value="Simpan Template">
					<?php if ( $edit_template ) : ?>
						<a href="?page=aaag-templates" class="button">Batal</a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<div class="aaag-split-right">
			<h2>Daftar Template</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Nama</th>
						<th>Aksi</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $templates ) ) : ?>
						<tr><td colspan="2">Belum ada template.</td></tr>
					<?php else : ?>
						<?php foreach ( $templates as $tpl ) : ?>
							<tr>
								<td><?php echo esc_html( $tpl->name ); ?></td>
								<td>
									<a href="?page=aaag-templates&action=edit&id=<?php echo $tpl->id; ?>" class="button button-small">Edit</a>
									<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=aaag-templates&action=delete&id=' . $tpl->id), 'delete_template_' . $tpl->id ); ?>" class="button button-small button-link-delete">Hapus</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
