<?php
/**
 * Plugin Name: SCORM Player
 * Description: Upload and display SCORM courses in WordPress via shortcode. [scorm_player id=""]
 * Version: 1.0.1
 * Author: Ali Murtaza
 * Text Domain: scorm-player
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCORM_PLAYER_VERSION', '1.0.1' );
define( 'SCORM_PLAYER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCORM_PLAYER_URL', plugin_dir_url( __FILE__ ) );

// Load includes
foreach ( glob( SCORM_PLAYER_PATH . 'includes/class-*.php' ) as $file ) {
    require_once $file;
}

// Initialize plugin when plugins_loaded
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'SCORM_Loader' ) ) {
        new SCORM_Loader();
    }
});