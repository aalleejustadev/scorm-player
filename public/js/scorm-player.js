jQuery(document).ready(function ($) {
  // Prevent double initialization
  if (window.scormPlayerInitialized) return;
  window.scormPlayerInitialized = true;

  $(".scorm-player-wrapper").each(function () {
    const wrapper = $(this);
    const btn = wrapper.find(".scorm-launch-btn");
    const logArea = wrapper.find(".scorm-log-area");
    const modal = $("#scorm-modal-overlay");
    const closeBtn = modal.find(".scorm-close");
    const iframe = $("#scorm-modal-iframe");
    const courseId = wrapper.data("course-id");
    let sessionData = {};

    function log(msg) {
      const time = new Date().toLocaleTimeString();
      logArea.append(`<div>[${time}] ${msg}</div>`);
    }

    btn.on("click", function () {
      const launchUrl = wrapper.data("launch-url");
      iframe.attr("src", launchUrl);
      modal.fadeIn(200);
      $("body").addClass("scorm-modal-open");
      log("🎬 Course launched");
    });

    closeBtn.on("click", closeModal);
    modal.on("click", (e) => {
      if ($(e.target).is("#scorm-modal-overlay")) closeModal();
    });

    function closeModal() {
      log("⏸️ Saving progress before closing...");

      // Try to get final progress from iframe before closing
      try {
        const iframeWindow = iframe[0].contentWindow;
        if (iframeWindow && iframeWindow.scormSessionData) {
          sessionData = iframeWindow.scormSessionData;
          log("📊 Retrieved session data: " + JSON.stringify(sessionData));
        }
      } catch (e) {
        console.warn("Could not access iframe data:", e);
      }

      // Save progress using proper format for sendBeacon
      if (Object.keys(sessionData).length > 0) {
        // Create FormData for proper content-type
        const formData = new FormData();
        formData.append("action", "scorm_save_progress");
        formData.append("course_id", courseId);
        formData.append("progress", JSON.stringify(sessionData));

        if (navigator.sendBeacon) {
          // SendBeacon with FormData automatically sets correct content-type
          const sent = navigator.sendBeacon(scormPlayer.ajax_url, formData);
          if (sent) {
            log("✅ Progress saved via beacon");
          } else {
            log("⚠️ Beacon failed, using AJAX");
            saveViaAjax();
          }
        } else {
          saveViaAjax();
        }
      }

      function saveViaAjax() {
        $.ajax({
          url: scormPlayer.ajax_url,
          method: "POST",
          async: false,
          data: {
            action: "scorm_save_progress",
            course_id: courseId,
            progress: JSON.stringify(sessionData),
          },
          success: function (response) {
            log("✅ Progress saved via AJAX");
            console.log("Save response:", response);
          },
          error: function (xhr, status, error) {
            log("❌ Failed to save progress: " + error);
            console.error("Save error:", xhr.responseText);
          },
        });
      }

      // Small delay to ensure save completes
      setTimeout(function () {
        iframe.attr("src", "");
        modal.fadeOut(200);
        $("body").removeClass("scorm-modal-open");
        log("🛑 Course closed");
        sessionData = {}; // Reset session data
      }, 500);
    }

    // Listen for SCORM API events from iframe
    window.addEventListener("message", function (event) {
      if (event.data && event.data.scorm_event) {
        const { event_type, event_data } = event.data;
        log(`📡 ${event_type} → ${JSON.stringify(event_data)}`);

        // Track session data
        if (event_type === "LMSSetValue" && event_data.key) {
          sessionData[event_data.key] = event_data.value;
        }

        // Save progress to WP
        if (window.scormLogHandler) {
          window.scormLogHandler.save(event_type, event_data, courseId);
        }
      }
    });

    // Make sessionData accessible globally for the iframe
    window.scormSessionData = sessionData;
  });
});
