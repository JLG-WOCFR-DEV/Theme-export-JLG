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
            const presetInputs = exportForm.querySelectorAll('[data-exclusion-preset]');
            const customInput = exportForm.querySelector('[data-exclusion-custom]');
            const summaryList = exportForm.querySelector('[data-exclusion-summary]');
            const startButton = exportForm.querySelector('[data-export-start]');
            const feedback = exportForm.querySelector('[data-export-feedback]');
            const statusText = exportForm.querySelector('[data-export-status-text]');
            const messageEl = exportForm.querySelector('[data-export-message]');
            const progressBar = exportForm.querySelector('[data-export-progress-bar]');
            const downloadLink = exportForm.querySelector('[data-export-download]');
            const spinner = exportForm.querySelector('[data-export-spinner]');
            const strings = typeof exportAsync.strings === 'object' ? exportAsync.strings : {};
            const pollInterval = typeof exportAsync.pollInterval === 'number' ? exportAsync.pollInterval : 4000;
            const defaults = (typeof exportAsync.defaults === 'object' && exportAsync.defaults !== null)
                ? exportAsync.defaults
                : null;
            const exclusionLocalization = (typeof localization.exclusions === 'object' && localization.exclusions !== null)
                ? localization.exclusions
                : null;
            const summaryEmptyText = summaryList
                ? summaryList.getAttribute('data-empty-text') || ((exclusionLocalization && exclusionLocalization.strings)
                    ? exclusionLocalization.strings.summaryEmpty
                    : '')
                : '';
            const presetCatalog = exclusionLocalization && Array.isArray(exclusionLocalization.presets)
                ? exclusionLocalization.presets
                : [];
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

            const ensureDefaultsApplied = function() {
                if (!defaults) {
                    return;
                }

                if (customInput && typeof defaults.custom === 'string' && !customInput.value) {
                    customInput.value = defaults.custom;
                }

                if (presetInputs && defaults.presets && Array.isArray(defaults.presets)) {
                    const presetSet = {};
                    defaults.presets.forEach(function(key) {
                        presetSet[key] = true;
                    });

                    Array.prototype.forEach.call(presetInputs, function(input) {
                        const value = input && typeof input.value === 'string' ? input.value : '';
                        if (value && Object.prototype.hasOwnProperty.call(presetSet, value)) {
                            input.checked = true;
                        }
                    });
                }
            };

            const getSelectedPresetKeys = function() {
                if (!presetInputs) {
                    return [];
                }

                const keys = [];

                Array.prototype.forEach.call(presetInputs, function(input) {
                    if (!input || !input.checked) {
                        return;
                    }

                    const value = typeof input.value === 'string' ? input.value : '';

                    if (!value || keys.indexOf(value) !== -1) {
                        return;
                    }

                    keys.push(value);
                });

                return keys;
            };

            const getPatternsForPreset = function(key) {
                if (!presetCatalog.length) {
                    return [];
                }

                for (let index = 0; index < presetCatalog.length; index += 1) {
                    const preset = presetCatalog[index];
                    if (!preset || typeof preset.key !== 'string') {
                        continue;
                    }

                    if (preset.key === key) {
                        return Array.isArray(preset.patterns) ? preset.patterns : [];
                    }
                }

                return [];
            };

            const parseCustomPatterns = function(rawValue) {
                if (typeof rawValue !== 'string' || !rawValue.length) {
                    return [];
                }

                const split = rawValue.split(/[,\r\n]+/);

                if (!split || !split.length) {
                    return [];
                }

                return split
                    .map(function(item) {
                        return item.trim();
                    })
                    .filter(function(item) {
                        return item.length > 0;
                    });
            };

            const renderExclusionSummary = function() {
                if (!summaryList) {
                    return;
                }

                const summaryItems = [];
                const seen = {};
                const presetKeys = getSelectedPresetKeys();

                presetKeys.forEach(function(key) {
                    const patterns = getPatternsForPreset(key);
                    patterns.forEach(function(pattern) {
                        if (typeof pattern !== 'string' || !pattern.length) {
                            return;
                        }

                        if (Object.prototype.hasOwnProperty.call(seen, pattern)) {
                            return;
                        }

                        seen[pattern] = true;
                        summaryItems.push(pattern);
                    });
                });

                const customPatterns = parseCustomPatterns(customInput ? customInput.value : '');
                customPatterns.forEach(function(pattern) {
                    if (Object.prototype.hasOwnProperty.call(seen, pattern)) {
                        return;
                    }

                    seen[pattern] = true;
                    summaryItems.push(pattern);
                });

                while (summaryList.firstChild) {
                    summaryList.removeChild(summaryList.firstChild);
                }

                if (!summaryItems.length) {
                    const emptyItem = document.createElement('li');
                    emptyItem.className = 'description';
                    emptyItem.textContent = summaryEmptyText || '';
                    summaryList.appendChild(emptyItem);
                    return;
                }

                summaryItems.forEach(function(pattern) {
                    const item = document.createElement('li');
                    const code = document.createElement('code');
                    code.textContent = pattern;
                    item.appendChild(code);
                    summaryList.appendChild(item);
                });
            };

            ensureDefaultsApplied();
            renderExclusionSummary();

            if (presetInputs) {
                Array.prototype.forEach.call(presetInputs, function(input) {
                    if (!input) {
                        return;
                    }

                    input.addEventListener('change', function() {
                        renderExclusionSummary();
                    });
                });
            }

            if (customInput) {
                customInput.addEventListener('input', function() {
                    renderExclusionSummary();
                });
            }

            let currentJobId = null;
            let pollTimeout = null;

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
                    return;
                }

                feedback.hidden = false;
                feedback.classList.remove('notice-error', 'notice-success', 'notice-info');

                let statusLabel = strings.queued || '';
                let description = '';
                let progressValue = 0;

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
                    const failureMessage = job.message && job.message.length ? job.message : (strings.failed || '');
                    statusLabel = strings.failed ? formatString(strings.failed, { '1': failureMessage }) : failureMessage;
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
                        const failureMessage = job.message && job.message.length ? job.message : (strings.unknownError || '');
                        messageEl.textContent = failureMessage;
                    } else {
                        messageEl.textContent = description;
                    }
                }

                if (job.status !== 'completed' && downloadLink) {
                    downloadLink.hidden = true;
                    downloadLink.removeAttribute('href');
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

                    if (job.status === 'completed' || job.status === 'failed') {
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
                    const presetKeys = getSelectedPresetKeys();

                    if (presetKeys.length) {
                        presetKeys.forEach(function(key) {
                            formData.append('tejlg_exclusion_presets[]', key);
                        });
                    }

                    if (customInput) {
                        formData.append('tejlg_exclusion_custom', customInput.value || '');
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
        }
    }

    const themeImportConfirmMessage = typeof localization.themeImportConfirm === 'string'
        ? localization.themeImportConfirm
        : '';

    // Gérer la case "Tout sélectionner" pour l'import
    const selectAllCheckbox = document.getElementById('select-all-patterns');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            document.querySelectorAll('input[name="selected_patterns[]"]').forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
    }

    // Gérer l'accordéon sur la page de débogage
    const accordionContainer = document.getElementById('debug-accordion');
    if (accordionContainer) {
        const accordionTitles = accordionContainer.querySelectorAll('.accordion-section-title');
        accordionTitles.forEach(function(title) {
            title.addEventListener('click', function() {
                const parentSection = this.closest('.accordion-section');
                if (parentSection) {
                    parentSection.classList.toggle('open');
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
        let codeView = null;

        if (controlledId) {
            codeView = document.getElementById(controlledId);
        }

        if (!codeView) {
            const patternItem = button.closest('.pattern-item');
            codeView = patternItem ? patternItem.querySelector('.pattern-code-view') : null;
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
    if (previewWrappers.length) {
        const blobSupported = typeof URL !== 'undefined' && URL !== null && typeof URL.createObjectURL === 'function';
        const blobUrls = [];

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

            const existingHrefs = Array.prototype.slice.call(headElement.querySelectorAll('link[rel="stylesheet"]')).map(function(linkEl) {
                return linkEl.href;
            });

            const appendLinkElement = function(linkEl) {
                if (!linkEl) {
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

        const hidePreviewMessage = function(element) {
            if (!element) {
                return;
            }

            element.textContent = '';
            element.hidden = true;
        };

        const showPreviewMessage = function(element, text) {
            if (!element) {
                return;
            }

            element.textContent = text;
            element.hidden = false;
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

        previewWrappers.forEach(function(wrapper) {
            const dataElement = wrapper.querySelector('.pattern-preview-data');
            const iframe = wrapper.querySelector('.pattern-preview-iframe');
            const messageElement = wrapper.querySelector('.pattern-preview-message');

            if (!dataElement || !iframe) {
                hidePreviewMessage(messageElement);
                return;
            }

            let htmlContent = '';
            try {
                htmlContent = JSON.parse(dataElement.textContent || '""');
            } catch (error) {
                htmlContent = '';
            }

            if (typeof htmlContent !== 'string' || htmlContent === '') {
                hidePreviewMessage(messageElement);
                return;
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
                    // Keep the original HTML content if stylesheet injection fails.
                }
            }

            let previewLoaded = false;
            const minHeightValue = getEffectiveMinHeight(iframe);
            if (iframe && iframe.style) {
                iframe.style.height = minHeightValue + 'px';
            }
            let resizeObserver = null;
            let resizeInterval = null;
            let removalObserver = null;
            let pendingHeightSyncHandle = null;
            let pendingHeightSyncIsAnimationFrame = false;

            const cancelScheduledHeightSync = function() {
                if (pendingHeightSyncHandle === null) {
                    return;
                }

                if (pendingHeightSyncIsAnimationFrame && typeof window.cancelAnimationFrame === 'function') {
                    window.cancelAnimationFrame(pendingHeightSyncHandle);
                } else {
                    window.clearTimeout(pendingHeightSyncHandle);
                }

                pendingHeightSyncHandle = null;
                pendingHeightSyncIsAnimationFrame = false;
            };

            const cleanupResizeListeners = function() {
                if (resizeObserver) {
                    resizeObserver.disconnect();
                    resizeObserver = null;
                }

                if (resizeInterval) {
                    window.clearInterval(resizeInterval);
                    resizeInterval = null;
                }
            };

            const cleanupOnRemoval = function() {
                cleanupResizeListeners();
                cancelScheduledHeightSync();
                if (removalObserver) {
                    removalObserver.disconnect();
                    removalObserver = null;
                }
                iframe.removeEventListener('load', handleIframeLoad);
            };

            const ensureRemovalObserver = function() {
                if (removalObserver || typeof MutationObserver !== 'function') {
                    return;
                }

                removalObserver = new MutationObserver(function() {
                    if (!document.body || !document.body.contains(iframe)) {
                        cleanupOnRemoval();
                    }
                });

                if (document.body) {
                    removalObserver.observe(document.body, { childList: true, subtree: true });
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
                    iframe.style.height = minHeightValue + 'px';
                    return;
                }

                const finalHeight = Math.max(contentHeight, minHeightValue);
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
                        resizeObserver = new ResizeObserver(function() {
                            updateHeight();
                        });

                        if (doc.documentElement) {
                            resizeObserver.observe(doc.documentElement);
                        }

                        if (doc.body && doc.body !== doc.documentElement) {
                            resizeObserver.observe(doc.body);
                        }
                    } catch (error) {
                        resizeObserver = null;
                    }
                }

                if (!resizeObserver) {
                    resizeInterval = window.setInterval(function() {
                        updateHeight();
                    }, 400);
                }

                updateHeight();
            };

            const handleIframeLoad = function() {
                cancelScheduledHeightSync();
                try {
                    const iframeDocument = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document) || null;
                    if (!iframeDocument) {
                        iframe.style.height = minHeightValue + 'px';
                        return;
                    }

                    applyIframeHeight(iframeDocument);
                    startResizeTracking(iframeDocument);
                    ensureRemovalObserver();
                } catch (error) {
                    iframe.style.height = minHeightValue + 'px';
                }
            };

            const scheduleHeightSync = function() {
                if (pendingHeightSyncHandle !== null) {
                    return;
                }

                const run = function() {
                    pendingHeightSyncHandle = null;
                    pendingHeightSyncIsAnimationFrame = false;
                    handleIframeLoad();
                };

                if (typeof window.requestAnimationFrame === 'function') {
                    pendingHeightSyncIsAnimationFrame = true;
                    pendingHeightSyncHandle = window.requestAnimationFrame(run);
                } else {
                    pendingHeightSyncIsAnimationFrame = false;
                    pendingHeightSyncHandle = window.setTimeout(run, 0);
                }
            };

            iframe.addEventListener('load', handleIframeLoad);

            if (blobSupported) {
                try {
                    const blob = new Blob([htmlContent], { type: 'text/html' });
                    const blobUrl = URL.createObjectURL(blob);
                    iframe.src = blobUrl;
                    blobUrls.push(blobUrl);
                    previewLoaded = true;
                    hidePreviewMessage(messageElement);
                    scheduleHeightSync();
                } catch (error) {
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
                            handleIframeLoad();
                            scheduleHeightSync();
                        } catch (error) {
                            fallbackLoaded = false;
                        }
                    }
                }

                showPreviewMessage(messageElement, previewFallbackWarning);
            }

            if (previewLoaded) {
                ensureRemovalObserver();
            }
        });

        if (blobSupported && blobUrls.length) {
            window.addEventListener('beforeunload', function() {
                blobUrls.forEach(function(blobUrl) {
                    URL.revokeObjectURL(blobUrl);
                });
            });
        }
    }

    // Gérer la confirmation d'importation de thème
    const themeImportForm = document.getElementById('tejlg-import-theme-form');
    if (themeImportForm && themeImportConfirmMessage) {
        themeImportForm.addEventListener('submit', function(event) {
            if (!window.confirm(themeImportConfirmMessage)) {
                event.preventDefault();
            }
        });
    }

    // Gérer la case "Tout sélectionner" pour l'export sélectif
    const patternList = document.querySelector('[data-searchable="true"]');
    const patternItems = patternList ? Array.from(patternList.querySelectorAll('.pattern-selection-item')) : [];
    const selectAllExportCheckbox = document.getElementById('select-all-export-patterns');
    const patternSearchInput = document.getElementById('pattern-search');
    const patternSelectionStatus = document.getElementById('pattern-selection-status');
    const patternSelectionStatusStrings = (typeof localization.patternSelectionStatus === 'object' && localization.patternSelectionStatus !== null)
        ? localization.patternSelectionStatus
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

    function getVisiblePatternItems() {
        return patternItems.filter(function(item) {
            return !item.classList.contains('is-hidden');
        });
    }

    function setPatternSelectionBusy(isBusy) {
        if (!patternSelectionStatus) {
            return;
        }

        patternSelectionStatus.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    }

    function formatPatternSelectionCount(count) {
        if (patternSelectionNumberFormatter) {
            return patternSelectionNumberFormatter.format(count);
        }

        return String(count);
    }

    function updatePatternSelectionStatus() {
        if (!patternSelectionStatus) {
            return;
        }

        const visibleCount = getVisiblePatternItems().length;
        let message = '';

        if (visibleCount === 0) {
            message = typeof patternSelectionStatusStrings.empty === 'string'
                ? patternSelectionStatusStrings.empty
                : '';
        } else if (visibleCount === 1) {
            const template = typeof patternSelectionStatusStrings.countSingular === 'string'
                ? patternSelectionStatusStrings.countSingular
                : (typeof patternSelectionStatusStrings.countPlural === 'string'
                    ? patternSelectionStatusStrings.countPlural
                    : '%s');
            message = template.replace('%s', formatPatternSelectionCount(visibleCount));
        } else {
            const template = typeof patternSelectionStatusStrings.countPlural === 'string'
                ? patternSelectionStatusStrings.countPlural
                : (typeof patternSelectionStatusStrings.countSingular === 'string'
                    ? patternSelectionStatusStrings.countSingular
                    : '%s');
            message = template.replace('%s', formatPatternSelectionCount(visibleCount));
        }

        patternSelectionStatus.textContent = message;
        setPatternSelectionBusy(false);
    }

    function updateSelectAllExportCheckbox() {
        const visibleItems = getVisiblePatternItems();

        if (!selectAllExportCheckbox) {
            updatePatternSelectionStatus();
            return;
        }

        const visibleCheckboxes = visibleItems
            .map(function(item) {
                return item.querySelector('input[type="checkbox"]');
            })
            .filter(function(checkbox) {
                return checkbox !== null;
            });

        if (visibleCheckboxes.length === 0) {
            selectAllExportCheckbox.checked = false;
            selectAllExportCheckbox.indeterminate = false;
            selectAllExportCheckbox.disabled = true;
            return;
        }

        selectAllExportCheckbox.disabled = false;

        const checkedCount = visibleCheckboxes.filter(function(checkbox) {
            return checkbox.checked;
        }).length;

        if (checkedCount === 0) {
            selectAllExportCheckbox.checked = false;
            selectAllExportCheckbox.indeterminate = false;
        } else if (checkedCount === visibleCheckboxes.length) {
            selectAllExportCheckbox.checked = true;
            selectAllExportCheckbox.indeterminate = false;
        } else {
            selectAllExportCheckbox.checked = false;
            selectAllExportCheckbox.indeterminate = true;
        }

        updatePatternSelectionStatus();
    }

    function filterPatternItems(query) {
        if (!patternItems.length) {
            setPatternSelectionBusy(true);
            updateSelectAllExportCheckbox();
            return;
        }

        setPatternSelectionBusy(true);
        const normalizedQuery = query.trim().toLowerCase();

        patternItems.forEach(function(item) {
            const label = item.getAttribute('data-label') || '';
            const excerpt = item.getAttribute('data-excerpt') || '';
            const terms = item.getAttribute('data-terms') || '';
            const date = item.getAttribute('data-date') || '';
            const haystack = [label, excerpt, terms, date]
                .join(' ')
                .toLowerCase();
            const isMatch = normalizedQuery === '' || haystack.indexOf(normalizedQuery) !== -1;

            if (isMatch) {
                item.classList.remove('is-hidden');
            } else {
                item.classList.add('is-hidden');
            }
        });

        updateSelectAllExportCheckbox();
    }

    if (patternSelectionStatus) {
        setPatternSelectionBusy(false);
    }

    if (selectAllExportCheckbox) {
        selectAllExportCheckbox.addEventListener('change', function(e) {
            const shouldCheck = e.target.checked;
            getVisiblePatternItems().forEach(function(item) {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = shouldCheck;
                }
            });

            updateSelectAllExportCheckbox();
        });
    }

    if (patternItems.length) {
        patternItems.forEach(function(item) {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.addEventListener('change', updateSelectAllExportCheckbox);
            }
        });

        updateSelectAllExportCheckbox();
    }

    if (patternSearchInput) {
        const searchHandler = function(event) {
            filterPatternItems(event.target.value || '');
        };

        patternSearchInput.addEventListener('input', searchHandler);
        patternSearchInput.addEventListener('keyup', searchHandler);
    }

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
