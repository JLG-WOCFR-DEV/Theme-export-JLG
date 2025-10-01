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
            const spinner = exportForm.querySelector('[data-export-spinner]');
            const strings = typeof exportAsync.strings === 'object' ? exportAsync.strings : {};
            const pollInterval = typeof exportAsync.pollInterval === 'number' ? exportAsync.pollInterval : 4000;
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
    if (previewList) {
        previewList.addEventListener('click', function(e) {
            if (e.target.classList.contains('toggle-code-view')) {
                const button = e.target;
                const patternItem = button.closest('.pattern-item');
                const codeView = patternItem ? patternItem.querySelector('.pattern-code-view') : null;

                if (codeView) {
                    const isHidden = codeView.hasAttribute('hidden');

                    if (isHidden) {
                        codeView.hidden = false;
                        button.setAttribute('aria-expanded', 'true');
                        if (hideBlockCodeText) {
                            button.textContent = hideBlockCodeText;
                        }
                    } else {
                        codeView.hidden = true;
                        button.setAttribute('aria-expanded', 'false');
                        if (showBlockCodeText) {
                            button.textContent = showBlockCodeText;
                        }
                    }
                }
            }
        });
    }

    const previewWrappers = document.querySelectorAll('.pattern-preview-wrapper');
    if (previewWrappers.length) {
        const blobSupported = typeof URL !== 'undefined' && URL !== null && typeof URL.createObjectURL === 'function';
        const blobUrls = [];

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

            let previewLoaded = false;

            if (blobSupported) {
                try {
                    const blob = new Blob([htmlContent], { type: 'text/html' });
                    const blobUrl = URL.createObjectURL(blob);
                    iframe.src = blobUrl;
                    blobUrls.push(blobUrl);
                    previewLoaded = true;
                    hidePreviewMessage(messageElement);
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
                        } catch (error) {
                            fallbackLoaded = false;
                        }
                    }
                }

                showPreviewMessage(messageElement, previewFallbackWarning);
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

    function getVisiblePatternItems() {
        return patternItems.filter(function(item) {
            return !item.classList.contains('is-hidden');
        });
    }

    function updateSelectAllExportCheckbox() {
        if (!selectAllExportCheckbox) {
            return;
        }

        const visibleItems = getVisiblePatternItems();
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
    }

    function filterPatternItems(query) {
        if (!patternItems.length) {
            updateSelectAllExportCheckbox();
            return;
        }

        const normalizedQuery = query.trim().toLowerCase();

        patternItems.forEach(function(item) {
            const label = item.getAttribute('data-label') || '';
            const isMatch = label.toLowerCase().indexOf(normalizedQuery) !== -1;

            if (isMatch || normalizedQuery === '') {
                item.classList.remove('is-hidden');
            } else {
                item.classList.add('is-hidden');
            }
        });

        updateSelectAllExportCheckbox();
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
