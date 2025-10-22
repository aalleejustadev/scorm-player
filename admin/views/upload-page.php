<div class="wrap">
  <h1>Upload SCORM Course</h1>
  <form id="scorm-upload-form" method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('scorm_upload_nonce', 'scorm_upload_nonce_field'); ?>
      <input type="file" name="scorm_zip" accept=".zip" required>
      <input type="submit" class="button button-primary" value="Upload">
  </form>
  <div id="scorm-upload-result"></div>
</div>