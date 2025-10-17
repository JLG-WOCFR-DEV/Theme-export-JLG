<?php

require_once TEJLG_PATH . 'includes/class-tejlg-admin-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-export-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-import-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-debug-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-profiles-page.php';

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
    private $profiles_page;

    private $template_dir;

    private const COMPACT_MODE_META_KEY = 'tejlg_admin_compact_mode';

    /**
     * Cached quick actions configuration shared across admin tabs.
     *
     * @var array<int,array<string,mixed>>|null
     */
    private $quick_actions;

    public function __construct() {
        $this->template_dir = TEJLG_PATH . 'templates/admin/';

        $this->export_page   = new TEJLG_Admin_Export_Page($this->template_dir, $this->page_slug);
        $this->import_page   = new TEJLG_Admin_Import_Page($this->template_dir, $this->page_slug);
        $this->debug_page    = new TEJLG_Admin_Debug_Page($this->template_dir, $this->page_slug);
        $this->profiles_page = new TEJLG_Admin_Profiles_Page($this->template_dir, $this->page_slug);

        add_action('admin_menu', [ $this, 'add_menu_page' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ]);
        add_action('admin_init', [ $this, 'handle_form_requests' ]);
        add_action('wp_ajax_tejlg_toggle_compact_mode', [ $this, 'handle_toggle_compact_mode' ]);
        add_filter('admin_body_class', [ $this, 'filter_admin_body_class' ]);
        add_action('admin_post_tejlg_profiles_export', [ $this, 'handle_profiles_export_action' ]);
        add_action('admin_post_tejlg_profiles_import', [ $this, 'handle_profiles_import_action' ]);
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

        $compact_mode_enabled = $this->get_compact_mode_preference();

        wp_enqueue_script('tejlg-admin-base', TEJLG_URL . 'assets/js/admin-base.js', [], TEJLG_VERSION, true);
        wp_localize_script(
            'tejlg-admin-base',
            'tejlgAdminBaseSettings',
            [
                'compactMode' => [
                    'enabled' => $compact_mode_enabled,
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('tejlg_toggle_compact_mode'),
                    'messages' => [
                        'error' => esc_html__("La préférence d'affichage n'a pas pu être enregistrée.", 'theme-export-jlg'),
                    ],
                ],
            ]
        );

        $action_param = filter_input(INPUT_GET, 'action', FILTER_DEFAULT);

        if (null === $action_param && isset($_GET['action'])) {
            $action_param = $_GET['action'];
        }

        $current_action = is_string($action_param) ? sanitize_key($action_param) : '';
        $is_import_preview = ('import' === $active_tab && 'preview_patterns' === $current_action);

        if ('export' === $active_tab || $is_import_preview) {
            wp_enqueue_script('tejlg-admin-export', TEJLG_URL . 'assets/js/admin-export.js', ['tejlg-admin-base'], TEJLG_VERSION, true);
            wp_enqueue_script('tejlg-admin-assistant', TEJLG_URL . 'assets/js/admin-assistant.js', ['tejlg-admin-export'], TEJLG_VERSION, true);

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
                            'summaryLabel'    => esc_html__('Télécharger le résumé JSON', 'theme-export-jlg'),
                            'summaryHint'     => esc_html__('%1$d fichier(s) inclus · %2$d exclu(s)', 'theme-export-jlg'),
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
                        'banner'         => [
                            'statusText'    => esc_html__('Statut : %s', 'theme-export-jlg'),
                            'sizeText'      => esc_html__('Taille : %s', 'theme-export-jlg'),
                            'sizeUnknown'   => esc_html__('Inconnue', 'theme-export-jlg'),
                            'exclusionsText' => esc_html__('Motifs : %s', 'theme-export-jlg'),
                            'noExclusion'   => esc_html__('Aucun motif', 'theme-export-jlg'),
                            'summaryCounts' => esc_html__('%1$d fichier(s) inclus · %2$d exclu(s)', 'theme-export-jlg'),
                            'summaryWarnings' => esc_html__('Avertissements : %s', 'theme-export-jlg'),
                            'statusLabels'  => [
                                'completed' => esc_html__('Succès', 'theme-export-jlg'),
                                'failed'    => esc_html__('Échec', 'theme-export-jlg'),
                                'cancelled' => esc_html__('Annulé', 'theme-export-jlg'),
                                'processing'=> esc_html__('En cours', 'theme-export-jlg'),
                                'queued'    => esc_html__('En attente', 'theme-export-jlg'),
                            ],
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

            $assistant_hints = [
                'theme-export' => [
                    'selection'    => esc_html__('Vérifiez les informations du thème actif avant de poursuivre.', 'theme-export-jlg'),
                    'confirmation' => esc_html__('Testez vos motifs d’exclusion pour éviter toute mauvaise surprise dans l’archive.', 'theme-export-jlg'),
                    'summary'      => esc_html__('Relisez le résumé puis lancez l’export lorsque tout est prêt.', 'theme-export-jlg'),
                ],
                'import-preview' => [
                    'selection'    => esc_html__('Filtrez la liste et cochez uniquement les compositions à importer.', 'theme-export-jlg'),
                    'confirmation' => esc_html__('Contrôlez votre sélection et consultez les avertissements éventuels.', 'theme-export-jlg'),
                    'summary'      => esc_html__('Téléchargez le résumé pour archivage puis lancez l’import.', 'theme-export-jlg'),
                ],
            ];

            $assistant_strings = [
                'selectionEmpty'        => esc_html__('Aucune composition sélectionnée.', 'theme-export-jlg'),
                'selectionCountSingular'=> esc_html__('%s composition sélectionnée.', 'theme-export-jlg'),
                'selectionCountPlural'  => esc_html__('%s compositions sélectionnées.', 'theme-export-jlg'),
                'filtersEmpty'          => esc_html__('Aucun filtre actif.', 'theme-export-jlg'),
                'warningsEmpty'         => esc_html__('Aucun avertissement.', 'theme-export-jlg'),
                'previewLimit'          => esc_html__('Aperçu des %1$d premières compositions.', 'theme-export-jlg'),
                'filterSearch'          => esc_html__('Recherche « %s »', 'theme-export-jlg'),
                'filterCategory'        => esc_html__('Catégorie « %s »', 'theme-export-jlg'),
                'filterDate'            => esc_html__('Période « %s »', 'theme-export-jlg'),
                'filterSort'            => esc_html__('Tri : %s', 'theme-export-jlg'),
                'downloadFileName'      => esc_html__('assistant-summary-%date%.json', 'theme-export-jlg'),
                'untitled'              => esc_html__('Sans titre', 'theme-export-jlg'),
                'locale'                => get_user_locale(),
            ];

            $assistant_configs = [
                'theme-export' => [
                    'downloadFileName' => esc_html__('theme-export-summary-%date%.json', 'theme-export-jlg'),
                ],
                'import-preview' => [
                    'downloadFileName' => esc_html__('patterns-import-summary-%date%.json', 'theme-export-jlg'),
                ],
            ];

            wp_localize_script(
                'tejlg-admin-assistant',
                'tejlgAssistantSettings',
                [
                    'storagePrefix' => 'tejlg:assistant:',
                    'locale'        => get_user_locale(),
                    'hints'         => $assistant_hints,
                    'strings'       => $assistant_strings,
                    'assistants'    => $assistant_configs,
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

        if (TEJLG_Capabilities::current_user_can('settings')) {
            $this->profiles_page->handle_request();
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

        $quick_actions = $this->prepare_quick_actions($active_tab);
        $shared_context = [
            'quick_actions' => $quick_actions,
            'quick_actions_settings' => [
                'current_tab' => $active_tab,
                'page_slug'   => $this->page_slug,
            ],
        ];

        $this->export_page->set_shared_context($shared_context);
        $this->import_page->set_shared_context($shared_context);
        $this->debug_page->set_shared_context($shared_context);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="tejlg-admin-toolbar" role="toolbar" aria-label="<?php echo esc_attr__('Navigation principale de Theme Export', 'theme-export-jlg'); ?>">
                <div class="tejlg-admin-toolbar__nav">
                    <h2 class="nav-tab-wrapper">
                        <?php foreach ($tabs as $tab_slug => $tab_config) :
                            $url = add_query_arg([
                                'page' => $this->page_slug,
                                'tab'  => $tab_slug,
                            ], admin_url('admin.php'));
                            $is_active = ($active_tab === $tab_slug);
                        ?>
                            <a
                                href="<?php echo esc_url($url); ?>"
                                class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"
                                <?php echo $is_active ? 'aria-current="page"' : ''; ?>
                            >
                                <?php echo esc_html($tab_config['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </h2>
                    <div class="tejlg-mobile-accordion" data-tejlg-mobile-accordion>
                        <?php foreach ($tabs as $tab_slug => $tab_config) :
                            $url = add_query_arg([
                                'page' => $this->page_slug,
                                'tab'  => $tab_slug,
                            ], admin_url('admin.php'));
                            $is_active = ($active_tab === $tab_slug);
                            $trigger_id = sprintf('tejlg-mobile-accordion-trigger-%s', sanitize_html_class($tab_slug));
                            $panel_id = sprintf('tejlg-mobile-accordion-panel-%s', sanitize_html_class($tab_slug));
                        ?>
                            <div class="tejlg-mobile-accordion__item">
                                <button
                                    type="button"
                                    class="tejlg-mobile-accordion__trigger"
                                    id="<?php echo esc_attr($trigger_id); ?>"
                                    data-tejlg-accordion-trigger
                                    data-default-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
                                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                                    aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
                                >
                                    <span class="tejlg-mobile-accordion__title"><?php echo esc_html($tab_config['label']); ?></span>
                                    <span class="tejlg-mobile-accordion__icon" aria-hidden="true"></span>
                                </button>
                                <div
                                    id="<?php echo esc_attr($panel_id); ?>"
                                    class="tejlg-mobile-accordion__panel"
                                    data-tejlg-accordion-panel
                                    role="region"
                                    aria-labelledby="<?php echo esc_attr($trigger_id); ?>"
                                    <?php echo $is_active ? '' : 'hidden'; ?>
                                >
                                    <a
                                        class="tejlg-mobile-accordion__link<?php echo $is_active ? ' is-current' : ''; ?>"
                                        data-tejlg-accordion-link
                                        href="<?php echo esc_url($url); ?>"
                                    >
                                        <?php esc_html_e('Ouvrir la section', 'theme-export-jlg'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tejlg-admin-toolbar__actions">
                    <?php $compact_mode_enabled = $this->get_compact_mode_preference(); ?>
                    <div class="tejlg-compact-toggle components-toggle-control">
                        <div class="components-base-control components-base-control__field">
                            <div class="components-form-toggle">
                                <input
                                    class="components-form-toggle__input"
                                    type="checkbox"
                                    id="tejlg-compact-view-toggle"
                                    data-tejlg-compact-toggle
                                    <?php checked($compact_mode_enabled); ?>
                                >
                                <span class="components-form-toggle__track"></span>
                                <span class="components-form-toggle__thumb"></span>
                            </div>
                            <label class="components-toggle-control__label" for="tejlg-compact-view-toggle">
                                <?php esc_html_e('Vue compacte', 'theme-export-jlg'); ?>
                            </label>
                        </div>
                        <p class="components-toggle-control__help">
                            <?php esc_html_e('Réduit les marges et regroupe les cartes pour faciliter la navigation.', 'theme-export-jlg'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
            $active_tab_config = $tabs[$active_tab];

            if (is_callable($active_tab_config['callback'])) {
                call_user_func($active_tab_config['callback']);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Prepare the list of quick actions displayed in the floating radial menu.
     *
     * @param string $active_tab
     *
     * @return array<int,array<string,mixed>>
     */
    private function prepare_quick_actions($active_tab) {
        if (null !== $this->quick_actions) {
            return $this->quick_actions;
        }

        $export_tab_url = add_query_arg([
            'page' => $this->page_slug,
            'tab'  => 'export',
        ], admin_url('admin.php'));

        $actions = [
            [
                'id'         => 'export-now',
                'label'      => __('Exporter maintenant', 'theme-export-jlg'),
                'url'        => $export_tab_url . '#tejlg-theme-export-form',
                'aria_label' => __('Aller au formulaire principal d’export.', 'theme-export-jlg'),
            ],
        ];

        $history_snapshot = TEJLG_Export_History::get_entries([
            'per_page' => 1,
            'paged'    => 1,
            'orderby'  => 'timestamp',
            'order'    => 'desc',
        ]);

        $latest_entry = null;

        if (
            isset($history_snapshot['entries'][0])
            && is_array($history_snapshot['entries'][0])
        ) {
            $latest_entry = $history_snapshot['entries'][0];
        }

        $latest_archive_url = '';
        $latest_archive_context = [
            'timestamp'   => 0,
            'size_bytes'  => 0,
            'result'      => '',
            'download_url'=> '',
        ];

        if ($latest_entry) {
            if (!empty($latest_entry['persistent_url']) && is_string($latest_entry['persistent_url'])) {
                $latest_archive_url = $latest_entry['persistent_url'];
            } elseif (!empty($latest_entry['download_url']) && is_string($latest_entry['download_url'])) {
                $latest_archive_url = $latest_entry['download_url'];
            }

            $latest_archive_context['timestamp'] = isset($latest_entry['timestamp'])
                ? (int) $latest_entry['timestamp']
                : 0;
            $latest_archive_context['size_bytes'] = isset($latest_entry['zip_file_size'])
                ? (int) $latest_entry['zip_file_size']
                : 0;
            $latest_archive_context['result'] = isset($latest_entry['result'])
                ? sanitize_key((string) $latest_entry['result'])
                : '';
            $latest_archive_context['download_url'] = $latest_archive_url;
        }

        if ('' !== $latest_archive_url) {
            $date_format = get_option('date_format', 'Y-m-d');
            $time_format = get_option('time_format', 'H:i');
            $datetime_label = '';

            if ($latest_archive_context['timestamp'] > 0) {
                if (function_exists('wp_date')) {
                    $datetime_label = wp_date($date_format . ' ' . $time_format, $latest_archive_context['timestamp']);
                } else {
                    $datetime_label = date_i18n($date_format . ' ' . $time_format, $latest_archive_context['timestamp']);
                }
            }

            $size_label = '';

            if ($latest_archive_context['size_bytes'] > 0) {
                $size_label = size_format($latest_archive_context['size_bytes'], 2);
            }

            if ('' !== $datetime_label || '' !== $size_label) {
                $latest_archive_context['description'] = '' !== $datetime_label && '' !== $size_label
                    ? sprintf(__('Généré le %1$s · %2$s', 'theme-export-jlg'), $datetime_label, $size_label)
                    : ('' !== $datetime_label
                        ? sprintf(__('Généré le %s', 'theme-export-jlg'), $datetime_label)
                        : sprintf(__('Taille : %s', 'theme-export-jlg'), $size_label));
            } else {
                $latest_archive_context['description'] = '';
            }

            $actions[] = [
                'id'          => 'latest-archive',
                'label'       => __('Dernière archive', 'theme-export-jlg'),
                'url'         => $latest_archive_url,
                'target'      => '_blank',
                'rel'         => 'noopener noreferrer',
                'description' => isset($latest_archive_context['description']) ? $latest_archive_context['description'] : '',
                'aria_label'  => isset($latest_archive_context['description']) && '' !== $latest_archive_context['description']
                    ? sprintf(__('Télécharger la dernière archive (%s)', 'theme-export-jlg'), $latest_archive_context['description'])
                    : __('Télécharger la dernière archive générée.', 'theme-export-jlg'),
            ];
        }

        $debug_tab_url = add_query_arg([
            'page' => $this->page_slug,
            'tab'  => 'debug',
        ], admin_url('admin.php'));

        $actions[] = [
            'id'         => 'debug-report',
            'label'      => __('Rapport de débogage', 'theme-export-jlg'),
            'url'        => $debug_tab_url . '#tejlg-section-debug',
            'aria_label' => __('Ouvrir les outils de diagnostic et télécharger le rapport.', 'theme-export-jlg'),
        ];

        $import_tab_url = add_query_arg([
            'page' => $this->page_slug,
            'tab'  => 'import',
        ], admin_url('admin.php'));

        $actions[] = [
            'id'         => 'open-import',
            'label'      => __('Ouvrir l’onglet Import', 'theme-export-jlg'),
            'url'        => $import_tab_url . '#tejlg-section-import',
            'aria_label' => __('Aller directement aux formulaires d’import.', 'theme-export-jlg'),
        ];

        $filter_context = [
            'current_tab'   => $active_tab,
            'page_slug'     => $this->page_slug,
            'latest_export' => $latest_entry,
            'latest_context'=> $latest_archive_context,
        ];

        /**
         * Allows third-party extensions to adjust the floating quick actions.
         *
         * @param array<int,array<string,mixed>> $actions
         * @param array<string,mixed>            $context
         */
        $actions = apply_filters('tejlg_quick_actions', $actions, $filter_context);

        if (!is_array($actions)) {
            $this->quick_actions = [];

            return $this->quick_actions;
        }

        $normalized = [];

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }

            $label = isset($action['label']) ? (string) $action['label'] : '';
            $label = trim($label);

            if ('' === $label) {
                continue;
            }

            $type = isset($action['type']) ? (string) $action['type'] : 'link';
            $type = in_array($type, ['link', 'button'], true) ? $type : 'link';

            if ('link' === $type) {
                $url = isset($action['url']) ? (string) $action['url'] : '';

                if ('' === trim($url)) {
                    continue;
                }
            }

            $id = isset($action['id']) ? sanitize_key((string) $action['id']) : '';

            if ('' === $id) {
                $id = 'quick-action-' . $index;
            }

            $normalized[] = [
                'id'          => $id,
                'type'        => $type,
                'label'       => $label,
                'url'         => isset($action['url']) ? (string) $action['url'] : '',
                'target'      => isset($action['target']) ? (string) $action['target'] : '',
                'rel'         => isset($action['rel']) ? (string) $action['rel'] : '',
                'description' => isset($action['description']) ? (string) $action['description'] : '',
                'aria_label'  => isset($action['aria_label']) ? (string) $action['aria_label'] : '',
                'attributes'  => isset($action['attributes']) && is_array($action['attributes'])
                    ? $action['attributes']
                    : [],
            ];
        }

        $this->quick_actions = $normalized;

        return $this->quick_actions;
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
            'profiles' => [
                'label'    => __('Profils', 'theme-export-jlg'),
                'cap'      => 'settings',
                'callback' => [ $this->profiles_page, 'render' ],
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

    public function handle_profiles_export_action() {
        if (!TEJLG_Capabilities::current_user_can('settings')) {
            wp_die(
                esc_html__("Vous n'avez pas les autorisations nécessaires pour exporter un profil.", 'theme-export-jlg'),
                esc_html__('Accès refusé', 'theme-export-jlg'),
                [
                    'response' => 403,
                ]
            );
        }

        check_admin_referer('tejlg_profiles_export_action', 'tejlg_profiles_export_nonce');

        $result = $this->profiles_page->handle_request();

        if (is_wp_error($result) || !is_array($result) || !isset($result['type']) || 'export' !== $result['type']) {
            if (!is_wp_error($result)) {
                add_settings_error(
                    'tejlg_profiles_messages',
                    'profiles_export_unexpected',
                    esc_html__("Erreur : l'export du profil n'a pas pu être initialisé.", 'theme-export-jlg'),
                    'error'
                );
            }

            $this->persist_profiles_messages();
            $this->redirect_to_profiles_tab('false');
        }

        $filename = isset($result['filename']) ? (string) $result['filename'] : 'theme-export-profiles.json';
        $payload  = isset($result['json']) ? (string) $result['json'] : '';

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        echo $payload;
        exit;
    }

    public function handle_profiles_import_action() {
        if (!TEJLG_Capabilities::current_user_can('settings')) {
            wp_die(
                esc_html__("Vous n'avez pas les autorisations nécessaires pour importer un profil.", 'theme-export-jlg'),
                esc_html__('Accès refusé', 'theme-export-jlg'),
                [
                    'response' => 403,
                ]
            );
        }

        check_admin_referer('tejlg_profiles_import_action', 'tejlg_profiles_import_nonce');

        $result = $this->profiles_page->handle_request();

        $this->persist_profiles_messages();

        $status = 'false';

        if (is_array($result) && isset($result['status']) && 'success' === $result['status']) {
            $status = 'true';
        }

        $this->redirect_to_profiles_tab($status);
    }

    private function persist_profiles_messages() {
        $errors = get_settings_errors('tejlg_profiles_messages');
        set_transient('settings_errors', $errors, 30);
    }

    private function redirect_to_profiles_tab($updated) {
        $redirect_url = add_query_arg(
            [
                'page'             => $this->page_slug,
                'tab'              => 'profiles',
                'settings-updated' => $updated,
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=profiles');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function filter_admin_body_class($classes) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || $screen->id !== 'toplevel_page_' . $this->page_slug) {
            return $classes;
        }

        if ($this->get_compact_mode_preference()) {
            $classes .= ' tejlg-compact-mode';
        }

        return $classes;
    }

    public function handle_toggle_compact_mode() {
        if (!TEJLG_Capabilities::current_user_can('menu')) {
            wp_send_json_error([
                'message' => esc_html__("Vous n'avez pas les autorisations nécessaires pour modifier cette option.", 'theme-export-jlg'),
            ], 403);
        }

        check_ajax_referer('tejlg_toggle_compact_mode');

        $state = isset($_POST['state']) ? (string) $_POST['state'] : '0';
        $enabled = in_array($state, ['1', 'true', 'on'], true);

        $this->set_compact_mode_preference($enabled);

        wp_send_json_success([
            'enabled' => $enabled,
        ]);
    }

    private function get_compact_mode_preference() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        $stored = get_user_meta($user_id, self::COMPACT_MODE_META_KEY, true);

        return !empty($stored);
    }

    private function set_compact_mode_preference($enabled) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        if ($enabled) {
            update_user_meta($user_id, self::COMPACT_MODE_META_KEY, '1');
        } else {
            delete_user_meta($user_id, self::COMPACT_MODE_META_KEY);
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
