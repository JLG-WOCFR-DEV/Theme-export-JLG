(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const localization = (typeof window.tejlgAdminDebugL10n === 'object' && window.tejlgAdminDebugL10n !== null)
            ? window.tejlgAdminDebugL10n
            : {};

        (function initializeDebugAccordion() {
            const accordionContainer = document.getElementById('debug-accordion');

            if (!accordionContainer) {
                return;
            }

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
        })();

        (function initializeMetricsBadge() {
            const fpsElement = document.getElementById('tejlg-metric-fps');
            const latencyElement = document.getElementById('tejlg-metric-latency');

            if (!fpsElement || !latencyElement) {
                return;
            }

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

                let total = 0;
                for (let i = 0; i < samples.length; i += 1) {
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

                const digits = Math.max(0, fallbackDigits);
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

            const scheduleNextFrame = function() {
                animationFrameId = scheduleFrame(onAnimationFrame);
            };

            const onAnimationFrame = function(timestamp) {
                if (!monitoringActive) {
                    return;
                }

                if (typeof lastFrameTime === 'number') {
                    let frameDelta = timestamp - lastFrameTime;

                    if (frameDelta > 0 && frameDelta < maxFrameGap) {
                        pushSample(fpsSamples, 1000 / frameDelta);
                        let latencyValue = frameDelta - idealFrameDuration;
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

            const setupPerformanceObserver = function() {
                if (typeof window.PerformanceObserver !== 'function') {
                    return;
                }

                const supportedEntryTypes = Array.isArray(window.PerformanceObserver.supportedEntryTypes)
                    ? window.PerformanceObserver.supportedEntryTypes
                    : [];

                let observerType = '';

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
                        const entries = list.getEntries ? list.getEntries() : [];

                        for (let i = 0; i < entries.length; i += 1) {
                            const entry = entries[i];
                            if (entry && typeof entry.duration === 'number') {
                                pushSample(latencySamplesFromObserver, entry.duration);
                            }
                        }

                        updateDisplay();
                    });

                    performanceObserverInstance.observe({
                        type: observerType,
                        buffered: true,
                    });
                } catch (error) {
                    performanceObserverInstance = null;
                }
            };

            const pauseMonitoring = function() {
                if (!monitoringActive) {
                    return;
                }

                monitoringActive = false;

                if (animationFrameId !== null) {
                    cancelFrame(animationFrameId);
                    animationFrameId = null;
                }

                if (performanceObserverInstance && typeof performanceObserverInstance.disconnect === 'function') {
                    performanceObserverInstance.disconnect();
                    performanceObserverInstance = null;
                }
            };

            const resumeMonitoring = function() {
                if (monitoringActive) {
                    return;
                }

                monitoringActive = true;
                lastFrameTime = undefined;

                updateDisplay();
                setupPerformanceObserver();

                if (animationFrameId === null) {
                    scheduleNextFrame();
                }
            };

            const handleVisibilityChange = function() {
                if (document.hidden) {
                    pauseMonitoring();
                } else {
                    resumeMonitoring();
                }
            };

            document.addEventListener('visibilitychange', handleVisibilityChange, { passive: true });

            updateDisplay();
            setupPerformanceObserver();
            scheduleNextFrame();
        })();
    });
})();
