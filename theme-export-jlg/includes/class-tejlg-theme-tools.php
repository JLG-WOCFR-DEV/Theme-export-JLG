<?php
class TEJLG_Theme_Tools {

    public static function create_child_theme( $child_name ) {
        $parent_theme = wp_get_theme();
        if ( $parent_theme->parent() ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', 'Erreur : Le thème actif est déjà un thème enfant. Vous ne pouvez pas créer un enfant d\'un enfant.', 'error');
            return;
        }

        if ( empty( $child_name ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', 'Erreur : Le nom du thème enfant ne peut pas être vide.', 'error');
            return;
        }

        $theme_root = get_theme_root();
        if ( ! is_writable( $theme_root ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', 'Erreur : Le dossier des thèmes (wp-content/themes) n\'est pas accessible en écriture par le serveur.', 'error');
            return;
        }

        $child_slug = sanitize_title( $child_name );
        $child_dir = $theme_root . '/' . $child_slug;
        if ( file_exists( $child_dir ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', 'Erreur : Un thème avec le même nom de dossier existe déjà.', 'error');
            return;
        }

        wp_mkdir_p( $child_dir );

        $css_content = sprintf(
'/*
Theme Name: %1$s
Theme URI: %2$s
Description: Thème enfant pour %3$s
Author: %4$s
Author URI: %5$s
Template: %6$s
Version: 1.0.0
*/
',
            esc_html( $child_name ),
            esc_url( $parent_theme->get('ThemeURI') ),
            esc_html( $parent_theme->get('Name') ),
            esc_html( $parent_theme->get('Author') ),
            esc_url( $parent_theme->get('AuthorURI') ),
            esc_html( $parent_theme->get_stylesheet() )
        );

        $function_name_prefix = str_replace( '-', '_', $child_slug );
        $php_content = sprintf(
'<?php
/**
 * Enqueue scripts and styles.
 */
function %1$s_enqueue_styles() {
    wp_enqueue_style( \'%2$s-parent-style\', get_template_directory_uri() . \'/style.css\' );
}
add_action( \'wp_enqueue_scripts\', \'%1$s_enqueue_styles\' );
',
            $function_name_prefix,
            $parent_theme->get_stylesheet()
        );

        file_put_contents( $child_dir . '/style.css', $css_content );
        file_put_contents( $child_dir . '/functions.php', $php_content );

        $themes_page_url = admin_url('themes.php');
        add_settings_error(
            'tejlg_admin_messages', 
            'child_theme_success', 
            sprintf(
                'Le thème enfant "%s" a été créé avec succès ! Vous pouvez maintenant <a href="%s">l\'activer depuis la page des thèmes</a>.',
                esc_html($child_name),
                $themes_page_url
            ),
            'success'
        );
    }
}