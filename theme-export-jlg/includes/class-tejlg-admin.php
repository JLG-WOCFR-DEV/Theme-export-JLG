<?php

require_once TEJLG_PATH . 'includes/class-tejlg-admin-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-export-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-import-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-debug-page.php';

class TEJLG_Admin {
    /**
     * Hook suffix returned by add_menu_page().
     *
     * @var string
     */
    private $menu_hook_suffix = '';

    private $page_slug = 'theme-export-jlg';

    private $export_page;
    private $import_page;
    private $debug_page;

    private $template_dir;

    public function __construct() {
        $this->template_dir = TEJLG_PATH . 'templates/admin/';

        $this->export_page = new TEJLG_Admin_Export_Page($this->template_dir, $this->page_slug);
        $this->import_page = new TEJLG_Admin_Import_Page($this->template_dir, $this->page_slug);
        $this->debug_page  = new TEJLG_Admin_Debug_Page($this->template_dir, $this->page_slug);

        add_action('admin_menu', [ $this, 'add_menu_page' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ]);
        add_action('admin_init', [ $this, 'handle_form_requests' ]);
    }

    public function add_menu_page() {
        $this->menu_hook_suffix = add_menu_page(
            __('Theme Export - JLG', 'theme-export-jlg'),
            __('Theme Export', 'theme-export-jlg'),
            'manage_options',
            $this->page_slug,
            [ $this, 'render_admin_page' ],
            'dashicons-download',
            80
        );

        if (!empty($this->menu_hook_suffix)) {
            add_action('load-' . $this->menu_hook_suffix, [ 'TEJLG_Import', 'cleanup_expired_patterns_storage' ]);
        }
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_' . $this->page_slug) {
            return;
        }

        wp_enqueue_style('tejlg-admin-styles', TEJLG_URL . 'assets/css/admin-styles.css', [], TEJLG_VERSION);

        $icon_size = $this->debug_page->get_metrics_icon_size();
        if ($icon_size > 0) {
            $inline_style = sprintf(':root { --tejlg-metric-icon-size: %dpx; }', (int) $icon_size);
            wp_add_inline_style('tejlg-admin-styles', $inline_style);
        }

        wp_enqueue_script('tejlg-admin-scripts', TEJLG_URL . 'assets/js/admin-scripts.js', [], TEJLG_VERSION, true);

