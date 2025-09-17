<?php
class TEJLG_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_form_requests' ] );
    }

    public function add_menu_page() {
        add_menu_page('Theme Export - JLG', 'Theme Export', 'manage_options', 'theme-export-jlg', [ $this, 'render_admin_page' ], 'dashicons-download', 80);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_theme-export-jlg') {
            return;
        }
        wp_enqueue_style('tejlg-admin-styles', TEJLG_URL . 'assets/css/admin-styles.css', [], TEJLG_VERSION);
        wp_enqueue_script('tejlg-admin-scripts', TEJLG_URL . 'assets/js/admin-scripts.js', [], TEJLG_VERSION, true);
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
            if (isset($_FILES['theme_zip']) && $_FILES['theme_zip']['error'] === UPLOAD_ERR_OK) {
                TEJLG_Import::import_theme($_FILES['theme_zip']);
            }
        }

        // Import Compositions (Étape 1)
        if (isset($_POST['tejlg_import_patterns_step1_nonce']) && wp_verify_nonce($_POST['tejlg_import_patterns_step1_nonce'], 'tejlg_import_patterns_step1_action')) {
            if (isset($_FILES['patterns_json']) && $_FILES['patterns_json']['error'] === UPLOAD_ERR_OK) {
                TEJLG_Import::handle_patterns_upload_step1($_FILES['patterns_json']);
            }
        }

        // Import Compositions (Étape 2)
        if (isset($_POST['tejlg_import_patterns_step2_nonce']) && wp_verify_nonce($_POST['tejlg_import_patterns_step2_nonce'], 'tejlg_import_patterns_step2_action')) {
            if (isset($_POST['transient_id']) && isset($_POST['selected_patterns'])) {
                TEJLG_Import::handle_patterns_import_step2(sanitize_key($_POST['transient_id']), $_POST['selected_patterns']);
            }
        }

        // Création du thème enfant
        if (isset($_POST['tejlg_create_child_nonce']) && wp_verify_nonce($_POST['tejlg_create_child_nonce'], 'tejlg_create_child_action')) {
            if (isset($_POST['child_theme_name'])) {
                TEJLG_Theme_Tools::create_child_theme(sanitize_text_field($_POST['child_theme_name']));
            }
        }
    }

    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=theme-export-jlg&tab=export" class="nav-tab <?php echo $active_tab == 'export' ? 'nav-tab-active' : ''; ?>">Exporter & Outils</a>
                <a href="?page=theme-export-jlg&tab=import" class="nav-tab <?php echo $active_tab == 'import' ? 'nav-tab-active' : ''; ?>">Importer</a>
                <a href="?page=theme-export-jlg&tab=migration_guide" class="nav-tab <?php echo $active_tab == 'migration_guide' ? 'nav-tab-active' : ''; ?>">Guide de Migration</a>
                <a href="?page=theme-export-jlg&tab=debug" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>">Débogage</a>
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
        
        <h2>Actions sur le Thème Actif</h2>
        <div class="tejlg-cards-container">
            <div class="tejlg-card">
                <h3>Exporter le Thème Actif (.zip)</h3>
                <p>Crée une archive <code>.zip</code> de votre thème. Idéal pour les sauvegardes ou les migrations.</p>
                <form method="post" action="">
                    <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
                    <p><button type="submit" name="tejlg_export_theme" class="button button-primary">Exporter le Thème Actif</button></p>
                </form>
            </div>
            <div class="tejlg-card">
                <h3>Exporter les Compositions (.json)</h3>
                <p>Générez un fichier <code>.json</code> contenant vos compositions.</p>
                <form method="post" action="">
                    <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
                    <p><label><input type="checkbox" name="export_portable" value="1"> <strong>Export portable</strong> (compatibilité maximale)</label></p>
                    <p><button type="submit" name="tejlg_export_patterns" class="button button-primary">Exporter TOUTES les compositions</button></p>
                </form>
                <p>
                    <a href="?page=theme-export-jlg&tab=export&action=select_patterns" class="button">Exporter une sélection...</a>
                </p>
            </div>
             <div class="tejlg-card">
                <h3>Créer un Thème Enfant</h3>
                <p>Générez un thème enfant pour le thème actif. Indispensable pour ajouter du code personnalisé.</p>
                <form method="post" action="?page=theme-export-jlg&tab=export">
                    <?php wp_nonce_field('tejlg_create_child_action', 'tejlg_create_child_nonce'); ?>
                    <p>
                        <label for="child_theme_name">Nom du thème enfant :</label>
                        <input type="text" name="child_theme_name" id="child_theme_name" class="regular-text" placeholder="<?php echo esc_attr(wp_get_theme()->get('Name') . ' Enfant'); ?>" required>
                    </p>
                    <p><button type="submit" name="tejlg_create_child" class="button button-primary">Créer le Thème Enfant</button></p>
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
        <p><a href="?page=theme-export-jlg&tab=export">&larr; Retour aux outils principaux</a></p>
        <h2>Exporter une sélection de compositions</h2>
        <p>Cochez les compositions que vous souhaitez inclure dans votre fichier d'exportation <code>.json</code>.</p>
        
        <?php if (!$patterns_query->have_posts()): ?>
            <p>Aucune composition personnalisée n'a été trouvée.</p>
        <?php else: ?>
            <form method="post" action="">
                <?php wp_nonce_field('tejlg_export_selected_action', 'tejlg_export_selected_nonce'); ?>
                
                <div class="pattern-selection-list">
                    <p class="select-all-wrapper"><label><input type="checkbox" id="select-all-export-patterns"> <strong>Tout sélectionner</strong></label></p>
                    <ul>
                        <?php while ($patterns_query->have_posts()): $patterns_query->the_post(); ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr(get_the_ID()); ?>">
                                    <?php the_title(); ?>
                                </label>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php wp_reset_postdata(); ?>
                
                <p><label><input type="checkbox" name="export_portable" value="1" checked> <strong>Générer un export "portable"</strong> (Recommandé pour migrer vers un autre site)</label></p>
                
                <p><button type="submit" name="tejlg_export_selected_patterns" class="button button-primary button-hero">Exporter la sélection</button></p>
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
            <h2>Tutoriel : Que pouvez-vous importer ?</h2>
            <div class="tejlg-cards-container">
                <div class="tejlg-card">
                    <h3>Importer un Thème (.zip)</h3>
                    <p>Téléversez une archive <code>.zip</code> d'un thème. Le plugin l'installera. <strong>Attention :</strong> Un thème existant sera remplacé.</p>
                    <form id="tejlg-import-theme-form" method="post" action="?page=theme-export-jlg&tab=import" enctype="multipart/form-data">
                        <?php wp_nonce_field('tejlg_import_theme_action', 'tejlg_import_theme_nonce'); ?>
                        <p><label for="theme_zip">Fichier du thème (.zip) :</label><br><input type="file" id="theme_zip" name="theme_zip" accept=".zip" required></p>
                        <p><button type="submit" name="tejlg_import_theme" class="button button-primary">Importer le Thème</button></p>
                    </form>
                </div>
                <div class="tejlg-card">
                    <h3>Importer des Compositions (.json)</h3>
                    <p>Téléversez un fichier <code>.json</code> (généré par l'export). Vous pourrez choisir quelles compositions importer à l'étape suivante.</p>
                     <form method="post" action="?page=theme-export-jlg&tab=import" enctype="multipart/form-data">
                        <?php wp_nonce_field('tejlg_import_patterns_step1_action', 'tejlg_import_patterns_step1_nonce'); ?>
                        <p><label for="patterns_json">Fichier des compositions (.json, .txt) :</label><br><input type="file" id="patterns_json" name="patterns_json" accept=".json,.txt" required></p>
                        <p><button type="submit" name="tejlg_import_patterns_step1" class="button button-primary">Analyser et prévisualiser</button></p>
                    </form>
                </div>
            </div>
            <?php
        }
    }

    private function render_debug_tab() {
        $theme = wp_get_theme();
        ?>
        <h2>Outils de Débogage</h2>
        <p>Ces informations peuvent vous aider à diagnostiquer des problèmes liés à votre configuration ou à vos données.</p>
        <div id="debug-accordion">
            <div class="accordion-section">
                <h3 class="accordion-section-title">Informations Système & WordPress</h3>
                <div class="accordion-section-content">
                    <table class="widefat striped">
                        <tbody>
                            <tr><td>Version de WordPress</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                            <tr><td>Version de PHP</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                            <tr><td>Classe <code>ZipArchive</code> disponible</td><td><?php echo class_exists('ZipArchive') ? '<span style="color:green;">Oui</span>' : '<span style="color:red;">Non (Export de thème impossible)</span>'; ?></td></tr>
                            <tr>
                                <td>Extension PHP <code>mbstring</code></td>
                                <td><?php echo extension_loaded('mbstring') ? '<span style="color:green;">Activée</span>' : '<span style="color:red; font-weight: bold;">Manquante (CRITIQUE pour la fiabilité des exports JSON)</span>'; ?></td>
                            </tr>
                            <tr><td>Limite de mémoire WP</td><td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td></tr>
                            <tr><td>Taille max. d'upload</td><td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="accordion-section">
                <h3 class="accordion-section-title">Compositions personnalisées enregistrées</h3>
                <div class="accordion-section-content">
                    <?php
                    $patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
                    $custom_patterns = array_filter($patterns, function($p) { return !(isset($p['source']) && $p['source'] === 'core'); });
                    if (empty($custom_patterns)) {
                        echo '<p>Aucune composition personnalisée n\'a été trouvée.</p>';
                    } else {
                        echo '<p>'. count($custom_patterns) .' composition(s) personnalisée(s) trouvée(s) :</p>';
                        echo '<ul>';
                        foreach ($custom_patterns as $p) {
                            printf('<li><strong>%s</strong> (Slug: <code>%s</code>)</li>', esc_html($p['title']), esc_html($p['name']));
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
        $patterns = get_transient($transient_id);
        if (false === $patterns) {
            echo '<div class="error"><p>La session d\'importation a expiré ou est invalide. Veuillez téléverser à nouveau votre fichier.</p></div>';
            return;
        }

        $global_styles = wp_get_global_stylesheet();
        ?>
        <h2>Étape 2 : Choisir les compositions à importer</h2>
        <p>Cochez les compositions à importer. Vous pouvez prévisualiser le rendu et inspecter le code du bloc (le code CSS du thème est masqué par défaut).</p>
        <form method="post" action="?page=theme-export-jlg&tab=import">
            <?php wp_nonce_field('tejlg_import_patterns_step2_action', 'tejlg_import_patterns_step2_nonce'); ?>
            <input type="hidden" name="transient_id" value="<?php echo esc_attr($transient_id); ?>">
            <div id="patterns-preview-list">
                <div style="margin-bottom:15px;">
                     <label><input type="checkbox" id="select-all-patterns" checked> <strong>Tout sélectionner</strong></label>
                </div>
                <?php foreach ($patterns as $index => $pattern): ?>
                    <?php
                    $pattern_content = isset($pattern['content']) ? (string) $pattern['content'] : '';
                    $rendered_pattern = do_blocks($pattern_content);
                    $sanitized_rendered_pattern = wp_kses_post($rendered_pattern);
                    $iframe_content = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>' . $global_styles . '</style></head><body class="block-editor-writing-flow">' . $sanitized_rendered_pattern . '</body></html>';
                    ?>
                    <div class="pattern-item">
                        <div class="pattern-selector">
                            <label>
                                <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($index); ?>" checked>
                                <strong><?php echo esc_html($pattern['title']); ?></strong>
                            </label>
                        </div>
                        <div class="pattern-preview-wrapper">
                            <iframe srcdoc="<?php echo esc_attr($iframe_content); ?>" class="pattern-preview-iframe" sandbox="allow-same-origin"></iframe>
                        </div>

                        <div class="pattern-controls">
                            <button type="button" class="button-link toggle-code-view">Afficher le code du bloc</button>
                        </div>

                        <div class="pattern-code-view" style="display: none;">
                            <pre><code><?php echo esc_html($pattern['content']); ?></code></pre>

                            <details class="css-accordion">
                                <summary>Afficher le CSS global du thème</summary>
                                <pre><code><?php echo esc_html($global_styles); ?></code></pre>
                            </details>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
            <p><button type="submit" name="tejlg_import_patterns_step2" class="button button-primary button-hero">Importer la sélection</button></p>
        </form>
        <?php
    }
    
    private function render_migration_guide_tab() {
        ?>
        <h2>Guide : Migrer ses personnalisations d'un thème bloc à un autre</h2>
        <p>Ce guide explique comment transférer vos compositions et vos modifications de l'Éditeur de Site (comme de <strong>Twenty Twenty-Four</strong> à <strong>Twenty Twenty-Five</strong>) en utilisant ce plugin.</p>
        <hr>
        <h3>Le Concept Clé : Le fichier <code>theme.json</code></h3>
        <p>Un "mode de compatibilité" automatique entre thèmes blocs est presque impossible car chaque thème est un <strong>système de design</strong> unique, défini par son fichier <code>theme.json</code>. Ce fichier contrôle tout :</p>
        <ul>
            <li>🎨 <strong>La palette de couleurs</strong> (les couleurs "Primaire", "Secondaire", etc. sont différentes).</li>
            <li>✒️ <strong>La typographie</strong> (familles de polices, tailles, graisses).</li>
            <li>📏 <strong>Les espacements</strong> (marges, paddings, etc.).</li>
        </ul>
        <p>Lorsque vous activez un nouveau thème, vos blocs s'adaptent volontairement à ce nouveau système de design. C'est le comportement attendu.</p>
        <hr>
        <h3>La Stratégie de Migration en 3 Étapes</h3>
        <div class="tejlg-cards-container">
            <div class="tejlg-card">
                <h4>Étape 1 : Exporter TOUT depuis l'ancien thème</h4>
                <ol>
                    <li><strong>Exporter les Compositions :</strong> Dans l'onglet <strong>Exporter & Outils</strong>, cliquez sur "Exporter les Compositions" pour obtenir votre fichier <code>.json</code>.</li>
                    <li><strong>Exporter les Modèles de l'Éditeur :</strong> Dans <code>Apparence > Éditeur</code>, ouvrez le panneau de navigation, cliquez sur les trois points (⋮) et choisissez <strong>Outils > Exporter</strong> pour obtenir un <code>.zip</code>.</li>
                </ol>
                <div style="text-align: center; margin-top: 10px; padding: 10px; border: 1px solid #ccc; background-color: #f9f9f9; border-radius: 4px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor" aria-hidden="true" focusable="false">
                        <path d="M4 18.5h16V20H4v-1.5zM12 3c-1.1 0-2 .9-2 2v8.29l-2.12-2.12c-.39-.39-1.02-.39-1.41 0s-.39 1.02 0 1.41l3.83 3.83c.39.39 1.02.39 1.41 0l3.83-3.83c.39-.39.39-1.02 0-1.41s-1.02-.39-1.41 0L14 13.29V5c0-1.1-.9-2-2-2z"></path>
                    </svg>
                    <p style="font-size: 0.9em; color: #555;">Icône des outils d'exportation de l'Éditeur de Site WordPress (trois points verticaux puis "Outils > Exporter").</p>
                </div>
            </div>
            <div class="tejlg-card">
                <h4>Étape 2 : Activer le nouveau thème</h4>
                <p>Allez dans <code>Apparence > Thèmes</code> et activez votre nouveau thème. L'apparence de votre site va radicalement changer, c'est normal.</p>
            </div>
            <div class="tejlg-card">
                <h4>Étape 3 : Importer et Adapter</h4>
                <ol>
                    <li><strong>Importer les Compositions :</strong> Dans l'onglet <strong>Importer</strong>, téléversez votre fichier <code>.json</code>. L'aperçu vous montrera vos compositions avec le style du NOUVEAU thème. Importez votre sélection.</li>
                    <li><strong>Adapter les Modèles :</strong> Utilisez le <code>.zip</code> de l'étape 1 comme référence pour recréer la structure de vos anciens modèles dans l'Éditeur de Site du nouveau thème.</li>
                </ol>
            </div>
        </div>
        <?php
    }
}