<?php
class TEJLG_Theme_Tools {

    public static function create_child_theme( $child_name ) {
        if (
            ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ||
            ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
        ) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_error',
                esc_html__(
                    "Erreur : Les modifications de fichiers sont désactivées sur ce site.",
                    'theme-export-jlg'
                ),
                'error'
            );
            return;
        }

        if ( is_string( $child_name ) ) {
            $child_name = wp_unslash( $child_name );
        }

        $sanitized_child_name = is_string( $child_name ) ? sanitize_text_field( $child_name ) : '';

        $parent_theme = wp_get_theme();
        if ( $parent_theme->parent() ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Le thème actif est déjà un thème enfant. Vous ne pouvez pas créer un enfant d\'un enfant.', 'theme-export-jlg'), 'error');
            return;
        }

        if ( '' === $sanitized_child_name ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Le nom du thème enfant ne peut pas être vide.', 'theme-export-jlg'), 'error');
            return;
        }

        $theme_root = get_theme_root();

        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( ! class_exists( 'WP_Upgrader' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        $filesystem_url  = admin_url( 'themes.php' );
        $filesystem_creds = request_filesystem_credentials( $filesystem_url, '', false, $theme_root );

        if ( false === $filesystem_creds ) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_error',
                esc_html__(
                    'Erreur : Les identifiants du système de fichiers sont requis pour créer le thème enfant.',
                    'theme-export-jlg'
                ),
                'error'
            );
            return;
        }

        if ( ! WP_Filesystem( $filesystem_creds, $theme_root ) ) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_error',
                esc_html__(
                    "Erreur : Impossible d'initialiser le système de fichiers WordPress avec les identifiants fournis.",
                    'theme-export-jlg'
                ),
                'error'
            );
            return;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem || ! $wp_filesystem->is_writable( $theme_root ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Le dossier des thèmes (wp-content/themes) n\'est pas accessible en écriture par le serveur.', 'theme-export-jlg'), 'error');
            return;
        }

        $child_slug = sanitize_title( $sanitized_child_name );
        $child_dir = $theme_root . '/' . $child_slug;
        if ( $wp_filesystem->exists( $child_dir ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Un thème avec le même nom de dossier existe déjà.', 'theme-export-jlg'), 'error');
            return;
        }

        if ( ! $wp_filesystem->mkdir( $child_dir, FS_CHMOD_DIR ) ) {
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Impossible de créer le dossier du thème enfant. Vérifiez les permissions du serveur.', 'theme-export-jlg'), 'error');
            return;
        }

        $sanitized_theme_uri    = esc_url_raw( $parent_theme->get( 'ThemeURI' ) );
        $sanitized_parent_name  = sanitize_text_field( $parent_theme->get( 'Name' ) );
        $sanitized_author_name  = sanitize_text_field( $parent_theme->get( 'Author' ) );
        $sanitized_author_uri   = esc_url_raw( $parent_theme->get( 'AuthorURI' ) );
        $sanitized_template     = sanitize_text_field( $parent_theme->get_stylesheet() );

        $child_description = sprintf(__('Thème enfant pour %s', 'theme-export-jlg'), $sanitized_parent_name);
        $css_lines = [
            '/*',
            sprintf('Theme Name: %s', $sanitized_child_name),
            sprintf('Theme URI: %s', $sanitized_theme_uri),
            sprintf('Description: %s', $child_description),
            sprintf('Author: %s', $sanitized_author_name),
            sprintf('Author URI: %s', $sanitized_author_uri),
            sprintf('Template: %s', $sanitized_template),
            sprintf('Version: %s', TEJLG_VERSION),
            '*/',
            '',
        ];

        $css_content = implode("\n", $css_lines);

        $function_name_prefix   = preg_replace( '/[^A-Za-z0-9_]/', '_', $child_slug );
        if ( '' === $function_name_prefix ) {
            $function_name_prefix = 'tejlg_child_theme';
        }
        if ( ! preg_match( '/^[A-Za-z_]/', $function_name_prefix ) ) {
            $function_name_prefix = 'tejlg_' . $function_name_prefix;
        }
        $sanitized_stylesheet   = sanitize_key( $parent_theme->get_stylesheet() );
        $php_template = <<<'PHP'
<?php
/**
 * Enqueue scripts and styles.
 */
function %1$s_enqueue_styles() {
    $child_theme = wp_get_theme();
    $parent_version = $child_theme->parent() ? $child_theme->parent()->get( 'Version' ) : $child_theme->get( 'Version' );
    $child_version = $child_theme->get( 'Version' );
    wp_enqueue_style( '%2$s-parent-style', get_template_directory_uri() . '/style.css', array(), $parent_version );
    wp_enqueue_style( '%2$s-child-style', get_stylesheet_uri(), array( '%2$s-parent-style' ), $child_version );
}
add_action( 'wp_enqueue_scripts', '%1$s_enqueue_styles' );
PHP;

        $php_content = sprintf(
            $php_template,
            $function_name_prefix,
            $sanitized_stylesheet
        );

        $css_content = wp_check_invalid_utf8( $css_content, true );
        $php_content = wp_check_invalid_utf8( $php_content, true );

        $style_file     = $child_dir . '/style.css';
        $functions_file = $child_dir . '/functions.php';

        if ( ! $wp_filesystem->put_contents( $style_file, $css_content, FS_CHMOD_FILE ) ) {
            self::remove_child_theme_directory( $child_dir );
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Impossible de créer le fichier style.css du thème enfant.', 'theme-export-jlg'), 'error');
            return;
        }

        if ( ! $wp_filesystem->put_contents( $functions_file, $php_content, FS_CHMOD_FILE ) ) {
            self::remove_child_theme_directory( $child_dir );
            add_settings_error('tejlg_admin_messages', 'child_theme_error', esc_html__('Erreur : Impossible de créer le fichier functions.php du thème enfant.', 'theme-export-jlg'), 'error');
            return;
        }

        $themes_page_url = admin_url('themes.php');
        $success_message = sprintf(
            /* translators: 1: Child theme name, 2: URL to the themes admin page. */
            __("Le thème enfant \"%1$s\" a été créé avec succès ! Vous pouvez maintenant <a href=\"%2$s\">l'activer depuis la page des thèmes</a>.", 'theme-export-jlg'),
            esc_html($sanitized_child_name),
            esc_url($themes_page_url)
        );
        add_settings_error(
            'tejlg_admin_messages',
            'child_theme_success',
            wp_kses_post($success_message),
            'success'
        );
    }

    private static function remove_child_theme_directory( $child_dir ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            return;
        }

        $files = array( $child_dir . '/style.css', $child_dir . '/functions.php' );

        foreach ( $files as $file ) {
            if ( $wp_filesystem->exists( $file ) ) {
                $wp_filesystem->delete( $file );
            }
        }

        if ( $wp_filesystem->is_dir( $child_dir ) ) {
            $dir_files = $wp_filesystem->dirlist( $child_dir );

            if ( is_array( $dir_files ) && empty( $dir_files ) ) {
                $wp_filesystem->delete( $child_dir, true );
            }
        }
    }
}
