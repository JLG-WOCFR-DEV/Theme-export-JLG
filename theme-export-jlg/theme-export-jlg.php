<?php
/**
 * Plugin Name:       Theme Export - JLG
 * Plugin URI:        https://#
 * Description:       Exporte, importe et gère les thèmes et compositions, avec un outil de création de thème enfant et un export portable.
 * Version:           3.0
 * Author:            Le Gousse Jérôme
 * Author URI:        https://#
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theme-export-jlg
 * Domain Path:       /languages
 */

// Sécurité : Empêche l'accès direct au fichier
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Définir les constantes utiles du plugin
$plugin_data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
define( 'TEJLG_VERSION', ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.0.0' );
define( 'TEJLG_PATH', plugin_dir_path( __FILE__ ) );
define( 'TEJLG_URL', plugin_dir_url( __FILE__ ) );

// Charger les classes nécessaires
require_once TEJLG_PATH . 'includes/class-tejlg-capabilities.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-history.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-notifications.php';
require_once TEJLG_PATH . 'includes/class-wp-background-process.php';
require_once TEJLG_PATH . 'includes/class-tejlg-zip-writer.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-process.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export.php';
require_once TEJLG_PATH . 'includes/class-tejlg-import.php';
require_once TEJLG_PATH . 'includes/class-tejlg-settings.php';
require_once TEJLG_PATH . 'includes/class-tejlg-theme-tools.php';
require_once TEJLG_PATH . 'includes/class-tejlg-site-health.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once TEJLG_PATH . 'includes/class-tejlg-cli.php';
}

/**
 * Fonction principale pour initialiser le plugin.
 */
function tejlg_run_plugin() {
    TEJLG_Capabilities::init();
    TEJLG_Site_Health::init();
    TEJLG_Export_Notifications::init();
    new TEJLG_Admin();
}

function tejlg_load_textdomain() {
    load_plugin_textdomain( 'theme-export-jlg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function tejlg_register_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['weekly'] ) ) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Une fois par semaine', 'theme-export-jlg' ),
        ];
    }

    return $schedules;
}

function tejlg_activate_plugin() {
    TEJLG_Export::ensure_cleanup_event_scheduled();
    TEJLG_Export::reschedule_theme_export_event();
}

function tejlg_deactivate_plugin() {
    TEJLG_Export::clear_scheduled_theme_export_event();
    TEJLG_Export::clear_cleanup_event();
}


add_action('wp_ajax_tejlg_start_theme_export', ['TEJLG_Export', 'ajax_start_theme_export']);
add_action('wp_ajax_tejlg_theme_export_status', ['TEJLG_Export', 'ajax_get_theme_export_status']);
add_action('wp_ajax_tejlg_download_theme_export', ['TEJLG_Export', 'ajax_download_theme_export']);
add_action('wp_ajax_tejlg_cancel_theme_export', ['TEJLG_Export', 'ajax_cancel_theme_export']);
add_action('wp_ajax_tejlg_preview_exclusion_patterns', ['TEJLG_Admin_Export_Page', 'ajax_preview_exclusion_patterns']);
add_action('admin_init', ['TEJLG_Export', 'cleanup_stale_jobs']);
add_action('init', ['TEJLG_Export', 'maybe_schedule_theme_export_event']);
add_action('init', ['TEJLG_Export', 'ensure_cleanup_event_scheduled']);
add_action(TEJLG_Export::SCHEDULE_EVENT_HOOK, ['TEJLG_Export', 'run_scheduled_theme_export']);
add_action(TEJLG_Export::CLEANUP_EVENT_HOOK, ['TEJLG_Export', 'cleanup_stale_jobs']);
add_action( 'plugins_loaded', 'tejlg_load_textdomain' );
add_action( 'plugins_loaded', 'tejlg_run_plugin' );
add_filter( 'cron_schedules', 'tejlg_register_cron_schedules' );

register_activation_hook( __FILE__, 'tejlg_activate_plugin' );
register_deactivation_hook( __FILE__, 'tejlg_deactivate_plugin' );
