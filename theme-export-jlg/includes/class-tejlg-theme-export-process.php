<?php
/**
 * Background process for theme exports.
 *
 * @package ThemeExportJLG
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TEJLG_PATH . 'includes/class-tejlg-background-process.php';

class TEJLG_Theme_Export_Process extends WP_Background_Process {

    /**
     * Action identifier.
     *
     * @var string
     */
    protected $action = 'tejlg_theme_export';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Retrieve singleton instance.
     *
     * @return self
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Process a queue item.
     *
     * @param string $job_id Job identifier.
     *
     * @return string|false
     */
    protected function task( $job_id ) {
        if ( ! is_string( $job_id ) || '' === $job_id ) {
            return false;
        }

        return TEJLG_Export::process_theme_export_job( $job_id );
    }

    /**
     * When the queue is completed.
     */
    protected function complete() {
        parent::complete();
    }
}
