<?php

class TEJLG_Admin_Profiles_Page extends TEJLG_Admin_Page {
    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    /**
     * Handles incoming profile requests routed through admin-post.php.
     *
     * @return array<string,mixed>|WP_Error|null
     */
    public function handle_request() {
        $action = isset($_REQUEST['action']) ? sanitize_key((string) $_REQUEST['action']) : '';

        if ('tejlg_profiles_export' === $action) {
            return $this->handle_export_request();
        }

        if ('tejlg_profiles_import' === $action) {
            return $this->handle_import_request();
        }

        return null;
    }

    public function render() {
        settings_errors('tejlg_profiles_messages');

        $this->render_template('profiles.php', [
            'page_slug'                 => $this->page_slug,
            'export_action'             => 'tejlg_profiles_export',
            'export_nonce_action'       => 'tejlg_profiles_export_action',
            'export_nonce_name'         => 'tejlg_profiles_export_nonce',
            'import_action'             => 'tejlg_profiles_import',
            'import_nonce_action'       => 'tejlg_profiles_import_action',
            'import_nonce_name'         => 'tejlg_profiles_import_nonce',
        ]);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function handle_export_request() {
        $package = TEJLG_Settings::build_export_package();
        $json    = TEJLG_Settings::encode_export_package($package);

        if (is_wp_error($json)) {
            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_export_json_error',
                $json->get_error_message(),
                'error'
            );

            return $json;
        }

        $filename = sprintf('theme-export-profiles-%s.json', gmdate('Ymd-His'));

        return [
            'type'     => 'export',
            'json'     => (string) $json,
            'filename' => $filename,
            'package'  => $package,
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function handle_import_request() {
        $file = isset($_FILES['tejlg_profiles_file']) ? $_FILES['tejlg_profiles_file'] : null;

        if (!is_array($file) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            $error_code = is_array($file) && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            $message    = $this->get_upload_error_message($error_code);

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_upload_error',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_upload_error', $message);
        }

        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        if ('' === $tmp_name || !file_exists($tmp_name)) {
            $message = esc_html__("Erreur : le fichier téléchargé n'a pas pu être trouvé.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_missing_file',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_missing_file', $message);
        }

        $max_bytes = (int) apply_filters('tejlg_profiles_import_max_bytes', 1024 * 1024);

        if ($max_bytes > 0) {
            $file_size = @filesize($tmp_name);

            if (false !== $file_size && $file_size > $max_bytes) {
                @unlink($tmp_name);

                $message = sprintf(
                    esc_html__("Erreur : le fichier téléchargé dépasse la taille autorisée (%s maximum).", 'theme-export-jlg'),
                    size_format($max_bytes)
                );

                add_settings_error(
                    'tejlg_profiles_messages',
                    'profiles_import_size_limit',
                    $message,
                    'error'
                );

                return new WP_Error('tejlg_profiles_import_size_limit', $message);
            }
        }

        $handle = fopen($tmp_name, 'rb');

        if (false === $handle) {
            @unlink($tmp_name);

            $message = esc_html__("Erreur : le fichier téléchargé n'a pas pu être lu.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_read_error',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_read_error', $message);
        }

        $length   = ($max_bytes > 0) ? $max_bytes + 1 : -1;
        $contents = stream_get_contents($handle, $length);
        fclose($handle);
        @unlink($tmp_name);

        if (false === $contents) {
            $message = esc_html__("Erreur : le fichier téléchargé n'a pas pu être lu.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_read_error',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_read_error', $message);
        }

        if ($max_bytes > 0 && strlen($contents) > $max_bytes) {
            $message = sprintf(
                esc_html__("Erreur : le fichier téléchargé dépasse la taille autorisée (%s maximum).", 'theme-export-jlg'),
                size_format($max_bytes)
            );

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_size_limit',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_size_limit', $message);
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            $error_message = function_exists('json_last_error_msg') ? json_last_error_msg() : '';
            $error_message = $error_message ? sprintf('%s (%s)', esc_html__("Erreur : le fichier fourni n'est pas un JSON valide.", 'theme-export-jlg'), esc_html($error_message)) : esc_html__("Erreur : le fichier fourni n'est pas un JSON valide.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_json_error',
                $error_message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_json_error', $error_message);
        }

        if (!isset($decoded['settings']) || !is_array($decoded['settings'])) {
            $message = esc_html__("Erreur : le fichier fourni ne contient pas de réglages à appliquer.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_missing_settings',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_missing_settings', $message);
        }

        $signature = TEJLG_Settings::verify_signature($decoded);

        if (empty($signature['valid'])) {
            if (!empty($signature['legacy_valid'])) {
                $message = esc_html__("Erreur : ce profil provient d'une ancienne version et doit être réexporté avant import.", 'theme-export-jlg');

                add_settings_error(
                    'tejlg_profiles_messages',
                    'profiles_import_legacy_signature',
                    $message,
                    'error'
                );

                return new WP_Error('tejlg_profiles_import_legacy_signature', $message);
            }

            $message = esc_html__("Erreur : la signature du profil est invalide.", 'theme-export-jlg');

            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_invalid_signature',
                $message,
                'error'
            );

            return new WP_Error('tejlg_profiles_import_invalid_signature', $message);
        }

        $results = TEJLG_Settings::apply_snapshot(
            $decoded['settings'],
            [
                'origin' => 'profiles_import',
                'schema' => isset($decoded['schema']) ? (string) $decoded['schema'] : '',
            ]
        );

        $changes = array_filter($results);

        if (!empty($changes)) {
            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_success',
                esc_html__("Le profil a été importé avec succès.", 'theme-export-jlg'),
                'success'
            );
        } else {
            add_settings_error(
                'tejlg_profiles_messages',
                'profiles_import_no_changes',
                esc_html__("Aucune modification n'a été appliquée car le profil correspond déjà aux réglages actifs.", 'theme-export-jlg'),
                'info'
            );
        }

        return [
            'type'    => 'import',
            'status'  => 'success',
            'results' => $results,
        ];
    }

    private function get_upload_error_message($error_code) {
        switch ((int) $error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return esc_html__("Erreur : le fichier téléchargé dépasse la taille autorisée.", 'theme-export-jlg');
            case UPLOAD_ERR_PARTIAL:
                return esc_html__("Erreur : le fichier n'a été que partiellement téléchargé.", 'theme-export-jlg');
            case UPLOAD_ERR_NO_FILE:
                return esc_html__("Erreur : aucun fichier n'a été envoyé.", 'theme-export-jlg');
            case UPLOAD_ERR_NO_TMP_DIR:
                return esc_html__("Erreur : le dossier temporaire est manquant sur le serveur.", 'theme-export-jlg');
            case UPLOAD_ERR_CANT_WRITE:
                return esc_html__("Erreur : impossible d'écrire le fichier sur le disque.", 'theme-export-jlg');
            case UPLOAD_ERR_EXTENSION:
                return esc_html__("Erreur : une extension PHP a bloqué le téléchargement du fichier.", 'theme-export-jlg');
            default:
                return esc_html__("Erreur : le téléchargement du fichier a échoué.", 'theme-export-jlg');
        }
    }
}
