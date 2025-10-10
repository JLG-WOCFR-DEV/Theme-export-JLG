/**
 * @jest-environment jsdom
 */

describe('admin export resume persistence', () => {
    const sessionKey = 'tejlg:test:active-job';
    const storageKey = 'tejlg:test:last-job';
    const resumeStrings = {
        title: 'Export détecté',
        active: 'Un export est en cours.',
        queued: 'Export en attente de démarrage.',
        inactive: 'Aucun export actif.',
    };

    let hooks;

    const setUpDom = () => {
        document.body.innerHTML = `
            <div>
                <div data-export-resume hidden>
                    <p data-export-resume-title>${resumeStrings.title}</p>
                    <p data-export-resume-text>${resumeStrings.active}</p>
                    <div>
                        <button data-export-resume-resume type="button"></button>
                        <button data-export-resume-dismiss type="button"></button>
                    </div>
                </div>
                <form data-export-form>
                    <textarea id="tejlg_exclusion_patterns"></textarea>
                    <button type="button" data-export-start></button>
                    <div data-export-feedback hidden></div>
                    <span data-export-status-text></span>
                    <p data-export-message></p>
                    <progress data-export-progress-bar></progress>
                    <a data-export-download></a>
                    <button type="button" data-export-cancel hidden></button>
                    <div data-export-guidance hidden></div>
                    <div data-export-job-meta hidden>
                        <span data-export-job-title></span>
                        <span data-export-job-id></span>
                        <span data-export-job-status></span>
                        <span data-export-job-code></span>
                        <span data-export-job-message></span>
                        <span data-export-job-updated></span>
                        <p data-export-job-hint hidden></p>
                        <button type="button" data-export-job-copy></button>
                    </div>
                    <button type="button" data-export-retry hidden></button>
                </form>
            </div>
        `;
    };

    const loadScript = () => {
        jest.isolateModules(() => {
            require('../../theme-export-jlg/assets/js/admin-export.js');
        });
    };

    beforeEach(() => {
        jest.resetModules();
        window.sessionStorage.clear();
        window.localStorage.clear();
        setUpDom();

        window.__tejlgExportTestHooks = {};
        window.tejlgAdminL10n = {
            exportAsync: {
                ajaxUrl: '/wp-admin/admin-ajax.php',
                actions: {
                    start: 'tejlg_start_export',
                    status: 'tejlg_get_export_status',
                    cancel: 'tejlg_cancel_export',
                },
                nonces: {
                    start: 'nonce',
                    status: 'nonce',
                    cancel: 'nonce',
                },
                pollInterval: 2000,
                maxPollInterval: 4000,
                maxErrorRetries: 2,
                defaults: null,
                patternTester: null,
                strings: {
                    statusLabel: 'Statut : %1$s',
                    jobStatusUnknown: 'Inconnu',
                    queued: 'En file',
                    initializing: 'Initialisation',
                    progressValue: '%1$s',
                    cancelledStatus: 'Annulé',
                    cancelledMessage: 'Export annulé.',
                    failed: 'Échec : %1$s',
                    failedWithId: 'Export %1$s : %2$s',
                    failedWithoutId: 'Échec : %1$s',
                    retryReady: 'Réessayer',
                    retryAnnouncement: 'Nouvelle tentative prête.',
                    success: 'Export terminé.',
                    inProgress: '%1$s sur %2$s',
                    unknownError: 'Erreur inconnue',
                    errorSupportHint: 'Contactez le support',
                },
                jobMeta: {
                    storageKey: storageKey,
                    sessionStorageKey: sessionKey,
                    retryButton: 'Réessayer',
                    retryAnnouncement: 'Nouvelle tentative',
                    labels: {
                        title: 'Détails du job',
                        copy: 'Copier',
                        updated: 'Mis à jour %1$s',
                        noFailureCode: 'Aucun code',
                        supportHint: 'Besoin d’aide ?',
                    },
                },
                resumeNotice: resumeStrings,
            },
        };

        loadScript();
        document.dispatchEvent(new Event('DOMContentLoaded'));

        hooks = window.__tejlgExportTestHooks.adminExport;
        if (!hooks) {
            throw new Error('Test hooks were not registered.');
        }
    });

    afterEach(() => {
        delete window.__tejlgExportTestHooks;
        delete window.tejlgAdminL10n;
        hooks = undefined;
    });

    const getSessionSnapshot = () => {
        const raw = window.sessionStorage.getItem(sessionKey);
        return raw ? JSON.parse(raw) : null;
    };

    test('stores queued job state in session storage and shows resume banner', () => {
        hooks.rememberActiveJob('job-123', 'queued');

        const snapshot = getSessionSnapshot();
        expect(snapshot).toEqual({ id: 'job-123', status: 'queued' });

        const banner = hooks.getResumeBannerElement();
        expect(banner).not.toBeNull();
        expect(banner.hidden).toBe(false);
        expect(banner.dataset.jobId).toBe('job-123');

        const messageEl = banner.querySelector('[data-export-resume-text]');
        expect(messageEl.textContent).toBe(resumeStrings.queued);
    });

    test('processing job keeps banner visible until cancelled manually', () => {
        hooks.rememberActiveJob('job-456', 'processing');

        let snapshot = getSessionSnapshot();
        expect(snapshot).toEqual({ id: 'job-456', status: 'processing' });

        const banner = hooks.getResumeBannerElement();
        expect(banner.hidden).toBe(false);
        expect(banner.querySelector('[data-export-resume-text]').textContent).toBe(resumeStrings.active);

        hooks.forgetActiveJob();

        snapshot = getSessionSnapshot();
        expect(snapshot).toBeNull();
        expect(banner.hidden).toBe(true);
    });

    test('non-active job snapshot keeps storage but hides the banner', () => {
        hooks.rememberActiveJob('job-789', 'failed');

        const snapshot = getSessionSnapshot();
        expect(snapshot).toEqual({ id: 'job-789', status: 'failed' });

        const banner = hooks.getResumeBannerElement();
        expect(banner.hidden).toBe(true);
        const messageEl = banner.querySelector('[data-export-resume-text]');
        expect(messageEl.textContent).toBe(resumeStrings.inactive);
    });
});
