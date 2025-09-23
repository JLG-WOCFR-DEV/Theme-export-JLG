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
    const patternList = document.querySelector('.pattern-selection-items');
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

});