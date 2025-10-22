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

        $actor_param = isset( $_GET['actor'] ) ? wp_unslash( $_GET['actor'] ) : '';
        $actor_json = '{}';
        if ( ! empty( $actor_param ) ) {
            $actor_json = wp_json_encode( json_decode( urldecode( $actor_param ), true ) );
            if ( $actor_json === null ) $actor_json = '{}';
        }

        $launch_esc = esc_url( $launch_url );
        $actor_json_esc = esc_js( $actor_json );
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
          var SCORM_ACTOR = <?php echo $actor_json_esc; ?> || {};
          var COURSE_ID = <?php echo (int) $course_id; ?>;
          var ajaxURL = "<?php echo admin_url('admin-ajax.php'); ?>";

          (function () {
            if ( window.API || window.API_1484_11 ) return;
            function noopTrue() { return "true"; }
            function noopEmpty() { return ""; }

            var savedProgress = {};
            try {
              var xhr = new XMLHttpRequest();
              xhr.open("GET", ajaxURL + "?action=scorm_get_progress&course_id=" + COURSE_ID, false);
              xhr.send(null);
              if (xhr.status === 200) {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && resp.data) savedProgress = resp.data;
              }
            } catch (e) { console.warn("Could not load saved progress:", e); }

            var sessionData = {
              lesson_location: savedProgress.lesson_location || "",
              suspend_data: savedProgress.suspend_data || "",
              lesson_status: savedProgress.lesson_status || "not attempted"
            };

            var API12 = {
              LMSInitialize: noopTrue,
              LMSFinish: noopTrue,
              LMSGetValue: function (key) {
                if (key === "cmi.core.lesson_location") return sessionData.lesson_location;
                if (key === "cmi.suspend_data") return sessionData.suspend_data;
                if (key === "cmi.core.lesson_status") return sessionData.lesson_status;
                return "";
              },
              LMSSetValue: function (key, value) {
                if (key === "cmi.core.lesson_location") sessionData.lesson_location = value;
                if (key === "cmi.suspend_data") sessionData.suspend_data = value;
                if (key === "cmi.core.lesson_status") sessionData.lesson_status = value;
                logEvent("LMSSetValue", { key: key, value: value });
                saveProgress();
                return "true";
              },
              LMSCommit: function() {
                logEvent("LMSCommit");
                saveProgress();
                return "true";
              },
              LMSGetLastError: noopEmpty,
              LMSGetErrorString: noopEmpty,
              LMSGetDiagnostic: noopEmpty
            };

            window.API = API12;

            function saveProgress() {
              fetch(ajaxURL, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                  action: "scorm_save_progress",
                  course_id: COURSE_ID,
                  lesson_location: sessionData.lesson_location,
                  suspend_data: sessionData.suspend_data,
                  lesson_status: sessionData.lesson_status
                })
              }).catch(err => console.warn("Failed saving progress", err));
            }

            function logEvent(type, data) {
              console.log(`[SCORM] ${type}`, data || {});
            }

            window.addEventListener('beforeunload', saveProgress);
          })();

          document.getElementById('scorm-course-frame').addEventListener('load', function () {
            setTimeout(() => {
              document.getElementById('scorm-loading').style.display = 'none';
            }, 3000);
          });
        </script>
      </body>
    </html>
    <?php wp_die(); }
}

new SCORM_Launch();