<?php
/**
 * Fichier de désinstallation pour Theme Export - JLG
 *
 * Ce fichier est exécuté lorsqu'un utilisateur supprime le plugin.
 * Il nettoie les données temporaires (transients) que le plugin a pu créer.
 *
 * @package Theme_Export_JLG
 */

// Sécurité : S'assurer que ce fichier est bien appelé par WordPress lors de la désinstallation.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Accéder à l'objet de la base de données de WordPress.
global $wpdb;

// Définir le préfixe unique utilisé pour nos transients.
$transient_prefix = 'tejlg_';

// Préparer les motifs de recherche pour les transients. WordPress stocke les transients
// sous deux entrées dans la table wp_options : une pour la donnée et une pour son délai d'expiration.
$escaped_transient_prefix = $wpdb->esc_like( $transient_prefix );
$transient_pattern        = '_transient_' . $escaped_transient_prefix . '%';
$timeout_pattern          = '_transient_timeout_' . $escaped_transient_prefix . '%';

// Exécuter une requête SQL directe pour supprimer toutes les options qui correspondent à nos motifs.
// C'est la méthode la plus efficace pour supprimer des transients par lot.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $transient_pattern,
        $timeout_pattern
    )
);

$job_option_prefix  = 'tejlg_export_job_';
$escaped_job_option = $wpdb->esc_like( $job_option_prefix ) . '%';

$job_options = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        $escaped_job_option
    )
);

if ( ! empty( $job_options ) ) {
    foreach ( $job_options as $job_option ) {
        if ( empty( $job_option->option_name ) ) {
            continue;
        }

        $job_data = maybe_unserialize( $job_option->option_value );

        if ( is_array( $job_data ) && ! empty( $job_data['zip_path'] ) ) {
            $zip_path = (string) $job_data['zip_path'];

            if ( '' !== $zip_path && @is_file( $zip_path ) ) {
                @unlink( $zip_path );
            }
        }

        delete_option( $job_option->option_name );
    }
}

if ( function_exists( 'get_temp_dir' ) ) {
    $temp_dir = get_temp_dir();

    if ( is_string( $temp_dir ) && '' !== $temp_dir ) {
        $temp_dir = trailingslashit( $temp_dir );

        if ( @is_dir( $temp_dir ) && @is_readable( $temp_dir ) ) {
            $pattern_files = @glob( $temp_dir . 'tejlg-patterns*' );

            if ( is_array( $pattern_files ) ) {
                foreach ( $pattern_files as $pattern_file ) {
                    if ( @is_file( $pattern_file ) ) {
                        @unlink( $pattern_file );
                    }
                }
            }
        }
    }
}

// Supprimer l'option stockant la taille des icônes de métriques (mono et multisites).
delete_option( 'tejlg_metrics_icon_size' );
delete_site_option( 'tejlg_metrics_icon_size' );
