(function() {
    'use strict';

    var settings = (typeof window.tejlgAssistantSettings === 'object' && window.tejlgAssistantSettings !== null)
        ? window.tejlgAssistantSettings
        : {};

    var storagePrefix = (typeof settings.storagePrefix === 'string' && settings.storagePrefix !== '')
        ? settings.storagePrefix
        : 'tejlg:assistant:';

    var hints = (settings && typeof settings.hints === 'object' && settings.hints !== null)
        ? settings.hints
        : {};

    var strings = (settings && typeof settings.strings === 'object' && settings.strings !== null)
        ? settings.strings
        : {};

    var assistantsConfig = (settings && typeof settings.assistants === 'object' && settings.assistants !== null)
        ? settings.assistants
        : {};

    var locale = '';

    if (typeof strings.locale === 'string' && strings.locale !== '') {
        locale = strings.locale;
    } else if (typeof settings.locale === 'string' && settings.locale !== '') {
        locale = settings.locale;
    }

    var numberFormatter;

    try {
        numberFormatter = locale ? new Intl.NumberFormat(locale) : new Intl.NumberFormat();
    } catch (error) {
        numberFormatter = new Intl.NumberFormat();
    }

    var formatNumber = function(value) {
        try {
            return numberFormatter.format(value);
        } catch (error) {
            return String(value);
        }
    };

    var getHintForStep = function(assistantId, stepKey) {
        if (!assistantId || !stepKey) {
            return '';
        }

        if (hints[assistantId] && typeof hints[assistantId][stepKey] === 'string') {
            return hints[assistantId][stepKey];
        }

        if (hints.default && typeof hints.default[stepKey] === 'string') {
            return hints.default[stepKey];
        }

        return '';
    };

    var parseJsonAttribute = function(element, attribute, fallback) {
        if (!element) {
            return fallback;
        }

        var raw = element.getAttribute(attribute);

        if (typeof raw !== 'string' || raw === '') {
            return fallback;
        }

        try {
            var parsed = JSON.parse(raw);
            return parsed;
        } catch (error) {
            return fallback;
        }
    };

    var formatSelectionCountText = function(count) {
        if (count === 0) {
            return typeof strings.selectionEmpty === 'string'
                ? strings.selectionEmpty
                : '';
        }

        var template = count === 1
            ? strings.selectionCountSingular
            : strings.selectionCountPlural;

        if (typeof template !== 'string' || template === '') {
            template = '%s';
        }

        return template.replace('%s', formatNumber(count));
    };

    var formatPreviewLimitHint = function(limit) {
        if (typeof strings.previewLimit === 'string' && strings.previewLimit !== '') {
            return strings.previewLimit.replace('%1$d', formatNumber(limit));
        }

        return '';
    };

    var getSelectedOptionLabel = function(select) {
        if (!select) {
            return '';
        }

        var option = select.options[select.selectedIndex];

        if (!option) {
            return '';
        }

        return option.textContent ? option.textContent.trim() : '';
    };

    var createSelectionController = function(assistant, assistantId) {
        var selectionList = assistant.querySelector('[data-assistant-selection-list]');

        if (!selectionList) {
            return null;
        }

        var countDisplays = assistant.querySelectorAll('[data-assistant-selection-count]');
        var previewList = assistant.querySelector('[data-assistant-selection-preview]');
        var previewHint = assistant.querySelector('[data-assistant-preview-hint]');
        var summaryCount = assistant.querySelector('[data-assistant-summary-count]');
        var summaryFilters = assistant.querySelector('[data-assistant-summary-filters]');
        var summaryWarnings = assistant.querySelector('[data-assistant-summary-warnings]');
        var searchInput = assistant.querySelector('#tejlg-import-pattern-search');
        var categorySelect = assistant.querySelector('#tejlg-import-filter-category');
        var dateSelect = assistant.querySelector('#tejlg-import-filter-date');
        var sortSelect = assistant.querySelector('#tejlg-import-sort');
        var selectionButtons = assistant.querySelectorAll('[data-assistant-requires-selection]');
        var warningsContainers = assistant.querySelectorAll('[data-assistant-warning]');
        var encodingContainers = assistant.querySelectorAll('[data-assistant-encoding]');
        var previewLimit = 5;
        var previewEmpty = '';

        if (previewList) {
            var limitAttr = previewList.getAttribute('data-assistant-preview-limit');
            var parsedLimit = parseInt(limitAttr, 10);

            if (!isNaN(parsedLimit) && parsedLimit > 0) {
                previewLimit = parsedLimit;
            }

            previewEmpty = previewList.getAttribute('data-assistant-preview-empty') || '';
        }

        var summaryCountEmpty = summaryCount
            ? (summaryCount.getAttribute('data-assistant-summary-empty') || '')
            : '';

        var summaryFiltersEmpty = summaryFilters
            ? (summaryFilters.getAttribute('data-assistant-summary-empty') || (strings.filtersEmpty || ''))
            : (strings.filtersEmpty || '');

        var summaryWarningsEmpty = summaryWarnings
            ? (summaryWarnings.getAttribute('data-assistant-summary-empty') || (strings.warningsEmpty || ''))
            : (strings.warningsEmpty || '');

        var assistantConfig = (assistantsConfig[assistantId] && typeof assistantsConfig[assistantId] === 'object')
            ? assistantsConfig[assistantId]
            : {};

        var gatherSelectedItems = function() {
            var items = [];
            var candidates = selectionList.querySelectorAll('[data-assistant-selectable]');

            Array.prototype.forEach.call(candidates, function(candidate) {
                var checkbox = candidate.querySelector('input[type="checkbox"]');

                if (!checkbox || !checkbox.checked) {
                    return;
                }

                var indexAttr = candidate.getAttribute('data-original-index');
                var parsedIndex = parseInt(indexAttr, 10);

                if (isNaN(parsedIndex)) {
                    parsedIndex = null;
                }

                var timestampAttr = candidate.getAttribute('data-timestamp');
                var parsedTimestamp = null;

                if (timestampAttr !== null && timestampAttr !== '') {
                    var numericTimestamp = parseInt(timestampAttr, 10);

                    if (!isNaN(numericTimestamp)) {
                        parsedTimestamp = numericTimestamp;
                    }
                }

                var categories = parseJsonAttribute(candidate, 'data-category-labels', []);

                if (!Array.isArray(categories)) {
                    categories = [];
                }

                items.push({
                    index: parsedIndex,
                    originalIndex: parsedIndex,
                    title: candidate.getAttribute('data-title') || '',
                    date: candidate.getAttribute('data-date-display') || '',
                    period: candidate.getAttribute('data-period-label') || '',
                    periodValue: candidate.getAttribute('data-period') || '',
                    timestamp: parsedTimestamp,
                    categories: categories
                });
            });

            return items;
        };

        var updateCountDisplays = function(count) {
            var text = formatSelectionCountText(count);

            Array.prototype.forEach.call(countDisplays, function(display) {
                display.textContent = text;
            });

            if (summaryCount) {
                summaryCount.textContent = count > 0 ? text : summaryCountEmpty;
            }
        };

        var updatePreviewList = function(items) {
            if (!previewList) {
                return;
            }

            previewList.textContent = '';

            if (!items.length) {
                if (previewEmpty) {
                    var emptyItem = document.createElement('li');
                    emptyItem.textContent = previewEmpty;
                    previewList.appendChild(emptyItem);
                }

                if (previewHint) {
                    previewHint.hidden = true;
                    previewHint.textContent = '';
                }

                return;
            }

            var limit = Math.max(1, previewLimit);

            items.slice(0, limit).forEach(function(item) {
                var entry = document.createElement('li');
                var title = document.createElement('strong');
                title.textContent = item.title || strings.untitled || '';
                entry.appendChild(title);

                var metaParts = [];

                if (item.date) {
                    metaParts.push(item.date);
                }

                if (item.period) {
                    metaParts.push(item.period);
                }

                if (Array.isArray(item.categories) && item.categories.length) {
                    metaParts.push(item.categories.join(', '));
                }

                if (metaParts.length) {
                    var meta = document.createElement('span');
                    meta.textContent = ' — ' + metaParts.join(' · ');
                    entry.appendChild(meta);
                }

                previewList.appendChild(entry);
            });

            if (previewHint) {
                if (items.length > limit) {
                    previewHint.hidden = false;
                    previewHint.textContent = formatPreviewLimitHint(limit);
                } else {
                    previewHint.hidden = true;
                    previewHint.textContent = '';
                }
            }
        };

        var gatherFilterLabels = function() {
            var labels = [];
            var searchValue = searchInput ? searchInput.value.trim() : '';

            if (searchValue !== '') {
                if (typeof strings.filterSearch === 'string' && strings.filterSearch !== '') {
                    labels.push(strings.filterSearch.replace('%s', searchValue));
                } else {
                    labels.push(searchValue);
                }
            }

            if (categorySelect && categorySelect.value !== '') {
                var categoryLabel = getSelectedOptionLabel(categorySelect);

                if (categoryLabel !== '') {
                    if (typeof strings.filterCategory === 'string' && strings.filterCategory !== '') {
                        labels.push(strings.filterCategory.replace('%s', categoryLabel));
                    } else {
                        labels.push(categoryLabel);
                    }
                }
            }

            if (dateSelect && dateSelect.value !== '') {
                var dateLabel = getSelectedOptionLabel(dateSelect);

                if (dateLabel !== '') {
                    if (typeof strings.filterDate === 'string' && strings.filterDate !== '') {
                        labels.push(strings.filterDate.replace('%s', dateLabel));
                    } else {
                        labels.push(dateLabel);
                    }
                }
            }

            if (sortSelect) {
                var sortLabel = getSelectedOptionLabel(sortSelect);

                if (sortLabel !== '') {
                    if (typeof strings.filterSort === 'string' && strings.filterSort !== '') {
                        labels.push(strings.filterSort.replace('%s', sortLabel));
                    } else {
                        labels.push(sortLabel);
                    }
                }
            }

            return labels;
        };

        var updateFilterSummary = function() {
            if (!summaryFilters) {
                return;
            }

            var labels = gatherFilterLabels();

            if (labels.length) {
                summaryFilters.textContent = labels.join(' · ');
            } else {
                summaryFilters.textContent = summaryFiltersEmpty;
            }
        };

        var gatherWarnings = function() {
            var messages = [];

            Array.prototype.forEach.call(warningsContainers, function(container) {
                var paragraphs = container.querySelectorAll('p');

                Array.prototype.forEach.call(paragraphs, function(paragraph) {
                    var text = paragraph.textContent ? paragraph.textContent.trim() : '';

                    if (text) {
                        messages.push(text);
                    }
                });
            });

            Array.prototype.forEach.call(encodingContainers, function(container) {
                var paragraphs = container.querySelectorAll('p');

                Array.prototype.forEach.call(paragraphs, function(paragraph) {
                    var text = paragraph.textContent ? paragraph.textContent.trim() : '';

                    if (text) {
                        messages.push(text);
                    }
                });
            });

            return messages;
        };

        var updateWarningsSummary = function() {
            if (!summaryWarnings) {
                return;
            }

            var warnings = gatherWarnings();

            if (warnings.length) {
                summaryWarnings.textContent = warnings.join(' ');
            } else {
                summaryWarnings.textContent = summaryWarningsEmpty;
            }
        };

        var toggleSelectionButtons = function(hasSelection) {
            Array.prototype.forEach.call(selectionButtons, function(button) {
                if (!button) {
                    return;
                }

                button.disabled = !hasSelection;

                if (!hasSelection) {
                    button.setAttribute('aria-disabled', 'true');
                } else {
                    button.removeAttribute('aria-disabled');
                }
            });
        };

        var refresh = function() {
            var items = gatherSelectedItems();
            updateCountDisplays(items.length);
            updatePreviewList(items);
            updateFilterSummary();
            updateWarningsSummary();
            toggleSelectionButtons(items.length > 0);
            return items;
        };

        var getFilterDetails = function() {
            return {
                search: searchInput ? searchInput.value.trim() : '',
                category: categorySelect ? {
                    value: categorySelect.value || '',
                    label: getSelectedOptionLabel(categorySelect)
                } : { value: '', label: '' },
                date: dateSelect ? {
                    value: dateSelect.value || '',
                    label: getSelectedOptionLabel(dateSelect)
                } : { value: '', label: '' },
                sort: sortSelect ? {
                    value: sortSelect.value || '',
                    label: getSelectedOptionLabel(sortSelect)
                } : { value: '', label: '' },
                activeLabels: gatherFilterLabels()
            };
        };

        var getPayload = function() {
            var items = gatherSelectedItems();
            var encodingMessages = [];

            Array.prototype.forEach.call(encodingContainers, function(container) {
                var paragraphs = container.querySelectorAll('p');

                if (paragraphs.length) {
                    Array.prototype.forEach.call(paragraphs, function(paragraph) {
                        var text = paragraph.textContent ? paragraph.textContent.trim() : '';

                        if (text) {
                            encodingMessages.push(text);
                        }
                    });
                } else {
                    var text = container.textContent ? container.textContent.trim() : '';

                    if (text) {
                        encodingMessages.push(text);
                    }
                }
            });

            return {
                assistant: assistantId || '',
                generatedAt: new Date().toISOString(),
                transientId: assistant.getAttribute('data-assistant-transient-id') || '',
                selection: {
                    count: items.length,
                    items: items
                },
                filters: getFilterDetails(),
                warnings: gatherWarnings(),
                encodingFailures: encodingMessages,
                meta: {
                    hasGlobalStyles: assistant.getAttribute('data-assistant-has-global-styles') === '1'
                }
            };
        };

        selectionList.addEventListener('change', function(event) {
            if (event && event.target && event.target.matches('input[type="checkbox"]')) {
                refresh();
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', refresh);
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', refresh);
        }

        if (dateSelect) {
            dateSelect.addEventListener('change', refresh);
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', refresh);
        }

        refresh();

        return {
            refresh: refresh,
            getPayload: getPayload
        };
    };

    var initializeAssistant = function(assistant) {
        var assistantId = assistant.getAttribute('data-assistant-id') || '';
        var steps = assistant.querySelectorAll('[data-assistant-step]');

        if (!steps.length) {
            return;
        }

        var stepElements = Array.prototype.slice.call(steps);
        var stepKeys = stepElements.map(function(stepElement) {
            return stepElement.getAttribute('data-assistant-step') || '';
        });
        var stepIndexByKey = {};

        stepKeys.forEach(function(key, index) {
            if (key !== '' && typeof stepIndexByKey[key] === 'undefined') {
                stepIndexByKey[key] = index;
            }
        });

        var stepperItems = assistant.querySelectorAll('[data-assistant-stepper-item]');
        var hintRegion = assistant.querySelector('[data-assistant-hint]');
        var currentStepIndex = 0;
        var storageKey = assistant.getAttribute('data-assistant-storage-key') || '';

        if (!storageKey && assistantId) {
            storageKey = storagePrefix + assistantId;
        }

        var selectionController = createSelectionController(assistant, assistantId);
        var exclusionSource = assistant.querySelector('[data-assistant-exclusion-source]');
        var exclusionSummary = assistant.querySelector('[data-assistant-exclusion-summary]');
        var exclusionSummaryEmpty = exclusionSummary
            ? (exclusionSummary.getAttribute('data-assistant-summary-empty') || '')
            : '';

        var updateExclusionSummary = function() {
            if (!exclusionSummary) {
                return;
            }

            if (!exclusionSource) {
                exclusionSummary.textContent = exclusionSummaryEmpty;
                return;
            }

            var value = exclusionSource.value ? exclusionSource.value.trim() : '';

            if (value === '') {
                exclusionSummary.textContent = exclusionSummaryEmpty;
                return;
            }

            var tokens = value.split(/[\r\n,]+/).map(function(token) {
                return token.trim();
            }).filter(function(token) {
                return token.length > 0;
            });

            exclusionSummary.textContent = tokens.length
                ? tokens.join(', ')
                : exclusionSummaryEmpty;
        };

        var focusStepTitle = function(stepElement) {
            if (!stepElement) {
                return;
            }

            var title = stepElement.querySelector('.tejlg-step__title');

            if (!title || typeof title.focus !== 'function') {
                return;
            }

            window.requestAnimationFrame(function() {
                try {
                    title.focus({ preventScroll: true });
                } catch (error) {
                    title.focus();
                }
            });
        };

        var applyHint = function(stepKey) {
            if (!hintRegion) {
                return;
            }

            var message = getHintForStep(assistantId, stepKey);

            if (message) {
                hintRegion.hidden = false;
                hintRegion.textContent = message;
            } else {
                hintRegion.hidden = true;
                hintRegion.textContent = '';
            }
        };

        var readStoredStep = function() {
            if (!storageKey) {
                return null;
            }

            try {
                var stored = window.localStorage.getItem(storageKey);

                if (typeof stored === 'string' && stored !== '') {
                    return stored;
                }
            } catch (error) {
                return null;
            }

            return null;
        };

        var writeStoredStep = function(stepKey) {
            if (!storageKey) {
                return;
            }

            try {
                window.localStorage.setItem(storageKey, stepKey || '');
            } catch (error) {
                // Ignore storage errors.
            }
        };

        var clearStoredStep = function() {
            if (!storageKey) {
                return;
            }

            try {
                window.localStorage.removeItem(storageKey);
            } catch (error) {
                // Ignore storage errors.
            }
        };

        var updateStepperVisualState = function() {
            Array.prototype.forEach.call(stepperItems, function(item) {
                var targetKey = item.getAttribute('data-assistant-step-target');
                var targetIndex = typeof stepIndexByKey[targetKey] === 'number'
                    ? stepIndexByKey[targetKey]
                    : Array.prototype.indexOf.call(stepperItems, item);

                var isActive = targetIndex === currentStepIndex;
                var isComplete = targetIndex < currentStepIndex;

                item.classList.toggle('is-active', isActive);
                item.classList.toggle('is-complete', isComplete);

                if (isActive) {
                    item.setAttribute('aria-current', 'step');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        };

        var setStep = function(nextIndex, options) {
            var opts = options || {};
            var maxIndex = stepElements.length - 1;
            var clampedIndex = Math.max(0, Math.min(nextIndex, maxIndex));

            if (clampedIndex === currentStepIndex && !opts.force) {
                return;
            }

            currentStepIndex = clampedIndex;

            stepElements.forEach(function(stepElement, index) {
                var isActive = index === currentStepIndex;
                stepElement.classList.toggle('is-active', isActive);
                stepElement.hidden = !isActive;

                if (isActive) {
                    stepElement.removeAttribute('aria-hidden');
                } else {
                    stepElement.setAttribute('aria-hidden', 'true');
                }
            });

            updateStepperVisualState();

            var currentKey = stepKeys[currentStepIndex] || '';

            if (selectionController) {
                selectionController.refresh();
            }

            if (opts.persist !== false) {
                writeStoredStep(currentKey);
            }

            if (!opts.silent) {
                applyHint(currentKey);

                assistant.dispatchEvent(new CustomEvent('tejlg-assistant-stepchange', {
                    bubbles: true,
                    detail: {
                        stepKey: currentKey,
                        stepIndex: currentStepIndex
                    }
                }));
            }

            if (opts.focus !== false) {
                focusStepTitle(stepElements[currentStepIndex]);
            }
        };

        var nextButtons = assistant.querySelectorAll('[data-assistant-next]');
        var prevButtons = assistant.querySelectorAll('[data-assistant-prev]');

        Array.prototype.forEach.call(nextButtons, function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                setStep(currentStepIndex + 1);
            });
        });

        Array.prototype.forEach.call(prevButtons, function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                setStep(currentStepIndex - 1);
            });
        });

        Array.prototype.forEach.call(stepperItems, function(item) {
            item.addEventListener('click', function(event) {
                event.preventDefault();
                var targetKey = item.getAttribute('data-assistant-step-target');

                if (typeof stepIndexByKey[targetKey] === 'number') {
                    setStep(stepIndexByKey[targetKey]);
                }
            });
        });

        if (exclusionSource) {
            exclusionSource.addEventListener('input', updateExclusionSummary);
            exclusionSource.addEventListener('change', updateExclusionSummary);
        }

        updateExclusionSummary();

        var downloadButton = assistant.querySelector('[data-assistant-download-summary]');

        if (downloadButton && selectionController) {
            downloadButton.addEventListener('click', function(event) {
                event.preventDefault();
                var payload = selectionController.getPayload();

                if (!payload) {
                    return;
                }

                var json;

                try {
                    json = JSON.stringify(payload, null, 2);
                } catch (error) {
                    return;
                }

                var blob = new Blob([json], { type: 'application/json' });
                var url = window.URL.createObjectURL(blob);
                var link = document.createElement('a');
                var config = assistantsConfig[assistantId] || {};
                var fileTemplate = (config && typeof config.downloadFileName === 'string' && config.downloadFileName !== '')
                    ? config.downloadFileName
                    : (strings.downloadFileName || 'assistant-summary-%date%.json');
                var timestamp = new Date().toISOString().replace(/[:]/g, '-');
                var filename = fileTemplate.replace('%date%', timestamp);

                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                window.setTimeout(function() {
                    try {
                        window.URL.revokeObjectURL(url);
                    } catch (error) {
                        // Ignore revoke errors.
                    }
                }, 1000);
            });
        }

        var storedStepKey = readStoredStep();

        if (storedStepKey && typeof stepIndexByKey[storedStepKey] === 'number') {
            setStep(stepIndexByKey[storedStepKey], { force: true, focus: false, persist: false, silent: false });
        } else {
            setStep(0, { force: true, focus: false, persist: false, silent: false });
        }

        var form = assistant.closest('form');

        if (form) {
            form.addEventListener('submit', function() {
                clearStoredStep();
            });
        }
    };

    var runInitialization = function() {
        var root = document.documentElement;

        if (root && !root.classList.contains('js')) {
            root.classList.add('js');
        }

        var assistants = document.querySelectorAll('[data-assistant]');

        Array.prototype.forEach.call(assistants, function(assistant) {
            initializeAssistant(assistant);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runInitialization);
    } else {
        runInitialization();
    }
})();
