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
require_once TEJLG_PATH . 'includes/class-tejlg-admin.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export.php';
require_once TEJLG_PATH . 'includes/class-tejlg-import.php';
require_once TEJLG_PATH . 'includes/class-tejlg-theme-tools.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once TEJLG_PATH . 'includes/class-tejlg-cli.php';
}

/**
 * Fonction principale pour initialiser le plugin.
 */
function tejlg_run_plugin() {
    new TEJLG_Admin();
}

function tejlg_load_textdomain() {
    load_plugin_textdomain( 'theme-export-jlg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'tejlg_load_textdomain' );
add_action( 'plugins_loaded', 'tejlg_run_plugin' );
