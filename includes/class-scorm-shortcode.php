<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Shortcode {
    private static $modal_rendered = false;
    private static $scripts_enqueued = false;

    public function __construct() {
        add_shortcode( 'scorm_player', array( $this, 'render_player' ) );
        add_action( 'wp_footer', array( $this, 'render_modal_template' ) );
    }

    public function render_player( $atts ) {
        $atts = shortcode_atts( array(
            'id'     => 0,
            'width'  => '100%',
            'height' => '80vh'
        ), $atts );

        $post_id = intval( $atts['id'] );
        if ( ! $post_id ) return '';

        $user_id = get_current_user_id();
        $nonce_action = 'scorm_launch_' . $post_id . '_' . $user_id;
        $nonce = wp_create_nonce( $nonce_action );

        $params = array(
            'action'    => 'scorm_launch',
            'course_id' => $post_id,
            'nonce'     => $nonce,
        );
        $launch_url = add_query_arg( $params, admin_url( 'admin-ajax.php' ) );

        // Enqueue scripts only once
        if ( ! self::$scripts_enqueued ) {
            wp_enqueue_style( 'scorm-player-css', plugins_url( '../public/css/scorm-player.css', __FILE__ ), [], SCORM_PLAYER_VERSION );
            wp_enqueue_script( 'scorm-player-js', plugins_url( '../public/js/scorm-player.js', __FILE__ ), [ 'jquery' ], SCORM_PLAYER_VERSION, true );
            wp_enqueue_script( 'scorm-log-handler', plugins_url( '../public/js/scorm-log-handler.js', __FILE__ ), [ 'jquery' ], SCORM_PLAYER_VERSION, true );

            wp_localize_script( 'scorm-player-js', 'scormPlayer', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => $nonce,
            ]);
            
            self::$scripts_enqueued = true;
        }

        ob_start(); ?>
        <div class="scorm-player-wrapper"
             data-course-id="<?php echo esc_attr( $post_id ); ?>"
             data-launch-url="<?php echo esc_url( $launch_url ); ?>">
            <button class="scorm-launch-btn">🎓 Start Course</button>
            <div class="scorm-log-area"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_modal_template() {
        // Render modal only once even if multiple shortcodes exist
        if ( self::$modal_rendered ) {
            return;
        }
        self::$modal_rendered = true;
        ?>
        <div id="scorm-modal-overlay" class="scorm-modal-overlay" style="display:none;">
            <div class="scorm-modal-content">
                <span class="scorm-close">&times;</span>
                <iframe id="scorm-modal-iframe" src="" frameborder="0" allow="autoplay; fullscreen"></iframe>
            </div>
        </div>
        <?php
    }
}

// Only instantiate once
if ( ! isset( $GLOBALS['scorm_shortcode_instance'] ) ) {
    $GLOBALS['scorm_shortcode_instance'] = new SCORM_Shortcode();
}