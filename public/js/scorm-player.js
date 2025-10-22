jQuery(document).ready(function ($) {
  $(".scorm-player-wrapper").each(function () {
    const wrapper = $(this);
    const btn = wrapper.find(".scorm-launch-btn");
    const logArea = wrapper.find(".scorm-log-area");
    const modal = $("#scorm-modal-overlay");
    const closeBtn = modal.find(".scorm-close");
    const iframe = $("#scorm-modal-iframe");
    const courseId = wrapper.data("course-id");

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
      iframe.attr("src", "");
      modal.fadeOut(200);
      $("body").removeClass("scorm-modal-open");
      log("🛑 Course closed");

      // ✅ Manual save before unload
      if (window.scormLogHandler && window.sessionData) {
        Object.keys(window.sessionData).forEach(function (key) {
          window.scormLogHandler.save(
            "LMSSetValue",
            { key: key, value: window.sessionData[key] },
            courseId,
          );
        });
      }
    }

    // Listen for SCORM API events from iframe
    window.addEventListener("message", function (event) {
      if (event.data && event.data.scorm_event) {
        const { event_type, event_data } = event.data;
        log(`📡 ${event_type} → ${JSON.stringify(event_data)}`);

        // ✅ Save progress to WP
        if (window.scormLogHandler) {
          window.scormLogHandler.save(event_type, event_data, courseId);
        }

        // ✅ Track session data for manual save
        if (!window.sessionData) window.sessionData = {};
        if (event_type === "LMSSetValue" && event_data.key) {
          window.sessionData[event_data.key] = event_data.value;
        }
      }
    });
  });
});
