(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var chipContainers = document.querySelectorAll('[data-tejlg-recipient-chips]');

        if (chipContainers.length) {
            var parseRecipients = function(value) {
                if (typeof value !== 'string') {
                    return [];
                }

                return value
                    .split(/[\r\n,]+/)
                    .map(function(token) {
                        return token.trim();
                    })
                    .filter(function(token, index, array) {
                        return token.length > 0 && array.indexOf(token) === index;
                    });
            };

            var formatCount = function(target, count) {
                if (!target) {
                    return;
                }

                var singularTemplate = target.getAttribute('data-label-singular') || '%d';
                var pluralTemplate = target.getAttribute('data-label-plural') || '%d';
                var template = count === 1 ? singularTemplate : pluralTemplate;

                target.textContent = template.replace('%d', String(count));
            };

            chipContainers.forEach(function(container) {
                var inputId = container.getAttribute('data-recipient-input');
                var textarea = inputId ? document.getElementById(inputId) : null;

                if (!textarea) {
                    textarea = container.querySelector('textarea');
                }

                if (!textarea) {
                    return;
                }

                var list = container.querySelector('[data-chip-list]');
                var emptyState = container.querySelector('[data-chip-empty]');
                var count = container.querySelector('[data-chip-count]');

                var render = function() {
                    var recipients = parseRecipients(textarea.value || '');

                    if (list) {
                        list.innerHTML = '';

                        if (recipients.length) {
                            recipients.forEach(function(recipient) {
                                var chip = document.createElement('span');
                                chip.className = 'tejlg-chip';
                                chip.setAttribute('role', 'listitem');
                                chip.textContent = recipient;
                                list.appendChild(chip);
                            });
                        }
                    }

                    if (emptyState) {
                        if (recipients.length) {
                            emptyState.hidden = true;
                        } else {
                            emptyState.hidden = false;
                        }
                    }

                    formatCount(count, recipients.length);
                };

                textarea.addEventListener('input', render);
                textarea.addEventListener('change', render);
                render();
            });
        }

        var persistableDetails = document.querySelectorAll('details[data-tejlg-persist]');

        if (!persistableDetails.length) {
            return;
        }

        var storageKey = 'tejlg:details:state';

        var readState = function() {
            try {
                var raw = window.localStorage.getItem(storageKey);

                if (!raw) {
                    return {};
                }

                var parsed = JSON.parse(raw);

                if (parsed && typeof parsed === 'object') {
                    return parsed;
                }
            } catch (error) {
                // Ignore parsing/storage failures to avoid breaking the UI.
            }

            return {};
        };

        var writeState = function(state) {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (error) {
                // Ignore storage failures.
            }
        };

        var stateCache = readState();

        persistableDetails.forEach(function(details) {
            if (!details || details.nodeName !== 'DETAILS') {
                return;
            }

            var uniqueId = details.getAttribute('data-tejlg-persist-key') || details.id;

            if (!uniqueId) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(stateCache, uniqueId)) {
                var shouldBeOpen = !!stateCache[uniqueId];

                if (details.open !== shouldBeOpen) {
                    details.open = shouldBeOpen;
                }
            }

            details.addEventListener('toggle', function() {
                stateCache = stateCache || {};
                stateCache[uniqueId] = !!details.open;
                writeState(stateCache);
            });
        });
    });
})();
