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

    const exportConfig = (typeof window.tejlgThemeExportData === 'object' && window.tejlgThemeExportData !== null)
        ? window.tejlgThemeExportData
        : null;

    const exportForm = document.getElementById('tejlg-theme-export-form');
    const exportStatusWrapper = document.getElementById('tejlg-theme-export-status');
    const exportMessage = exportStatusWrapper ? exportStatusWrapper.querySelector('.tejlg-theme-export-message') : null;
    const exportCount = exportStatusWrapper ? exportStatusWrapper.querySelector('.tejlg-theme-export-count') : null;
    const exportProgress = exportStatusWrapper ? exportStatusWrapper.querySelector('.tejlg-theme-export-progress') : null;
    const exportProgressBar = exportStatusWrapper ? exportStatusWrapper.querySelector('.tejlg-theme-export-progress-bar') : null;
    const downloadWrapper = exportStatusWrapper ? exportStatusWrapper.querySelector('.tejlg-theme-export-download') : null;
    const downloadLink = document.getElementById('tejlg-theme-export-download');
    const submitButton = document.getElementById('tejlg-theme-export-submit');

    let currentJobId = null;
    let pollTimer = null;

    function setSubmitDisabled(isDisabled) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = !!isDisabled;

        if (isDisabled) {
            submitButton.classList.add('disabled');
        } else {
            submitButton.classList.remove('disabled');
        }
    }

    function resetExportStatus() {
        if (!exportStatusWrapper) {
            return;
        }

        exportStatusWrapper.hidden = true;

        if (exportMessage) {
            exportMessage.textContent = '';
        }

        if (exportCount) {
            exportCount.textContent = '';
        }

        if (exportProgress) {
            exportProgress.setAttribute('aria-valuenow', '0');
        }

        if (exportProgressBar) {
            exportProgressBar.style.width = '0%';
        }

        if (downloadWrapper) {
            downloadWrapper.hidden = true;
        }

        if (downloadLink) {
            downloadLink.removeAttribute('href');
        }
    }

    function updateExportStatus(data) {
        if (!exportStatusWrapper) {
            return;
        }

        exportStatusWrapper.hidden = false;

        const status = typeof data.status === 'string' ? data.status : 'queued';
        const total = typeof data.total === 'number' ? data.total : 0;
        const processed = typeof data.processed === 'number' ? data.processed : 0;
        const messages = exportConfig && exportConfig.messages ? exportConfig.messages : {};

        let messageText = '';

        if (typeof data.message === 'string' && data.message.trim() !== '') {
            messageText = data.message;
        } else if (status && typeof messages[status] === 'string') {
            messageText = messages[status];
        }

        if (exportMessage) {
            exportMessage.textContent = messageText;
        }

        if (exportCount) {
            if (total > 0) {
                exportCount.textContent = processed + ' / ' + total;
            } else {
                exportCount.textContent = '';
            }
        }

        const percentage = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

        if (exportProgress) {
            exportProgress.setAttribute('aria-valuenow', String(percentage));
        }

        if (exportProgressBar) {
            exportProgressBar.style.width = percentage + '%';
        }

        if (status === 'completed' && downloadWrapper && downloadLink && typeof data.downloadUrl === 'string') {
            downloadLink.href = data.downloadUrl;
            downloadWrapper.hidden = false;
        }

        if (status === 'failed') {
            setSubmitDisabled(false);
        }
    }

    function scheduleNextPoll() {
        if (!exportConfig) {
            return;
        }

        const interval = typeof exportConfig.pollInterval === 'number' ? exportConfig.pollInterval : 4000;

        pollTimer = window.setTimeout(pollExportStatus, Math.max(1000, interval));
    }

    function pollExportStatus() {
        if (!exportConfig || !currentJobId) {
            return;
        }

        const params = new URLSearchParams();
        params.append('action', 'tejlg_get_theme_export_status');
        params.append('job_id', currentJobId);
        if (exportConfig.statusNonce) {
            params.append('nonce', exportConfig.statusNonce);
        }

        fetch(exportConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: params,
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Export failed');
                }

                updateExportStatus(payload.data);

                if (payload.data && payload.data.status === 'completed') {
                    currentJobId = null;
                    pollTimer = null;
                    setSubmitDisabled(false);
                    return;
                }

                if (payload.data && payload.data.status === 'failed') {
                    currentJobId = null;
                    pollTimer = null;
                    return;
                }

                scheduleNextPoll();
            })
            .catch(function(error) {
                if (exportMessage) {
                    exportMessage.textContent = error && error.message ? error.message : 'Erreur inattendue.';
                }
                currentJobId = null;
                pollTimer = null;
                setSubmitDisabled(false);
            });
    }

    if (exportForm && exportConfig && exportConfig.ajaxUrl) {
        exportForm.addEventListener('submit', function(event) {
            event.preventDefault();

            if (!exportConfig.startNonce) {
                return;
            }

            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }

            setSubmitDisabled(true);
            resetExportStatus();

            if (exportStatusWrapper) {
                exportStatusWrapper.hidden = false;
            }

            if (exportMessage) {
                const messages = exportConfig.messages || {};
                exportMessage.textContent = messages.starting || '';
            }

            const formData = new FormData(exportForm);
            formData.append('action', 'tejlg_start_theme_export');
            formData.append('nonce', exportConfig.startNonce);

            fetch(exportConfig.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.jobId) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Export failed');
                    }

                    currentJobId = payload.data.jobId;
                    updateExportStatus({ status: 'queued', processed: 0, total: 0 });
                    pollExportStatus();
                })
                .catch(function(error) {
                    if (exportMessage) {
                        exportMessage.textContent = error && error.message ? error.message : 'Erreur inattendue.';
                    }
                    currentJobId = null;
                    pollTimer = null;
                    setSubmitDisabled(false);
                });
        });
    }

});
