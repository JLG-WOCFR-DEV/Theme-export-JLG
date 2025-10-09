(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const localization = (typeof window.tejlgAdminImportL10n === 'object' && window.tejlgAdminImportL10n !== null)
            ? window.tejlgAdminImportL10n
            : {};

        const dropzones = document.querySelectorAll('[data-tejlg-dropzone]');

        if (dropzones.length) {
            const preventDefaults = function(event) {
                event.preventDefault();
                event.stopPropagation();
            };

            const buildFileList = function(files) {
                if (!files || typeof DataTransfer === 'undefined') {
                    return files;
                }

                const dataTransfer = new DataTransfer();

                Array.prototype.forEach.call(files, function(file) {
                    dataTransfer.items.add(file);
                });

                return dataTransfer.files;
            };

            dropzones.forEach(function(dropzone) {
                const fileInput = dropzone.querySelector('input[type="file"]');

                if (!fileInput) {
                    return;
                }

                const setDragState = function(isActive) {
                    if (isActive) {
                        dropzone.classList.add('is-dragover');
                        dropzone.setAttribute('data-tejlg-dropzone-state', 'dragover');
                    } else {
                        dropzone.classList.remove('is-dragover');
                        dropzone.setAttribute('data-tejlg-dropzone-state', 'idle');
                    }
                };

                setDragState(false);

                ['dragenter', 'dragover'].forEach(function(eventName) {
                    dropzone.addEventListener(eventName, function(event) {
                        preventDefaults(event);

                        if (event.dataTransfer) {
                            try {
                                event.dataTransfer.dropEffect = 'copy';
                            } catch (error) {
                                // Certains navigateurs empêchent l'écriture directe du dropEffect.
                            }
                        }

                        setDragState(true);
                    });
                });

                dropzone.addEventListener('dragleave', function(event) {
                    preventDefaults(event);

                    if (event.relatedTarget && dropzone.contains(event.relatedTarget)) {
                        return;
                    }

                    setDragState(false);
                });

                dropzone.addEventListener('drop', function(event) {
                    preventDefaults(event);
                    setDragState(false);

                    if (!event.dataTransfer || !event.dataTransfer.files || !event.dataTransfer.files.length) {
                        return;
                    }

                    const files = buildFileList(event.dataTransfer.files);

                    try {
                        fileInput.files = files;
                    } catch (error) {
                        // Certains environnements empêchent l'assignation programmée.
                    }

                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));

                    if (typeof dropzone.focus === 'function') {
                        try {
                            dropzone.focus({ preventScroll: true });
                        } catch (error) {
                            dropzone.focus();
                        }
                    }
                });

                dropzone.addEventListener('dragend', function() {
                    setDragState(false);
                });

                dropzone.addEventListener('click', function(event) {
                    if (event.target === fileInput) {
                        return;
                    }

                    fileInput.click();
                });

                dropzone.addEventListener('keydown', function(event) {
                    if (event.target === fileInput) {
                        return;
                    }

                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        fileInput.click();
                    }
                });
            });
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
    });
})();
