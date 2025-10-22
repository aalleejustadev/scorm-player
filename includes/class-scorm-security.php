<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Security {

    public static function can_upload() {
        return current_user_can( 'manage_options' );
    }

    public static function validate_file_type( $file ) {
        if ( empty( $file ) || empty( $file['type'] ) ) {
            return false;
        }
        $allowed = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream');
        return in_array( $file['type'], $allowed, true ) || preg_match('/\.zip$/i', $file['name']);
    }

    public static function sanitize_filename( $filename ) {
        $filename = sanitize_file_name( $filename );
        if ( preg_match( '/\.(php|phtml|php5|phar|exe|sh|bat)$/i', $filename ) ) {
            return false;
        }
        return $filename;
    }

    public static function protect_directory( $dir_path ) {
        if ( ! is_dir( $dir_path ) ) return;
        $htaccess = rtrim( $dir_path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $content = "Options -Indexes\n<FilesMatch '\\.(php|php5|phtml)$'>\nDeny from all\n</FilesMatch>\n";
            @file_put_contents( $htaccess, $content );
        }
    }

    public static function esc( $data ) {
        return esc_html( $data );
    }
}