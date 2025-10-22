// scorm-log-handler.js
jQuery(document).ready(function ($) {
  window.scormLogHandler = {
    /**
     * eventType: string (e.g., "LMSSetValue")
     * eventData: object { key, value }
     * courseId: int
     */
    save: function (eventType, eventData, courseId) {
      try {
        if (!courseId || !eventData) return;
        // we only want to persist certain keys
        if (eventType === "LMSSetValue" && eventData.key) {
          const key = eventData.key;
          const value = eventData.value;
          if (
            [
              "cmi.core.lesson_location",
              "cmi.suspend_data",
              "cmi.core.lesson_status",
            ].indexOf(key) === -1
          ) {
            // ignore others for persistent storage
            return;
          }
          // post to backend
          $.post(scormPlayer.ajax_url, {
            action: "scorm_save_progress",
            course_id: courseId,
            key: key,
            value: value,
          }).fail(function () {
            console.warn("SCORM progress save failed");
          });
        }
      } catch (e) {
        console.error("scormLogHandler.save error", e);
      }
    },
  };
});
