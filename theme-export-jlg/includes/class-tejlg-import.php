<?php
class TEJLG_Import {

    public static function import_theme($file) {
        if (!current_user_can('install_themes')) {
            if (isset($file['tmp_name'])) {
                @unlink($file['tmp_name']);
            }

            add_settings_error(
                'tejlg_import_messages',
                'theme_import_cap_missing',
                esc_html__('Vous n\'avez pas l\'autorisation d\'installer des thèmes sur ce site.', 'theme-export-jlg'),
                'error'
            );

            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($file['tmp_name'], ['overwrite_package' => true]);
        @unlink($file['tmp_name']);

        $message_type = is_wp_error($result) || !$result ? 'error' : 'success';
        $message_text = is_wp_error($result) ? $result->get_error_message() : esc_html__('Le thème a été installé avec succès !', 'theme-export-jlg');

        add_settings_error('tejlg_import_messages', 'theme_import_status', $message_text, $message_type);
    }

    public static function handle_patterns_upload_step1($file) {
        $json_content = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);

        $patterns = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($patterns)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : Le fichier n\'est pas un fichier JSON valide.', 'theme-export-jlg'), 'error');
            return;
        }

        $patterns = array_values(
            array_filter(
                $patterns,
                static function ($pattern) {
                    return is_array($pattern)
                        && array_key_exists('title', $pattern)
                        && array_key_exists('content', $pattern);
                }
            )
        );

        $transient_id = 'tejlg_' . md5(uniqid('', true));

        if (empty($patterns)) {
            delete_transient($transient_id);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__('Erreur : Aucune composition valide (titre + contenu) n\'a été trouvée dans le fichier fourni.', 'theme-export-jlg'),
                'error'
            );

            wp_safe_redirect(admin_url('admin.php?page=theme-export-jlg&tab=import'));
            exit;
        }
        set_transient($transient_id, $patterns, 15 * MINUTE_IN_SECONDS);

        wp_safe_redirect(
            admin_url('admin.php?page=theme-export-jlg&tab=import&action=preview_patterns&transient_id=' . $transient_id)
        );
        exit;
    }

    public static function handle_patterns_import_step2($transient_id, $selected_indices) {
        $all_patterns = get_transient($transient_id);

        if (false === $all_patterns) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : La session d\'importation a expiré. Veuillez réessayer.', 'theme-export-jlg'), 'error');
            return;
        }

        if (!is_array($selected_indices)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : La sélection des compositions est invalide.', 'theme-export-jlg'), 'error');
            return;
        }

        delete_transient($transient_id);

        $imported_count = 0;
        $errors = [];

        foreach ($selected_indices as $index) {
            $index = intval($index);
            if (!isset($all_patterns[$index]) || !is_array($all_patterns[$index])) {
                continue;
            }

            $pattern = $all_patterns[$index];

            $raw_slug = isset($pattern['slug']) ? (string) $pattern['slug'] : '';
            $raw_slug = trim($raw_slug);

            if (0 === strpos($raw_slug, 'custom-patterns/')) {
                $raw_slug = substr($raw_slug, strlen('custom-patterns/'));
            }

            $slug = sanitize_title($raw_slug);
            if ('' === $slug) {
                $errors[] = sprintf(__('La composition à l\'index %d ne possède pas de slug valide.', 'theme-export-jlg'), $index);
                continue;
            }

            $title = isset($pattern['title']) ? sanitize_text_field($pattern['title']) : '';
            $content = '';

            if (isset($pattern['content'])) {
                $content = self::sanitize_pattern_content_for_current_user((string) $pattern['content']);
            }

            $existing_block = get_page_by_path($slug, OBJECT, 'wp_block');

            if ($existing_block instanceof WP_Post) {
                $post_data = [
                    'ID'           => $existing_block->ID,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_name'    => $slug,
                ];

                $result = wp_update_post(wp_slash($post_data), true);
            } else {
                $post_data = [
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'wp_block',
                    'post_author'  => get_current_user_id(),
                ];

                $result = wp_insert_post(wp_slash($post_data), true);
            }

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }

            if ($result) {
                $imported_count++;
            }
        }

        if (!empty($errors)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_errors', esc_html(implode(' ', array_unique($errors))), 'error');
        }

        if ($imported_count > 0) {
            $success_message = sprintf(
                _n('%d composition a été enregistrée avec succès.', '%d compositions ont été enregistrées avec succès.', $imported_count, 'theme-export-jlg'),
                $imported_count
            );

            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html($success_message), 'success');
        } else {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Aucune composition n\'a pu être enregistrée (elles existent peut-être déjà ou des erreurs sont survenues).', 'theme-export-jlg'), 'info');
        }
    }

    /**
     * Sanitize pattern content for users without the unfiltered_html capability while preserving block structure.
     */
    private static function sanitize_pattern_content_for_current_user($raw_content) {
        if (current_user_can('unfiltered_html')) {
            return $raw_content;
        }

        $block_comment_tokens = [];

        $tokenized_content = preg_replace_callback(
            '/<!--\s*(\/?.*?wp:[^>]*?)\s*-->/',
            function ($matches) use (&$block_comment_tokens) {
                $token = '[[TEJLG_WP_COMMENT_' . count($block_comment_tokens) . ']]';
                $block_comment_tokens[$token] = $matches[0];

                return $token;
            },
            $raw_content
        );

        $allowed_html      = wp_kses_allowed_html('post');
        $allowed_protocols = wp_allowed_protocols();
        $sanitized_content = wp_kses($tokenized_content, $allowed_html, $allowed_protocols);

        if (!empty($block_comment_tokens)) {
            $sanitized_content = strtr($sanitized_content, $block_comment_tokens);
        }

        return $sanitized_content;
    }
}
