jQuery(document).ready(function ($) {
  const form = $('#scorm-upload-form');
  const fileInput = $('input[name="scorm_zip"]');
  const result = $('#scorm-upload-result');
  form.on('submit', function (e) {
    e.preventDefault();
    const file = fileInput[0].files[0];
    if (!file) {
      result.text('Please select a ZIP file.');
      return;
    }
    const fd = new FormData();
    fd.append('_ajax_nonce', scormAdminUpload.nonce);
    fd.append('action', 'scorm_ajax_upload');
    fd.append('scorm_zip', file);
    $('input[type=submit]', form).attr('disabled', true).val('Uploading...');
    $.ajax({
      url: scormAdminUpload.ajax_url,
      method: 'POST',
      processData: false,
      contentType: false,
      data: fd,
      success: function (res) {
        $('input[type=submit]', form).attr('disabled', false).val('Upload');
        if ( res.success ) {
          result.html('<div class="updated"><p>' + res.data.message + '</p></div>');
        } else {
          result.html('<div class="error"><p>' + res.data.message + '</p></div>');
        }
      },
      error: function () {
        $('input[type=submit]', form).attr('disabled', false).val('Upload');
        result.html('<div class="error"><p>Upload failed.</p></div>');
      }
    });
  });
});