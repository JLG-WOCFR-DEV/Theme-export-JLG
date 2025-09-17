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

        if (!is_array($selected_indices)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Erreur : La sélection des compositions est invalide.', 'error');
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
                $errors[] = sprintf('La composition à l\'index %d ne possède pas de slug valide.', $index);
                continue;
            }

            $title = isset($pattern['title']) ? sanitize_text_field($pattern['title']) : '';
            $content = '';

            if (isset($pattern['content'])) {
                $raw_content = (string) $pattern['content'];

                if (current_user_can('unfiltered_html')) {
                    $content = $raw_content;
                } else {
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

                    $allowed_html       = wp_kses_allowed_html('post');
                    $allowed_protocols  = wp_allowed_protocols();
                    $sanitized_content  = wp_kses($tokenized_content, $allowed_html, $allowed_protocols);

                    if (!empty($block_comment_tokens)) {
                        $sanitized_content = strtr($sanitized_content, $block_comment_tokens);
                    }

                    // Preserve Gutenberg block comments for editors lacking the `unfiltered_html` capability
                    // while still sanitizing the surrounding markup with wp_kses(). Without this, block
                    // structures would be flattened into static HTML, making imported patterns unusable.
                    $content = $sanitized_content;
                }
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
            add_settings_error('tejlg_import_messages', 'patterns_import_errors', implode(' ', array_unique($errors)), 'error');
        }

        if ($imported_count > 0) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', $imported_count . ' composition(s) ont été enregistrées avec succès.', 'success');
        } else {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', 'Aucune composition n\'a pu être enregistrée (elles existent peut-être déjà ou des erreurs sont survenues).', 'info');
        }
    }
}
