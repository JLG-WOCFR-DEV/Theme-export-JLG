(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var baseSettings = (typeof window.tejlgAdminBaseSettings === 'object' && window.tejlgAdminBaseSettings !== null)
            ? window.tejlgAdminBaseSettings
            : {};

        var compactConfig = baseSettings.compactMode || {};
        var compactToggle = document.querySelector('[data-tejlg-compact-toggle]');

        var applyCompactMode = function(enabled) {
            if (!document.body || !document.body.classList) {
                return;
            }

            document.body.classList.toggle('tejlg-compact-mode', !!enabled);
        };

        applyCompactMode(compactConfig.enabled);

        if (compactToggle) {
            var initialState = !!compactConfig.enabled;

            if (compactToggle.checked !== initialState) {
                compactToggle.checked = initialState;
            }

            compactToggle.addEventListener('change', function() {
                var nextState = !!compactToggle.checked;

                applyCompactMode(nextState);

                if (!compactConfig.ajaxUrl || !compactConfig.nonce || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
                    return;
                }

                var payload = new window.FormData();
                payload.append('action', 'tejlg_toggle_compact_mode');
                payload.append('_ajax_nonce', compactConfig.nonce);
                payload.append('state', nextState ? '1' : '0');

                window.fetch(compactConfig.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: payload,
                }).then(function(response) {
                    if (!response || !response.ok) {
                        throw new Error('Request failed');
                    }

                    return response.json();
                }).then(function(data) {
                    if (!data || data.success !== true) {
                        throw new Error('Unexpected response');
                    }
                }).catch(function(error) {
                    if (compactConfig.messages && compactConfig.messages.error) {
                        window.console.error(compactConfig.messages.error, error);
                    }
                });
            });
        }

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

        if (persistableDetails.length) {
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
        }

        var accordionContainers = document.querySelectorAll('[data-tejlg-mobile-accordion]');

        if (accordionContainers.length) {
            var collapseSiblings = function(currentTrigger) {
                if (!currentTrigger) {
                    return;
                }

                var container = currentTrigger.closest('[data-tejlg-mobile-accordion]');

                if (!container) {
                    return;
                }

                var triggers = container.querySelectorAll('[data-tejlg-accordion-trigger]');

                triggers.forEach(function(trigger) {
                    if (trigger === currentTrigger) {
                        return;
                    }

                    trigger.setAttribute('aria-expanded', 'false');

                    var panelId = trigger.getAttribute('aria-controls');

                    if (!panelId) {
                        return;
                    }

                    var panel = document.getElementById(panelId);

                    if (panel) {
                        panel.hidden = true;
                    }
                });
            };

            var syncPanels = function(isMobile) {
                accordionContainers.forEach(function(container) {
                    var triggers = container.querySelectorAll('[data-tejlg-accordion-trigger]');

                    triggers.forEach(function(trigger) {
                        var panelId = trigger.getAttribute('aria-controls');

                        if (!panelId) {
                            return;
                        }

                        var panel = document.getElementById(panelId);

                        if (!panel) {
                            return;
                        }

                        if (!isMobile) {
                            panel.hidden = true;
                        } else {
                            var expanded = trigger.getAttribute('aria-expanded') === 'true';
                            panel.hidden = !expanded;
                        }
                    });
                });
            };

            accordionContainers.forEach(function(container) {
                var triggers = container.querySelectorAll('[data-tejlg-accordion-trigger]');

                triggers.forEach(function(trigger) {
                    trigger.addEventListener('click', function() {
                        var panelId = trigger.getAttribute('aria-controls');

                        if (!panelId) {
                            return;
                        }

                        var panel = document.getElementById(panelId);

                        if (!panel) {
                            return;
                        }

                        var expanded = trigger.getAttribute('aria-expanded') === 'true';
                        var nextState = !expanded;

                        if (nextState) {
                            collapseSiblings(trigger);
                        }

                        trigger.setAttribute('aria-expanded', nextState ? 'true' : 'false');
                        panel.hidden = !nextState;

                        if (nextState) {
                            var focusTarget = panel.querySelector('[data-tejlg-accordion-link]');

                            if (focusTarget && typeof focusTarget.focus === 'function') {
                                focusTarget.focus({ preventScroll: true });
                            }
                        }
                    });
                });
            });

            var mediaQuery = typeof window.matchMedia === 'function'
                ? window.matchMedia('(max-width: 782px)')
                : null;

            if (mediaQuery) {
                var handleChange = function(event) {
                    syncPanels(!!event.matches);
                };

                if (typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', handleChange);
                } else if (typeof mediaQuery.addListener === 'function') {
                    mediaQuery.addListener(handleChange);
                }

                syncPanels(mediaQuery.matches);
            } else {
                syncPanels(true);
            }
        }


    });
})();
