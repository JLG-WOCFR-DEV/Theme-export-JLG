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


        if (quickActionContainers.length) {
            var quickActionsStorageKey = 'tejlg:admin:quick-actions:dismissed';

            var readDismissedPreference = function() {
                try {
                    var value = window.localStorage.getItem(quickActionsStorageKey);

                    return value === '1';
                } catch (error) {
                    return false;
                }
            };

            var persistDismissedPreference = function(state) {
                try {
                    window.localStorage.setItem(quickActionsStorageKey, state ? '1' : '0');
                } catch (error) {
                    // Ignore storage errors to keep the interface responsive.
                }
            };

            var isElementFocusable = function(element) {
                if (!element || typeof element.focus !== 'function') {
                    return false;
                }

                if (element.hasAttribute('disabled')) {
                    return false;
                }

                if (element.hasAttribute('hidden')) {
                    return false;
                }

                var ariaHidden = element.getAttribute('aria-hidden');

                if (ariaHidden && ariaHidden.toLowerCase() === 'true') {
                    return false;
                }

                if (element.offsetParent === null && window.getComputedStyle(element).position !== 'fixed') {
                    return false;
                }

                return true;
            };

            var focusElement = function(element) {
                if (!element || typeof element.focus !== 'function') {
                    return;
                }

                try {
                    element.focus({ preventScroll: true });
                } catch (error) {
                    element.focus();
                }
            };

            quickActionContainers.forEach(function(container) {
                var toggle = container.querySelector('[data-quick-actions-toggle]');
                var menu = container.querySelector('[data-quick-actions-menu]');
                var dismiss = container.querySelector('[data-quick-actions-dismiss]');
                var restore = container.querySelector('[data-quick-actions-restore]');
                var actionSelector = '[data-quick-actions-link]';

                if (!toggle || !menu) {
                    return;
                }

                var isOpen = false;
                var isDismissed = false;

                if (!menu.hasAttribute('aria-hidden')) {
                    menu.setAttribute('aria-hidden', 'true');
                }

                var getFocusableElements = function() {
                    var focusable = Array.prototype.slice.call(
                        container.querySelectorAll('[data-quick-actions-toggle], ' + actionSelector + ', [data-quick-actions-dismiss]')
                    );

                    return focusable.filter(isElementFocusable);
                };

                var getActionElements = function() {
                    return Array.prototype.slice.call(container.querySelectorAll(actionSelector)).filter(isElementFocusable);
                };

                var focusFirstAction = function() {
                    var actions = getActionElements();

                    if (!actions.length) {
                        return;
                    }

                    focusElement(actions[0]);
                };

                var setOpenState = function(state, options) {
                    var nextState = !!state;
                    var settings = options || {};

                    if (isDismissed && nextState) {
                        // Automatically restore the panel if the user toggles it while hidden.
                        setDismissedState(false, { persist: false, focusToggle: false, focusRestore: false, force: true });
                    }

                    if (isOpen === nextState) {
                        return;
                    }

                    isOpen = nextState;
                    container.classList.toggle('is-open', nextState);
                    toggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');
                    menu.setAttribute('aria-hidden', nextState ? 'false' : 'true');

                    if (nextState) {
                        menu.removeAttribute('hidden');

                        if (settings.focusFirst !== false && document.activeElement === toggle) {
                            focusFirstAction();
                        }
                    } else {
                        if (!menu.hasAttribute('hidden')) {
                            menu.setAttribute('hidden', '');
                        }

                        if (settings.focusToggle !== false) {
                            focusElement(toggle);
                        }
                    }
                };

                var setDismissedState = function(state, options) {
                    var nextState = !!state;
                    var settings = options || {};

                    if (!settings.force && isDismissed === nextState) {
                        if (!settings.persist) {
                            return;
                        }
                    }

                    isDismissed = nextState;
                    container.setAttribute('data-dismissed', nextState ? 'true' : 'false');

                    if (nextState) {
                        setOpenState(false, { focusToggle: false, focusFirst: false });

                        if (restore) {
                            restore.hidden = false;

                            if (settings.focusRestore !== false) {
                                focusElement(restore);
                            }
                        }
                    } else {
                        if (restore) {
                            restore.hidden = true;
                        }

                        if (settings.focusToggle) {
                            focusElement(toggle);
                        }
                    }

                    if (settings.persist !== false) {
                        persistDismissedPreference(nextState);
                    }
                };

                var handleKeyDown = function(event) {
                    if (!isOpen) {
                        if (event.key === 'Enter' && event.target === restore) {
                            event.preventDefault();
                            setDismissedState(false, { focusToggle: true });
                        }

                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        setOpenState(false);
                        return;
                    }

                    if (event.key !== 'Tab') {
                        return;
                    }

                    var focusable = getFocusableElements();

                    if (!focusable.length) {
                        return;
                    }

                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    var active = document.activeElement;

                    if (event.shiftKey) {
                        if (active === first) {
                            event.preventDefault();
                            focusElement(last);
                        }

                        return;
                    }

                    if (active === last) {
                        event.preventDefault();
                        focusElement(first);
                    }
                };

                var handleDocumentPointer = function(event) {
                    if (!isOpen) {
                        return;
                    }

                    if (container.contains(event.target)) {
                        return;
                    }

                    setOpenState(false, { focusToggle: false });
                };

                var initialDismissed = readDismissedPreference();

                setDismissedState(initialDismissed, {
                    persist: false,
                    focusRestore: false,
                    focusToggle: false,
                    force: true,
                });

                container.addEventListener('keydown', handleKeyDown);

                toggle.addEventListener('click', function() {
                    setOpenState(!isOpen, { focusFirst: true });
                });

                if (dismiss) {
                    dismiss.addEventListener('click', function(event) {
                        event.preventDefault();
                        setDismissedState(true, { focusRestore: true });
                    });
                }

                if (restore) {
                    restore.addEventListener('click', function(event) {
                        event.preventDefault();
                        setDismissedState(false, { focusToggle: true });
                    });
                }

                document.addEventListener('mousedown', handleDocumentPointer);
                document.addEventListener('touchstart', handleDocumentPointer);
            });
        }
    });
})();
