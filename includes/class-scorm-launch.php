<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Launch {

    public function __construct() {
        add_action( 'wp_ajax_scorm_launch', [ $this, 'ajax_launch' ] );
        add_action( 'wp_ajax_nopriv_scorm_launch', [ $this, 'ajax_launch' ] );
    }

    public function ajax_launch() {
        header( 'Content-Type: text/html; charset=utf-8' );

        $course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
        $nonce     = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

        if ( ! $course_id || empty( $nonce ) ) {
            echo '<p>Invalid request (missing course_id or nonce).</p>';
            wp_die();
        }

        $user_id = get_current_user_id();
        $expected_action = 'scorm_launch_' . $course_id . '_' . $user_id;
        if ( ! wp_verify_nonce( $nonce, $expected_action ) ) {
            if ( ! wp_verify_nonce( $nonce, 'scorm_launch_' . $course_id . '_0' ) ) {
                echo '<p>Invalid or expired launch token.</p>';
                wp_die();
            }
        }

        $launch_url = get_post_meta( $course_id, '_scorm_launch', true );
        if ( empty( $launch_url ) ) {
            echo '<p>Launch file not found.</p>';
            wp_die();
        }

        $launch_esc = esc_url( $launch_url );
        $ajax_url_esc = esc_url( admin_url('admin-ajax.php') );
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SCORM Launch</title>
  <style>
    html,body{height:100%;margin:0;background:#fff;}
    .scorm-wrapper{width:100%;height:100%;}
    .scorm-frame{width:100%;height:100%;border:0;}
    .scorm-loading{position:absolute;left:50%;top:50%;transform: translate(-50%, -50%);}
    .loader {
      width: 15px;
      aspect-ratio: 1;
      position: relative;
    }
    .loader::before,
    .loader::after {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: #000;
    }
    .loader::before {
      box-shadow: -25px 0;
      animation: l8-1 1s infinite linear;
    }
    .loader::after {
      transform: rotate(0deg) translateX(25px);
      animation: l8-2 1s infinite linear;
    }
    @keyframes l8-1 { 100%{transform: translateX(25px)} }
    @keyframes l8-2 { 100%{transform: rotate(-180deg) translateX(25px)} }
  </style>
</head>
<body>
  <div class="scorm-loading" id="scorm-loading">
    <div class="loader"></div>
  </div>
  <div class="scorm-wrapper">
    <iframe id="scorm-course-frame" class="scorm-frame" src="<?php echo $launch_esc; ?>" allow="autoplay; fullscreen;"></iframe>
  </div>

  <script>
    var COURSE_ID = <?php echo (int) $course_id; ?>;
    var ajaxURL = "<?php echo $ajax_url_esc; ?>";

    (function () {
      if ( window.API || window.API_1484_11 ) return;
      
      function noopTrue() { return "true"; }
      function noopEmpty() { return ""; }

      // Load saved progress synchronously before SCORM content loads
      var savedProgress = {};
      try {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", ajaxURL + "?action=scorm_get_progress&course_id=" + COURSE_ID, false);
        xhr.send(null);
        if (xhr.status === 200) {
          var resp = JSON.parse(xhr.responseText);
          if (resp.success && resp.data) {
            savedProgress = resp.data;
            console.log("[SCORM] Loaded saved progress:", savedProgress);
          } else {
            console.log("[SCORM] No saved progress found");
          }
        }
      } catch (e) { 
        console.warn("Could not load saved progress:", e); 
      }

      // Initialize session data with saved progress
      var sessionData = {
        "cmi.core.lesson_location": savedProgress["cmi.core.lesson_location"] || "",
        "cmi.suspend_data": savedProgress["cmi.suspend_data"] || "",
        "cmi.core.lesson_status": savedProgress["cmi.core.lesson_status"] || "not attempted",
        "cmi.core.score.raw": savedProgress["cmi.core.score.raw"] || "",
        "cmi.core.score.min": savedProgress["cmi.core.score.min"] || "",
        "cmi.core.score.max": savedProgress["cmi.core.score.max"] || ""
      };

      console.log("[SCORM] Initialized session data:", sessionData);
      console.log("[SCORM] Starting from location:", sessionData["cmi.core.lesson_location"]);

      // Debounced save function to avoid too many requests
      var saveTimeout = null;
      function debouncedSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
          saveProgressBatch();
        }, 1000);
      }

      function saveProgressBatch(useBeacon) {
        // Use sendBeacon for unload scenarios (more reliable)
        if (useBeacon && navigator.sendBeacon) {
          const formData = new FormData();
          formData.append("action", "scorm_save_progress");
          formData.append("course_id", COURSE_ID);
          formData.append("progress", JSON.stringify(sessionData));
          
          navigator.sendBeacon(ajaxURL, formData);
          console.log("[SCORM] Progress sent via sendBeacon");
          return;
        }

        // Normal fetch for regular saves
        var params = new URLSearchParams();
        params.append("action", "scorm_save_progress");
        params.append("course_id", COURSE_ID);
        params.append("progress", JSON.stringify(sessionData));

        fetch(ajaxURL, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: params.toString()
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success) {
            console.log("[SCORM] Progress saved successfully");
          }
        })
        .catch(function(err) { 
          console.warn("Failed saving progress", err); 
        });
      }

      // SCORM 1.2 API Implementation
      var API12 = {
        sessionData: sessionData, // Expose sessionData
        
        LMSInitialize: function() {
          console.log("[SCORM] LMSInitialize called");
          return "true";
        },
        
        LMSFinish: function() {
          console.log("[SCORM] LMSFinish called");
          clearTimeout(saveTimeout); // Clear any pending debounced saves
          saveProgressBatch(true); // Use beacon for reliability
          return "true";
        },
        
        LMSGetValue: function (key) {
          var value = sessionData[key] || "";
          console.log("[SCORM] LMSGetValue:", key, "=", value);
          return value;
        },
        
        LMSSetValue: function (key, value) {
          console.log("[SCORM] LMSSetValue:", key, "=", value);
          sessionData[key] = value;
          debouncedSave(); // Debounced save
          return "true";
        },
        
        LMSCommit: function() {
          console.log("[SCORM] LMSCommit called");
          clearTimeout(saveTimeout); // Clear any pending debounced saves
          saveProgressBatch(false); // Regular save for commit
          return "true";
        },
        
        LMSGetLastError: noopEmpty,
        LMSGetErrorString: noopEmpty,
        LMSGetDiagnostic: noopEmpty
      };

      window.API = API12;
      
      // Also expose sessionData directly on window for easier access
      window.scormSessionData = sessionData;

      // Save progress before unload using sendBeacon for reliability
      var beaconSent = false;
      var isUnloading = false;
      
      window.addEventListener('beforeunload', function () {
        if (beaconSent) return;
        beaconSent = true;
        isUnloading = true;

        const formData = new FormData();
        formData.append("action", "scorm_save_progress");
        formData.append("course_id", COURSE_ID);
        formData.append("progress", JSON.stringify(sessionData));

        // sendBeacon is more reliable than fetch on page unload
        if (navigator.sendBeacon) {
          navigator.sendBeacon(ajaxURL, formData);
          console.log("[SCORM] Final progress sent via sendBeacon");
        } else {
          // Fallback to synchronous AJAX
          try {
            var params = new URLSearchParams();
            params.append("action", "scorm_save_progress");
            params.append("course_id", COURSE_ID);
            params.append("progress", JSON.stringify(sessionData));
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", ajaxURL, false);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.send(params.toString());
            console.log("[SCORM] Final progress sent via XHR");
          } catch(e) {
            console.warn("[SCORM] Failed to send final progress", e);
          }
        }
      });
      
      // Handle page visibility changes (when user switches tabs or modal closes)
      document.addEventListener('visibilitychange', function() {
        if (document.hidden && !isUnloading) {
          saveProgressBatch(true); // Use beacon when page becomes hidden
        }
      });

      // Periodic auto-save every 30 seconds
      setInterval(function() {
        saveProgressBatch();
      }, 30000);
    })();

    // Hide loading spinner after iframe loads
    document.getElementById('scorm-course-frame').addEventListener('load', function () {
      setTimeout(function() {
        document.getElementById('scorm-loading').style.display = 'none';
      }, 1000);
    });
  </script>
</body>
</html>
        <?php 
        wp_die(); 
    }
}

new SCORM_Launch();

// Make sure to also update class-scorm-progress.php save_progress method