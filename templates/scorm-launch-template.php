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
    <iframe id="scorm-course-frame" class="scorm-frame" src="<?php echo $launch_url; ?>" allow="autoplay; fullscreen;"></iframe>
  </div>

  <script>
    var SCORM_ACTOR = <?php echo $actor_json; ?> || {};
    var COURSE_ID = <?php echo (int) $course_id; ?>;
    var ajaxURL = "<?php echo esc_url( $ajax_url ); ?>";

    (function () {
      if (window.API || window.API_1484_11) return;

      function noopTrue() { return "true"; }
      function noopEmpty() { return ""; }

      var sessionData = {
        "cmi.core.lesson_location": "",
        "cmi.suspend_data": "",
        "cmi.core.lesson_status": "not attempted"
      };

      try {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", ajaxURL + "?action=scorm_get_progress&course_id=" + COURSE_ID, false);
        xhr.send(null);
        if (xhr.status === 200) {
          var resp = JSON.parse(xhr.responseText);
          if (resp.success && resp.data) {
            Object.keys(sessionData).forEach(function (key) {
              if (resp.data[key]) sessionData[key] = resp.data[key];
            });
          }
        }
      } catch (e) {
        console.warn("Could not load saved progress:", e);
      }

      var API12 = {
        LMSInitialize: noopTrue,
        LMSFinish: noopTrue,
        LMSGetValue: function (key) {
          return sessionData[key] || "";
        },
        LMSSetValue: function (key, value) {
          sessionData[key] = value;
          logEvent("LMSSetValue", { key: key, value: value });
          saveProgress(key, value);
          return "true";
        },
        LMSCommit: function () {
          logEvent("LMSCommit");
          Object.keys(sessionData).forEach(function (key) {
            saveProgress(key, sessionData[key]);
          });
          return "true";
        },
        LMSGetLastError: noopEmpty,
        LMSGetErrorString: noopEmpty,
        LMSGetDiagnostic: noopEmpty
      };

      window.API = API12;

      function saveProgress(key, value) {
        const formData = new FormData();
        formData.append("action", "scorm_save_progress");
        formData.append("course_id", COURSE_ID);
        formData.append("key", key);
        formData.append("value", value);

        fetch(ajaxURL, {
          method: "POST",
          body: formData
        }).catch(err => console.warn("Failed saving progress", err));
      }

      function logEvent(type, data) {
        console.log(`[SCORM] ${type}`, data || {});
      }

      let beaconSent = false;
      window.addEventListener('beforeunload', function () {
        if (beaconSent) return;
        beaconSent = true;

        const params = new URLSearchParams();
        params.append("action", "scorm_save_progress");
        params.append("course_id", COURSE_ID);
        params.append("progress", JSON.stringify(sessionData));

        navigator.sendBeacon(ajaxURL, params.toString());
      });
    })();

    document.getElementById('scorm-course-frame').addEventListener('load', function () {
      setTimeout(() => {
        document.getElementById('scorm-loading').style.display = 'none';
      }, 3000);
    });
  </script>
</body>
</html>