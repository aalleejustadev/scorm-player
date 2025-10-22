<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCORM_Parser {
    public function get_launch_file($manifest_path) {
        if ( ! file_exists($manifest_path) ) return false;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($manifest_path);
        if ( ! $xml ) return false;

        if ( isset($xml->resources->resource) ) {
            foreach ( $xml->resources->resource as $res ) {
                $attrs = $res->attributes();
                if ( isset($attrs['href']) ) {
                    return (string) $attrs['href'];
                }
                if ( isset($res->file) ) {
                    foreach ( $res->file as $file ) {
                        $fattrs = $file->attributes();
                        if ( isset($fattrs['href']) ) {
                            return (string) $fattrs['href'];
                        }
                    }
                }
            }
        }

        return false;
    }

    public function get_title_from_manifest($manifest_path) {
        if ( ! file_exists($manifest_path) ) return '';
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($manifest_path);
        if ( ! $xml ) return '';
        if ( isset($xml->organizations->organization->title) ) {
            return (string) $xml->organizations->organization->title;
        }
        return '';
    }
}