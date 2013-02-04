<?php if ( isset( $_GET['ubc_import_data'] ) && 1 == $_GET['ubc_import_data'] )
		$updated_message = "インポートしました。"; ?>
<div class="wrap">
<h2>UBC</h2>
<?php if ( ! empty( $updated_message ) ) : ?>
<div id="message" class="updated"><p><?php echo esc_html( $updated_message ); ?></p></div>
<?php endif; ?>

<div class="tool-box">
<h3 class="title">Import</h3>

<form action="" method="post" enctype="multipart/form-data">
<?php wp_nonce_field( 'ubc_import_data' ); ?>
<input type="hidden" name="action" value="ubc_import_data" />

<p><input type="file" name="ubc_import_data_file" /></p>
<p><input type="submit" class="button-primary" value="インポート" /></p>
</form>

</div>
</div>