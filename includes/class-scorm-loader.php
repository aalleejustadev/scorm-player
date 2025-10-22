<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Loader {
    public function __construct() {
        // Register Custom Post Type
        if ( class_exists( 'SCORM_CPT' ) ) {
            new SCORM_CPT();
        }

        // Always include SCORM launch handler (needed for front-end AJAX)
        require_once SCORM_PLAYER_PATH . 'includes/class-scorm-launch.php';

        // Admin menu & uploader
        if ( is_admin() ) {
            require_once SCORM_PLAYER_PATH . 'admin/class-scorm-admin-menu.php';
            if ( class_exists( 'SCORM_Admin_Menu' ) ) {
                new SCORM_Admin_Menu();
            }
        }

        // Frontend shortcode
        if ( class_exists( 'SCORM_Shortcode' ) ) {
            new SCORM_Shortcode();
        }

        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'scorm-player',
            SCORM_PLAYER_URL . 'public/css/scorm-player.css',
            [],
            SCORM_PLAYER_VERSION
        );

        wp_enqueue_script(
            'scorm-player',
            SCORM_PLAYER_URL . 'public/js/scorm-player.js',
            ['jquery'],
            SCORM_PLAYER_VERSION,
            true
        );
    }
}