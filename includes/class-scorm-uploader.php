<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Uploader {

    public function __construct() {
    }

    public function handle_upload($file) {
        if ( empty( $file ) || $file['error'] !== UPLOAD_ERR_OK ) {
            return array( 'success' => false, 'message' => 'Upload failed or no file provided.' );
        }

        if ( ! SCORM_Security::validate_file_type( $file ) ) {
            return array( 'success' => false, 'message' => 'Invalid file type.' );
        }

        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'scorm_courses/';
        wp_mkdir_p( $base_dir );

        $raw_filename = basename( $file['name'] );
        $sanitized = SCORM_Security::sanitize_filename( $raw_filename );
        if ( ! $sanitized ) {
            return array( 'success' => false, 'message' => 'Invalid filename after sanitization.' );
        }

        $dest_zip = $base_dir . $sanitized;
        if ( ! move_uploaded_file( $file['tmp_name'], $dest_zip ) ) {
            return array( 'success' => false, 'message' => 'Failed to move uploaded file.' );
        }

        $extract_dir = $base_dir . pathinfo( $sanitized, PATHINFO_FILENAME ) . '-' . time();
        wp_mkdir_p( $extract_dir );

        $zip = new ZipArchive();
        if ( $zip->open( $dest_zip ) === TRUE ) {
            $zip->extractTo( $extract_dir );
            $zip->close();
        } else {
            return array( 'success' => false, 'message' => 'Cannot open ZIP archive.' );
        }

        SCORM_Security::protect_directory( $extract_dir );

        $manifest = $extract_dir . DIRECTORY_SEPARATOR . 'imsmanifest.xml';
        if ( ! file_exists( $manifest ) ) {
            $found = false;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extract_dir));
            foreach ( $it as $f ) {
                if ( strtolower($f->getFilename()) === 'imsmanifest.xml' ) {
                    $manifest = $f->getPathname();
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                return array( 'success' => false, 'message' => 'imsmanifest.xml not found in package.' );
            }
        }

        $parser = new SCORM_Parser();
        $launch = $parser->get_launch_file( $manifest );
        if ( ! $launch ) {
            $candidates = array('index.html','index.htm','story.html');
            $launch = false;
            foreach ( $candidates as $c ) {
                $candidate_path = dirname($manifest) . DIRECTORY_SEPARATOR . $c;
                if ( file_exists( $candidate_path ) ) {
                    $launch = $c;
                    break;
                }
            }
        }

        if ( ! $launch ) {
            return array( 'success' => false, 'message' => 'Launch file not found in manifest or common candidates.' );
        }

        $title = $parser->get_title_from_manifest( $manifest );
        if ( empty( $title ) ) {
            $title = pathinfo( $sanitized, PATHINFO_FILENAME );
        }

        $post_id = wp_insert_post( array(
            'post_type' => 'scorm_course',
            'post_title' => $title,
            'post_status' => 'publish',
            'meta_input' => array(
                '_scorm_extract_dir' => $extract_dir,
                '_scorm_manifest' => $manifest,
                '_scorm_launch' => trailingslashit( str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], dirname( $manifest ) ) ) . ltrim( $launch, '/\\' ),
            ),
        ) );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return array( 'success' => false, 'message' => 'Failed to create SCORM course post.' );
        }

        return array( 'success' => true, 'message' => 'SCORM uploaded and created (ID: ' . $post_id . ')', 'post_id' => $post_id );
    }

    public static function ajax_upload() {
        if ( empty( $_REQUEST['_ajax_nonce'] ) || ! wp_verify_nonce( $_REQUEST['_ajax_nonce'], 'scorm_upload_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Missing or invalid nonce.' ) );
        }

        if ( ! SCORM_Security::can_upload() ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        if ( empty( $_FILES['scorm_zip'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
        }

        $uploader = new self();
        $res = $uploader->handle_upload( $_FILES['scorm_zip'] );
        if ( $res['success'] ) {
            wp_send_json_success( array( 'message' => $res['message'], 'post_id' => $res['post_id'] ?? 0 ) );
        } else {
            wp_send_json_error( array( 'message' => $res['message'] ) );
        }
    }
}

add_action( 'wp_ajax_scorm_ajax_upload', array( 'SCORM_Uploader', 'ajax_upload' ) );