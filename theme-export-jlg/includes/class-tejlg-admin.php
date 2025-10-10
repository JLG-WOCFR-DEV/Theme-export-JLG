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
            TEJLG_Capabilities::get_capability('menu'),
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

        $tabs = $this->get_accessible_tabs();
        $requested_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
        $active_tab = isset($tabs[$requested_tab]) ? $requested_tab : key($tabs);

        wp_enqueue_style('tejlg-admin-styles', TEJLG_URL . 'assets/css/admin-styles.css', [], TEJLG_VERSION);

        $icon_size = $this->debug_page->get_metrics_icon_size();
        if ($icon_size > 0) {
            $inline_style = sprintf(':root { --tejlg-metric-icon-size: %dpx; }', (int) $icon_size);
            wp_add_inline_style('tejlg-admin-styles', $inline_style);
        }

        wp_enqueue_script('tejlg-admin-base', TEJLG_URL . 'assets/js/admin-base.js', [], TEJLG_VERSION, true);

        $action_param = filter_input(INPUT_GET, 'action', FILTER_DEFAULT);

        if (null === $action_param && isset($_GET['action'])) {
            $action_param = $_GET['action'];
        }

        $current_action = is_string($action_param) ? sanitize_key($action_param) : '';
        $is_import_preview = ('import' === $active_tab && 'preview_patterns' === $current_action);

        if ('export' === $active_tab || $is_import_preview) {
            wp_enqueue_script('tejlg-admin-export', TEJLG_URL . 'assets/js/admin-export.js', ['tejlg-admin-base'], TEJLG_VERSION, true);

            $saved_exclusions = get_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION, '');

            if (!is_string($saved_exclusions)) {
                $saved_exclusions = '';
            }

            $preview_concurrency_limit = apply_filters('tejlg_preview_concurrency_limit', 2);

            if (!is_numeric($preview_concurrency_limit)) {
                $preview_concurrency_limit = 2;
            }

            $preview_concurrency_limit = max(1, (int) $preview_concurrency_limit);

            wp_localize_script(
                'tejlg-admin-export',
                'tejlgAdminL10n',
                [
                    'showBlockCode' => __('Afficher le code du bloc', 'theme-export-jlg'),
                    'hideBlockCode' => __('Masquer le code du bloc', 'theme-export-jlg'),
                    'previewFallbackWarning' => esc_html__("Avertissement : l'aperçu est chargé via un mode de secours (sans Blob). Le rendu peut être limité.", 'theme-export-jlg'),
                    'previewQueueMessage' => esc_html__('Prévisualisation en attente… Une autre composition est en cours de chargement.', 'theme-export-jlg'),
                    'previewConcurrencyLimit' => $preview_concurrency_limit,
                    'patternSelectionStatus' => [
                        'numberLocale'        => get_user_locale(),
                        'empty'               => esc_html__('Aucune composition visible.', 'theme-export-jlg'),
                        'countSingular'       => esc_html__('%s composition visible.', 'theme-export-jlg'),
                        'countPlural'         => esc_html__('%s compositions visibles.', 'theme-export-jlg'),
                        'filterSearch'        => esc_html__('recherche « %s »', 'theme-export-jlg'),
                        'filterCategory'      => esc_html__('catégorie « %s »', 'theme-export-jlg'),
                        'filterCategoryNone'  => esc_html__('sans catégorie', 'theme-export-jlg'),
                        'filterDate'          => esc_html__('période « %s »', 'theme-export-jlg'),
                        'filterDateNone'      => esc_html__('sans date', 'theme-export-jlg'),
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
                            'cancel'   => 'tejlg_cancel_theme_export',
                        ],
                        'nonces' => [
                            'start'   => wp_create_nonce('tejlg_start_theme_export'),
                            'status'  => wp_create_nonce('tejlg_theme_export_status'),
                            'cancel'  => wp_create_nonce('tejlg_cancel_theme_export'),
                        ],
                        'pollInterval'    => 4000,
                        'maxPollInterval' => 20000,
                        'maxErrorRetries' => 4,
                        'strings'         => [
                            'initializing'    => esc_html__("Initialisation de l'export…", 'theme-export-jlg'),
                            'queued'          => esc_html__("Tâche en file d'attente…", 'theme-export-jlg'),
                            'inProgress'      => esc_html__('Fichiers traités : %1$d / %2$d', 'theme-export-jlg'),
                            'progressValue'   => esc_html__('Progression : %1$d%%', 'theme-export-jlg'),
                            'completed'       => esc_html__('Export terminé !', 'theme-export-jlg'),
                            'failed'          => esc_html__("Échec de l'export : %1$s", 'theme-export-jlg'),
                            'failedWithId'    => esc_html__("Échec de l'export (ID %2$s) : %1$s", 'theme-export-jlg'),
                            'downloadLabel'   => esc_html__("Télécharger l'archive ZIP", 'theme-export-jlg'),
                            'unknownError'    => esc_html__('Une erreur inattendue est survenue.', 'theme-export-jlg'),
                            'statusLabel'     => esc_html__('Statut de la tâche : %1$s', 'theme-export-jlg'),
                            'cancelled'       => esc_html__('Export annulé.', 'theme-export-jlg'),
                            'cancelling'      => esc_html__('Annulation…', 'theme-export-jlg'),
                            'cancelFailed'    => esc_html__("Impossible d'annuler l'export.", 'theme-export-jlg'),
                            'autoFailedStatus' => esc_html__('Export interrompu pour inactivité.', 'theme-export-jlg'),
                            'autoFailedMessage' => esc_html__('La tâche est restée inactive trop longtemps et a été arrêtée automatiquement.', 'theme-export-jlg'),
                            'cancelledStatus' => esc_html__('Export annulé.', 'theme-export-jlg'),
                            'cancelledMessage' => esc_html__("L'export a été annulé. Aucun fichier n'a été généré.", 'theme-export-jlg'),
                            'retrying'        => esc_html__('Nouvelle tentative dans %1$d s…', 'theme-export-jlg'),
                            'retryReady'      => esc_html__('Vous pouvez relancer la vérification du statut.', 'theme-export-jlg'),
                            'retryAnnouncement' => esc_html__('Relance en cours…', 'theme-export-jlg'),
                            'errorSupportHint' => esc_html__('Consultez la page Historique et communiquez l’identifiant ci-dessous si vous sollicitez le support.', 'theme-export-jlg'),
                            'jobStatusUnknown' => esc_html__('Statut inconnu', 'theme-export-jlg'),
                            'jobTimestampFallback' => esc_html__('Date inconnue', 'theme-export-jlg'),
                        ],
                        'previousJob' => TEJLG_Export::get_current_user_job_snapshot(),
                        'defaults'    => [
                            'exclusions' => $saved_exclusions,
                        ],
                        'patternTester' => [
                            'action' => 'tejlg_preview_exclusion_patterns',
                            'nonce'  => wp_create_nonce('tejlg_preview_exclusion_patterns'),
                            'strings' => [
                                'summary'        => esc_html__('%1$d fichier(s) inclus, %2$d exclu(s).', 'theme-export-jlg'),
                                'emptyList'      => esc_html__('Aucun fichier listé.', 'theme-export-jlg'),
                                'invalidPatterns' => esc_html__('Motifs invalides : %1$s', 'theme-export-jlg'),
                                'unknownError'   => esc_html__('Une erreur inattendue est survenue lors du test des motifs.', 'theme-export-jlg'),
                                'requestFailed'  => esc_html__('La requête a échoué. Veuillez réessayer.', 'theme-export-jlg'),
                                'successMessage' => esc_html__('Aperçu généré avec succès.', 'theme-export-jlg'),
                                'listSeparator'  => esc_html__(', ', 'theme-export-jlg'),
                            ],
                        ],
                        'jobMeta' => [
                            'storageKey' => 'tejlg:export:last-job',
                            'sessionStorageKey' => 'tejlg:export:active-job',
                            'labels'     => [
                                'title'        => esc_html__('Diagnostic de la tâche', 'theme-export-jlg'),
                                'jobId'        => esc_html__('Identifiant de la tâche', 'theme-export-jlg'),
                                'status'       => esc_html__('Statut courant', 'theme-export-jlg'),
                                'failureCode'  => esc_html__('Code d’erreur', 'theme-export-jlg'),
                                'noFailureCode' => esc_html__('Aucun code renvoyé', 'theme-export-jlg'),
                                'message'      => esc_html__('Dernier message', 'theme-export-jlg'),
                                'noMessage'    => esc_html__('Aucun message supplémentaire.', 'theme-export-jlg'),
                                'updated'      => esc_html__('Mis à jour : %s', 'theme-export-jlg'),
                                'copy'         => esc_html__('Copier l’ID', 'theme-export-jlg'),
                                'copySuccess'  => esc_html__('Identifiant copié !', 'theme-export-jlg'),
                                'copyFailed'   => esc_html__('Copie impossible : sélectionnez et copiez manuellement.', 'theme-export-jlg'),
                                'supportHint'  => esc_html__('Conservez ces informations pour vos rapports d’incident.', 'theme-export-jlg'),
                            ],
                            'retryButton' => esc_html__('Relancer la vérification', 'theme-export-jlg'),
                            'retryAnnouncement' => esc_html__('Relance en cours…', 'theme-export-jlg'),
                        ],
                        'resumeNotice' => [
                            'title'   => esc_html__('Export en cours détecté', 'theme-export-jlg'),
                            'active'  => esc_html__('Un export est toujours actif. Affichez le suivi pour vérifier sa progression.', 'theme-export-jlg'),
                            'queued'  => esc_html__('Un export est en attente de traitement. Cliquez sur « Afficher le suivi » pour reprendre.', 'theme-export-jlg'),
                            'inactive' => esc_html__('Le dernier suivi est indisponible. Relancez l’export pour obtenir un nouveau statut.', 'theme-export-jlg'),
                        ],
                    ],
                ]
            );
        }

        if ('import' === $active_tab && !$is_import_preview) {
            wp_enqueue_script('tejlg-admin-import', TEJLG_URL . 'assets/js/admin-import.js', ['tejlg-admin-base'], TEJLG_VERSION, true);

            wp_localize_script(
                'tejlg-admin-import',
                'tejlgAdminImportL10n',
                [
                    /* translators: Warning shown before importing a theme zip file. */
                    'themeImportConfirm' => __("⚠️ ATTENTION ⚠️\n\nSi un thème avec le même nom de dossier existe déjà, il sera DÉFINITIVEMENT écrasé.\n\nÊtes-vous sûr de vouloir continuer ?", 'theme-export-jlg'),
                ]
            );
        }

        if ('debug' === $active_tab) {
            wp_enqueue_script('tejlg-admin-debug', TEJLG_URL . 'assets/js/admin-debug.js', ['tejlg-admin-base'], TEJLG_VERSION, true);

            wp_localize_script(
                'tejlg-admin-debug',
                'tejlgAdminDebugL10n',
                [
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
                ]
            );
        }
    }

    public function handle_form_requests() {
        if (TEJLG_Capabilities::current_user_can('debug')) {
            $this->debug_page->handle_request();
        }

        if (TEJLG_Capabilities::current_user_can('exports')) {
            $this->export_page->handle_request();
        }

        if (TEJLG_Capabilities::current_user_can('imports')) {
            $this->import_page->handle_request();
        }
    }

    public function render_admin_page() {
        $tabs = $this->get_accessible_tabs();

        if (empty($tabs)) {
            wp_die(
                esc_html__("Vous n'avez pas les autorisations nécessaires pour accéder à Theme Export - JLG.", 'theme-export-jlg'),
                esc_html__('Accès refusé', 'theme-export-jlg'),
                [
                    'response' => 403,
                ]
            );
        }

        $requested_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
        $active_tab    = isset($tabs[$requested_tab]) ? $requested_tab : key($tabs);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_slug => $tab_config) :
                    $url = add_query_arg([
                        'page' => $this->page_slug,
                        'tab'  => $tab_slug,
                    ], admin_url('admin.php'));
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tab_config['label']); ?></a>
                <?php endforeach; ?>
            </h2>
            <?php
            $active_tab_config = $tabs[$active_tab];

            if (is_callable($active_tab_config['callback'])) {
                call_user_func($active_tab_config['callback']);
            }
            ?>
        </div>
        <?php
    }

    private function get_accessible_tabs() {
        $tabs = [
            'export' => [
                'label'    => __('Exporter & Outils', 'theme-export-jlg'),
                'cap'      => 'exports',
                'callback' => [ $this->export_page, 'render' ],
            ],
            'import' => [
                'label'    => __('Importer', 'theme-export-jlg'),
                'cap'      => 'imports',
                'callback' => [ $this->import_page, 'render' ],
            ],
            'migration_guide' => [
                'label'    => __('Guide de Migration', 'theme-export-jlg'),
                'cap'      => 'menu',
                'callback' => [ $this, 'render_migration_guide_tab' ],
            ],
            'debug' => [
                'label'    => __('Débogage', 'theme-export-jlg'),
                'cap'      => 'debug',
                'callback' => [ $this->debug_page, 'render' ],
            ],
        ];

        /**
         * Permet de modifier la liste des onglets disponibles dans l'interface d'administration.
         *
         * @param array<string,array<string,mixed>> $tabs
         */
        $tabs = apply_filters('tejlg_admin_tabs', $tabs);

        $accessible = [];

        foreach ($tabs as $slug => $config) {
            $cap_context = isset($config['cap']) ? (string) $config['cap'] : 'menu';

            if (TEJLG_Capabilities::current_user_can($cap_context)) {
                $accessible[$slug] = $config;
            }
        }

        return $accessible;
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
