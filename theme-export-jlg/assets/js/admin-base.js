(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var chipContainers = document.querySelectorAll('[data-tejlg-recipient-chips]');

        if (!chipContainers.length) {
            return;
        }

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
    });
})();
