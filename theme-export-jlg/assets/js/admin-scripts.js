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
                const codeView = patternItem.querySelector('.pattern-code-view');

                if (codeView) {
                    const isVisible = codeView.style.display !== 'none';
                    if (isVisible) {
                        codeView.style.display = 'none';
                        if (showBlockCodeText) {
                            button.textContent = showBlockCodeText;
                        }
                    } else {
                        codeView.style.display = 'block';
                        if (hideBlockCodeText) {
                            button.textContent = hideBlockCodeText;
                        }
                    }
                }
            }
        });
    }

    const previewWrappers = document.querySelectorAll('.pattern-preview-wrapper');
    if (previewWrappers.length && typeof URL === 'object' && typeof URL.createObjectURL === 'function') {
        const blobUrls = [];

        previewWrappers.forEach(function(wrapper) {
            const dataElement = wrapper.querySelector('.pattern-preview-data');
            const iframe = wrapper.querySelector('.pattern-preview-iframe');

            if (!dataElement || !iframe) {
                return;
            }

            let htmlContent = '';
            try {
                htmlContent = JSON.parse(dataElement.textContent || '""');
            } catch (error) {
                htmlContent = '';
            }

            if (typeof htmlContent !== 'string' || htmlContent === '') {
                return;
            }

            const blob = new Blob([htmlContent], { type: 'text/html' });
            const blobUrl = URL.createObjectURL(blob);
            iframe.src = blobUrl;
            blobUrls.push(blobUrl);
        });

        window.addEventListener('beforeunload', function() {
            blobUrls.forEach(function(blobUrl) {
                URL.revokeObjectURL(blobUrl);
            });
        });
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
            animationFrameId = window.requestAnimationFrame(onAnimationFrame);
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
                window.cancelAnimationFrame(animationFrameId);
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
        animationFrameId = window.requestAnimationFrame(onAnimationFrame);
        window.addEventListener('beforeunload', stopMonitoring, { once: true });
    }

});
