<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_CPT {
    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
    }

    public function register_cpt() {
        $labels = array(
            'name' => __( 'SCORM Courses', 'scorm-player' ),
            'singular_name' => __( 'SCORM Course', 'scorm-player' ),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title'),
        );

        register_post_type( 'scorm_course', $args );
    }
}