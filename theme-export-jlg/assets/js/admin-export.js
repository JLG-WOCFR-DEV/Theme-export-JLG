document.addEventListener('DOMContentLoaded', function() {

    const localization = (typeof window.tejlgAdminL10n === 'object' && window.tejlgAdminL10n !== null)
        ? window.tejlgAdminL10n
        : {};

    const showBlockCodeText = typeof localization.showBlockCode === 'string'
        ? localization.showBlockCode
        : '';

    const hideBlockCodeText = typeof localization.hideBlockCode === 'string'
        ? localization.hideBlockCode
        : '';

    const previewFallbackWarning = typeof localization.previewFallbackWarning === 'string'
        ? localization.previewFallbackWarning
        : 'La prévisualisation a été chargée via un mode de secours. Certaines fonctionnalités peuvent être limitées.';

    const previewQueueMessage = typeof localization.previewQueueMessage === 'string'
        ? localization.previewQueueMessage
        : '';

    const previewConcurrencyLimit = Math.max(
        1,
        Number.isFinite(parseInt(localization.previewConcurrencyLimit, 10))
            ? parseInt(localization.previewConcurrencyLimit, 10)
            : 2
    );

    const exportAsync = (localization && typeof localization.exportAsync === 'object')
        ? localization.exportAsync
        : null;

    (function initializeContrastToggle() {
        const toggle = document.querySelector('[data-contrast-toggle]');
        const presetButtons = document.querySelectorAll('[data-contrast-preset]');

        if (!toggle && !presetButtons.length) {
            return;
        }

        const storageKey = 'tejlg:admin:contrast-mode';
        const root = document.body || document.documentElement;

        const readStoredPreference = function() {
            try {
                return window.localStorage.getItem(storageKey);
            } catch (error) {
                return null;
            }
        };

        let storedPreference = readStoredPreference();
        let hasStoredPreference = storedPreference === '0' || storedPreference === '1';

        const prefersHighContrast = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-contrast: more)').matches
            : false;

        let enabled = storedPreference === '1' || (!hasStoredPreference && prefersHighContrast);

        const syncPresetButtons = function(state) {
            if (!presetButtons.length) {
                return;
            }

            presetButtons.forEach(function(button) {
                button.classList.toggle('is-active', state);
                button.setAttribute('aria-pressed', state ? 'true' : 'false');
            });
        };

        const applyState = function(state) {
            if (root) {
                root.classList.toggle('tejlg-contrast-enabled', state);
            }

            if (toggle && toggle.checked !== state) {
                toggle.checked = state;
            }

            syncPresetButtons(state);
        };

        const persistState = function(state) {
            try {
                window.localStorage.setItem(storageKey, state ? '1' : '0');
                storedPreference = state ? '1' : '0';
                hasStoredPreference = true;
            } catch (error) {
                // Ignore storage failures to keep the UI responsive.
            }
        };

        applyState(enabled);

        if (toggle) {
            toggle.addEventListener('change', function() {
                enabled = !!toggle.checked;
                applyState(enabled);
                persistState(enabled);
            });
        }

        if (presetButtons.length) {
            presetButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const nextState = !button.classList.contains('is-active');
                    enabled = nextState;
                    applyState(enabled);
                    persistState(enabled);
                });
            });
        }

        if (!hasStoredPreference && typeof window.matchMedia === 'function') {
            try {
                const mediaQuery = window.matchMedia('(prefers-contrast: more)');

                const handlePreferenceChange = function(event) {
                    if (hasStoredPreference) {
                        return;
                    }

                    enabled = !!event.matches;
                    applyState(enabled);
                };

                if (typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', handlePreferenceChange);
                } else if (typeof mediaQuery.addListener === 'function') {
                    mediaQuery.addListener(handlePreferenceChange);
                }
            } catch (error) {
                // Ignore preference observer errors to avoid breaking other scripts.
            }
        }
    })();

    (function initializeAccordions() {
        const sections = document.querySelectorAll('[data-tejlg-accordion]');

        if (!sections.length) {
            return;
        }

        const storagePrefix = 'tejlg:admin:accordion:';

        const readStoredState = function(key) {
            if (!key) {
                return null;
            }

            try {
                const raw = window.localStorage.getItem(storagePrefix + key);

                if (raw === '0') {
                    return false;
                }

                if (raw === '1') {
                    return true;
                }
            } catch (error) {
                return null;
            }

            return null;
        };

        const writeStoredState = function(key, state) {
            if (!key) {
                return;
            }

            try {
                window.localStorage.setItem(storagePrefix + key, state ? '1' : '0');
            } catch (error) {
                // Ignore storage errors.
            }
        };

        sections.forEach(function(section) {
            const trigger = section.querySelector('[data-accordion-trigger]');
            const panel = section.querySelector('[data-accordion-panel]');

            if (!trigger || !panel) {
                return;
            }

            const id = section.getAttribute('data-accordion-id') || panel.id || '';

            if (!id) {
                return;
            }

            const defaultOpen = section.getAttribute('data-accordion-open') === '1';
            const storedState = readStoredState(id);
            let expanded = storedState === null ? defaultOpen : storedState;

            const applyState = function(state) {
                expanded = !!state;
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                section.setAttribute('data-accordion-open', expanded ? '1' : '0');

                if (expanded) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            };

            applyState(expanded);

            trigger.addEventListener('click', function(event) {
                event.preventDefault();
                applyState(!expanded);
                writeStoredState(id, expanded);
            });

            trigger.addEventListener('keydown', function(event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    const focusableTriggers = Array.prototype.slice.call(document.querySelectorAll('[data-accordion-trigger]'));
                    const currentIndex = focusableTriggers.indexOf(trigger);

                    if (currentIndex === -1) {
                        return;
                    }

                    const nextIndex = event.key === 'ArrowDown'
                        ? (currentIndex + 1) % focusableTriggers.length
                        : (currentIndex - 1 + focusableTriggers.length) % focusableTriggers.length;

                    const nextTrigger = focusableTriggers[nextIndex];

                    if (nextTrigger && typeof nextTrigger.focus === 'function') {
                        event.preventDefault();
                        nextTrigger.focus();
                    }
                } else if (event.key === 'Home') {
                    const focusableTriggers = document.querySelectorAll('[data-accordion-trigger]');

                    if (focusableTriggers.length) {
                        event.preventDefault();
                        focusableTriggers[0].focus();
                    }
                } else if (event.key === 'End') {
                    const focusableTriggers = document.querySelectorAll('[data-accordion-trigger]');

                    if (focusableTriggers.length) {
                        event.preventDefault();
                        focusableTriggers[focusableTriggers.length - 1].focus();
                    }
                }
            });
        });
    })();

    (function initializeTooltips() {
        const triggers = document.querySelectorAll('[data-tejlg-tooltip-trigger]');

        if (!triggers.length) {
            return;
        }

        triggers.forEach(function(trigger) {
            const targetId = trigger.getAttribute('aria-controls');
            const target = targetId ? document.getElementById(targetId) : null;

            if (!target) {
                return;
            }

            let isOpen = false;

            const closeTooltip = function() {
                isOpen = false;
                trigger.setAttribute('aria-expanded', 'false');
                target.setAttribute('hidden', '');
            };

            const openTooltip = function() {
                isOpen = true;
                trigger.setAttribute('aria-expanded', 'true');
                target.removeAttribute('hidden');
            };

            const handleDocumentClick = function(event) {
                if (!isOpen) {
                    return;
                }

                if (!trigger.contains(event.target) && !target.contains(event.target)) {
                    closeTooltip();
                }
            };

            document.addEventListener('click', handleDocumentClick);

            trigger.addEventListener('click', function(event) {
                event.preventDefault();

                if (isOpen) {
                    closeTooltip();
                } else {
                    openTooltip();
                }
            });

            trigger.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isOpen) {
                    event.preventDefault();
                    closeTooltip();
                    trigger.focus();
                }
            });

            target.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isOpen) {
                    event.preventDefault();
                    closeTooltip();
                    trigger.focus();
                }
            });
        });
    })();

    if (exportAsync) {
        const exportForm = document.querySelector('[data-export-form]');

        if (exportForm && exportAsync.ajaxUrl && exportAsync.actions && exportAsync.nonces) {
            const textarea = exportForm.querySelector('#tejlg_exclusion_patterns');
            const startButton = exportForm.querySelector('[data-export-start]');
            const feedback = exportForm.querySelector('[data-export-feedback]');
            const statusText = exportForm.querySelector('[data-export-status-text]');
            const messageEl = exportForm.querySelector('[data-export-message]');
            const progressBar = exportForm.querySelector('[data-export-progress-bar]');
            const downloadLink = exportForm.querySelector('[data-export-download]');
            const cancelButton = exportForm.querySelector('[data-export-cancel]');
            const spinner = exportForm.querySelector('[data-export-spinner]');
            const strings = typeof exportAsync.strings === 'object' ? exportAsync.strings : {};
            const basePollInterval = Math.max(1000, typeof exportAsync.pollInterval === 'number' ? exportAsync.pollInterval : 4000);
            const maxPollInterval = Math.max(basePollInterval, typeof exportAsync.maxPollInterval === 'number' ? exportAsync.maxPollInterval : Math.max(basePollInterval * 4, basePollInterval + 8000));
            const maxErrorRetries = Math.max(0, typeof exportAsync.maxErrorRetries === 'number' ? exportAsync.maxErrorRetries : 4);
            const defaults = (typeof exportAsync.defaults === 'object' && exportAsync.defaults !== null)
                ? exportAsync.defaults
                : null;
            const patternTester = (typeof exportAsync.patternTester === 'object' && exportAsync.patternTester !== null)
                ? exportAsync.patternTester
                : null;
            const guidanceEl = exportForm.querySelector('[data-export-guidance]');
            const jobMetaContainer = exportForm.querySelector('[data-export-job-meta]');
            const jobMetaTitle = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-title]') : null;
            const jobMetaId = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-id]') : null;
            const jobMetaStatus = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-status]') : null;
            const jobMetaCode = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-code]') : null;
            const jobMetaMessage = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-message]') : null;
            const jobMetaUpdated = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-updated]') : null;
            const jobMetaHint = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-hint]') : null;
            const jobMetaCopyButton = jobMetaContainer ? jobMetaContainer.querySelector('[data-export-job-copy]') : null;
            const retryButton = exportForm.querySelector('[data-export-retry]');
            const jobMetaConfig = (typeof exportAsync.jobMeta === 'object' && exportAsync.jobMeta !== null) ? exportAsync.jobMeta : {};
            const jobMetaLabels = (typeof jobMetaConfig.labels === 'object' && jobMetaConfig.labels !== null) ? jobMetaConfig.labels : {};
            const jobStorageKey = typeof jobMetaConfig.storageKey === 'string' && jobMetaConfig.storageKey.length
                ? jobMetaConfig.storageKey
                : 'tejlg:export:last-job';
            const jobSessionStorageKey = typeof jobMetaConfig.sessionStorageKey === 'string' && jobMetaConfig.sessionStorageKey.length
                ? jobMetaConfig.sessionStorageKey
                : 'tejlg:export:active-job';
            const jobRetryLabel = typeof jobMetaConfig.retryButton === 'string' && jobMetaConfig.retryButton.length
                ? jobMetaConfig.retryButton
                : (strings.retryReady || '');
            const jobRetryAnnouncement = typeof jobMetaConfig.retryAnnouncement === 'string' && jobMetaConfig.retryAnnouncement.length
                ? jobMetaConfig.retryAnnouncement
                : (strings.retryAnnouncement || '');
            const supportHint = typeof strings.errorSupportHint === 'string' ? strings.errorSupportHint : '';
            const resumeBanner = document.querySelector('[data-export-resume]');
            const resumeTitleEl = resumeBanner ? resumeBanner.querySelector('[data-export-resume-title]') : null;
            const resumeMessageEl = resumeBanner ? resumeBanner.querySelector('[data-export-resume-text]') : null;
            const resumeResumeButton = resumeBanner ? resumeBanner.querySelector('[data-export-resume-resume]') : null;
            const resumeDismissButton = resumeBanner ? resumeBanner.querySelector('[data-export-resume-dismiss]') : null;
            const resumeNoticeConfig = (typeof exportAsync.resumeNotice === 'object' && exportAsync.resumeNotice !== null)
                ? exportAsync.resumeNotice
                : {};
            const resumeTitleText = typeof resumeNoticeConfig.title === 'string' ? resumeNoticeConfig.title : '';
            const resumeActiveMessage = typeof resumeNoticeConfig.active === 'string' ? resumeNoticeConfig.active : '';
            const resumeQueuedMessage = typeof resumeNoticeConfig.queued === 'string' ? resumeNoticeConfig.queued : resumeActiveMessage;
            const resumeInactiveMessage = typeof resumeNoticeConfig.inactive === 'string' ? resumeNoticeConfig.inactive : '';
            const extractResponseMessage = function(payload) {
                if (!payload || typeof payload !== 'object') {
                    return '';
                }

                if (typeof payload.message === 'string' && payload.message.length) {
                    return payload.message;
                }

                if (payload.data && typeof payload.data === 'object' && payload.data !== null) {
                    if (typeof payload.data.message === 'string' && payload.data.message.length) {
                        return payload.data.message;
                    }
                }

                return '';
            };

            let currentJobId = null;
            let pollTimeout = null;
            let idlePollCount = 0;
            let consecutiveErrors = 0;
            let lastJobSignature = '';
            let latestKnownJobId = null;
            let lastKnownJob = null;

            if (textarea && defaults && typeof defaults.exclusions === 'string' && !textarea.value) {
                textarea.value = defaults.exclusions;
            }

            const formatString = function(template, replacements) {
                if (typeof template !== 'string') {
                    return '';
                }

                let formatted = template;

                Object.keys(replacements).forEach(function(key) {
                    const value = replacements[key];
                    formatted = formatted.replace(new RegExp('%' + key + '[$][sd]', 'g'), value);
                });

                return formatted.replace(/%%/g, '%');
            };

            if (jobMetaTitle && jobMetaLabels.title) {
                jobMetaTitle.textContent = jobMetaLabels.title;
            }

            if (jobMetaCopyButton && jobMetaLabels.copy) {
                jobMetaCopyButton.textContent = jobMetaLabels.copy;
            }

            if (retryButton && jobRetryLabel) {
                retryButton.textContent = jobRetryLabel;
            }

            const formatTimestamp = function(timestamp) {
                if (typeof timestamp !== 'number' || !Number.isFinite(timestamp)) {
                    return '';
                }

                try {
                    const value = timestamp * 1000;
                    const date = new Date(value);

                    if (Number.isNaN(date.getTime())) {
                        return '';
                    }

                    if (typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat === 'function') {
                        const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
                        return formatter.format(date);
                    }

                    return date.toLocaleString();
                } catch (error) {
                    return '';
                }
            };

            const readStoredJobSnapshot = function() {
                if (!jobStorageKey) {
                    return null;
                }

                try {
                    const raw = window.localStorage.getItem(jobStorageKey);

                    if (!raw) {
                        return null;
                    }

                    const parsed = JSON.parse(raw);

                    if (parsed && typeof parsed === 'object') {
                        return parsed;
                    }
                } catch (error) {
                    return null;
                }

                return null;
            };

            const clearStoredJobSnapshot = function() {
                if (!jobStorageKey) {
                    return;
                }

                try {
                    window.localStorage.removeItem(jobStorageKey);
                } catch (error) {
                    // Ignore storage failures.
                }
            };

            const writeStoredJobSnapshot = function(snapshot) {
                if (!jobStorageKey) {
                    return;
                }

                if (!snapshot || typeof snapshot !== 'object') {
                    clearStoredJobSnapshot();
                    return;
                }

                try {
                    window.localStorage.setItem(jobStorageKey, JSON.stringify(snapshot));
                } catch (error) {
                    // Ignore storage failures.
                }
            };

            const readSessionJobSnapshot = function() {
                if (!jobSessionStorageKey) {
                    return null;
                }

                try {
                    const raw = window.sessionStorage.getItem(jobSessionStorageKey);

                    if (!raw) {
                        return null;
                    }

                    const parsed = JSON.parse(raw);

                    if (parsed && typeof parsed === 'object' && typeof parsed.id === 'string' && parsed.id.length) {
                        return {
                            id: parsed.id,
                            status: typeof parsed.status === 'string' ? parsed.status : '',
                        };
                    }
                } catch (error) {
                    return null;
                }

                return null;
            };

            const clearSessionJobSnapshot = function() {
                if (!jobSessionStorageKey) {
                    return;
                }

                try {
                    window.sessionStorage.removeItem(jobSessionStorageKey);
                } catch (error) {
                    // Ignore storage failures.
                }
            };

            const writeSessionJobSnapshot = function(jobId, status) {
                if (!jobSessionStorageKey) {
                    return;
                }

                if (!jobId) {
                    clearSessionJobSnapshot();
                    return;
                }

                const payload = {
                    id: jobId,
                    status: typeof status === 'string' ? status : '',
                };

                try {
                    window.sessionStorage.setItem(jobSessionStorageKey, JSON.stringify(payload));
                } catch (error) {
                    // Ignore storage failures to keep the UI responsive.
                }
            };

            const persistJobSnapshot = function(jobId, job, extra) {
                if (!jobId) {
                    clearStoredJobSnapshot();
                    clearSessionJobSnapshot();
                    return;
                }

                const normalizedExtra = extra && typeof extra === 'object' ? extra : {};
                const resolvedJob = job && typeof job === 'object' ? job : null;

                const status = resolvedJob && typeof resolvedJob.status === 'string'
                    ? resolvedJob.status
                    : (typeof normalizedExtra.status === 'string' ? normalizedExtra.status : '');

                const message = resolvedJob && typeof resolvedJob.message === 'string'
                    ? resolvedJob.message
                    : (typeof normalizedExtra.message === 'string' ? normalizedExtra.message : '');

                const failureCode = resolvedJob && typeof resolvedJob.failure_code === 'string'
                    ? resolvedJob.failure_code
                    : (typeof normalizedExtra.failureCode === 'string' ? normalizedExtra.failureCode : '');

                let updatedAt = 0;

                if (resolvedJob && typeof resolvedJob.updated_at === 'number' && resolvedJob.updated_at > 0) {
                    updatedAt = resolvedJob.updated_at;
                } else if (resolvedJob && typeof resolvedJob.created_at === 'number' && resolvedJob.created_at > 0) {
                    updatedAt = resolvedJob.created_at;
                } else if (typeof normalizedExtra.updatedAt === 'number') {
                    updatedAt = normalizedExtra.updatedAt;
                } else {
                    updatedAt = Math.round(Date.now() / 1000);
                }

                const snapshot = {
                    id: jobId,
                    status: status,
                    message: message,
                    failureCode: failureCode,
                    updatedAt: updatedAt,
                    downloadUrl: typeof normalizedExtra.downloadUrl === 'string' ? normalizedExtra.downloadUrl : '',
                };

                writeStoredJobSnapshot(snapshot);
                writeSessionJobSnapshot(jobId, status);
            };

            const updateResumeBannerFromSession = function(forceHide) {
                if (!resumeBanner) {
                    return;
                }

                if (forceHide === true) {
                    resumeBanner.hidden = true;
                    return;
                }

                const snapshot = readSessionJobSnapshot();

                if (!snapshot || !snapshot.id) {
                    resumeBanner.hidden = true;
                    return;
                }

                const normalizedStatus = typeof snapshot.status === 'string' ? snapshot.status.toLowerCase() : '';

                if (resumeTitleEl && resumeTitleText) {
                    resumeTitleEl.textContent = resumeTitleText;
                }

                if (resumeMessageEl) {
                    if (normalizedStatus === 'queued' && resumeQueuedMessage) {
                        resumeMessageEl.textContent = resumeQueuedMessage;
                    } else if (normalizedStatus === 'processing' && resumeActiveMessage) {
                        resumeMessageEl.textContent = resumeActiveMessage;
                    } else if (!resumeMessageEl.textContent && resumeActiveMessage) {
                        resumeMessageEl.textContent = resumeActiveMessage;
                    } else if (normalizedStatus === 'failed' && resumeInactiveMessage) {
                        resumeMessageEl.textContent = resumeInactiveMessage;
                    }
                }

                if (resumeBanner.dataset.dismissed === '1') {
                    resumeBanner.hidden = true;
                    return;
                }

                if (['processing', 'queued'].indexOf(normalizedStatus) === -1) {
                    resumeBanner.hidden = true;
                    return;
                }

                resumeBanner.hidden = false;
                resumeBanner.dataset.jobId = snapshot.id;
            };

            const rememberActiveJob = function(jobId, status) {
                if (!jobId) {
                    forgetActiveJob();
                    return;
                }

                writeSessionJobSnapshot(jobId, status);

                if (resumeBanner && resumeBanner.dataset.dismissed !== '1') {
                    updateResumeBannerFromSession();
                }
            };

            const forgetActiveJob = function() {
                clearSessionJobSnapshot();
                updateResumeBannerFromSession(true);
            };

            const registerTestHooks = function() {
                if (typeof window !== 'object' || !window || !window.__tejlgExportTestHooks) {
                    return;
                }

                const container = window.__tejlgExportTestHooks;
                const existing = (typeof container.adminExport === 'object' && container.adminExport !== null)
                    ? container.adminExport
                    : {};

                container.adminExport = Object.assign({}, existing, {
                    readSessionJobSnapshot: readSessionJobSnapshot,
                    writeSessionJobSnapshot: writeSessionJobSnapshot,
                    clearSessionJobSnapshot: clearSessionJobSnapshot,
                    updateResumeBannerFromSession: updateResumeBannerFromSession,
                    rememberActiveJob: rememberActiveJob,
                    forgetActiveJob: forgetActiveJob,
                    getResumeBannerElement: function() {
                        return resumeBanner || null;
                    },
                });
            };

            registerTestHooks();

            const updateGuidance = function(message, shouldShow) {
                if (!guidanceEl) {
                    return;
                }

                if (!shouldShow || !message) {
                    guidanceEl.hidden = true;
                    guidanceEl.textContent = '';
                    return;
                }

                guidanceEl.hidden = false;
                guidanceEl.textContent = message;
            };

            const jobHintState = {
                defaultMessage: jobMetaLabels.supportHint || '',
                resetTimer: null,
            };

            const setJobHint = function(message, duration) {
                if (!jobMetaHint) {
                    return;
                }

                if (jobHintState.resetTimer) {
                    window.clearTimeout(jobHintState.resetTimer);
                    jobHintState.resetTimer = null;
                }

                if (!message) {
                    jobMetaHint.hidden = true;
                    jobMetaHint.textContent = '';
                    return;
                }

                jobMetaHint.hidden = false;
                jobMetaHint.textContent = message;

                if (typeof duration === 'number' && duration > 0 && jobHintState.defaultMessage && message !== jobHintState.defaultMessage) {
                    jobHintState.resetTimer = window.setTimeout(function() {
                        jobHintState.resetTimer = null;
                        if (jobHintState.defaultMessage) {
                            jobMetaHint.hidden = false;
                            jobMetaHint.textContent = jobHintState.defaultMessage;
                        } else {
                            jobMetaHint.hidden = true;
                            jobMetaHint.textContent = '';
                        }
                    }, duration);
                }
            };

            if (resumeTitleEl && resumeTitleText) {
                resumeTitleEl.textContent = resumeTitleText;
            }

            if (resumeResumeButton) {
                resumeResumeButton.addEventListener('click', function() {
                    if (resumeBanner) {
                        resumeBanner.dataset.dismissed = '';
                    }

                    const snapshot = readSessionJobSnapshot();

                    if (!snapshot || !snapshot.id) {
                        updateResumeBannerFromSession(true);
                        return;
                    }

                    updateResumeBannerFromSession(true);

                    if (!currentJobId || currentJobId !== snapshot.id) {
                        currentJobId = snapshot.id;
                        resetBackoffTracking();
                    }

                    setSpinner(true);

                    if (startButton) {
                        startButton.disabled = true;
                    }

                    fetchStatus(snapshot.id);
                });
            }

            if (resumeDismissButton) {
                resumeDismissButton.addEventListener('click', function() {
                    if (resumeBanner) {
                        resumeBanner.dataset.dismissed = '1';
                        resumeBanner.hidden = true;
                    }
                });
            }

            const updateJobMetaDisplay = function(jobId, job, extra) {
                if (!jobMetaContainer) {
                    return;
                }

                const normalizedJob = job && typeof job === 'object' ? job : null;
                const normalizedExtra = extra && typeof extra === 'object' ? extra : {};
                const resolvedJobId = typeof jobId === 'string' && jobId.length
                    ? jobId
                    : (normalizedJob && typeof normalizedJob.id === 'string' ? normalizedJob.id : '');

                if (!resolvedJobId) {
                    jobMetaContainer.hidden = true;
                    return;
                }

                jobMetaContainer.hidden = false;

                if (jobMetaId) {
                    jobMetaId.textContent = resolvedJobId;
                }

                const jobStatus = normalizedJob && typeof normalizedJob.status === 'string'
                    ? normalizedJob.status
                    : (typeof normalizedExtra.status === 'string' ? normalizedExtra.status : '');

                const statusLabel = jobStatus
                    ? (strings.statusLabel ? formatString(strings.statusLabel, { '1': jobStatus }) : jobStatus)
                    : (strings.jobStatusUnknown || '');

                if (jobMetaStatus) {
                    jobMetaStatus.textContent = statusLabel;
                }

                const failureCode = normalizedJob && typeof normalizedJob.failure_code === 'string' && normalizedJob.failure_code.length
                    ? normalizedJob.failure_code
                    : (typeof normalizedExtra.failureCode === 'string' && normalizedExtra.failureCode.length ? normalizedExtra.failureCode : '');

                if (jobMetaCode) {
                    jobMetaCode.textContent = failureCode || (jobMetaLabels.noFailureCode || '');
                }

                const message = normalizedJob && typeof normalizedJob.message === 'string' && normalizedJob.message.length
                    ? normalizedJob.message
                    : (typeof normalizedExtra.message === 'string' && normalizedExtra.message.length ? normalizedExtra.message : '');

                if (jobMetaMessage) {
                    jobMetaMessage.textContent = message || (jobMetaLabels.noMessage || '');
                }

                let updatedAt = 0;

                if (normalizedJob && typeof normalizedJob.updated_at === 'number' && normalizedJob.updated_at > 0) {
                    updatedAt = normalizedJob.updated_at;
                } else if (normalizedJob && typeof normalizedJob.created_at === 'number' && normalizedJob.created_at > 0) {
                    updatedAt = normalizedJob.created_at;
                } else if (typeof normalizedExtra.updatedAt === 'number') {
                    updatedAt = normalizedExtra.updatedAt;
                }

                if (jobMetaUpdated) {
                    if (updatedAt) {
                        const formatted = formatTimestamp(updatedAt) || strings.jobTimestampFallback || '';
                        jobMetaUpdated.textContent = jobMetaLabels.updated
                            ? formatString(jobMetaLabels.updated, { '1': formatted })
                            : formatted;
                    } else if (jobMetaLabels.updated) {
                        const fallback = strings.jobTimestampFallback || '';
                        jobMetaUpdated.textContent = fallback
                            ? formatString(jobMetaLabels.updated, { '1': fallback })
                            : '';
                    } else {
                        jobMetaUpdated.textContent = strings.jobTimestampFallback || '';
                    }
                }

                jobHintState.defaultMessage = jobMetaLabels.supportHint || jobHintState.defaultMessage || '';

                if (jobHintState.defaultMessage) {
                    setJobHint(jobHintState.defaultMessage, 0);
                } else {
                    setJobHint('', 0);
                }
            };

            if (jobMetaCopyButton) {
                jobMetaCopyButton.addEventListener('click', function(event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    if (!jobMetaId) {
                        return;
                    }

                    const jobIdValue = (jobMetaId.textContent || '').trim();

                    if (!jobIdValue) {
                        return;
                    }

                    const fallbackCopy = function(text) {
                        return new Promise(function(resolve, reject) {
                            const helper = document.createElement('textarea');
                            helper.value = text;
                            helper.setAttribute('readonly', 'readonly');
                            helper.style.position = 'absolute';
                            helper.style.left = '-9999px';
                            document.body.appendChild(helper);
                            helper.select();

                            let successful = false;

                            try {
                                successful = document.execCommand('copy');
                            } catch (error) {
                                successful = false;
                            }

                            document.body.removeChild(helper);

                            if (successful) {
                                resolve();
                            } else {
                                reject(new Error('copy-failed'));
                            }
                        });
                    };

                    const executeCopy = function(text) {
                        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            return navigator.clipboard.writeText(text).catch(function() {
                                return fallbackCopy(text);
                            });
                        }

                        return fallbackCopy(text);
                    };

                    executeCopy(jobIdValue).then(function() {
                        if (jobMetaLabels.copySuccess) {
                            setJobHint(jobMetaLabels.copySuccess, 3500);
                        }
                    }).catch(function() {
                        if (jobMetaLabels.copyFailed) {
                            setJobHint(jobMetaLabels.copyFailed, 4500);
                        }
                    });
                });
            }

            if (retryButton) {
                retryButton.addEventListener('click', function(event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    const snapshot = readStoredJobSnapshot();
                    const jobId = latestKnownJobId
                        || currentJobId
                        || (snapshot && typeof snapshot.id === 'string' ? snapshot.id : '');

                    if (!jobId) {
                        return;
                    }

                    retryButton.disabled = true;

                    if (statusText && jobRetryAnnouncement) {
                        statusText.textContent = jobRetryAnnouncement;
                    }

                    setSpinner(true);
                    updateGuidance('', false);
                    fetchStatus(jobId, { isRetry: true });
                });
            }

            if (patternTester && patternTester.action && patternTester.nonce) {
                const patternTestContainer = exportForm.querySelector('[data-pattern-test]');

                if (patternTestContainer) {
                    const patternStrings = typeof patternTester.strings === 'object' ? patternTester.strings : {};
                    const patternTestButton = patternTestContainer.querySelector('[data-pattern-test-trigger]');
                    const patternTestSpinner = patternTestContainer.querySelector('[data-pattern-test-spinner]');
                    const patternTestFeedback = patternTestContainer.querySelector('[data-pattern-test-feedback]');
                    const patternTestSummary = patternTestContainer.querySelector('[data-pattern-test-summary]');
                    const patternTestMessage = patternTestContainer.querySelector('[data-pattern-test-message]');
                    const patternTestIncluded = patternTestContainer.querySelector('[data-pattern-test-included]');
                    const patternTestExcluded = patternTestContainer.querySelector('[data-pattern-test-excluded]');
                    const patternTestInvalid = patternTestContainer.querySelector('[data-pattern-test-invalid]');
                    const patternTestLists = patternTestContainer.querySelector('[data-pattern-test-lists]');
                    const patternExampleButtons = patternTestContainer.querySelectorAll('[data-pattern-example]');

                    if (patternExampleButtons.length && textarea) {
                        patternExampleButtons.forEach(function(button) {
                            button.addEventListener('click', function(event) {
                                event.preventDefault();
                                const patternValue = button.getAttribute('data-pattern-example');

                                if (typeof patternValue !== 'string' || !patternValue.length) {
                                    return;
                                }

                                const currentValue = textarea.value || '';
                                const normalizedCurrent = currentValue.trim();
                                let nextValue = '';

                                if (!normalizedCurrent.length) {
                                    nextValue = patternValue;
                                } else if (normalizedCurrent.indexOf(patternValue) !== -1) {
                                    nextValue = currentValue;
                                } else {
                                    nextValue = normalizedCurrent + '\n' + patternValue;
                                }

                                textarea.value = nextValue;
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));

                                if (typeof textarea.focus === 'function') {
                                    textarea.focus();
                                }

                                if (typeof textarea.setSelectionRange === 'function') {
                                    const valueLength = textarea.value.length;
                                    textarea.setSelectionRange(valueLength, valueLength);
                                }
                            });
                        });
                    }

                    const togglePatternSpinner = function(isActive) {
                        if (!patternTestSpinner) {
                            return;
                        }

                        if (isActive) {
                            patternTestSpinner.classList.add('is-active');
                        } else {
                            patternTestSpinner.classList.remove('is-active');
                        }
                    };

                    const setTextareaValidity = function(invalidPatterns) {
                        if (!textarea) {
                            return;
                        }

                        const hasInvalid = Array.isArray(invalidPatterns) && invalidPatterns.length > 0;

                        if (hasInvalid) {
                            textarea.classList.add('has-pattern-error');
                            textarea.setAttribute('aria-invalid', 'true');
                        } else {
                            textarea.classList.remove('has-pattern-error');
                            textarea.removeAttribute('aria-invalid');
                        }

                        if (patternTestInvalid) {
                            if (hasInvalid) {
                                const separator = typeof patternStrings.listSeparator === 'string'
                                    ? patternStrings.listSeparator
                                    : ', ';
                                const invalidMessage = patternStrings.invalidPatterns
                                    ? formatString(patternStrings.invalidPatterns, {
                                        '1': invalidPatterns.join(separator)
                                    })
                                    : invalidPatterns.join(separator);
                                patternTestInvalid.textContent = invalidMessage;
                                patternTestInvalid.hidden = false;
                            } else {
                                patternTestInvalid.textContent = '';
                                patternTestInvalid.hidden = true;
                            }
                        }
                    };

                    const renderList = function(target, items) {
                        if (!target) {
                            return;
                        }

                        target.innerHTML = '';

                        if (!Array.isArray(items) || !items.length) {
                            const emptyItem = document.createElement('li');
                            emptyItem.classList.add('is-empty');
                            emptyItem.textContent = patternStrings.emptyList || '';
                            target.appendChild(emptyItem);
                            return;
                        }

                        items.forEach(function(item) {
                            const li = document.createElement('li');
                            li.textContent = item;
                            target.appendChild(li);
                        });
                    };

                    const resetPatternFeedback = function() {
                        if (patternTestFeedback) {
                            patternTestFeedback.hidden = true;
                            patternTestFeedback.classList.remove('notice-error');
                            if (!patternTestFeedback.classList.contains('notice-info')) {
                                patternTestFeedback.classList.add('notice-info');
                            }
                        }

                        if (patternTestSummary) {
                            patternTestSummary.textContent = '';
                        }

                        if (patternTestMessage) {
                            patternTestMessage.textContent = '';
                        }

                        if (patternTestIncluded) {
                            patternTestIncluded.innerHTML = '';
                        }

                        if (patternTestExcluded) {
                            patternTestExcluded.innerHTML = '';
                        }

                        if (patternTestLists) {
                            patternTestLists.removeAttribute('hidden');
                        }

                        setTextareaValidity([]);
                    };

                    const showPatternError = function(message, invalidPatterns) {
                        if (!patternTestFeedback) {
                            return;
                        }

                        patternTestFeedback.hidden = false;
                        patternTestFeedback.classList.remove('notice-info');
                        patternTestFeedback.classList.add('notice-error');

                        if (patternTestSummary) {
                            patternTestSummary.textContent = '';
                        }

                        if (patternTestMessage) {
                            patternTestMessage.textContent = message || (patternStrings.unknownError || '');
                        }

                        if (patternTestLists) {
                            patternTestLists.setAttribute('hidden', 'hidden');
                        }

                        if (Array.isArray(invalidPatterns) && invalidPatterns.length) {
                            setTextareaValidity(invalidPatterns);
                        } else {
                            setTextareaValidity([]);
                        }
                    };

                    const showPatternSuccess = function(payload) {
                        if (!patternTestFeedback) {
                            return;
                        }

                        patternTestFeedback.hidden = false;
                        patternTestFeedback.classList.remove('notice-error');
                        if (!patternTestFeedback.classList.contains('notice-info')) {
                            patternTestFeedback.classList.add('notice-info');
                        }

                        const included = Array.isArray(payload.included) ? payload.included : [];
                        const excluded = Array.isArray(payload.excluded) ? payload.excluded : [];
                        const includedCount = typeof payload.includedCount === 'number'
                            ? payload.includedCount
                            : included.length;
                        const excludedCount = typeof payload.excludedCount === 'number'
                            ? payload.excludedCount
                            : excluded.length;

                        if (patternTestSummary && patternStrings.summary) {
                            patternTestSummary.textContent = formatString(patternStrings.summary, {
                                '1': includedCount,
                                '2': excludedCount
                            });
                        } else if (patternTestSummary) {
                            patternTestSummary.textContent = '';
                        }

                        if (patternTestMessage) {
                            patternTestMessage.textContent = patternStrings.successMessage || '';
                        }

                        renderList(patternTestIncluded, included);
                        renderList(patternTestExcluded, excluded);

                        if (patternTestLists) {
                            patternTestLists.removeAttribute('hidden');
                        }

                        setTextareaValidity([]);
                    };

                    if (patternTestButton) {
                        patternTestButton.addEventListener('click', function() {
                            if (!exportAsync.ajaxUrl) {
                                return;
                            }

                            togglePatternSpinner(true);
                            patternTestButton.disabled = true;
                            resetPatternFeedback();

                            const params = new URLSearchParams();
                            params.append('action', patternTester.action);
                            params.append('nonce', patternTester.nonce);
                            params.append('patterns', textarea ? textarea.value : '');

                            window.fetch(exportAsync.ajaxUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                body: params.toString(),
                                credentials: 'same-origin'
                            }).then(function(response) {
                                return response.json().catch(function() {
                                    return null;
                                }).then(function(data) {
                                    return {
                                        ok: response.ok,
                                        status: response.status,
                                        payload: data
                                    };
                                });
                            }).then(function(result) {
                                togglePatternSpinner(false);
                                patternTestButton.disabled = false;

                                if (!result || !result.payload) {
                                    showPatternError(patternStrings.unknownError || '', []);
                                    return;
                                }

                                if (result.payload.success) {
                                    showPatternSuccess(result.payload.data || {});
                                    return;
                                }

                                const errorPayload = result.payload.data && typeof result.payload.data === 'object'
                                    ? result.payload.data
                                    : {};
                                const invalidPatterns = Array.isArray(errorPayload.invalid_patterns)
                                    ? errorPayload.invalid_patterns
                                    : (Array.isArray(errorPayload.invalidPatterns) ? errorPayload.invalidPatterns : []);
                                const message = extractResponseMessage(result.payload)
                                    || patternStrings.unknownError
                                    || '';

                                showPatternError(message, invalidPatterns);
                            }).catch(function() {
                                togglePatternSpinner(false);
                                patternTestButton.disabled = false;
                                showPatternError(patternStrings.requestFailed || patternStrings.unknownError || '', []);
                            });
                        });
                    }
                }
            }

            const setSpinner = function(isActive) {
                if (spinner) {
                    if (isActive) {
                        spinner.classList.add('is-active');
                    } else {
                        spinner.classList.remove('is-active');
                    }
                }

                if (exportForm) {
                    if (isActive) {
                        exportForm.setAttribute('aria-busy', 'true');
                    } else {
                        exportForm.setAttribute('aria-busy', 'false');
                    }
                }
            };

            const resetFeedback = function() {
                if (!feedback) {
                    return;
                }

                feedback.hidden = true;
                feedback.classList.remove('notice-error', 'notice-success');
                if (!feedback.classList.contains('notice-info')) {
                    feedback.classList.add('notice-info');
                }

                if (statusText) {
                    statusText.textContent = strings.initializing || '';
                }

                if (messageEl) {
                    messageEl.textContent = '';
                }

                if (progressBar) {
                    progressBar.value = 0;
                }

                if (downloadLink) {
                    downloadLink.hidden = true;
                    downloadLink.removeAttribute('href');
                }

                if (cancelButton) {
                    cancelButton.hidden = true;
                    cancelButton.disabled = false;
                }

                updateGuidance('', false);

                if (jobMetaContainer) {
                    jobMetaContainer.hidden = true;
                }

                setJobHint('', 0);

                if (retryButton) {
                    retryButton.hidden = true;
                    retryButton.disabled = false;
                }

                resetBackoffTracking();
                lastJobSignature = '';
                lastKnownJob = null;
            };

            const resetBackoffTracking = function() {
                idlePollCount = 0;
                consecutiveErrors = 0;
            };

            const computeJobSignature = function(job) {
                if (!job || typeof job !== 'object') {
                    return '';
                }

                const parts = [
                    typeof job.status === 'string' ? job.status : '',
                    typeof job.progress === 'number' ? job.progress : '',
                    typeof job.processed_items === 'number' ? job.processed_items : '',
                    typeof job.total_items === 'number' ? job.total_items : '',
                    job.updated_at || job.last_activity || job.timestamp || ''
                ];

                return parts.join('|');
            };

            const stopPolling = function() {
                if (pollTimeout) {
                    window.clearTimeout(pollTimeout);
                    pollTimeout = null;
                }
            };

            const scheduleNextPoll = function(jobId, reason) {
                if (!jobId) {
                    return 0;
                }

                stopPolling();

                let delay = basePollInterval;

                if (reason === 'error') {
                    consecutiveErrors += 1;
                    if (consecutiveErrors > maxErrorRetries) {
                        return 0;
                    }
                    idlePollCount = 0;
                    delay = Math.min(maxPollInterval, basePollInterval * Math.pow(2, consecutiveErrors));
                } else if (reason === 'idle') {
                    consecutiveErrors = 0;
                    idlePollCount = Math.min(idlePollCount + 1, 6);
                    delay = Math.min(maxPollInterval, basePollInterval + idlePollCount * 1500);
                } else {
                    consecutiveErrors = 0;
                    idlePollCount = 0;
                    delay = basePollInterval;
                }

                pollTimeout = window.setTimeout(function() {
                    fetchStatus(jobId);
                }, delay);

                return delay;
            };

            const updateFeedback = function(job, extra) {
                if (!feedback || !job) {
                    if (cancelButton) {
                        cancelButton.hidden = true;
                        cancelButton.disabled = false;
                    }
                    return;
                }

                const extraData = extra && typeof extra === 'object' ? extra : {};
                const jobId = typeof extraData.jobId === 'string' && extraData.jobId.length
                    ? extraData.jobId
                    : (typeof job.id === 'string' && job.id.length ? job.id : (currentJobId || ''));

                if (jobId) {
                    latestKnownJobId = jobId;
                }

                lastKnownJob = job;

                feedback.hidden = false;
                feedback.classList.remove('notice-error', 'notice-success', 'notice-info');

                let statusLabel = strings.queued || '';
                let description = '';
                let finalMessage = '';
                let progressValue = 0;
                let shouldShowCancel = false;
                const failureCode = typeof job.failure_code === 'string' ? job.failure_code : '';
                const normalizedStatus = typeof job.status === 'string' ? job.status.toLowerCase() : '';

                if (typeof job.progress === 'number') {
                    progressValue = Math.max(0, Math.min(100, Math.round(job.progress)));
                }

                if (progressBar) {
                    progressBar.value = progressValue;
                }

                if (job.status === 'completed') {
                    feedback.classList.add('notice-success');
                    statusLabel = strings.completed || '';
                    finalMessage = description;

                    if (downloadLink && typeof extraData.downloadUrl === 'string') {
                        downloadLink.hidden = false;
                        downloadLink.href = extraData.downloadUrl;
                        downloadLink.textContent = strings.downloadLabel || downloadLink.textContent;
                    }
                } else if (job.status === 'failed') {
                    feedback.classList.add('notice-error');

                    if (failureCode === 'timeout' && strings.autoFailedStatus) {
                        statusLabel = strings.autoFailedStatus;
                        finalMessage = job.message && job.message.length
                            ? job.message
                            : (strings.autoFailedMessage || strings.unknownError || '');
                    } else {
                        const failureMessage = job.message && job.message.length
                            ? job.message
                            : (strings.unknownError || '');
                        statusLabel = strings.failed
                            ? formatString(strings.failed, { '1': failureMessage })
                            : failureMessage;
                        finalMessage = failureMessage;
                    }
                } else if (job.status === 'cancelled') {
                    feedback.classList.add('notice-info');
                    progressValue = 0;
                    if (progressBar) {
                        progressBar.value = 0;
                    }
                    statusLabel = strings.cancelledStatus || strings.cancelled || '';
                    description = job.message && job.message.length
                        ? job.message
                        : (strings.cancelledMessage || '');
                    finalMessage = description;
                } else {
                    feedback.classList.add('notice-info');
                    statusLabel = job.status === 'queued' && strings.queued
                        ? strings.queued
                        : strings.initializing || '';

                    if (job.status === 'processing' || job.status === 'queued') {
                        const processed = typeof job.processed_items === 'number' ? job.processed_items : 0;
                        const total = typeof job.total_items === 'number' ? job.total_items : 0;
                        if (strings.inProgress) {
                            description = formatString(strings.inProgress, { '1': processed, '2': total });
                        }
                        if (strings.progressValue) {
                            statusLabel = formatString(strings.progressValue, { '1': progressValue });
                        }
                        shouldShowCancel = true;
                    }

                    finalMessage = description;
                }

                if (statusText) {
                    if (strings.statusLabel && statusLabel) {
                        statusText.textContent = formatString(strings.statusLabel, { '1': statusLabel });
                    } else {
                        statusText.textContent = statusLabel;
                    }
                }

                if (messageEl) {
                    messageEl.textContent = finalMessage;
                }

                if (downloadLink && job.status !== 'completed') {
                    downloadLink.hidden = true;
                    downloadLink.removeAttribute('href');
                }

                if (cancelButton) {
                    if (shouldShowCancel && currentJobId) {
                        cancelButton.hidden = false;
                        cancelButton.disabled = false;
                    } else {
                        cancelButton.hidden = true;
                        cancelButton.disabled = false;
                    }
                }

                const metadata = {
                    downloadUrl: typeof extraData.downloadUrl === 'string' ? extraData.downloadUrl : '',
                    status: normalizedStatus,
                    failureCode: failureCode,
                    message: finalMessage,
                    updatedAt: (typeof job.updated_at === 'number' && job.updated_at > 0)
                        ? job.updated_at
                        : (typeof job.created_at === 'number' && job.created_at > 0
                            ? job.created_at
                            : Math.round(Date.now() / 1000)),
                };

                updateJobMetaDisplay(jobId, job, metadata);
                persistJobSnapshot(jobId, job, metadata);

                if (jobId) {
                    if (normalizedStatus === 'completed' || normalizedStatus === 'failed' || normalizedStatus === 'cancelled') {
                        forgetActiveJob();
                    } else {
                        rememberActiveJob(jobId, normalizedStatus);
                    }
                }

                if (retryButton) {
                    if (normalizedStatus === 'failed') {
                        retryButton.hidden = false;
                        retryButton.disabled = false;
                    } else {
                        retryButton.hidden = true;
                        retryButton.disabled = false;
                    }
                }

                if (normalizedStatus === 'failed' || normalizedStatus === 'cancelled') {
                    updateGuidance(supportHint, true);
                } else {
                    updateGuidance('', false);
                }
            };

            const handleError = function(message) {
                stopPolling();
                if (feedback) {
                    feedback.hidden = false;
                    feedback.classList.remove('notice-info', 'notice-success');
                    feedback.classList.add('notice-error');
                }
                const snapshot = readStoredJobSnapshot();
                const jobIdForError = latestKnownJobId
                    || currentJobId
                    || (snapshot && typeof snapshot.id === 'string' ? snapshot.id : '');
                const fallbackMessage = message || strings.unknownError || '';
                if (statusText) {
                    if (jobIdForError && strings.failedWithId) {
                        statusText.textContent = formatString(strings.failedWithId, {
                            '1': fallbackMessage,
                            '2': jobIdForError,
                        });
                    } else if (strings.failed) {
                        statusText.textContent = formatString(strings.failed, { '1': fallbackMessage });
                    } else {
                        statusText.textContent = fallbackMessage;
                    }
                }
                if (messageEl) {
                    messageEl.textContent = fallbackMessage;
                }
                if (downloadLink) {
                    downloadLink.hidden = true;
                    downloadLink.removeAttribute('href');
                }
                const metadata = {
                    status: 'failed',
                    failureCode: lastKnownJob && typeof lastKnownJob.failure_code === 'string' ? lastKnownJob.failure_code : '',
                    message: fallbackMessage,
                    updatedAt: Math.round(Date.now() / 1000),
                };
                updateJobMetaDisplay(jobIdForError, lastKnownJob, metadata);
                persistJobSnapshot(jobIdForError, lastKnownJob, metadata);
                forgetActiveJob();
                updateGuidance(supportHint, true);
                if (retryButton) {
                    if (jobIdForError) {
                        retryButton.hidden = false;
                        retryButton.disabled = false;
                    } else {
                        retryButton.hidden = true;
                        retryButton.disabled = true;
                    }
                }
                if (jobHintState.defaultMessage) {
                    setJobHint(jobHintState.defaultMessage, 0);
                }
            };

            const fetchStatus = function(jobId) {
                if (!jobId) {
                    return;
                }

                const params = new URLSearchParams();
                params.append('action', exportAsync.actions.status);
                params.append('nonce', exportAsync.nonces.status);
                params.append('job_id', jobId);

                window.fetch(exportAsync.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: params.toString(),
                }).then(function(response) {
                    return response.json().catch(function() {
                        return null;
                    }).then(function(payload) {
                        if (!response.ok) {
                            const message = extractResponseMessage(payload) || strings.unknownError || '';
                            return Promise.reject(message || strings.unknownError || '');
                        }
                        return payload;
                    });
                }).then(function(payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.job) {
                        const message = extractResponseMessage(payload) || strings.unknownError || '';
                        return Promise.reject(message || strings.unknownError || '');
                    }

                    const job = payload.data.job;
                    const extra = {
                        downloadUrl: payload.data.download_url || '',
                        jobId: jobId,
                    };
                    updateFeedback(job, extra);

                    const signature = computeJobSignature(job);
                    const unchanged = signature === lastJobSignature;
                    lastJobSignature = signature;

                    if (job.status === 'completed' || job.status === 'failed' || job.status === 'cancelled') {
                        resetBackoffTracking();
                        lastJobSignature = '';
                        stopPolling();
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }
                        currentJobId = null;
                    } else {
                        scheduleNextPoll(jobId, unchanged ? 'idle' : 'active');
                    }
                }).catch(function(error) {
                    const message = (typeof error === 'string' && error.length)
                        ? error
                        : (error && typeof error.message === 'string' && error.message.length)
                            ? error.message
                            : strings.unknownError || '';

                    if (currentJobId) {
                        const retryDelay = scheduleNextPoll(currentJobId, 'error');

                        if (retryDelay > 0) {
                            if (feedback) {
                                feedback.hidden = false;
                                feedback.classList.remove('notice-error', 'notice-success');
                                if (!feedback.classList.contains('notice-info')) {
                                    feedback.classList.add('notice-info');
                                }
                            }

                            if (statusText) {
                                if (strings.retrying) {
                                    const seconds = Math.max(1, Math.round(retryDelay / 1000));
                                    statusText.textContent = formatString(strings.retrying, { '1': seconds });
                                } else {
                                    statusText.textContent = message;
                                }
                            }

                            if (messageEl) {
                                messageEl.textContent = message;
                            }

                            setSpinner(true);
                            if (startButton) {
                                startButton.disabled = true;
                            }

                            return;
                        }
                    }

                    resetBackoffTracking();
                    lastJobSignature = '';
                    stopPolling();
                    setSpinner(false);
                    if (startButton) {
                        startButton.disabled = false;
                    }
                    currentJobId = null;
                    handleError(message || strings.unknownError || '');
                });
            };

            const requestImmediateStatus = function() {
                if (!currentJobId) {
                    return;
                }

                stopPolling();
                fetchStatus(currentJobId);
            };

            const handleForegroundRefresh = function() {
                if (typeof document !== 'undefined' && document.hidden) {
                    return;
                }

                requestImmediateStatus();
            };

            window.addEventListener('focus', handleForegroundRefresh);
            document.addEventListener('visibilitychange', handleForegroundRefresh);

            const resumePersistedJob = function(snapshot) {
                if (!snapshot || typeof snapshot !== 'object') {
                    return;
                }

                const job = (typeof snapshot.job === 'object' && snapshot.job !== null)
                    ? snapshot.job
                    : null;

                const persistedJobId = typeof snapshot.job_id === 'string' && snapshot.job_id.length
                    ? snapshot.job_id
                    : (job && typeof job.id === 'string')
                        ? job.id
                        : '';

                if (!persistedJobId) {
                    return;
                }

                currentJobId = persistedJobId;
                resetBackoffTracking();

                if (job) {
                    updateFeedback(job, { downloadUrl: '', jobId: persistedJobId });
                    lastJobSignature = computeJobSignature(job);
                }

                const statusFromSnapshot = typeof snapshot.status === 'string' && snapshot.status.length
                    ? snapshot.status
                    : (job && typeof job.status === 'string')
                        ? job.status
                        : '';

                const normalizedStatus = statusFromSnapshot.toLowerCase();

                rememberActiveJob(persistedJobId, normalizedStatus);

                if (!job) {
                    const metadata = {
                        status: normalizedStatus,
                        message: '',
                        updatedAt: Math.round(Date.now() / 1000),
                    };
                    updateJobMetaDisplay(persistedJobId, null, metadata);
                    persistJobSnapshot(persistedJobId, null, metadata);
                }

                const shouldFetch = ['queued', 'processing', 'completed', 'failed'].indexOf(normalizedStatus) !== -1;

                if (!shouldFetch) {
                    if (normalizedStatus === 'completed' || normalizedStatus === 'failed' || normalizedStatus === 'cancelled') {
                        forgetActiveJob();
                    }
                    if (normalizedStatus === 'cancelled') {
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }
                        currentJobId = null;
                    }
                    return;
                }

                if (normalizedStatus === 'queued' || normalizedStatus === 'processing') {
                    setSpinner(true);
                    if (startButton) {
                        startButton.disabled = true;
                    }
                }

                fetchStatus(persistedJobId);
            };

            if (exportAsync.previousJob) {
                resumePersistedJob(exportAsync.previousJob);
            }

            if (!exportAsync.previousJob) {
                const storedSnapshot = readStoredJobSnapshot();

                if (storedSnapshot && typeof storedSnapshot.id === 'string' && storedSnapshot.id.length) {
                    latestKnownJobId = storedSnapshot.id;

                    const normalizedStatus = typeof storedSnapshot.status === 'string'
                        ? storedSnapshot.status.toLowerCase()
                        : '';

                    if (feedback) {
                        feedback.hidden = false;
                        feedback.classList.remove('notice-error', 'notice-success');
                        if (!feedback.classList.contains('notice-info')) {
                            feedback.classList.add('notice-info');
                        }
                    }

                    const statusLabelFromSnapshot = storedSnapshot.status
                        ? (strings.statusLabel
                            ? formatString(strings.statusLabel, { '1': storedSnapshot.status })
                            : storedSnapshot.status)
                        : (strings.jobStatusUnknown || '');

                    if (statusText) {
                        statusText.textContent = statusLabelFromSnapshot;
                    }

                    if (messageEl) {
                        const storedMessage = typeof storedSnapshot.message === 'string'
                            ? storedSnapshot.message
                            : '';
                        messageEl.textContent = storedMessage;
                    }

                    updateJobMetaDisplay(storedSnapshot.id, null, {
                        status: normalizedStatus,
                        message: typeof storedSnapshot.message === 'string' ? storedSnapshot.message : '',
                        failureCode: typeof storedSnapshot.failureCode === 'string' ? storedSnapshot.failureCode : '',
                        updatedAt: typeof storedSnapshot.updatedAt === 'number' ? storedSnapshot.updatedAt : Math.round(Date.now() / 1000),
                    });

                    if (retryButton) {
                        if (normalizedStatus === 'failed') {
                            retryButton.hidden = false;
                            retryButton.disabled = false;
                        } else {
                            retryButton.hidden = true;
                            retryButton.disabled = false;
                        }
                    }

                    if (normalizedStatus === 'failed' || normalizedStatus === 'cancelled') {
                        updateGuidance(supportHint, true);
                    } else {
                        updateGuidance('', false);
                    }
                }
            }

            const sessionSnapshot = readSessionJobSnapshot();

            if (sessionSnapshot && sessionSnapshot.id) {
                if (!currentJobId) {
                    currentJobId = sessionSnapshot.id;
                    resetBackoffTracking();
                    setSpinner(true);

                    if (startButton) {
                        startButton.disabled = true;
                    }

                    fetchStatus(sessionSnapshot.id);
                }

                if (resumeBanner && resumeBanner.dataset.dismissed !== '1') {
                    updateResumeBannerFromSession();
                }
            } else {
                updateResumeBannerFromSession();
            }

            if (startButton) {
                startButton.addEventListener('click', function(event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    stopPolling();
                    resetBackoffTracking();
                    lastJobSignature = '';
                    setSpinner(true);
                    if (startButton) {
                        startButton.disabled = true;
                    }
                    resetFeedback();
                    currentJobId = null;

                    const formData = new FormData();
                    formData.append('action', exportAsync.actions.start);
                    formData.append('nonce', exportAsync.nonces.start);
                    if (textarea) {
                        formData.append('exclusions', textarea.value || '');
                    }

                    window.fetch(exportAsync.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    }).then(function(response) {
                        return response.json().catch(function() {
                            return null;
                        }).then(function(payload) {
                            if (!response.ok) {
                                const message = extractResponseMessage(payload) || strings.unknownError || '';
                                return Promise.reject(message || strings.unknownError || '');
                            }
                            return payload;
                        });
                    }).then(function(payload) {
                        if (!payload || !payload.success || !payload.data) {
                            const message = extractResponseMessage(payload) || strings.unknownError || '';
                            return Promise.reject(message || strings.unknownError || '');
                        }

                        const job = payload.data.job;
                        currentJobId = payload.data.job_id || (job && job.id) || '';

                        if (!currentJobId) {
                            return Promise.reject(strings.unknownError || '');
                        }

                        updateFeedback(job || null, { downloadUrl: '', jobId: currentJobId });
                        const initialStatus = job && typeof job.status === 'string' ? job.status.toLowerCase() : 'queued';
                        rememberActiveJob(currentJobId, initialStatus);
                        scheduleNextPoll(currentJobId, 'active');
                    }).catch(function(error) {
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }
                        resetBackoffTracking();
                        lastJobSignature = '';
                        currentJobId = null;
                        forgetActiveJob();
                        const message = (typeof error === 'string' && error.length)
                            ? error
                            : (error && typeof error.message === 'string' && error.message.length)
                                ? error.message
                                : strings.unknownError || '';
                        handleError(message || strings.unknownError || '');
                    });
                });
            }

            if (cancelButton && exportAsync.actions.cancel && exportAsync.nonces.cancel) {
                cancelButton.addEventListener('click', function(event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    if (!currentJobId) {
                        return;
                    }

                    stopPolling();

                    if (feedback) {
                        feedback.hidden = false;
                        feedback.classList.remove('notice-error', 'notice-success');
                        feedback.classList.add('notice-info');
                    }

                    setSpinner(true);
                    cancelButton.disabled = true;

                    const cancellingLabel = strings.cancelling || '';

                    if (statusText) {
                        if (strings.statusLabel && cancellingLabel) {
                            statusText.textContent = formatString(strings.statusLabel, { '1': cancellingLabel });
                        } else {
                            statusText.textContent = cancellingLabel;
                        }
                    }

                    if (messageEl) {
                        messageEl.textContent = '';
                    }

                    if (progressBar) {
                        progressBar.value = 0;
                    }

                    if (downloadLink) {
                        downloadLink.hidden = true;
                        downloadLink.removeAttribute('href');
                    }

                    const formData = new FormData();
                    formData.append('action', exportAsync.actions.cancel);
                    formData.append('nonce', exportAsync.nonces.cancel);
                    formData.append('job_id', currentJobId);

                    window.fetch(exportAsync.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    }).then(function(response) {
                        return response.json().catch(function() {
                            return null;
                        }).then(function(payload) {
                            if (!response.ok) {
                                const message = extractResponseMessage(payload) || strings.cancelFailed || strings.unknownError || '';
                                return Promise.reject(message || strings.unknownError || '');
                            }

                            return payload;
                        });
                    }).then(function(payload) {
                        if (!payload || !payload.success || !payload.data || !payload.data.job) {
                            const message = extractResponseMessage(payload) || strings.cancelFailed || strings.unknownError || '';
                            return Promise.reject(message || strings.unknownError || '');
                        }

                        stopPolling();
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }

                        resetBackoffTracking();
                        lastJobSignature = '';
                        cancelButton.disabled = false;
                        const job = payload.data.job;
                        const cancelledJobId = currentJobId;
                        currentJobId = null;
                        forgetActiveJob();
                        updateFeedback(job, { downloadUrl: '', jobId: cancelledJobId });
                    }).catch(function(error) {
                        const message = (typeof error === 'string' && error.length)
                            ? error
                            : (error && typeof error.message === 'string' && error.message.length)
                                ? error.message
                                : strings.cancelFailed || strings.unknownError || '';

                        if (feedback) {
                            feedback.hidden = false;
                            feedback.classList.remove('notice-success');
                            feedback.classList.add('notice-error');
                        }

                        if (statusText) {
                            if (strings.statusLabel && message) {
                                statusText.textContent = formatString(strings.statusLabel, { '1': message });
                            } else {
                                statusText.textContent = message;
                            }
                        }

                        if (messageEl) {
                            messageEl.textContent = message;
                        }

                        cancelButton.disabled = false;

                        if (currentJobId) {
                            scheduleNextPoll(currentJobId, 'active');
                        } else {
                            setSpinner(false);
                            if (startButton) {
                                startButton.disabled = false;
                            }
                        }
                    });
                });
            }
        }
    }

    // Gérer l'affichage/masquage du code des compositions
    const previewList = document.getElementById('patterns-preview-list');
    const globalCssDetails = document.querySelector('[data-tejlg-global-css]')
        || document.getElementById('tejlg-global-css');

    const handleToggleCodeView = function(button) {
        if (!button) {
            return;
        }

        if (!button.dataset.showLabel) {
            button.dataset.showLabel = button.textContent.trim();
        }

        const controlledId = button.getAttribute('aria-controls');
        const patternItem = button.closest('.pattern-item');
        let codeView = null;

        if (controlledId) {
            codeView = document.getElementById(controlledId);
        }

        if (!codeView && patternItem) {
            codeView = patternItem.querySelector('.pattern-code-view');
        }

        if (!codeView) {
            return;
        }

        const isHidden = codeView.hasAttribute('hidden');

        if (isHidden) {
            codeView.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            const hideLabel = hideBlockCodeText || button.dataset.hideLabel;
            if (hideLabel) {
                button.textContent = hideLabel;
                button.dataset.hideLabel = hideLabel;
            }
            if (patternItem) {
                patternItem.dispatchEvent(new CustomEvent('tejlg:request-preview', { bubbles: true }));
            }
        } else {
            codeView.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            const showLabel = showBlockCodeText || button.dataset.showLabel;
            if (showLabel) {
                button.textContent = showLabel;
            }
        }
    };

    const focusDetails = function(targetDetails) {
        if (targetDetails.tagName === 'DETAILS') {
            targetDetails.open = true;
            const summary = targetDetails.querySelector('summary');
            if (summary && typeof summary.focus === 'function') {
                summary.focus();
            } else if (typeof targetDetails.focus === 'function') {
                targetDetails.setAttribute('tabindex', '-1');
                targetDetails.focus();
                targetDetails.removeAttribute('tabindex');
            }
        }

        if (targetDetails.id) {
            window.location.hash = targetDetails.id;
        }

        if (typeof targetDetails.scrollIntoView === 'function') {
            targetDetails.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const resolveGlobalCssTarget = function(trigger) {
        if (!trigger) {
            return null;
        }

        let selector = trigger.getAttribute('data-target');
        if (!selector) {
            const href = trigger.getAttribute('href');
            if (href && href.charAt(0) === '#') {
                selector = href;
            }
        }

        if (selector) {
            try {
                const resolved = document.querySelector(selector);
                if (resolved) {
                    return resolved;
                }
            } catch (error) {
                // Ignore invalid selectors.
            }
        }

        return globalCssDetails;
    };

    const handleGlobalCssTrigger = function(trigger, event) {
        if (!trigger) {
            return;
        }

        if (event) {
            event.preventDefault();
        }

        const targetDetails = resolveGlobalCssTarget(trigger);
        if (!targetDetails) {
            return;
        }

        focusDetails(targetDetails);
    };

    if (previewList) {
        previewList.addEventListener('click', function(e) {
            const toggleButton = e.target.closest('.toggle-code-view');
            if (toggleButton) {
                handleToggleCodeView(toggleButton);
                return;
            }

            const cssTrigger = e.target.closest('.global-css-trigger');
            if (cssTrigger) {
                handleGlobalCssTrigger(cssTrigger, e);
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (previewList && previewList.contains(e.target)) {
            return;
        }

        const cssTrigger = e.target.closest('.global-css-trigger');
        if (cssTrigger) {
            handleGlobalCssTrigger(cssTrigger, e);
        }
    });

    const previewWrappers = document.querySelectorAll('.pattern-preview-wrapper');

    const MAX_PREVIEW_CONCURRENCY = Math.max(1, previewConcurrencyLimit);
    const previewLoadQueue = [];
    let activePreviewLoads = 0;

    const enqueuePreviewLoad = function(state, startCallback) {
        if (!state || typeof startCallback !== 'function') {
            return true;
        }

        const runner = function() {
            state.queueRunner = null;
            state.queuePending = false;

            let released = false;

            const release = function() {
                if (released) {
                    return;
                }

                released = true;

                if (state.queueRelease === release) {
                    state.queueRelease = null;
                }

                activePreviewLoads = Math.max(0, activePreviewLoads - 1);

                if (previewLoadQueue.length) {
                    const nextTask = previewLoadQueue.shift();

                    if (typeof nextTask === 'function') {
                        nextTask();
                    }
                }
            };

            state.queueRelease = release;
            activePreviewLoads += 1;
            startCallback(release);
        };

        if (activePreviewLoads < MAX_PREVIEW_CONCURRENCY) {
            runner();
            return true;
        }

        state.queueRunner = runner;
        state.queuePending = true;
        previewLoadQueue.push(runner);
        return false;
    };

    const removeQueuedPreview = function(state) {
        if (!state || !state.queueRunner) {
            return;
        }

        const index = previewLoadQueue.indexOf(state.queueRunner);

        if (index !== -1) {
            previewLoadQueue.splice(index, 1);
        }

        state.queueRunner = null;
        state.queuePending = false;
    };
    const previewControllers = (typeof WeakMap === 'function') ? new WeakMap() : null;

    const getPreviewController = function(wrapper) {
        if (!previewControllers) {
            return null;
        }

        return previewControllers.get(wrapper) || null;
    };

    const setPreviewController = function(wrapper, controller) {
        if (!previewControllers || !wrapper || !controller) {
            return;
        }

        previewControllers.set(wrapper, controller);
    };

    const clearPreviewController = function(wrapper) {
        if (!previewControllers || !wrapper) {
            return;
        }

        if (typeof previewControllers.delete === 'function') {
            previewControllers.delete(wrapper);
        }
    };

    const previewWidthControl = document.querySelector('[data-preview-width-control]');
    if (previewWidthControl && previewWrappers.length) {
        const previewWidthStrings = (typeof localization.previewWidth === 'object' && localization.previewWidth !== null)
            ? localization.previewWidth
            : {};

        const STORAGE_CHOICE_KEY = 'tejlgPreviewWidthChoice';
        const STORAGE_CUSTOM_KEY = 'tejlgPreviewWidthCustom';
        const CUSTOM_MIN_WIDTH = 320;
        const CUSTOM_MAX_WIDTH = 1600;
        const DEFAULT_EDITOR_WIDTH = 960;
        const DEFAULT_CUSTOM_WIDTH = 1024;

        const PRESET_WIDTHS = {
            editor: DEFAULT_EDITOR_WIDTH,
            full: null,
        };

        const widthButtons = previewWidthControl.querySelectorAll('[data-preview-width-option]');
        const customContainer = previewWidthControl.querySelector('[data-preview-width-custom]');
        const customRange = previewWidthControl.querySelector('[data-preview-width-range]');
        const customNumber = previewWidthControl.querySelector('[data-preview-width-number]');
        const valueDisplay = previewWidthControl.querySelector('[data-preview-width-value]');
        const customButton = previewWidthControl.querySelector('[data-preview-width-option="custom"]');

        const clampToCustomRange = function(value) {
            if (typeof value !== 'number' || Number.isNaN(value)) {
                return DEFAULT_CUSTOM_WIDTH;
            }

            if (value < CUSTOM_MIN_WIDTH) {
                return CUSTOM_MIN_WIDTH;
            }

            if (value > CUSTOM_MAX_WIDTH) {
                return CUSTOM_MAX_WIDTH;
            }

            return value;
        };

        const normalizeWidthValue = function(value, fallback) {
            if (typeof value === 'number' && !Number.isNaN(value)) {
                return clampToCustomRange(value);
            }

            const parsed = parseInt(value, 10);

            if (Number.isNaN(parsed)) {
                const fallbackValue = typeof fallback === 'number' ? fallback : DEFAULT_CUSTOM_WIDTH;
                return clampToCustomRange(fallbackValue);
            }

            return clampToCustomRange(parsed);
        };

        const parseChoice = function(choice) {
            if (choice === 'full' || choice === 'custom' || choice === 'editor') {
                return choice;
            }

            return 'editor';
        };

        const storage = {
            get: function(key) {
                try {
                    return window.localStorage.getItem(key);
                } catch (error) {
                    return null;
                }
            },
            set: function(key, value) {
                try {
                    window.localStorage.setItem(key, value);
                } catch (error) {
                    // Ignore storage write errors (e.g. private browsing).
                }
            },
        };

        let valueTemplate = '';
        if (typeof previewWidthStrings.valueTemplate === 'string' && previewWidthStrings.valueTemplate.length) {
            valueTemplate = previewWidthStrings.valueTemplate;
        } else if (valueDisplay && typeof valueDisplay.dataset.valueTemplate === 'string' && valueDisplay.dataset.valueTemplate.length) {
            valueTemplate = valueDisplay.dataset.valueTemplate;
        } else {
            valueTemplate = 'Largeur : %s px';
        }

        const valueUnit = (typeof previewWidthStrings.unit === 'string' && previewWidthStrings.unit.length)
            ? previewWidthStrings.unit
            : 'px';

        if (valueDisplay) {
            valueDisplay.dataset.valueTemplate = valueTemplate;
        }

        const formatWidthLabel = function(width) {
            if (typeof valueTemplate === 'string' && valueTemplate.indexOf('%s') !== -1) {
                return valueTemplate.replace('%s', width);
            }

            return width + ' ' + valueUnit;
        };

        const customInputLabel = (typeof previewWidthStrings.customInputLabel === 'string' && previewWidthStrings.customInputLabel.length)
            ? previewWidthStrings.customInputLabel
            : '';

        if (customRange && customInputLabel) {
            customRange.setAttribute('aria-label', customInputLabel);
        }

        if (customNumber && customInputLabel) {
            customNumber.setAttribute('aria-label', customInputLabel);
        }

        const updateCustomInputs = function(width) {
            const sanitized = clampToCustomRange(width);
            const widthString = String(sanitized);

            if (customRange && customRange.value !== widthString) {
                customRange.value = widthString;
            }

            if (customNumber && customNumber.value !== widthString) {
                customNumber.value = widthString;
            }

            if (valueDisplay) {
                valueDisplay.textContent = formatWidthLabel(sanitized);
            }

            return sanitized;
        };

        const updateActiveButton = function(choice) {
            Array.prototype.forEach.call(widthButtons, function(button) {
                const option = button.getAttribute('data-preview-width-option');
                const isActive = option === choice;

                if (isActive) {
                    button.classList.add('is-active');
                } else {
                    button.classList.remove('is-active');
                }

                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

                if (option === 'custom') {
                    button.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                }
            });
        };

        const toggleCustomVisibility = function(choice) {
            if (!customContainer) {
                return;
            }

            const isCustom = choice === 'custom';
            customContainer.hidden = !isCustom;
            previewWidthControl.classList.toggle('is-custom-width-active', isCustom);

            if (customButton) {
                customButton.setAttribute('aria-expanded', isCustom ? 'true' : 'false');
            }
        };

        const forEachWrapper = function(callback) {
            Array.prototype.forEach.call(previewWrappers, function(wrapper, index) {
                if (typeof callback === 'function') {
                    callback(wrapper, index);
                }
            });
        };

        const applyWidthChoice = function(choice, options) {
            const shouldSaveChoice = !options || options.saveChoice !== false;
            const resolvedChoice = parseChoice(choice);
            let cssValue = '';

            if (resolvedChoice === 'full') {
                cssValue = 'none';
            } else if (resolvedChoice === 'custom') {
                cssValue = clampToCustomRange(currentCustomWidth) + 'px';
            } else {
                const presetValue = PRESET_WIDTHS[resolvedChoice];
                if (typeof presetValue === 'number') {
                    cssValue = presetValue + 'px';
                }
            }

            previewWidthControl.setAttribute('data-preview-width-active', resolvedChoice);

            forEachWrapper(function(wrapper) {
                if (!wrapper) {
                    return;
                }

                wrapper.setAttribute('data-preview-width', resolvedChoice);

                if (cssValue === 'none') {
                    wrapper.style.setProperty('--tejlg-preview-max-width', 'none');
                } else if (cssValue) {
                    wrapper.style.setProperty('--tejlg-preview-max-width', cssValue);
                } else {
                    wrapper.style.removeProperty('--tejlg-preview-max-width');
                }
            });

            forEachWrapper(function(wrapper) {
                const controller = getPreviewController(wrapper);
                if (controller && typeof controller.syncHeight === 'function') {
                    controller.syncHeight();
                }
            });

            if (shouldSaveChoice) {
                storage.set(STORAGE_CHOICE_KEY, resolvedChoice);
            }
        };

        const commitCustomWidth = function(value, options) {
            const sanitized = normalizeWidthValue(value, currentCustomWidth);
            currentCustomWidth = sanitized;
            updateCustomInputs(sanitized);

            if (!options || options.save !== false) {
                storage.set(STORAGE_CUSTOM_KEY, String(sanitized));
            }

            if (currentChoice === 'custom') {
                applyWidthChoice('custom', { saveChoice: false });
            }
        };

        let currentChoice = parseChoice(storage.get(STORAGE_CHOICE_KEY));
        let currentCustomWidth = normalizeWidthValue(storage.get(STORAGE_CUSTOM_KEY), DEFAULT_CUSTOM_WIDTH);

        currentCustomWidth = updateCustomInputs(currentCustomWidth);
        updateActiveButton(currentChoice);
        toggleCustomVisibility(currentChoice);
        applyWidthChoice(currentChoice, { saveChoice: false });

        Array.prototype.forEach.call(widthButtons, function(button) {
            button.addEventListener('click', function() {
                const option = parseChoice(button.getAttribute('data-preview-width-option'));
                currentChoice = option;
                updateActiveButton(option);
                toggleCustomVisibility(option);
                applyWidthChoice(option);
            });
        });

        if (customRange) {
            customRange.addEventListener('input', function() {
                commitCustomWidth(customRange.value);
            });
        }

        if (customNumber) {
            customNumber.addEventListener('input', function() {
                if (customNumber.value === '') {
                    return;
                }

                commitCustomWidth(customNumber.value);
            });

            customNumber.addEventListener('blur', function() {
                if (customNumber.value === '') {
                    updateCustomInputs(currentCustomWidth);
                    return;
                }

                commitCustomWidth(customNumber.value);
            });

            customNumber.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    if (customNumber.value === '') {
                        updateCustomInputs(currentCustomWidth);
                        return;
                    }

                    commitCustomWidth(customNumber.value);
                }
            });
        }

        previewWidthControl.addEventListener('tejlg:refresh-preview-width', function() {
            applyWidthChoice(currentChoice, { saveChoice: false });
        });
    }

    if (previewWrappers.length) {
        const blobSupported = typeof URL !== 'undefined' && URL !== null && typeof URL.createObjectURL === 'function';
        const activeBlobUrls = new Set();
        let beforeUnloadRegistered = false;

        const registerBeforeUnload = function() {
            if (beforeUnloadRegistered) {
                return;
            }

            beforeUnloadRegistered = true;
            window.addEventListener('beforeunload', function() {
                activeBlobUrls.forEach(function(url) {
                    try {
                        URL.revokeObjectURL(url);
                    } catch (error) {
                        // Ignore revoke errors during unload.
                    }
                });
                activeBlobUrls.clear();
            });
        };

        const parsePreviewStylesheets = function(element) {
            if (!element) {
                return [];
            }

            const attributeValue = element.getAttribute('data-tejlg-stylesheets');

            if (typeof attributeValue !== 'string' || attributeValue === '') {
                return [];
            }

            try {
                const parsed = JSON.parse(attributeValue);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        };

        const parsePreviewStylesheetMarkup = function(element) {
            if (!element) {
                return '';
            }

            const attributeValue = element.getAttribute('data-tejlg-stylesheet-links-html');

            if (typeof attributeValue !== 'string' || attributeValue === '') {
                return '';
            }

            try {
                const parsed = JSON.parse(attributeValue);
                return typeof parsed === 'string' ? parsed : '';
            } catch (error) {
                return '';
            }
        };

        const normalizeStylesheetUrls = function(urls) {
            if (!Array.isArray(urls) || !urls.length) {
                return [];
            }

            const validUrls = [];
            const seen = [];

            urls.forEach(function(urlCandidate) {
                if (typeof urlCandidate !== 'string') {
                    return;
                }

                const trimmedUrl = urlCandidate.trim();

                if (!trimmedUrl.length) {
                    return;
                }

                const anchor = document.createElement('a');
                anchor.href = trimmedUrl;

                if (anchor.protocol !== 'http:' && anchor.protocol !== 'https:') {
                    return;
                }

                const normalized = anchor.href;

                if (seen.indexOf(normalized) !== -1) {
                    return;
                }

                seen.push(normalized);
                validUrls.push(normalized);
            });

            return validUrls;
        };

        const injectStylesheetsIntoHtml = function(html, stylesheetUrls, stylesheetMarkup) {
            if (typeof html !== 'string' || !html.length) {
                return html;
            }

            let workingHtml = html;
            let hasDoctype = false;

            const doctypePattern = /^<!doctype html>/i;
            if (doctypePattern.test(workingHtml)) {
                hasDoctype = true;
                workingHtml = workingHtml.replace(doctypePattern, '');
            }

            let previewDocument;

            try {
                previewDocument = document.implementation.createHTMLDocument('');
            } catch (error) {
                return html;
            }

            try {
                previewDocument.documentElement.innerHTML = workingHtml;
            } catch (error) {
                return html;
            }

            const headElement = previewDocument.head || previewDocument.getElementsByTagName('head')[0];
            if (!headElement) {
                return html;
            }

            const existingLinks = headElement.querySelectorAll('link[rel="stylesheet"]');
            const existingHrefs = [];

            Array.prototype.forEach.call(existingLinks, function(link) {
                if (link.href) {
                    existingHrefs.push(link.href);
                }
            });

            const appendLinkElement = function(linkEl) {
                if (!linkEl || !linkEl.href) {
                    return;
                }

                const href = linkEl.href;

                if (typeof href !== 'string' || !href.length) {
                    return;
                }

                const validator = previewDocument.createElement('a');
                validator.href = href;

                if (validator.protocol !== 'http:' && validator.protocol !== 'https:') {
                    return;
                }

                if (existingHrefs.indexOf(href) !== -1) {
                    return;
                }

                const newLink = previewDocument.createElement('link');
                newLink.rel = 'stylesheet';
                newLink.href = href;

                if (linkEl.media) {
                    newLink.media = linkEl.media;
                }

                if (linkEl.crossOrigin) {
                    newLink.crossOrigin = linkEl.crossOrigin;
                }

                if (linkEl.integrity) {
                    newLink.integrity = linkEl.integrity;
                }

                headElement.appendChild(newLink);
                existingHrefs.push(newLink.href);
            };

            if (typeof stylesheetMarkup === 'string' && stylesheetMarkup.length) {
                const container = previewDocument.createElement('div');

                try {
                    container.innerHTML = stylesheetMarkup;
                } catch (error) {
                    container.innerHTML = '';
                }

                const linkNodes = container.querySelectorAll('link[rel="stylesheet"]');
                Array.prototype.forEach.call(linkNodes, appendLinkElement);
            }

            if (Array.isArray(stylesheetUrls) && stylesheetUrls.length) {
                stylesheetUrls.forEach(function(url) {
                    if (existingHrefs.indexOf(url) !== -1) {
                        return;
                    }

                    const validator = previewDocument.createElement('a');
                    validator.href = url;

                    if (validator.protocol !== 'http:' && validator.protocol !== 'https:') {
                        return;
                    }

                    const linkEl = previewDocument.createElement('link');
                    linkEl.rel = 'stylesheet';
                    linkEl.href = url;
                    headElement.appendChild(linkEl);
                    existingHrefs.push(linkEl.href);
                });
            }

            const serializedHtml = previewDocument.documentElement ? previewDocument.documentElement.outerHTML : '';

            if (!serializedHtml.length) {
                return html;
            }

            return (hasDoctype ? '<!DOCTYPE html>' : '') + serializedHtml;
        };

        const DEFAULT_MIN_IFRAME_HEIGHT = 200;

        const getEffectiveMinHeight = function(element) {
            if (!element || typeof window.getComputedStyle !== 'function') {
                return DEFAULT_MIN_IFRAME_HEIGHT;
            }

            const computed = window.getComputedStyle(element);
            if (!computed) {
                return DEFAULT_MIN_IFRAME_HEIGHT;
            }

            const minHeight = computed.minHeight;
            if (!minHeight || minHeight === 'auto') {
                return DEFAULT_MIN_IFRAME_HEIGHT;
            }

            const parsed = parseFloat(minHeight);
            if (!isNaN(parsed) && isFinite(parsed)) {
                return Math.max(parsed, 0);
            }

            return DEFAULT_MIN_IFRAME_HEIGHT;
        };

        const computeContentHeight = function(doc) {
            if (!doc) {
                return 0;
            }

            const documentElement = doc.documentElement;
            const body = doc.body;
            let maxHeight = 0;

            if (documentElement) {
                maxHeight = Math.max(
                    maxHeight,
                    documentElement.scrollHeight || 0,
                    documentElement.offsetHeight || 0,
                    documentElement.clientHeight || 0
                );
            }

            if (body && body !== documentElement) {
                maxHeight = Math.max(
                    maxHeight,
                    body.scrollHeight || 0,
                    body.offsetHeight || 0,
                    body.clientHeight || 0
                );
            }

            return maxHeight;
        };

        const isElementVisible = function(element) {
            if (!element || typeof element.getBoundingClientRect !== 'function') {
                return false;
            }

            const rect = element.getBoundingClientRect();
            const viewHeight = window.innerHeight || document.documentElement.clientHeight || 0;

            return rect.top < viewHeight && rect.bottom > 0;
        };

        const intersectionObserver = (typeof IntersectionObserver === 'function')
            ? new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    const controller = getPreviewController(entry.target);
                    if (!controller) {
                        return;
                    }

                    controller.onIntersection(entry);
                });
            }, { rootMargin: '150px 0px' })
            : null;

        const createPreviewController = function(wrapper) {
            const dataElement = wrapper.querySelector('.pattern-preview-data');
            const iframe = wrapper.querySelector('.pattern-preview-iframe');
            const messageElement = wrapper.querySelector('.pattern-preview-message');
            const liveContainer = wrapper.querySelector('[data-preview-live]');
            const compactContainer = wrapper.querySelector('[data-preview-compact]');
            const loadingIndicator = wrapper.querySelector('[data-preview-loading]');
            const expandButtons = wrapper.querySelectorAll('[data-preview-trigger="expand"]');
            const collapseButtons = wrapper.querySelectorAll('[data-preview-trigger="collapse"]');
            const patternItem = wrapper.closest('.pattern-item');

            let state = null;

            const hidePreviewMessage = function(target) {
                if (state) {
                    state.queueMessageVisible = false;
                }

                if (!target) {
                    return;
                }

                target.hidden = true;
                target.setAttribute('hidden', 'hidden');
                target.textContent = '';
            };

            const showPreviewMessage = function(target, text) {
                if (state) {
                    state.queueMessageVisible = false;
                }

                if (!target) {
                    return;
                }

                if (typeof text !== 'string' || text === '') {
                    hidePreviewMessage(target);
                    return;
                }

                target.textContent = text;
                target.hidden = false;
                target.removeAttribute('hidden');
            };

            if (!dataElement || !iframe || !liveContainer || !compactContainer) {
                hidePreviewMessage(messageElement);
                return null;
            }

            state = {
                htmlContent: null,
                minHeightValue: getEffectiveMinHeight(iframe),
                blobUrl: null,
                resizeObserver: null,
                resizeInterval: null,
                removalObserver: null,
                pendingHeightSyncHandle: null,
                pendingHeightSyncIsAnimationFrame: false,
                previewInitialized: false,
                loadRequested: false,
                isVisible: false,
                previewUsesFallback: false,
                queuePending: false,
                queueRunner: null,
                queueRelease: null,
                queueMessageVisible: false,
            };

            const releaseQueueSlot = function() {
                if (typeof state.queueRelease === 'function') {
                    const release = state.queueRelease;
                    state.queueRelease = null;

                    try {
                        release();
                    } catch (error) {
                        // Ignore release errors to keep the queue moving.
                    }
                }
            };

            const clearQueueMessage = function() {
                if (!state || !state.queueMessageVisible) {
                    return;
                }

                if (messageElement) {
                    hidePreviewMessage(messageElement);
                } else {
                    state.queueMessageVisible = false;
                }
            };

            const showQueueMessage = function() {
                if (!previewQueueMessage || !messageElement) {
                    return;
                }

                showPreviewMessage(messageElement, previewQueueMessage);
                state.queueMessageVisible = true;
            };

            const setWrapperState = function(value) {
                wrapper.setAttribute('data-preview-state', value);
            };

            const setExpandedAttributes = function(isExpanded) {
                expandButtons.forEach(function(button) {
                    button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                });

                collapseButtons.forEach(function(button) {
                    button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                });
            };

            const showCompact = function() {
                if (compactContainer) {
                    compactContainer.hidden = false;
                }

                if (liveContainer) {
                    liveContainer.hidden = true;
                }

                setExpandedAttributes(false);
                setWrapperState('compact');
            };

            const showLive = function() {
                if (compactContainer) {
                    compactContainer.hidden = true;
                }

                if (liveContainer) {
                    liveContainer.hidden = false;
                }
            };

            const showLoadingIndicator = function() {
                if (!loadingIndicator) {
                    return;
                }

                loadingIndicator.hidden = false;
                loadingIndicator.setAttribute('aria-busy', 'true');
            };

            const hideLoadingIndicator = function() {
                if (!loadingIndicator) {
                    return;
                }

                loadingIndicator.hidden = true;
                loadingIndicator.removeAttribute('aria-busy');
            };

            const cancelScheduledHeightSync = function() {
                if (state.pendingHeightSyncHandle === null) {
                    return;
                }

                if (state.pendingHeightSyncIsAnimationFrame && typeof window.cancelAnimationFrame === 'function') {
                    window.cancelAnimationFrame(state.pendingHeightSyncHandle);
                } else {
                    window.clearTimeout(state.pendingHeightSyncHandle);
                }

                state.pendingHeightSyncHandle = null;
                state.pendingHeightSyncIsAnimationFrame = false;
            };

            const cleanupResizeListeners = function() {
                if (state.resizeObserver) {
                    state.resizeObserver.disconnect();
                    state.resizeObserver = null;
                }

                if (state.resizeInterval) {
                    window.clearInterval(state.resizeInterval);
                    state.resizeInterval = null;
                }
            };

            const cleanupRemovalObserver = function() {
                if (state.removalObserver) {
                    state.removalObserver.disconnect();
                    state.removalObserver = null;
                }
            };

            let handleIframeLoad;

            const cleanupPreview = function() {
                cancelScheduledHeightSync();
                cleanupResizeListeners();
                cleanupRemovalObserver();
                hideLoadingIndicator();
                clearQueueMessage();

                if (handleIframeLoad) {
                    iframe.removeEventListener('load', handleIframeLoad);
                }

                removeQueuedPreview(state);
                releaseQueueSlot();

                if (blobSupported && state.blobUrl) {
                    try {
                        URL.revokeObjectURL(state.blobUrl);
                    } catch (error) {
                        // Ignore revoke errors.
                    }

                    activeBlobUrls.delete(state.blobUrl);
                }

                state.blobUrl = null;

                if (typeof iframe.removeAttribute === 'function') {
                    iframe.removeAttribute('src');
                }

                if ('srcdoc' in iframe) {
                    try {
                        iframe.srcdoc = '';
                    } catch (error) {
                        // Ignore failures when clearing srcdoc.
                    }
                }

                try {
                    const iframeDocument = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
                    if (iframeDocument) {
                        iframeDocument.open();
                        iframeDocument.write('');
                        iframeDocument.close();
                    }
                } catch (error) {
                    // Ignore errors when clearing the iframe document.
                }

                if (iframe && iframe.style) {
                    iframe.style.height = state.minHeightValue + 'px';
                }

                hidePreviewMessage(messageElement);

                state.previewInitialized = false;
                state.loadRequested = false;
                state.previewUsesFallback = false;
            };

            const ensureRemovalObserver = function() {
                if (state.removalObserver || typeof MutationObserver !== 'function') {
                    return;
                }

                state.removalObserver = new MutationObserver(function() {
                    if (!wrapper.isConnected) {
                        cleanupPreview();
                    }
                });

                if (document.body) {
                    state.removalObserver.observe(document.body, { childList: true, subtree: true });
                }
            };

            const applyIframeHeight = function(doc) {
                let contentHeight = 0;

                try {
                    contentHeight = computeContentHeight(doc);
                } catch (error) {
                    contentHeight = 0;
                }

                if (!contentHeight && iframe) {
                    iframe.style.height = state.minHeightValue + 'px';
                    return;
                }

                const finalHeight = Math.max(contentHeight, state.minHeightValue);
                iframe.style.height = finalHeight + 'px';
            };

            const startResizeTracking = function(doc) {
                if (!doc) {
                    return;
                }

                cleanupResizeListeners();

                const updateHeight = function() {
                    applyIframeHeight(doc);
                };

                if (typeof ResizeObserver === 'function') {
                    try {
                        state.resizeObserver = new ResizeObserver(function() {
                            updateHeight();
                        });

                        if (doc.documentElement) {
                            state.resizeObserver.observe(doc.documentElement);
                        }

                        if (doc.body && doc.body !== doc.documentElement) {
                            state.resizeObserver.observe(doc.body);
                        }
                    } catch (error) {
                        state.resizeObserver = null;
                    }
                }

                if (!state.resizeObserver) {
                    state.resizeInterval = window.setInterval(function() {
                        updateHeight();
                    }, 400);
                }

                updateHeight();
            };

            handleIframeLoad = function() {
                cancelScheduledHeightSync();
                hideLoadingIndicator();

                try {
                    const iframeDocument = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document) || null;
                    if (!iframeDocument) {
                        iframe.style.height = state.minHeightValue + 'px';
                        return;
                    }

                    applyIframeHeight(iframeDocument);
                    startResizeTracking(iframeDocument);
                    ensureRemovalObserver();

                    if (!state.previewUsesFallback) {
                        hidePreviewMessage(messageElement);
                    }

                    state.loadRequested = false;
                    setWrapperState('expanded');
                } catch (error) {
                    iframe.style.height = state.minHeightValue + 'px';
                }

                clearQueueMessage();
                releaseQueueSlot();
            };

            const scheduleHeightSync = function() {
                if (state.pendingHeightSyncHandle !== null) {
                    return;
                }

                const run = function() {
                    state.pendingHeightSyncHandle = null;
                    state.pendingHeightSyncIsAnimationFrame = false;
                    handleIframeLoad();
                };

                if (typeof window.requestAnimationFrame === 'function') {
                    state.pendingHeightSyncIsAnimationFrame = true;
                    state.pendingHeightSyncHandle = window.requestAnimationFrame(run);
                } else {
                    state.pendingHeightSyncIsAnimationFrame = false;
                    state.pendingHeightSyncHandle = window.setTimeout(run, 0);
                }
            };

            const ensureHtmlContent = function() {
                if (state.htmlContent !== null) {
                    return state.htmlContent;
                }

                let htmlContent = '';

                try {
                    htmlContent = JSON.parse(dataElement.textContent || '""');
                } catch (error) {
                    htmlContent = '';
                }

                if (typeof htmlContent !== 'string' || htmlContent === '') {
                    state.htmlContent = '';
                    return '';
                }

                const stylesheetData = parsePreviewStylesheets(dataElement);
                const normalizedStylesheets = normalizeStylesheetUrls(stylesheetData);
                const stylesheetMarkup = parsePreviewStylesheetMarkup(dataElement);

                if (normalizedStylesheets.length || (typeof stylesheetMarkup === 'string' && stylesheetMarkup.length)) {
                    try {
                        const enrichedHtml = injectStylesheetsIntoHtml(htmlContent, normalizedStylesheets, stylesheetMarkup);
                        if (typeof enrichedHtml === 'string' && enrichedHtml.length) {
                            htmlContent = enrichedHtml;
                        }
                    } catch (error) {
                        // Keep original HTML if enrichment fails.
                    }
                }

                state.htmlContent = htmlContent;
                return htmlContent;
            };

            const startPreviewInitialization = function() {
                clearQueueMessage();

                const htmlContent = ensureHtmlContent();

                if (typeof htmlContent !== 'string' || htmlContent === '') {
                    hideLoadingIndicator();
                    state.loadRequested = false;
                    showCompact();
                    setWrapperState('compact');
                    releaseQueueSlot();
                    return;
                }

                state.previewInitialized = true;
                state.minHeightValue = getEffectiveMinHeight(iframe);
                state.previewUsesFallback = false;

                if (iframe && iframe.style) {
                    iframe.style.height = state.minHeightValue + 'px';
                }

                if (handleIframeLoad) {
                    iframe.removeEventListener('load', handleIframeLoad);
                }

                iframe.addEventListener('load', handleIframeLoad);

                let previewLoaded = false;

                if (blobSupported) {
                    try {
                        const blob = new Blob([htmlContent], { type: 'text/html' });
                        const blobUrl = URL.createObjectURL(blob);
                        state.blobUrl = blobUrl;
                        activeBlobUrls.add(blobUrl);
                        registerBeforeUnload();
                        iframe.src = blobUrl;
                        previewLoaded = true;
                        state.previewUsesFallback = false;
                        scheduleHeightSync();
                    } catch (error) {
                        state.blobUrl = null;
                        previewLoaded = false;
                    }
                }

                if (!previewLoaded) {
                    let fallbackLoaded = false;

                    if (typeof iframe.removeAttribute === 'function') {
                        iframe.removeAttribute('src');
                    }

                    if ('srcdoc' in iframe) {
                        try {
                            iframe.srcdoc = htmlContent;
                            fallbackLoaded = true;
                            state.previewUsesFallback = true;
                            scheduleHeightSync();
                        } catch (error) {
                            fallbackLoaded = false;
                        }
                    }

                    if (!fallbackLoaded) {
                        const iframeDocument = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);

                        if (iframeDocument) {
                            try {
                                iframeDocument.open();
                                iframeDocument.write(htmlContent);
                                iframeDocument.close();
                                fallbackLoaded = true;
                                state.previewUsesFallback = true;
                                handleIframeLoad();
                                scheduleHeightSync();
                            } catch (error) {
                                fallbackLoaded = false;
                            }
                        }
                    }

                    if (!fallbackLoaded) {
                        state.previewInitialized = false;
                        state.loadRequested = false;
                        hideLoadingIndicator();
                        showPreviewMessage(messageElement, previewFallbackWarning);
                        showCompact();
                        setWrapperState('compact');
                        releaseQueueSlot();
                        return;
                    }

                    showPreviewMessage(messageElement, previewFallbackWarning);
                }

                setWrapperState('expanded');
            };

            const initializePreview = function() {
                if (state.previewInitialized || state.queuePending) {
                    return;
                }

                state.queuePending = true;

                const startedImmediately = enqueuePreviewLoad(state, function() {
                    startPreviewInitialization();
                });

                if (!startedImmediately) {
                    showQueueMessage();
                }
            };

            const requestLoad = function() {
                if (state.previewInitialized) {
                    clearQueueMessage();
                    showLive();
                    setWrapperState('expanded');
                    setExpandedAttributes(true);
                    return;
                }

                state.loadRequested = true;
                showLive();
                showLoadingIndicator();
                setWrapperState('loading');
                setExpandedAttributes(true);

                if (!intersectionObserver) {
                    initializePreview();
                    return;
                }

                if (state.isVisible || isElementVisible(wrapper)) {
                    initializePreview();
                }
            };

            const collapsePreview = function() {
                cleanupPreview();
                showCompact();
            };

            const onIntersection = function(entry) {
                state.isVisible = entry.isIntersecting;

                if (entry.isIntersecting && state.loadRequested && !state.previewInitialized) {
                    initializePreview();
                }
            };

            const onPreviewRequest = function() {
                requestLoad();
            };

            expandButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    requestLoad();
                });
            });

            collapseButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    collapsePreview();
                });
            });

            if (patternItem) {
                patternItem.addEventListener('tejlg:request-preview', onPreviewRequest);
            }

            showCompact();

            const controller = {
                requestLoad: requestLoad,
                collapse: collapsePreview,
                onIntersection: onIntersection,
                syncHeight: function() {
                    if (state.previewInitialized) {
                        scheduleHeightSync();
                    }
                },
                destroy: function() {
                    if (patternItem) {
                        patternItem.removeEventListener('tejlg:request-preview', onPreviewRequest);
                    }

                    cleanupPreview();
                    clearPreviewController(wrapper);
                },
            };

            setPreviewController(wrapper, controller);

            return controller;
        };

        previewWrappers.forEach(function(wrapper) {
            const controller = createPreviewController(wrapper);

            if (!controller) {
                return;
            }

            if (intersectionObserver) {
                intersectionObserver.observe(wrapper);
            }
        });

        if (previewWidthControl) {
            try {
                previewWidthControl.dispatchEvent(new CustomEvent('tejlg:refresh-preview-width'));
            } catch (error) {
                const event = document.createEvent('CustomEvent');
                event.initCustomEvent('tejlg:refresh-preview-width', false, false, {});
                previewWidthControl.dispatchEvent(event);
            }
        }
    }
    // Gérer la sélection, la recherche et les filtres pour l'export et l'import de compositions
    const patternSelectionStatusStrings = (typeof localization.patternSelectionStatus === 'object' && localization.patternSelectionStatus !== null)
        ? localization.patternSelectionStatus
        : {};
    const patternSelectionCountStrings = (typeof localization.patternSelectionCount === 'object' && localization.patternSelectionCount !== null)
        ? localization.patternSelectionCount
        : {};
    const patternSelectionNumberFormatter = (function() {
        const locale = typeof patternSelectionStatusStrings.numberLocale === 'string' && patternSelectionStatusStrings.numberLocale
            ? patternSelectionStatusStrings.numberLocale
            : undefined;

        if (typeof Intl === 'object' && typeof Intl.NumberFormat === 'function') {
            try {
                return new Intl.NumberFormat(locale);
            } catch (error) {
                return new Intl.NumberFormat();
            }
        }

        return null;
    })();

    function formatPatternSelectionCount(count) {
        if (patternSelectionNumberFormatter) {
            return patternSelectionNumberFormatter.format(count);
        }

        return String(count);
    }

    function buildPatternSelectionCountMessage(count) {
        const formattedCount = formatPatternSelectionCount(count);

        if (count === 0) {
            if (typeof patternSelectionCountStrings.none === 'string' && patternSelectionCountStrings.none !== '') {
                if (patternSelectionCountStrings.none.indexOf('%s') !== -1) {
                    return patternSelectionCountStrings.none.replace('%s', formattedCount);
                }

                return patternSelectionCountStrings.none;
            }

            if (typeof patternSelectionCountStrings.some === 'string' && patternSelectionCountStrings.some.indexOf('%s') !== -1) {
                return patternSelectionCountStrings.some.replace('%s', formattedCount);
            }

            return formattedCount + ' selection(s)';
        }

        const template = (typeof patternSelectionCountStrings.some === 'string' && patternSelectionCountStrings.some !== '')
            ? patternSelectionCountStrings.some
            : '%s selection(s)';

        if (template.indexOf('%s') !== -1) {
            return template.replace('%s', formattedCount);
        }

        return template;
    }

    function isElement(node) {
        return !!node && typeof node === 'object' && node.nodeType === 1;
    }

    function toFiniteNumber(value) {
        if (typeof value === 'number') {
            return isFinite(value) ? value : null;
        }

        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);
            return isFinite(parsed) ? parsed : null;
        }

        return null;
    }

    function createPatternSelectionController(options) {
        if (!options || !options.listElement) {
            return null;
        }

        const listElement = options.listElement;
        const itemSelector = typeof options.itemSelector === 'string' && options.itemSelector
            ? options.itemSelector
            : '.pattern-selection-item';
        const items = Array.from(listElement.querySelectorAll(itemSelector));
        const hiddenClass = typeof options.hiddenClass === 'string' && options.hiddenClass
            ? options.hiddenClass
            : 'is-hidden';
        const selectAllCheckbox = options.selectAllCheckbox || null;
        const searchInput = options.searchInput || null;
        const categorySelect = options.categorySelect || null;
        const dateSelect = options.dateSelect || null;
        const sortSelect = options.sortSelect || null;
        const statusElement = options.statusElement || null;
        const noCategoryValue = typeof options.noCategoryValue === 'string' ? options.noCategoryValue : '';
        const noDateValue = typeof options.noDateValue === 'string' ? options.noDateValue : '';
        const defaultSort = typeof options.defaultSort === 'string' ? options.defaultSort : '';
        const selectionCountElement = isElement(options.selectionCountElement)
            ? options.selectionCountElement
            : null;
        const submitButtons = (function(source) {
            const elements = [];

            const addElement = function(element) {
                if (isElement(element) && elements.indexOf(element) === -1) {
                    elements.push(element);
                }
            };

            if (!source) {
                return elements;
            }

            if (Array.isArray(source)) {
                source.forEach(addElement);
                return elements;
            }

            if (typeof source.forEach === 'function') {
                source.forEach(addElement);
                return elements;
            }

            if (typeof source.length === 'number') {
                Array.prototype.forEach.call(source, addElement);
                return elements;
            }

            addElement(source);
            return elements;
        })(options.submitButtons);

        if (!items.length) {
            updateSelectionFeedback();
            if (statusElement) {
                statusElement.setAttribute('aria-busy', 'false');
                const emptyMessage = typeof patternSelectionStatusStrings.empty === 'string'
                    ? patternSelectionStatusStrings.empty
                    : '';
                statusElement.textContent = emptyMessage;
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.disabled = true;
            }

            return null;
        }

        const state = {
            search: '',
            category: '',
            date: '',
            sort: '',
        };

        const getItemCheckbox = typeof options.getItemCheckbox === 'function'
            ? options.getItemCheckbox
            : function(item) {
                return item.querySelector('input[type="checkbox"]');
            };

        const getSearchableText = typeof options.getSearchableText === 'function'
            ? options.getSearchableText
            : function(item) {
                return (item.textContent || '').toLowerCase();
            };

        const getCategoryTokens = typeof options.getCategoryTokens === 'function'
            ? options.getCategoryTokens
            : function(item) {
                const attr = item.getAttribute('data-terms') || '';
                if (!attr) {
                    return [];
                }

                return attr
                    .split(/\s+/)
                    .filter(function(token) { return token !== ''; })
                    .map(function(token) { return token.toLowerCase(); });
            };

        const getDateValue = typeof options.getDateValue === 'function'
            ? options.getDateValue
            : function(item) {
                return item.getAttribute('data-date') || '';
            };

        const getTimestamp = typeof options.getTimestamp === 'function'
            ? options.getTimestamp
            : function() {
                return null;
            };

        const getTitleSortValue = typeof options.getTitleSortValue === 'function'
            ? options.getTitleSortValue
            : function(item) {
                const label = item.getAttribute('data-label') || '';
                return label.toLowerCase();
            };

        const getOriginalIndex = typeof options.getOriginalIndex === 'function'
            ? options.getOriginalIndex
            : function(item, index) {
                const attr = item.getAttribute('data-original-index');
                const parsed = toFiniteNumber(attr);
                if (parsed !== null) {
                    return parsed;
                }

                return index;
            };

        const originalIndexMap = typeof WeakMap === 'function' ? new WeakMap() : null;
        items.forEach(function(item, index) {
            if (originalIndexMap) {
                originalIndexMap.set(item, index);
            }
        });

        function getOriginalIndexNumber(item, index) {
            const provided = getOriginalIndex(item, index);
            const parsedProvided = toFiniteNumber(provided);

            if (parsedProvided !== null) {
                return parsedProvided;
            }

            if (originalIndexMap && originalIndexMap.has(item)) {
                return originalIndexMap.get(item);
            }

            const fallbackIndex = items.indexOf(item);
            return fallbackIndex === -1 ? index : fallbackIndex;
        }

        function getTimestampNumber(item) {
            const raw = getTimestamp(item);
            const parsed = toFiniteNumber(raw);
            return parsed === null ? null : parsed;
        }

        function compareTitleValues(valueA, valueB) {
            const a = typeof valueA === 'string' ? valueA : '';
            const b = typeof valueB === 'string' ? valueB : '';

            if (typeof a.localeCompare === 'function') {
                return a.localeCompare(b, undefined, { sensitivity: 'base' });
            }

            if (a === b) {
                return 0;
            }

            return a > b ? 1 : -1;
        }

        function compareByOriginal(a, b) {
            const indexA = getOriginalIndexNumber(a, 0);
            const indexB = getOriginalIndexNumber(b, 0);

            if (indexA === indexB) {
                return 0;
            }

            return indexA < indexB ? -1 : 1;
        }

        function compareByTitleAsc(a, b) {
            const result = compareTitleValues(getTitleSortValue(a), getTitleSortValue(b));
            if (result !== 0) {
                return result;
            }

            return compareByOriginal(a, b);
        }

        function compareByTitleDesc(a, b) {
            const result = compareTitleValues(getTitleSortValue(b), getTitleSortValue(a));
            if (result !== 0) {
                return result;
            }

            return compareByOriginal(a, b);
        }

        function compareByTimestampDesc(a, b) {
            const timeA = getTimestampNumber(a);
            const timeB = getTimestampNumber(b);
            const hasA = timeA !== null;
            const hasB = timeB !== null;

            if (hasA && hasB) {
                if (timeA === timeB) {
                    return compareByTitleAsc(a, b);
                }

                return timeB - timeA;
            }

            if (hasA) {
                return -1;
            }

            if (hasB) {
                return 1;
            }

            return compareByTitleAsc(a, b);
        }

        function compareByTimestampAsc(a, b) {
            const timeA = getTimestampNumber(a);
            const timeB = getTimestampNumber(b);
            const hasA = timeA !== null;
            const hasB = timeB !== null;

            if (hasA && hasB) {
                if (timeA === timeB) {
                    return compareByTitleAsc(a, b);
                }

                return timeA - timeB;
            }

            if (hasA) {
                return -1;
            }

            if (hasB) {
                return 1;
            }

            return compareByTitleAsc(a, b);
        }

        const comparators = {
            'title-asc': compareByTitleAsc,
            'title-desc': compareByTitleDesc,
            'date-desc': compareByTimestampDesc,
            'date-asc': compareByTimestampAsc,
            'original': compareByOriginal,
        };

        function getVisibleItems() {
            return items.filter(function(item) {
                return !item.classList.contains(hiddenClass);
            });
        }

        function getVisibleCheckboxes() {
            return getVisibleItems()
                .map(function(item) {
                    return getItemCheckbox(item);
                })
                .filter(function(checkbox) {
                    return checkbox !== null && !checkbox.disabled;
                });
        }

        function getAllCheckboxes() {
            return items
                .map(function(item) {
                    return getItemCheckbox(item);
                })
                .filter(function(checkbox) {
                    return checkbox !== null && !checkbox.disabled;
                });
        }

        function updateSelectionFeedback() {
            const allCheckboxes = getAllCheckboxes();
            const checkedCount = allCheckboxes.filter(function(checkbox) {
                return checkbox.checked;
            }).length;

            if (selectionCountElement) {
                const message = buildPatternSelectionCountMessage(checkedCount);
                selectionCountElement.textContent = message;
                selectionCountElement.setAttribute('data-selection-count', String(checkedCount));
            }

            submitButtons.forEach(function(button) {
                if (!button) {
                    return;
                }

                if (checkedCount === 0) {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                } else {
                    button.disabled = false;
                    button.setAttribute('aria-disabled', 'false');
                }
            });

            return {
                total: allCheckboxes.length,
                checked: checkedCount,
            };
        }

        function applySelectAllStateToVisible(shouldCheck) {
            getVisibleCheckboxes().forEach(function(checkbox) {
                if (checkbox.checked !== shouldCheck) {
                    checkbox.checked = shouldCheck;
                }
            });
        }

        function setBusy(isBusy) {
            if (!statusElement) {
                return;
            }

            statusElement.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        }

        function buildStatusMessage() {
            if (!statusElement) {
                return '';
            }

            const visibleCount = getVisibleItems().length;
            let message = '';

            if (visibleCount === 0) {
                message = typeof patternSelectionStatusStrings.empty === 'string'
                    ? patternSelectionStatusStrings.empty
                    : '';
            } else if (visibleCount === 1) {
                const templateSingular = typeof patternSelectionStatusStrings.countSingular === 'string'
                    ? patternSelectionStatusStrings.countSingular
                    : (typeof patternSelectionStatusStrings.countPlural === 'string'
                        ? patternSelectionStatusStrings.countPlural
                        : '%s');
                message = templateSingular.replace('%s', formatPatternSelectionCount(visibleCount));
            } else {
                const templatePlural = typeof patternSelectionStatusStrings.countPlural === 'string'
                    ? patternSelectionStatusStrings.countPlural
                    : (typeof patternSelectionStatusStrings.countSingular === 'string'
                        ? patternSelectionStatusStrings.countSingular
                        : '%s');
                message = templatePlural.replace('%s', formatPatternSelectionCount(visibleCount));
            }

            const summaries = [];

            if (searchInput && state.search) {
                const rawValue = searchInput.value ? searchInput.value.trim() : state.search;
                const template = typeof patternSelectionStatusStrings.filterSearch === 'string'
                    ? patternSelectionStatusStrings.filterSearch
                    : '';

                if (template && template.indexOf('%s') !== -1) {
                    summaries.push(template.replace('%s', rawValue));
                } else if (rawValue) {
                    summaries.push(rawValue);
                }
            }

            if (categorySelect && state.category) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const label = selectedOption ? selectedOption.textContent.trim() : state.category;
                if (state.category === noCategoryValue) {
                    const templateNone = typeof patternSelectionStatusStrings.filterCategoryNone === 'string'
                        ? patternSelectionStatusStrings.filterCategoryNone
                        : '';

                    if (templateNone && templateNone.indexOf('%s') !== -1) {
                        summaries.push(templateNone.replace('%s', label));
                    } else if (templateNone) {
                        summaries.push(templateNone);
                    } else if (label) {
                        summaries.push(label);
                    }
                } else {
                    const template = typeof patternSelectionStatusStrings.filterCategory === 'string'
                        ? patternSelectionStatusStrings.filterCategory
                        : '';

                    if (template && template.indexOf('%s') !== -1) {
                        summaries.push(template.replace('%s', label));
                    } else if (label) {
                        summaries.push(label);
                    }
                }
            }

            if (dateSelect && state.date) {
                const selectedOption = dateSelect.options[dateSelect.selectedIndex];
                const label = selectedOption ? selectedOption.textContent.trim() : state.date;
                if (state.date === noDateValue) {
                    const templateNone = typeof patternSelectionStatusStrings.filterDateNone === 'string'
                        ? patternSelectionStatusStrings.filterDateNone
                        : '';

                    if (templateNone && templateNone.indexOf('%s') !== -1) {
                        summaries.push(templateNone.replace('%s', label));
                    } else if (templateNone) {
                        summaries.push(templateNone);
                    } else if (label) {
                        summaries.push(label);
                    }
                } else {
                    const template = typeof patternSelectionStatusStrings.filterDate === 'string'
                        ? patternSelectionStatusStrings.filterDate
                        : '';

                    if (template && template.indexOf('%s') !== -1) {
                        summaries.push(template.replace('%s', label));
                    } else if (label) {
                        summaries.push(label);
                    }
                }
            }

            if (summaries.length) {
                const intro = typeof patternSelectionStatusStrings.filtersSummaryIntro === 'string'
                    ? patternSelectionStatusStrings.filtersSummaryIntro
                    : '';
                const joiner = typeof patternSelectionStatusStrings.filtersSummaryJoin === 'string'
                    ? patternSelectionStatusStrings.filtersSummaryJoin
                    : ', ';
                const summaryText = (intro ? intro + ' ' : '') + summaries.join(joiner);
                message = message ? message + ' ' + summaryText : summaryText;
            }

            return message;
        }

        function updateStatus() {
            if (!statusElement) {
                return;
            }

            statusElement.textContent = buildStatusMessage();
            setBusy(false);
        }

        function matchesFilters(item) {
            const haystack = (getSearchableText(item) || '').toLowerCase();

            if (state.search && haystack.indexOf(state.search) === -1) {
                return false;
            }

            if (categorySelect) {
                if (state.category === noCategoryValue) {
                    const tokens = getCategoryTokens(item);
                    if (tokens.length !== 0) {
                        return false;
                    }
                } else if (state.category) {
                    const tokens = getCategoryTokens(item).map(function(token) {
                        return String(token).toLowerCase();
                    });
                    if (tokens.indexOf(state.category.toLowerCase()) === -1) {
                        return false;
                    }
                }
            }

            if (dateSelect) {
                const value = getDateValue(item) || '';
                if (state.date === noDateValue) {
                    if (value !== '') {
                        return false;
                    }
                } else if (state.date && value !== state.date) {
                    return false;
                }
            }

            return true;
        }

        function applyFilters() {
            setBusy(true);

            items.forEach(function(item) {
                if (matchesFilters(item)) {
                    item.classList.remove(hiddenClass);
                } else {
                    item.classList.add(hiddenClass);
                }
            });

            updateSelectAllState();
        }

        function sortItems() {
            if (!sortSelect) {
                return;
            }

            const activeSort = state.sort && comparators[state.sort]
                ? state.sort
                : (defaultSort && comparators[defaultSort] ? defaultSort : '');
            const comparator = activeSort && comparators[activeSort]
                ? comparators[activeSort]
                : compareByOriginal;

            const orderedItems = items.slice().sort(comparator);
            orderedItems.forEach(function(item) {
                listElement.appendChild(item);
            });
            items.splice.apply(items, [0, items.length].concat(orderedItems));
        }

        function updateSelectAllState() {
            updateSelectionFeedback();

            if (!selectAllCheckbox) {
                updateStatus();
                return;
            }

            const visibleCheckboxes = getVisibleCheckboxes();

            if (!visibleCheckboxes.length) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.disabled = true;
                updateStatus();
                return;
            }

            selectAllCheckbox.disabled = false;

            const checkedCount = visibleCheckboxes.filter(function(checkbox) {
                return checkbox.checked;
            }).length;

            if (checkedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount === visibleCheckboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }

            updateStatus();
        }

        function setSearchTerm(rawValue) {
            const normalized = (rawValue || '').trim().toLowerCase();
            if (normalized === state.search) {
                updateSelectAllState();
                return;
            }

            state.search = normalized;
            applyFilters();
        }

        function setCategory(value) {
            const normalized = typeof value === 'string' ? value : '';
            if (normalized === state.category) {
                applyFilters();
                return;
            }

            state.category = normalized;
            applyFilters();
        }

        function setDate(value) {
            const normalized = typeof value === 'string' ? value : '';
            if (normalized === state.date) {
                applyFilters();
                return;
            }

            state.date = normalized;
            applyFilters();
        }

        function setSort(value) {
            const normalized = typeof value === 'string' ? value : '';
            if (normalized === state.sort) {
                sortItems();
                applyFilters();
                return;
            }

            state.sort = normalized;
            sortItems();
            applyFilters();
        }

        if (statusElement) {
            statusElement.setAttribute('aria-busy', 'false');
        }

        if (searchInput && searchInput.value) {
            state.search = (searchInput.value || '').trim().toLowerCase();
        }

        if (categorySelect && categorySelect.value) {
            state.category = categorySelect.value;
        }

        if (dateSelect && dateSelect.value) {
            state.date = dateSelect.value;
        }

        if (sortSelect) {
            const initialSort = defaultSort || sortSelect.value || '';
            state.sort = initialSort;
            if (sortSelect.value !== initialSort) {
                sortSelect.value = initialSort;
            }
            sortItems();
        }

        applyFilters();

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function(event) {
                const shouldCheck = event.target.checked;
                applySelectAllStateToVisible(shouldCheck);

                updateSelectAllState();
            });
        }

        items.forEach(function(item, index) {
            const checkbox = getItemCheckbox(item);
            if (checkbox) {
                checkbox.addEventListener('change', updateSelectAllState);
            }

            if (originalIndexMap) {
                originalIndexMap.set(item, index);
            }
        });

        if (searchInput) {
            const searchHandler = function(event) {
                setSearchTerm(event.target.value || '');
            };

            searchInput.addEventListener('input', searchHandler);
            searchInput.addEventListener('keyup', searchHandler);
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', function(event) {
                setCategory(event.target.value || '');
            });
        }

        if (dateSelect) {
            dateSelect.addEventListener('change', function(event) {
                setDate(event.target.value || '');
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function(event) {
                setSort(event.target.value || '');
            });
        }

        return {
            refresh: applyFilters,
        };
    }

    const exportPatternList = document.getElementById('pattern-selection-items');

    createPatternSelectionController({
        listElement: exportPatternList,
        itemSelector: '.pattern-selection-item',
        hiddenClass: 'is-hidden',
        selectAllCheckbox: document.getElementById('select-all-export-patterns'),
        searchInput: document.getElementById('pattern-search'),
        statusElement: document.getElementById('pattern-selection-status'),
        selectionCountElement: document.getElementById('pattern-selection-count'),
        submitButtons: (function() {
            if (!exportPatternList) {
                return [];
            }

            const parentForm = exportPatternList.closest('form');
            return parentForm ? parentForm.querySelectorAll('[data-pattern-submit]') : [];
        })(),
    });

    const importPatternList = document.getElementById('patterns-preview-items');

    createPatternSelectionController({
        listElement: importPatternList,
        itemSelector: '.pattern-item',
        hiddenClass: 'is-hidden',
        selectAllCheckbox: document.getElementById('select-all-patterns'),
        searchInput: document.getElementById('tejlg-import-pattern-search'),
        statusElement: document.getElementById('pattern-import-status'),
        selectionCountElement: document.getElementById('pattern-import-selection-count'),
        submitButtons: (function() {
            if (!importPatternList) {
                return [];
            }

            const parentForm = importPatternList.closest('form');
            return parentForm ? parentForm.querySelectorAll('[data-pattern-submit]') : [];
        })(),
        categorySelect: document.getElementById('tejlg-import-filter-category'),
        dateSelect: document.getElementById('tejlg-import-filter-date'),
        sortSelect: document.getElementById('tejlg-import-sort'),
        noCategoryValue: '__no-category__',
        noDateValue: '__no-date__',
        defaultSort: (function() {
            const container = document.getElementById('patterns-preview-list');
            if (!container) {
                return '';
            }

            return container.getAttribute('data-default-sort') || '';
        })(),
        getItemCheckbox: function(item) {
            return item.querySelector('.pattern-selector input[type="checkbox"]');
        },
        getSearchableText: function(item) {
            return (item.getAttribute('data-search') || '').toLowerCase();
        },
        getCategoryTokens: function(item) {
            const attr = item.getAttribute('data-categories') || '';
            if (!attr) {
                return [];
            }

            return attr
                .split(/\s+/)
                .filter(function(token) { return token !== ''; })
                .map(function(token) { return token.toLowerCase(); });
        },
        getDateValue: function(item) {
            return item.getAttribute('data-period') || '';
        },
        getTimestamp: function(item) {
            return item.getAttribute('data-timestamp') || null;
        },
        getTitleSortValue: function(item) {
            return item.getAttribute('data-title-sort') || '';
        },
        getOriginalIndex: function(item) {
            return item.getAttribute('data-original-index') || null;
        },
    });

    const stepForms = document.querySelectorAll('[data-step-form]');

    if (stepForms.length) {
        stepForms.forEach(function(form) {
            const steps = form.querySelectorAll('[data-step]');
            const stepperItems = form.querySelectorAll('[data-stepper-item]');

            if (!steps.length || !stepperItems.length) {
                return;
            }

            let currentStepIndex = 0;

            const textarea = form.querySelector('#tejlg_exclusion_patterns');
            const summaryTarget = form.querySelector('[data-step-summary-exclusions]');
            const summaryEmptyValue = summaryTarget
                ? summaryTarget.getAttribute('data-step-summary-empty') || ''
                : '';

            const focusStepTitle = function(stepElement) {
                if (!stepElement) {
                    return;
                }

                const title = stepElement.querySelector('.tejlg-step__title');

                if (title && typeof title.focus === 'function') {
                    window.requestAnimationFrame(function() {
                        try {
                            title.focus({ preventScroll: true });
                        } catch (error) {
                            title.focus();
                        }
                    });
                }
            };

            const updateStepperVisualState = function() {
                stepperItems.forEach(function(item, index) {
                    const isActive = index === currentStepIndex;
                    const isComplete = index < currentStepIndex;

                    item.classList.toggle('is-active', isActive);
                    item.classList.toggle('is-complete', isComplete);

                    if (isActive) {
                        item.setAttribute('aria-current', 'step');
                    } else {
                        item.removeAttribute('aria-current');
                    }
                });
            };

            const setStep = function(nextIndex, options) {
                const maxIndex = steps.length - 1;
                const clampedIndex = Math.max(0, Math.min(nextIndex, maxIndex));

                if (clampedIndex === currentStepIndex && !(options && options.force)) {
                    return;
                }

                currentStepIndex = clampedIndex;

                steps.forEach(function(stepElement, index) {
                    const isActive = index === currentStepIndex;
                    stepElement.classList.toggle('is-active', isActive);
                    stepElement.hidden = !isActive;

                    if (isActive) {
                        stepElement.removeAttribute('aria-hidden');
                    } else {
                        stepElement.setAttribute('aria-hidden', 'true');
                    }
                });

                updateStepperVisualState();

                if (!options || options.focus !== false) {
                    focusStepTitle(steps[currentStepIndex]);
                }
            };

            const goToNextStep = function() {
                setStep(currentStepIndex + 1);
            };

            const goToPreviousStep = function() {
                setStep(currentStepIndex - 1);
            };

            const updateSummary = function() {
                if (!summaryTarget) {
                    return;
                }

                if (!textarea || !textarea.value.trim()) {
                    summaryTarget.textContent = summaryEmptyValue;
                    return;
                }

                const tokens = textarea.value.split(/[\r\n,]+/).map(function(token) {
                    return token.trim();
                }).filter(function(token) {
                    return token.length > 0;
                });

                summaryTarget.textContent = tokens.length
                    ? tokens.join(', ')
                    : summaryEmptyValue;
            };

            const nextButtons = form.querySelectorAll('[data-step-next]');
            const prevButtons = form.querySelectorAll('[data-step-prev]');

            nextButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    goToNextStep();
                });
            });

            prevButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    goToPreviousStep();
                });
            });

            if (textarea) {
                textarea.addEventListener('input', updateSummary);
                textarea.addEventListener('change', updateSummary);
            }

            updateSummary();
            setStep(0, { force: true, focus: false });
        });
    }

});
