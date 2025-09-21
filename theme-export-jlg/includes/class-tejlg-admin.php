<?php
class TEJLG_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_form_requests' ] );
    }

    public function add_menu_page() {
        add_menu_page(
            __('Theme Export - JLG', 'theme-export-jlg'),
            __('Theme Export', 'theme-export-jlg'),
            'manage_options',
            'theme-export-jlg',
            [ $this, 'render_admin_page' ],
            'dashicons-download',
            80
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_theme-export-jlg') {
            return;
        }
        wp_enqueue_style('tejlg-admin-styles', TEJLG_URL . 'assets/css/admin-styles.css', [], TEJLG_VERSION);
        wp_enqueue_script('tejlg-admin-scripts', TEJLG_URL . 'assets/js/admin-scripts.js', [], TEJLG_VERSION, true);
        wp_localize_script(
            'tejlg-admin-scripts',
            'tejlgAdminL10n',
            [
                'showBlockCode' => __('Afficher le code du bloc', 'theme-export-jlg'),
                'hideBlockCode' => __('Masquer le code du bloc', 'theme-export-jlg'),
                /* translators: Warning shown before importing a theme zip file. */
                'themeImportConfirm' => __("⚠️ ATTENTION ⚠️\n\nSi un thème avec le même nom de dossier existe déjà, il sera DÉFINITIVEMENT écrasé.\n\nÊtes-vous sûr de vouloir continuer ?", 'theme-export-jlg'),
            ]
        );
    }

    public function handle_form_requests() {
        if (!current_user_can('manage_options')) return;

        // Export (TOUT)
        if (isset($_POST['tejlg_nonce']) && wp_verify_nonce($_POST['tejlg_nonce'], 'tejlg_export_action')) {
            if (isset($_POST['tejlg_export_theme'])) TEJLG_Export::export_theme();
            if (isset($_POST['tejlg_export_patterns'])) TEJLG_Export::export_patterns_json();
        }

        // Export SÉLECTIF des compositions
        if (isset($_POST['tejlg_export_selected_nonce']) && wp_verify_nonce($_POST['tejlg_export_selected_nonce'], 'tejlg_export_selected_action')) {
            if (isset($_POST['selected_patterns']) && is_array($_POST['selected_patterns'])) {
                TEJLG_Export::export_selected_patterns_json($_POST['selected_patterns']);
            }
        }
        
        // Import Thème
        if (isset($_POST['tejlg_import_theme_nonce']) && wp_verify_nonce($_POST['tejlg_import_theme_nonce'], 'tejlg_import_theme_action')) {
            $theme_file = isset($_FILES['theme_zip']) ? $_FILES['theme_zip'] : [ 'error' => UPLOAD_ERR_NO_FILE ];

            if (!current_user_can('install_themes')) {
                add_settings_error(
                    'tejlg_import_messages',
                    'theme_import_cap_missing',
                    esc_html__('Vous n\'avez pas l\'autorisation d\'installer des thèmes sur ce site.', 'theme-export-jlg'),
                    'error'
                );
            } elseif ((int) $theme_file['error'] === UPLOAD_ERR_OK) {
                TEJLG_Import::import_theme($theme_file);
            } else {
                if (!empty($theme_file['tmp_name']) && is_string($theme_file['tmp_name']) && file_exists($theme_file['tmp_name'])) {
                    @unlink($theme_file['tmp_name']);
                }

                add_settings_error(
                    'tejlg_import_messages',
                    'theme_import_upload_error_' . (int) $theme_file['error'],
                    $this->get_upload_error_message((int) $theme_file['error'], esc_html__('du thème', 'theme-export-jlg')),
                    'error'
                );
            }
        }

        // Import Compositions (Étape 1)
        if (isset($_POST['tejlg_import_patterns_step1_nonce']) && wp_verify_nonce($_POST['tejlg_import_patterns_step1_nonce'], 'tejlg_import_patterns_step1_action')) {
            $patterns_file = isset($_FILES['patterns_json']) ? $_FILES['patterns_json'] : [ 'error' => UPLOAD_ERR_NO_FILE ];

            if ((int) $patterns_file['error'] === UPLOAD_ERR_OK) {
                TEJLG_Import::handle_patterns_upload_step1($patterns_file);
            } else {
                if (!empty($patterns_file['tmp_name']) && is_string($patterns_file['tmp_name']) && file_exists($patterns_file['tmp_name'])) {
                    @unlink($patterns_file['tmp_name']);
                }

                add_settings_error(
                    'tejlg_import_messages',
                    'patterns_import_upload_error_' . (int) $patterns_file['error'],
                    $this->get_upload_error_message((int) $patterns_file['error'], esc_html__('des compositions', 'theme-export-jlg')),
                    'error'
                );
            }
        }

        // Import Compositions (Étape 2)
        if (isset($_POST['tejlg_import_patterns_step2_nonce']) && wp_verify_nonce($_POST['tejlg_import_patterns_step2_nonce'], 'tejlg_import_patterns_step2_action')) {
            if (isset($_POST['transient_id']) && isset($_POST['selected_patterns']) && is_array($_POST['selected_patterns'])) {
                $transient_id = sanitize_key($_POST['transient_id']);

                if (0 !== strpos($transient_id, 'tejlg_')) {
                    add_settings_error(
                        'tejlg_import_messages',
                        'patterns_import_status',
                        esc_html__("Erreur : L'identifiant de session est invalide. Veuillez réessayer.", 'theme-export-jlg'),
                        'error'
                    );
                    return;
                }

                TEJLG_Import::handle_patterns_import_step2($transient_id, $_POST['selected_patterns']);
            }
        }

        // Création du thème enfant
        if (isset($_POST['tejlg_create_child_nonce']) && wp_verify_nonce($_POST['tejlg_create_child_nonce'], 'tejlg_create_child_action')) {
            if (isset($_POST['child_theme_name'])) {
                TEJLG_Theme_Tools::create_child_theme(sanitize_text_field(wp_unslash($_POST['child_theme_name'])));
            }
        }
    }

    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'export'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'export' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Exporter & Outils', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'import'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'import' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Importer', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'migration_guide'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'migration_guide' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Guide de Migration', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'debug'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Débogage', 'theme-export-jlg'); ?></a>
            </h2>
            <?php
            switch ($active_tab) {
                case 'import': $this->render_import_tab(); break;
                case 'migration_guide': $this->render_migration_guide_tab(); break;
                case 'debug': $this->render_debug_tab(); break;
                default: $this->render_export_tab(); break;
            }
            ?>
        </div>
        <?php
    }

    private function render_export_tab() {
        if (isset($_GET['action']) && $_GET['action'] === 'select_patterns') {
            $this->render_pattern_selection_page();
        } else {
            $this->render_export_default_page();
        }
    }

    private function render_export_default_page() {
        ?>
        <?php settings_errors('tejlg_admin_messages'); ?>

        <h2><?php esc_html_e('Actions sur le Thème Actif', 'theme-export-jlg'); ?></h2>
        <div class="tejlg-cards-container">
            <div class="tejlg-card">
                <h3><?php esc_html_e('Exporter le Thème Actif (.zip)', 'theme-export-jlg'); ?></h3>
                <p><?php echo wp_kses_post(__('Crée une archive <code>.zip</code> de votre thème. Idéal pour les sauvegardes ou les migrations.', 'theme-export-jlg')); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
                    <p><button type="submit" name="tejlg_export_theme" class="button button-primary"><?php esc_html_e('Exporter le Thème Actif', 'theme-export-jlg'); ?></button></p>
                </form>
            </div>
            <div class="tejlg-card">
                <h3><?php esc_html_e('Exporter les Compositions (.json)', 'theme-export-jlg'); ?></h3>
                <p><?php echo wp_kses_post(__('Générez un fichier <code>.json</code> contenant vos compositions.', 'theme-export-jlg')); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
                    <p><label><input type="checkbox" name="export_portable" value="1"> <strong><?php esc_html_e('Export portable', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(compatibilité maximale)', 'theme-export-jlg'); ?></label></p>
                    <p><button type="submit" name="tejlg_export_patterns" class="button button-primary"><?php esc_html_e('Exporter TOUTES les compositions', 'theme-export-jlg'); ?></button></p>
                </form>
                <p>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'export', 'action' => 'select_patterns'], admin_url('admin.php'))); ?>" class="button"><?php esc_html_e('Exporter une sélection...', 'theme-export-jlg'); ?></a>
                </p>
            </div>
             <div class="tejlg-card">
                <h3><?php esc_html_e('Créer un Thème Enfant', 'theme-export-jlg'); ?></h3>
                <p><?php esc_html_e('Générez un thème enfant pour le thème actif. Indispensable pour ajouter du code personnalisé.', 'theme-export-jlg'); ?></p>
                <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'export'], admin_url('admin.php'))); ?>">
                    <?php wp_nonce_field('tejlg_create_child_action', 'tejlg_create_child_nonce'); ?>
                    <p>
                        <label for="child_theme_name"><?php esc_html_e('Nom du thème enfant :', 'theme-export-jlg'); ?></label>
                        <input type="text" name="child_theme_name" id="child_theme_name" class="regular-text" placeholder="<?php echo esc_attr(wp_get_theme()->get('Name') . ' ' . __('Enfant', 'theme-export-jlg')); ?>" required>
                    </p>
                    <p><button type="submit" name="tejlg_create_child" class="button button-primary"><?php esc_html_e('Créer le Thème Enfant', 'theme-export-jlg'); ?></button></p>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_pattern_selection_page() {
        $patterns_query = new WP_Query([
            'post_type' => 'wp_block',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <p><a href="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'export'], admin_url('admin.php'))); ?>">&larr; <?php esc_html_e('Retour aux outils principaux', 'theme-export-jlg'); ?></a></p>
        <h2><?php esc_html_e('Exporter une sélection de compositions', 'theme-export-jlg'); ?></h2>
        <p><?php echo wp_kses_post(__('Cochez les compositions que vous souhaitez inclure dans votre fichier d\'exportation <code>.json</code>.', 'theme-export-jlg')); ?></p>

        <?php if (!$patterns_query->have_posts()): ?>
            <p><?php esc_html_e('Aucune composition personnalisée n\'a été trouvée.', 'theme-export-jlg'); ?></p>
        <?php else: ?>
            <form method="post" action="">
                <?php wp_nonce_field('tejlg_export_selected_action', 'tejlg_export_selected_nonce'); ?>

                <div class="pattern-selection-list">
                    <p class="select-all-wrapper"><label><input type="checkbox" id="select-all-export-patterns"> <strong><?php esc_html_e('Tout sélectionner', 'theme-export-jlg'); ?></strong></label></p>
                    <ul>
                        <?php while ($patterns_query->have_posts()): $patterns_query->the_post(); ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr(get_the_ID()); ?>">
                                    <?php echo esc_html(get_the_title()); ?>
                                </label>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php wp_reset_postdata(); ?>

                <p><label><input type="checkbox" name="export_portable" value="1" checked> <strong><?php esc_html_e('Générer un export "portable"', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(Recommandé pour migrer vers un autre site)', 'theme-export-jlg'); ?></label></p>

                <p><button type="submit" name="tejlg_export_selected_patterns" class="button button-primary button-hero"><?php esc_html_e('Exporter la sélection', 'theme-export-jlg'); ?></button></p>
            </form>
        <?php endif; ?>
        <?php
    }

    private function render_import_tab() {
        settings_errors('tejlg_import_messages');

        if (isset($_GET['action']) && $_GET['action'] == 'preview_patterns' && isset($_GET['transient_id'])) {
            $this->render_patterns_preview_page(sanitize_key($_GET['transient_id']));
        } else {
            ?>
            <h2><?php esc_html_e('Tutoriel : Que pouvez-vous importer ?', 'theme-export-jlg'); ?></h2>
            <div class="tejlg-cards-container">
                <div class="tejlg-card">
                    <h3><?php esc_html_e('Importer un Thème (.zip)', 'theme-export-jlg'); ?></h3>
                    <p><?php echo wp_kses_post(__('Téléversez une archive <code>.zip</code> d\'un thème. Le plugin l\'installera (capacité WordPress « Installer des thèmes » requise). <strong>Attention :</strong> Un thème existant sera remplacé.', 'theme-export-jlg')); ?></p>
                    <form id="tejlg-import-theme-form" method="post" action="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'import'], admin_url('admin.php'))); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('tejlg_import_theme_action', 'tejlg_import_theme_nonce'); ?>
                        <p><label for="theme_zip"><?php esc_html_e('Fichier du thème (.zip) :', 'theme-export-jlg'); ?></label><br><input type="file" id="theme_zip" name="theme_zip" accept=".zip" required></p>
                        <p><button type="submit" name="tejlg_import_theme" class="button button-primary"><?php esc_html_e('Importer le Thème', 'theme-export-jlg'); ?></button></p>
                    </form>
                </div>
                <div class="tejlg-card">
                    <h3><?php esc_html_e('Importer des Compositions (.json)', 'theme-export-jlg'); ?></h3>
                    <p><?php echo wp_kses_post(__('Téléversez un fichier <code>.json</code> (généré par l\'export). Vous pourrez choisir quelles compositions importer à l\'étape suivante.', 'theme-export-jlg')); ?></p>
                     <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'import'], admin_url('admin.php'))); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('tejlg_import_patterns_step1_action', 'tejlg_import_patterns_step1_nonce'); ?>
                        <p><label for="patterns_json"><?php esc_html_e('Fichier des compositions (.json, .txt) :', 'theme-export-jlg'); ?></label><br><input type="file" id="patterns_json" name="patterns_json" accept=".json,.txt" required></p>
                        <p><button type="submit" name="tejlg_import_patterns_step1" class="button button-primary"><?php esc_html_e('Analyser et prévisualiser', 'theme-export-jlg'); ?></button></p>
                    </form>
                </div>
            </div>
            <?php
        }
    }

    private function render_debug_tab() {
        $zip_status = class_exists('ZipArchive')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Oui', 'theme-export-jlg'))
            : sprintf('<span style="color:red;">%s</span>', esc_html__('Non (Export de thème impossible)', 'theme-export-jlg'));

        $mbstring_status = extension_loaded('mbstring')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Activée', 'theme-export-jlg'))
            : sprintf('<span style="color:red; font-weight: bold;">%s</span>', esc_html__('Manquante (CRITIQUE pour la fiabilité des exports JSON)', 'theme-export-jlg'));
        ?>
        <h2><?php esc_html_e('Outils de Débogage', 'theme-export-jlg'); ?></h2>
        <p><?php esc_html_e('Ces informations peuvent vous aider à diagnostiquer des problèmes liés à votre configuration ou à vos données.', 'theme-export-jlg'); ?></p>
        <div id="debug-accordion">
            <div class="accordion-section">
                <h3 class="accordion-section-title"><?php esc_html_e('Informations Système & WordPress', 'theme-export-jlg'); ?></h3>
                <div class="accordion-section-content">
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Version de WordPress', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Version de PHP', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo wp_kses_post(__('Classe <code>ZipArchive</code> disponible', 'theme-export-jlg')); ?></td>
                                <td><?php echo wp_kses_post($zip_status); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo wp_kses_post(__('Extension PHP <code>mbstring</code>', 'theme-export-jlg')); ?></td>
                                <td><?php echo wp_kses_post($mbstring_status); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Limite de mémoire WP', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Taille max. d\'upload', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="accordion-section">
                <h3 class="accordion-section-title"><?php esc_html_e('Compositions personnalisées enregistrées', 'theme-export-jlg'); ?></h3>
                <div class="accordion-section-content">
                    <?php
                    $patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
                    $custom_patterns = array_filter(
                        $patterns,
                        function ($pattern) {
                            return ! (isset($pattern['source']) && $pattern['source'] === 'core');
                        }
                    );

                    if (empty($custom_patterns)) {
                        echo '<p>' . esc_html__('Aucune composition personnalisée n\'a été trouvée.', 'theme-export-jlg') . '</p>';
                    } else {
                        $count = count($custom_patterns);
                        printf(
                            '<p>%s</p>',
                            esc_html(
                                sprintf(
                                    _n('%d composition personnalisée trouvée :', '%d compositions personnalisées trouvées :', $count, 'theme-export-jlg'),
                                    $count
                                )
                            )
                        );
                        echo '<ul>';
                        foreach ($custom_patterns as $pattern) {
                            printf(
                                '<li><strong>%1$s</strong> (%2$s <code>%3$s</code>)</li>',
                                esc_html($pattern['title']),
                                esc_html__('Slug :', 'theme-export-jlg'),
                                esc_html($pattern['name'])
                            );
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_patterns_preview_page($transient_id) {
        $transient_id = (string) $transient_id;

        if ('' === $transient_id || 0 !== strpos($transient_id, 'tejlg_')) {
            echo '<div class="error"><p>' . esc_html__("Erreur : L'identifiant de session est invalide. Veuillez téléverser à nouveau votre fichier.", 'theme-export-jlg') . '</p></div>';
            return;
        }

        $patterns = get_transient($transient_id);
        if (false === $patterns) {
            echo '<div class="error"><p>' . esc_html__('La session d\'importation a expiré ou est invalide. Veuillez téléverser à nouveau votre fichier.', 'theme-export-jlg') . '</p></div>';
            return;
        }

        if (!is_array($patterns)) {
            $patterns = [];
        }

        $global_styles = wp_get_global_stylesheet();
        if (!is_string($global_styles)) {
            $global_styles = '';
        }

        $invalid_patterns = [];
        $prepared_patterns = [];
        $has_renderable_pattern = false;

        foreach ($patterns as $index => $pattern) {
            if (!is_array($pattern) || !array_key_exists('title', $pattern) || !array_key_exists('content', $pattern)) {
                $invalid_patterns[] = (int) $index;
                continue;
            }

            $raw_title = $pattern['title'];
            if (!is_scalar($raw_title)) {
                $raw_title = '';
            }
            $title = trim((string) $raw_title);
            if ('' === $title) {
                $title = sprintf(__('Composition sans titre #%d', 'theme-export-jlg'), ((int) $index) + 1);
            }

            $raw_content = $pattern['content'];
            if (!is_scalar($raw_content)) {
                $raw_content = '';
            }
            $pattern_content = (string) $raw_content;

            $parsed_blocks = '' !== $pattern_content ? parse_blocks($pattern_content) : [];
            $rendered_pattern = '';

            if (!empty($parsed_blocks)) {
                $rendered_pattern = $this->render_blocks_preview($parsed_blocks);
            }

            if ('' === $rendered_pattern) {
                $rendered_pattern = $pattern_content;
            }

            $sanitized_rendered_pattern = wp_kses_post($rendered_pattern);
            if ('' !== trim($sanitized_rendered_pattern) || '' !== trim($pattern_content)) {
                $has_renderable_pattern = true;
            }

            $prepared_patterns[] = [
                'index'   => (int) $index,
                'title'   => $title,
                'content' => $pattern_content,
                'rendered' => $sanitized_rendered_pattern,
            ];
        }

        if (!empty($invalid_patterns)) {
            sort($invalid_patterns, SORT_NUMERIC);

            $display_indexes = array_map(
                static function ($index) {
                    return sprintf('#%d', ((int) $index) + 1);
                },
                $invalid_patterns
            );

            $invalid_count = count($display_indexes);
            if (1 === $invalid_count) {
                $warning_message = sprintf(
                    __('Une entrée a été ignorée car elle ne possède pas de titre et un contenu valides (%s).', 'theme-export-jlg'),
                    implode(', ', $display_indexes)
                );
            } else {
                $warning_message = sprintf(
                    __('%d entrées ont été ignorées car elles ne possèdent pas de titre et un contenu valides (%s).', 'theme-export-jlg'),
                    $invalid_count,
                    implode(', ', $display_indexes)
                );
            }

            echo '<div class="notice notice-warning"><p>' . esc_html($warning_message) . '</p></div>';
        }

        if (empty($prepared_patterns) || !$has_renderable_pattern) {
            delete_transient($transient_id);
            echo '<div class="error"><p>' . esc_html__('Erreur : Aucune composition valide n\'a pu être prévisualisée. Veuillez vérifier le fichier importé.', 'theme-export-jlg') . '</p></div>';
            echo '<p><a href="' . esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'import'], admin_url('admin.php'))) . '">&larr; ' . esc_html__('Retour au formulaire d\'import', 'theme-export-jlg') . '</a></p>';
            return;
        }

        ?>
        <h2><?php esc_html_e('Étape 2 : Choisir les compositions à importer', 'theme-export-jlg'); ?></h2>
        <p><?php esc_html_e('Cochez les compositions à importer. Vous pouvez prévisualiser le rendu et inspecter le code du bloc (le code CSS du thème est masqué par défaut).', 'theme-export-jlg'); ?></p>
        <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'theme-export-jlg', 'tab' => 'import'], admin_url('admin.php'))); ?>">
            <?php wp_nonce_field('tejlg_import_patterns_step2_action', 'tejlg_import_patterns_step2_nonce'); ?>
            <input type="hidden" name="transient_id" value="<?php echo esc_attr($transient_id); ?>">
            <div id="patterns-preview-list">
                <div style="margin-bottom:15px;">
                     <label><input type="checkbox" id="select-all-patterns" checked> <strong><?php esc_html_e('Tout sélectionner', 'theme-export-jlg'); ?></strong></label>
                </div>
                <?php foreach ($prepared_patterns as $pattern_data): ?>
                    <?php
                    $iframe_content = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>' . $global_styles . '</style></head><body class="block-editor-writing-flow">' . $pattern_data['rendered'] . '</body></html>';
                    ?>
                    <div class="pattern-item">
                        <div class="pattern-selector">
                            <label>
                                <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_data['index']); ?>" checked>
                                <strong><?php echo esc_html($pattern_data['title']); ?></strong>
                            </label>
                        </div>
                        <div class="pattern-preview-wrapper">
                            <iframe srcdoc="<?php echo esc_attr($iframe_content); ?>" class="pattern-preview-iframe" sandbox="allow-same-origin"></iframe>
                        </div>

                        <div class="pattern-controls">
                            <button type="button" class="button-link toggle-code-view"><?php esc_html_e('Afficher le code du bloc', 'theme-export-jlg'); ?></button>
                        </div>

                        <div class="pattern-code-view" style="display: none;">
                            <pre><code><?php echo esc_html($pattern_data['content']); ?></code></pre>

                            <details class="css-accordion">
                                <summary><?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?></summary>
                                <pre><code><?php echo esc_html($global_styles); ?></code></pre>
                            </details>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
            <p><button type="submit" name="tejlg_import_patterns_step2" class="button button-primary button-hero"><?php esc_html_e('Importer la sélection', 'theme-export-jlg'); ?></button></p>
        </form>
        <?php
    }

    private function render_blocks_preview(array $blocks) {
        $output = '';

        foreach ($blocks as $block) {
            $output .= $this->render_block_preview($block);
        }

        return $output;
    }

    private function render_block_preview(array $block) {
        if (empty($block['blockName'])) {
            return isset($block['innerHTML']) ? $block['innerHTML'] : '';
        }

        $block_name = $block['blockName'];
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered($block_name);

        $is_dynamic = ('core/shortcode' === $block_name);

        if ($block_type instanceof WP_Block_Type && !empty($block_type->render_callback)) {
            $is_dynamic = true;
        } elseif (!$block_type) {
            $is_dynamic = true;
        }

        $rendered_inner_blocks = [];
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                $rendered_inner_blocks[] = $this->render_block_preview($inner_block);
            }
        }

        if (!empty($block['innerContent'])) {
            $content = '';
            $inner_index = 0;

            foreach ($block['innerContent'] as $chunk) {
                if (null === $chunk) {
                    $content .= isset($rendered_inner_blocks[$inner_index]) ? $rendered_inner_blocks[$inner_index] : '';
                    $inner_index++;
                } else {
                    $content .= $chunk;
                }
            }

            if ('' !== trim($content) || !empty($rendered_inner_blocks)) {
                return $content;
            }
        }

        if (isset($block['innerHTML'])) {
            if ('' !== trim($block['innerHTML'])) {
                return $block['innerHTML'];
            }
        }

        if (!empty($rendered_inner_blocks)) {
            return implode('', $rendered_inner_blocks);
        }

        if ($is_dynamic) {
            return $this->get_dynamic_block_placeholder($block_name);
        }

        return '';
    }

    private function get_dynamic_block_placeholder($block_name) {
        $block_label = $block_name ? $block_name : __('bloc inconnu', 'theme-export-jlg');
        $placeholder_text = sprintf(
            /* translators: %s: dynamic block name. */
            esc_html__('Bloc dynamique "%s" non rendu dans cet aperçu.', 'theme-export-jlg'),
            esc_html($block_label)
        );

        return '<div class="tejlg-block-placeholder"><p>' . $placeholder_text . '</p></div>';
    }

    private function get_upload_error_message($error_code, $file_label) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(
                    esc_html__('Le fichier %1$s dépasse la taille maximale autorisée (%2$s).', 'theme-export-jlg'),
                    $file_label,
                    esc_html(ini_get('upload_max_filesize'))
                );
            case UPLOAD_ERR_PARTIAL:
                return sprintf(
                    esc_html__('Le fichier %s n\'a été que partiellement téléversé. Veuillez réessayer.', 'theme-export-jlg'),
                    $file_label
                );
            case UPLOAD_ERR_NO_FILE:
                return sprintf(
                    esc_html__('Aucun fichier %s n\'a été téléversé. Veuillez sélectionner un fichier avant de recommencer.', 'theme-export-jlg'),
                    $file_label
                );
            case UPLOAD_ERR_NO_TMP_DIR:
                return esc_html__('Le dossier temporaire du serveur est manquant. Contactez votre hébergeur.', 'theme-export-jlg');
            case UPLOAD_ERR_CANT_WRITE:
                return esc_html__('Impossible d\'écrire le fichier sur le disque. Vérifiez les permissions de votre serveur.', 'theme-export-jlg');
            case UPLOAD_ERR_EXTENSION:
                return esc_html__('Une extension PHP a interrompu le téléversement. Vérifiez la configuration de votre serveur.', 'theme-export-jlg');
            default:
                return sprintf(
                    esc_html__('Une erreur inconnue est survenue lors du téléversement du fichier %1$s (code %2$d).', 'theme-export-jlg'),
                    $file_label,
                    $error_code
                );
        }
    }

    private function render_migration_guide_tab() {
        ?>
        <h2><?php esc_html_e('Guide : Migrer ses personnalisations d\'un thème bloc à un autre', 'theme-export-jlg'); ?></h2>
        <p><?php echo wp_kses_post(__('Ce guide explique comment transférer vos compositions et vos modifications de l\'Éditeur de Site (comme de <strong>Twenty Twenty-Four</strong> à <strong>Twenty Twenty-Five</strong>) en utilisant ce plugin.', 'theme-export-jlg')); ?></p>
        <hr>
        <h3><?php echo wp_kses_post(__('Le Concept Clé : Le fichier <code>theme.json</code>', 'theme-export-jlg')); ?></h3>
        <p><?php echo wp_kses_post(__('Un "mode de compatibilité" automatique entre thèmes blocs est presque impossible car chaque thème est un <strong>système de design</strong> unique, défini par son fichier <code>theme.json</code>. Ce fichier contrôle tout :', 'theme-export-jlg')); ?></p>
        <ul>
            <li><?php echo wp_kses_post(__('🎨 <strong>La palette de couleurs</strong> (les couleurs "Primaire", "Secondaire", etc. sont différentes).', 'theme-export-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('✒️ <strong>La typographie</strong> (familles de polices, tailles, graisses).', 'theme-export-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('📏 <strong>Les espacements</strong> (marges, paddings, etc.).', 'theme-export-jlg')); ?></li>
        </ul>
        <p><?php esc_html_e('Lorsque vous activez un nouveau thème, vos blocs s\'adaptent volontairement à ce nouveau système de design. C\'est le comportement attendu.', 'theme-export-jlg'); ?></p>
        <hr>
        <h3><?php esc_html_e('La Stratégie de Migration en 3 Étapes', 'theme-export-jlg'); ?></h3>
        <div class="tejlg-cards-container">
            <div class="tejlg-card">
                <h4><?php esc_html_e('Étape 1 : Exporter TOUT depuis l\'ancien thème', 'theme-export-jlg'); ?></h4>
                <ol>
                    <li><?php echo wp_kses_post(__('<strong>Exporter les Compositions :</strong> Dans l\'onglet <strong>Exporter & Outils</strong>, cliquez sur "Exporter les Compositions" pour obtenir votre fichier <code>.json</code>.', 'theme-export-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>Exporter les Modèles de l\'Éditeur :</strong> Dans <code>Apparence > Éditeur</code>, ouvrez le panneau de navigation, cliquez sur les trois points (⋮) et choisissez <strong>Outils > Exporter</strong> pour obtenir un <code>.zip</code>.', 'theme-export-jlg')); ?></li>
                </ol>
                <div style="text-align: center; margin-top: 10px; padding: 10px; border: 1px solid #ccc; background-color: #f9f9f9; border-radius: 4px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor" aria-hidden="true" focusable="false">
                        <path d="M4 18.5h16V20H4v-1.5zM12 3c-1.1 0-2 .9-2 2v8.29l-2.12-2.12c-.39-.39-1.02-.39-1.41 0s-.39 1.02 0 1.41l3.83 3.83c.39.39 1.02.39 1.41 0l3.83-3.83c.39-.39.39-1.02 0-1.41s-1.02-.39-1.41 0L14 13.29V5c0-1.1-.9-2-2-2z"></path>
                    </svg>
                    <p style="font-size: 0.9em; color: #555;"><?php esc_html_e('Icône des outils d\'exportation de l\'Éditeur de Site WordPress (trois points verticaux puis "Outils > Exporter").', 'theme-export-jlg'); ?></p>
                </div>
            </div>
            <div class="tejlg-card">
                <h4><?php esc_html_e('Étape 2 : Activer le nouveau thème', 'theme-export-jlg'); ?></h4>
                <p><?php echo wp_kses_post(__('Allez dans <code>Apparence > Thèmes</code> et activez votre nouveau thème. L\'apparence de votre site va radicalement changer, c\'est normal.', 'theme-export-jlg')); ?></p>
            </div>
            <div class="tejlg-card">
                <h4><?php esc_html_e('Étape 3 : Importer et Adapter', 'theme-export-jlg'); ?></h4>
                <ol>
                    <li><?php echo wp_kses_post(__('<strong>Importer les Compositions :</strong> Dans l\'onglet <strong>Importer</strong>, téléversez votre fichier <code>.json</code>. L\'aperçu vous montrera vos compositions avec le style du NOUVEAU thème. Importez votre sélection.', 'theme-export-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>Adapter les Modèles :</strong> Utilisez le <code>.zip</code> de l\'étape 1 comme référence pour recréer la structure de vos anciens modèles dans l\'Éditeur de Site du nouveau thème.', 'theme-export-jlg')); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
}
