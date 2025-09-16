<?php
class TEJLG_Import {

    public static function import_theme($file) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($file['tmp_name'], ['overwrite_package' => true]);
        @unlink($file['tmp_name']);

        $message_type = is_wp_error($result) || !$result ? 'error' : 'success';
        $message_text = is_wp_error($result) ? $result->get_error_message() : 'Le thème a été installé avec succès !';
        
        add_settings_error('tejlg_import_messages', 'theme_import_status', $message_text, $message_type);
    }

    public static function handle_patterns_upload_step1($file) {
        $json_content = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);

        $patterns = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($patterns)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Erreur : Le fichier n\'est pas un fichier JSON valide.', 'error');
            return;
        }
        
        if (empty($patterns)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Information : Le fichier ne contient aucune composition.', 'info');
            return;
        }

        $transient_id = 'tejlg_' . md5(uniqid());
        set_transient($transient_id, $patterns, 15 * MINUTE_IN_SECONDS);

        wp_redirect(admin_url('admin.php?page=theme-export-jlg&tab=import&action=preview_patterns&transient_id=' . $transient_id));
        exit;
    }

    public static function handle_patterns_import_step2($transient_id, $selected_indices) {
        $all_patterns = get_transient($transient_id);

        if (false === $all_patterns) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Erreur : La session d\'importation a expiré. Veuillez réessayer.', 'error');
            return;
        }

        delete_transient($transient_id);
        
        $imported_count = 0;
        foreach ($selected_indices as $index) {
            $index = intval($index);
            if (isset($all_patterns[$index])) {
                $pattern = $all_patterns[$index];
                if (!WP_Block_Patterns_Registry::get_instance()->is_registered($pattern['slug'])) {
                    register_block_pattern($pattern['slug'], [
                        'title'   => $pattern['title'],
                        'content' => $pattern['content'],
                    ]);
                    $imported_count++;
                }
            }
        }
        
        if ($imported_count > 0) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', $imported_count . ' composition(s) ont été importées avec succès !', 'success');
        } else {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Aucune nouvelle composition n\'a été importée (peut-être existaient-elles déjà).', 'info');
        }
    }
}