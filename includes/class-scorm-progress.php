<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Progress {

    public function __construct() {
        add_action( 'wp_ajax_scorm_save_progress', [ $this, 'save_progress' ] );
        add_action( 'wp_ajax_nopriv_scorm_save_progress', [ $this, 'save_progress' ] );
        add_action( 'wp_ajax_scorm_get_progress', [ $this, 'get_progress' ] );
        add_action( 'wp_ajax_nopriv_scorm_get_progress', [ $this, 'get_progress' ] );
    }

    /**
     * Save SCORM progress + temporary logging.
     */
    public function save_progress() {
    $user_id   = get_current_user_id();
    $course_id = intval( $_POST['course_id'] ?? 0 );

    if ( ! $user_id || ! $course_id ) {
        wp_send_json_error( 'Invalid data' );
    }

    $meta_key = "scorm_progress_{$course_id}";
    $progress = get_user_meta( $user_id, $meta_key, true );
    if ( ! is_array( $progress ) ) {
        $progress = [];
    }

    // Handle bulk JSON payload
    if ( isset( $_POST['progress'] ) && is_string( $_POST['progress'] ) ) {
        $incoming = json_decode( stripslashes( $_POST['progress'] ), true );
        if ( is_array( $incoming ) ) {
            foreach ( $incoming as $key => $value ) {
                $progress[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
            }
        }
    }

    // Handle single key/value pair
    if ( isset( $_POST['key'] ) && isset( $_POST['value'] ) ) {
        $progress[ sanitize_text_field( $_POST['key'] ) ] = sanitize_text_field( $_POST['value'] );
    }

    update_user_meta( $user_id, $meta_key, $progress );
    wp_send_json_success( [ 'saved' => true ] );
}

    /**
     * Fetch saved SCORM progress.
     */
    public function get_progress() {
        $user_id   = get_current_user_id();
        $course_id = intval( $_GET['course_id'] ?? 0 );

        if ( ! $user_id || ! $course_id ) {
            wp_send_json_error( 'Invalid request' );
        }

        $meta_key = "scorm_progress_{$course_id}";
        $progress = get_user_meta( $user_id, $meta_key, true );

        wp_send_json_success( $progress ?: [] );
    }
}

new SCORM_Progress();