        $saved_exclusions = get_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION, '');

        if (!is_string($saved_exclusions)) {
            $saved_exclusions = '';
        }

        wp_localize_script(
            'tejlg-admin-scripts',
            'tejlgAdminL10n',
            [
                'showBlockCode' => __('Afficher le code du bloc', 'theme-export-jlg'),
                'hideBlockCode' => __('Masquer le code du bloc', 'theme-export-jlg'),
                'previewFallbackWarning' => esc_html__("Avertissement : l'aperçu est chargé via un mode de secours (sans Blob). Le rendu peut être limité.", 'theme-export-jlg'),
                /* translators: Warning shown before importing a theme zip file. */
                'themeImportConfirm' => __("⚠️ ATTENTION ⚠️\n\nSi un thème avec le même nom de dossier existe déjà, il sera DÉFINITIVEMENT écrasé.\n\nÊtes-vous sûr de vouloir continuer ?", 'theme-export-jlg'),
                'metrics' => [
                    'locale'           => get_user_locale(),
                    'fpsUnit'          => esc_html__('FPS', 'theme-export-jlg'),
                    'latencyUnit'      => esc_html__('ms', 'theme-export-jlg'),
                    'placeholder'      => esc_html__('--', 'theme-export-jlg'),
                    'stopped'          => esc_html__('Arrêté', 'theme-export-jlg'),
                    'loading'          => esc_html__('Initialisation…', 'theme-export-jlg'),
                    'ariaLivePolite'   => 'polite',
                    'ariaAtomic'       => 'true',
                    'latencyPrecision' => 1,
                ],
                'patternSelectionStatus' => [
                    'numberLocale'   => get_user_locale(),
                    'empty'          => esc_html__('Aucune composition visible.', 'theme-export-jlg'),
                    'countSingular'  => esc_html__('%s composition visible.', 'theme-export-jlg'),
                    'countPlural'    => esc_html__('%s compositions visibles.', 'theme-export-jlg'),
                    'filterSearch'   => esc_html__('recherche « %s »', 'theme-export-jlg'),
                    'filterCategory' => esc_html__('catégorie « %s »', 'theme-export-jlg'),
                    'filterCategoryNone' => esc_html__('sans catégorie', 'theme-export-jlg'),
                    'filterDate'     => esc_html__('période « %s »', 'theme-export-jlg'),
                    'filterDateNone' => esc_html__('sans date', 'theme-export-jlg'),
                    'filtersSummaryIntro' => esc_html__('Filtres actifs :', 'theme-export-jlg'),
                    'filtersSummaryJoin'  => esc_html__(' ; ', 'theme-export-jlg'),
                ],
                'patternSelectionCount' => [
                    'none' => esc_html__('0 sélection', 'theme-export-jlg'),
                    'some' => esc_html__('%s sélection(s)', 'theme-export-jlg'),
                ],
                'previewWidth' => [
                    'valueTemplate'    => esc_html__('Largeur : %s px', 'theme-export-jlg'),
                    'unit'             => esc_html__('px', 'theme-export-jlg'),
                    'customInputLabel' => esc_html__('Largeur personnalisée (px)', 'theme-export-jlg'),
                ],
                'exportAsync' => [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'actions' => [
                        'start'    => 'tejlg_start_theme_export',
                        'status'   => 'tejlg_theme_export_status',
                        'download' => 'tejlg_download_theme_export',
                    ],
                    'nonces' => [
                        'start'  => wp_create_nonce('tejlg_start_theme_export'),
                        'status' => wp_create_nonce('tejlg_theme_export_status'),
                    ],
                    'pollInterval' => 4000,
                    'strings' => [
                        'initializing'    => esc_html__("Initialisation de l'export…", 'theme-export-jlg'),
                        'queued'          => esc_html__("Tâche en file d'attente…", 'theme-export-jlg'),
                        'inProgress'      => esc_html__('Fichiers traités : %1$d / %2$d', 'theme-export-jlg'),
                        'progressValue'   => esc_html__('Progression : %1$d%%', 'theme-export-jlg'),
                        'completed'       => esc_html__('Export terminé !', 'theme-export-jlg'),
                        'failed'          => esc_html__("Échec de l'export : %1$s", 'theme-export-jlg'),
                        'downloadLabel'   => esc_html__("Télécharger l'archive ZIP", 'theme-export-jlg'),
                        'unknownError'    => esc_html__('Une erreur inattendue est survenue.', 'theme-export-jlg'),
                        'statusLabel'     => esc_html__('Statut de la tâche : %1$s', 'theme-export-jlg'),
                    ],
                    'previousJob' => TEJLG_Export::get_current_user_job_snapshot(),
                    'defaults'    => [
                        'exclusions' => $saved_exclusions,
                    ],
                ],
            ]
        );
    }

    public function handle_form_requests() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->debug_page->handle_request();
        $this->export_page->handle_request();
        $this->import_page->handle_request();
    }

    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'export'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Exporter & Outils', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'import'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Importer', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'migration_guide'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'migration_guide' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Guide de Migration', 'theme-export-jlg'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'debug'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Débogage', 'theme-export-jlg'); ?></a>
            </h2>
            <?php
            switch ($active_tab) {
                case 'import':
                    $this->import_page->render();
                    break;
                case 'migration_guide':
                    $this->render_migration_guide_tab();
                    break;
                case 'debug':
                    $this->debug_page->render();
                    break;
                default:
                    $this->export_page->render();
                    break;
            }
            ?>
        </div>
        <?php
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
