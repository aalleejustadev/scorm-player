<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Admin_Menu {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_menu_page() {
        add_menu_page(
            'SCORM Player',
            'SCORM Player',
            'manage_options',
            'scorm-player',
            array( $this, 'render_upload_page' ),
            'dashicons-upload'
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_scorm-player' ) {
            return;
        }
        wp_enqueue_script( 'scorm-admin-upload', SCORM_PLAYER_URL . 'admin/js/admin-upload.js', array('jquery'), SCORM_PLAYER_VERSION, true );
        wp_localize_script( 'scorm-admin-upload', 'scormAdminUpload', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('scorm_upload_nonce'),
        ) );
    }

    public function render_upload_page() {
        include SCORM_PLAYER_PATH . 'admin/views/upload-page.php';
    }
}