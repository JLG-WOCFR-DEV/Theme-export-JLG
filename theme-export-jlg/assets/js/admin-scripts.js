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

    const exportAsync = (localization && typeof localization.exportAsync === 'object')
        ? localization.exportAsync
        : null;

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
            const pollInterval = typeof exportAsync.pollInterval === 'number' ? exportAsync.pollInterval : 4000;
            const defaults = (typeof exportAsync.defaults === 'object' && exportAsync.defaults !== null)
                ? exportAsync.defaults
                : null;
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

            const setSpinner = function(isActive) {
                if (!spinner) {
                    return;
                }

                if (isActive) {
                    spinner.classList.add('is-active');
                } else {
                    spinner.classList.remove('is-active');
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
            };

            const stopPolling = function() {
                if (pollTimeout) {
                    window.clearTimeout(pollTimeout);
                    pollTimeout = null;
                }
            };

            const scheduleNextPoll = function(jobId) {
                stopPolling();

                pollTimeout = window.setTimeout(function() {
                    fetchStatus(jobId);
                }, pollInterval);
            };

            const updateFeedback = function(job, extra) {
                if (!feedback || !job) {
                    if (cancelButton) {
                        cancelButton.hidden = true;
                        cancelButton.disabled = false;
                    }
                    return;
                }

                feedback.hidden = false;
                feedback.classList.remove('notice-error', 'notice-success', 'notice-info');

                let statusLabel = strings.queued || '';
                let description = '';
                let progressValue = 0;
                let shouldShowCancel = false;
                const failureCode = typeof job.failure_code === 'string' ? job.failure_code : '';

                if (typeof job.progress === 'number') {
                    progressValue = Math.max(0, Math.min(100, Math.round(job.progress)));
                }

                if (progressBar) {
                    progressBar.value = progressValue;
                }

                if (job.status === 'completed') {
                    feedback.classList.add('notice-success');
                    statusLabel = strings.completed || '';
                    if (downloadLink && extra && typeof extra.downloadUrl === 'string') {
                        downloadLink.hidden = false;
                        downloadLink.href = extra.downloadUrl;
                        downloadLink.textContent = strings.downloadLabel || downloadLink.textContent;
                    }
                } else if (job.status === 'failed') {
                    feedback.classList.add('notice-error');
                    if (failureCode === 'timeout' && strings.autoFailedStatus) {
                        statusLabel = strings.autoFailedStatus;
                    } else {
                        const failureMessage = job.message && job.message.length ? job.message : (strings.failed || '');
                        statusLabel = strings.failed ? formatString(strings.failed, { '1': failureMessage }) : failureMessage;
                    }
                } else if (job.status === 'cancelled') {
                    feedback.classList.add('notice-info');
                    progressValue = 0;
                    if (progressBar) {
                        progressBar.value = 0;
                    }
                    statusLabel = strings.cancelledStatus || strings.cancelled || '';
                    description = job.message && job.message.length ? job.message : (strings.cancelledMessage || '');
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
                }

                if (statusText) {
                    if (strings.statusLabel && statusLabel) {
                        statusText.textContent = formatString(strings.statusLabel, { '1': statusLabel });
                    } else {
                        statusText.textContent = statusLabel;
                    }
                }

                if (messageEl) {
                    if (job.status === 'failed') {
                        if (failureCode === 'timeout') {
                            const autoFailureMessage = job.message && job.message.length
                                ? job.message
                                : (strings.autoFailedMessage || strings.unknownError || '');
                            messageEl.textContent = autoFailureMessage;
                        } else {
                            const failureMessage = job.message && job.message.length ? job.message : (strings.unknownError || '');
                            messageEl.textContent = failureMessage;
                        }
                    } else if (job.status === 'cancelled') {
                        const cancelledMessage = description && description.length
                            ? description
                            : (strings.cancelledMessage || strings.cancelled || '');
                        messageEl.textContent = cancelledMessage;
                    } else {
                        messageEl.textContent = description;
                    }
                }

                if (downloadLink) {
                    if (job.status === 'completed') {
                        // Link already handled above.
                    } else {
                        downloadLink.hidden = true;
                        downloadLink.removeAttribute('href');
                    }
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
            };

            const handleError = function(message) {
                stopPolling();
                if (feedback) {
                    feedback.hidden = false;
                    feedback.classList.remove('notice-info', 'notice-success');
                    feedback.classList.add('notice-error');
                }
                if (statusText) {
                    statusText.textContent = strings.failed ? formatString(strings.failed, { '1': message }) : message;
                }
                if (messageEl) {
                    messageEl.textContent = message || strings.unknownError || '';
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
                    const extra = { downloadUrl: payload.data.download_url || '' };
                    updateFeedback(job, extra);

                    if (job.status === 'completed' || job.status === 'failed' || job.status === 'cancelled') {
                        stopPolling();
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }
                        currentJobId = null;
                    } else {
                        scheduleNextPoll(jobId);
                    }
                }).catch(function(error) {
                    setSpinner(false);
                    if (startButton) {
                        startButton.disabled = false;
                    }
                    const message = (typeof error === 'string' && error.length)
                        ? error
                        : (error && typeof error.message === 'string' && error.message.length)
                            ? error.message
                            : strings.unknownError || '';
                    handleError(message || strings.unknownError || '');
                });
            };

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

                if (job) {
                    updateFeedback(job, { downloadUrl: '' });
                }

                const statusFromSnapshot = typeof snapshot.status === 'string' && snapshot.status.length
                    ? snapshot.status
                    : (job && typeof job.status === 'string')
                        ? job.status
                        : '';

                const normalizedStatus = statusFromSnapshot.toLowerCase();

                const shouldFetch = ['queued', 'processing', 'completed', 'failed'].indexOf(normalizedStatus) !== -1;

                if (!shouldFetch) {
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

            if (startButton) {
                startButton.addEventListener('click', function(event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    stopPolling();
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

                        updateFeedback(job || null, { downloadUrl: '' });
                        scheduleNextPoll(currentJobId);
                    }).catch(function(error) {
                        setSpinner(false);
                        if (startButton) {
                            startButton.disabled = false;
                        }
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

                        cancelButton.disabled = false;
                        const job = payload.data.job;
                        currentJobId = null;
                        updateFeedback(job, { downloadUrl: '' });
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
                            scheduleNextPoll(currentJobId);
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

    const themeImportConfirmMessage = typeof localization.themeImportConfirm === 'string'
        ? localization.themeImportConfirm
        : '';

    if (themeImportConfirmMessage) {
        const themeImportForm = document.getElementById('tejlg-import-theme-form');

        if (themeImportForm) {
            const overwriteField = themeImportForm.querySelector('#tejlg_confirm_theme_overwrite');

            if (overwriteField) {
                overwriteField.value = '0';
            }

            themeImportForm.addEventListener('submit', function(event) {
                if (!window.confirm(themeImportConfirmMessage)) {
                    if (overwriteField) {
                        overwriteField.value = '0';
                    }
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    return;
                }

                if (overwriteField) {
                    overwriteField.value = '1';
                }
            });
        }
    }

    // Gérer la case "Tout sélectionner" pour l'import

    // Gérer l'accordéon sur la page de débogage
    const accordionContainer = document.getElementById('debug-accordion');
    if (accordionContainer) {
        const accordionButtons = accordionContainer.querySelectorAll('.accordion-section-title');

        const updateSectionState = function(button, content, section, expanded) {
            const isExpanded = Boolean(expanded);
            button.setAttribute('aria-expanded', String(isExpanded));

            if (content) {
                content.hidden = !isExpanded;
                content.setAttribute('aria-hidden', String(!isExpanded));
            }

            if (section) {
                section.classList.toggle('open', isExpanded);
            }
        };

        accordionButtons.forEach(function(button) {
            const section = button.closest('.accordion-section');
            const controlledId = button.getAttribute('aria-controls');
            let content = null;

            if (controlledId) {
                content = document.getElementById(controlledId);
            } else if (section) {
                content = section.querySelector('.accordion-section-content');
            }

            if (content) {
                const initialExpanded = button.getAttribute('aria-expanded') === 'true';
                updateSectionState(button, content, section, initialExpanded);
            }

            const toggleSection = function() {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                updateSectionState(button, content, section, !isExpanded);
            };

            let skipNextClick = false;

            button.addEventListener('click', function(event) {
                event.preventDefault();
                if (skipNextClick) {
                    skipNextClick = false;
                    return;
                }

                toggleSection();
            });

            button.addEventListener('keydown', function(event) {
                if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
                    event.preventDefault();
                    skipNextClick = true;
                    toggleSection();
                }
            });
        });
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
    const previewControllers = new WeakMap();

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
                const controller = previewControllers.get(wrapper);
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
                    const controller = previewControllers.get(entry.target);
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

            if (!dataElement || !iframe || !liveContainer || !compactContainer) {
                hidePreviewMessage(messageElement);
                return null;
            }

            const state = {
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

                if (handleIframeLoad) {
                    iframe.removeEventListener('load', handleIframeLoad);
                }

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

            const initializePreview = function() {
                if (state.previewInitialized) {
                    return;
                }

                const htmlContent = ensureHtmlContent();

                if (typeof htmlContent !== 'string' || htmlContent === '') {
                    hideLoadingIndicator();
                    state.loadRequested = false;
                    showCompact();
                    setWrapperState('compact');
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
                        return;
                    }

                    showPreviewMessage(messageElement, previewFallbackWarning);
                }

                setWrapperState('expanded');
            };

            const requestLoad = function() {
                if (state.previewInitialized) {
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

            return {
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
                },
            };
        };

        previewWrappers.forEach(function(wrapper) {
            const controller = createPreviewController(wrapper);

            if (!controller) {
                return;
            }

            previewControllers.set(wrapper, controller);

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

    // Mettre à jour en continu les métriques de performance dans le badge.
    const fpsElement = document.getElementById('tejlg-metric-fps');
    const latencyElement = document.getElementById('tejlg-metric-latency');

    if (fpsElement && latencyElement) {
        const metricsLocalization = (typeof localization.metrics === 'object' && localization.metrics !== null)
            ? localization.metrics
            : {};

        const locale = typeof metricsLocalization.locale === 'string' && metricsLocalization.locale
            ? metricsLocalization.locale
            : undefined;

        const fpsUnit = typeof metricsLocalization.fpsUnit === 'string' && metricsLocalization.fpsUnit.trim() !== ''
            ? metricsLocalization.fpsUnit
            : 'FPS';

        const latencyUnit = typeof metricsLocalization.latencyUnit === 'string' && metricsLocalization.latencyUnit.trim() !== ''
            ? metricsLocalization.latencyUnit
            : 'ms';

        const placeholderText = typeof metricsLocalization.placeholder === 'string' && metricsLocalization.placeholder !== ''
            ? metricsLocalization.placeholder
            : '--';

        const stoppedText = typeof metricsLocalization.stopped === 'string' && metricsLocalization.stopped !== ''
            ? metricsLocalization.stopped
            : '⏹';

        const loadingText = typeof metricsLocalization.loading === 'string' && metricsLocalization.loading !== ''
            ? metricsLocalization.loading
            : placeholderText;

        const ariaLiveValue = typeof metricsLocalization.ariaLivePolite === 'string' && metricsLocalization.ariaLivePolite !== ''
            ? metricsLocalization.ariaLivePolite
            : 'polite';

        const ariaAtomicValue = typeof metricsLocalization.ariaAtomic === 'string' && metricsLocalization.ariaAtomic !== ''
            ? metricsLocalization.ariaAtomic
            : 'true';

        const latencyPrecision = typeof metricsLocalization.latencyPrecision === 'number' && metricsLocalization.latencyPrecision >= 0
            ? metricsLocalization.latencyPrecision
            : 1;

        fpsElement.setAttribute('aria-live', ariaLiveValue);
        fpsElement.setAttribute('aria-atomic', ariaAtomicValue);
        latencyElement.setAttribute('aria-live', ariaLiveValue);
        latencyElement.setAttribute('aria-atomic', ariaAtomicValue);

        fpsElement.textContent = loadingText;
        latencyElement.textContent = loadingText;

        const idealFrameDuration = 1000 / 60;
        const maxSampleSize = 120;
        const maxFrameGap = 1500;

        const hasPerformanceNow = typeof window.performance === 'object' && typeof window.performance.now === 'function';
        const now = hasPerformanceNow
            ? function() { return window.performance.now(); }
            : function() { return Date.now ? Date.now() : new Date().getTime(); };

        const hasNativeRequestAnimationFrame = typeof window.requestAnimationFrame === 'function';
        const scheduleFrame = hasNativeRequestAnimationFrame
            ? function(callback) {
                return window.requestAnimationFrame(callback);
            }
            : function(callback) {
                return window.setTimeout(function() {
                    callback(now());
                }, idealFrameDuration);
            };

        const cancelFrame = hasNativeRequestAnimationFrame
            ? function(handle) {
                window.cancelAnimationFrame(handle);
            }
            : function(handle) {
                window.clearTimeout(handle);
            };

        const fpsSamples = [];
        const latencySamplesFromRaf = [];
        const latencySamplesFromObserver = [];

        let animationFrameId = null;
        let lastFrameTime;
        let monitoringActive = true;
        let performanceObserverInstance = null;

        const hasIntl = typeof window.Intl === 'object' && typeof window.Intl.NumberFormat === 'function';
        const fpsFormatter = hasIntl
            ? new window.Intl.NumberFormat(locale, { maximumFractionDigits: 0, minimumFractionDigits: 0 })
            : null;
        const latencyFormatter = hasIntl
            ? new window.Intl.NumberFormat(locale, { maximumFractionDigits: latencyPrecision, minimumFractionDigits: 0 })
            : null;

        const pushSample = function(samples, value) {
            samples.push(value);
            if (samples.length > maxSampleSize) {
                samples.shift();
            }
        };

        const computeAverage = function(samples) {
            if (!samples.length) {
                return null;
            }

            var total = 0;
            for (var i = 0; i < samples.length; i += 1) {
                total += samples[i];
            }

            return total / samples.length;
        };

        const formatValue = function(value, formatter, fallbackDigits) {
            if (typeof value !== 'number' || !isFinite(value)) {
                return placeholderText;
            }

            if (formatter) {
                return formatter.format(value);
            }

            var digits = Math.max(0, fallbackDigits);
            return value.toFixed(digits);
        };

        const updateDisplay = function() {
            if (!monitoringActive) {
                return;
            }

            const averageFps = computeAverage(fpsSamples);
            if (averageFps === null) {
                fpsElement.textContent = placeholderText;
            } else {
                const fpsText = formatValue(averageFps, fpsFormatter, 0);
                fpsElement.textContent = fpsUnit ? fpsText + '\u00a0' + fpsUnit : fpsText;
            }

            const observerLatency = computeAverage(latencySamplesFromObserver);
            const rafLatency = computeAverage(latencySamplesFromRaf);
            const latencyToDisplay = observerLatency !== null ? observerLatency : rafLatency;

            if (latencyToDisplay === null) {
                latencyElement.textContent = placeholderText;
            } else {
                const latencyText = formatValue(latencyToDisplay, latencyFormatter, latencyPrecision);
                latencyElement.textContent = latencyUnit ? latencyText + '\u00a0' + latencyUnit : latencyText;
            }
        };

        const onAnimationFrame = function(timestamp) {
            if (!monitoringActive) {
                return;
            }

            if (typeof lastFrameTime === 'number') {
                var frameDelta = timestamp - lastFrameTime;

                if (frameDelta > 0 && frameDelta < maxFrameGap) {
                    pushSample(fpsSamples, 1000 / frameDelta);
                    var latencyValue = frameDelta - idealFrameDuration;
                    if (latencyValue < 0) {
                        latencyValue = 0;
                    }
                    pushSample(latencySamplesFromRaf, latencyValue);
                }
            }

            lastFrameTime = timestamp;
            updateDisplay();
            scheduleNextFrame();
        };

        const scheduleNextFrame = function() {
            animationFrameId = scheduleFrame(onAnimationFrame);
        };

        const setupPerformanceObserver = function() {
            if (typeof window.PerformanceObserver !== 'function') {
                return;
            }

            const supportedEntryTypes = Array.isArray(window.PerformanceObserver.supportedEntryTypes)
                ? window.PerformanceObserver.supportedEntryTypes
                : [];

            var observerType = '';
            if (supportedEntryTypes.indexOf('event') !== -1) {
                observerType = 'event';
            } else if (supportedEntryTypes.indexOf('longtask') !== -1) {
                observerType = 'longtask';
            }

            if (!observerType) {
                return;
            }

            try {
                performanceObserverInstance = new window.PerformanceObserver(function(list) {
                    const entries = list.getEntries();
                    for (var i = 0; i < entries.length; i += 1) {
                        var entry = entries[i];
                        var duration = 0;

                        if (observerType === 'event') {
                            if (typeof entry.duration === 'number' && entry.duration > 0) {
                                duration = entry.duration;
                            } else if (
                                typeof entry.processingEnd === 'number' &&
                                typeof entry.startTime === 'number'
                            ) {
                                duration = entry.processingEnd - entry.startTime;
                            }
                        } else if (observerType === 'longtask') {
                            duration = entry.duration;
                        }

                        if (duration > 0) {
                            pushSample(latencySamplesFromObserver, duration);
                        }
                    }

                    updateDisplay();
                });

                if (observerType === 'event') {
                    performanceObserverInstance.observe({ type: 'event', buffered: true, durationThreshold: 0 });
                } else {
                    performanceObserverInstance.observe({ type: 'longtask', buffered: true });
                }
            } catch (error) {
                performanceObserverInstance = null;
            }
        };

        const stopMonitoring = function() {
            if (!monitoringActive) {
                return;
            }

            monitoringActive = false;

            if (animationFrameId !== null) {
                cancelFrame(animationFrameId);
                animationFrameId = null;
            }

            if (performanceObserverInstance) {
                try {
                    performanceObserverInstance.disconnect();
                } catch (error) {
                    // Ignorer les erreurs de déconnexion.
                }
                performanceObserverInstance = null;
            }

            lastFrameTime = undefined;
            fpsSamples.length = 0;
            latencySamplesFromRaf.length = 0;
            latencySamplesFromObserver.length = 0;

            fpsElement.textContent = stoppedText;
            latencyElement.textContent = stoppedText;
        };

        setupPerformanceObserver();
        scheduleNextFrame();

        ['beforeunload', 'pagehide'].forEach(function(eventName) {
            window.addEventListener(eventName, stopMonitoring, { once: true });
        });
    }

});
