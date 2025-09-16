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
$transient_pattern = '_transient_' . $transient_prefix . '%';
$timeout_pattern = '_transient_timeout_' . $transient_prefix . '%';

// Exécuter une requête SQL directe pour supprimer toutes les options qui correspondent à nos motifs.
// C'est la méthode la plus efficace pour supprimer des transients par lot.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $transient_pattern,
        $timeout_pattern
    )
);