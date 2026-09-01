@php
    $draftUser = auth()->user();
    $draftClientId = $draftUser && $draftUser->client ? $draftUser->client->id : null;
@endphp
@once
<script data-kimrx-tab-draft-script>
(function () {
    if (window.KimRxTabDrafts) {
        return;
    }

    var draftContext = @json([
        'userId' => optional($draftUser)->id,
        'clientId' => $draftClientId,
    ]);
    var storagePrefix = 'kimrx-tab-draft:v1:';

    function wait(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function storageKey(config, form) {
        return [
            storagePrefix,
            window.location.host,
            'user:' + (draftContext.userId || 'guest'),
            'client:' + (draftContext.clientId || 'none'),
            config.key || form.id || window.location.pathname,
        ].join(':');
    }

    function formFor(config) {
        if (config.formSelector) {
            var form = document.querySelector(config.formSelector);
            if (form instanceof HTMLFormElement) {
                return form;
            }
        }

        if (config.anchorSelector) {
            var anchor = document.querySelector(config.anchorSelector);
            var anchorForm = anchor ? anchor.closest('form') : null;
            if (anchorForm instanceof HTMLFormElement) {
                return anchorForm;
            }
        }

        return null;
    }

    function rowBodyFor(config) {
        return config.rowBodySelector ? document.querySelector(config.rowBodySelector) : null;
    }

    function rowsFor(config) {
        var body = rowBodyFor(config);
        if (!body || !config.rowSelector) {
            return [];
        }

        return Array.from(body.querySelectorAll(config.rowSelector));
    }

    function isDraftControl(field) {
        if (!(field instanceof HTMLElement) || field.disabled) {
            return false;
        }

        if (field.dataset.draftIgnore === 'true') {
            return false;
        }

        if (field.closest('template')) {
            return false;
        }

        var tagName = field.tagName.toLowerCase();
        var type = tagName === 'input'
            ? (field.getAttribute('type') || 'text').toLowerCase()
            : tagName;

        if (field.name === '_token' || field.name === '_method') {
            return false;
        }

        return !['submit', 'button', 'reset', 'image', 'file', 'password'].includes(type);
    }

    function controlsWithin(container) {
        return Array.from(container.querySelectorAll('input, select, textarea')).filter(isDraftControl);
    }

    function topControlsFor(config, form) {
        var rowSelector = config.rowSelector || '';

        return controlsWithin(form).filter(function (field) {
            return !rowSelector || !field.closest(rowSelector);
        });
    }

    function controlIdentity(field, index) {
        return {
            name: field.name || '',
            id: field.id || '',
            type: field.tagName.toLowerCase() === 'input'
                ? (field.getAttribute('type') || 'text').toLowerCase()
                : field.tagName.toLowerCase(),
            className: String(field.className || ''),
            index: index,
        };
    }

    function readControl(field, index) {
        var identity = controlIdentity(field, index);

        if (identity.type === 'checkbox' || identity.type === 'radio') {
            return Object.assign(identity, {
                checked: field.checked,
                value: field.value,
            });
        }

        if (field instanceof HTMLSelectElement && field.multiple) {
            return Object.assign(identity, {
                value: Array.from(field.selectedOptions).map(function (option) {
                    return option.value;
                }),
            });
        }

        return Object.assign(identity, {
            value: field.value,
        });
    }

    function writeControl(field, saved) {
        if (!field || !saved) {
            return;
        }

        if (saved.type === 'checkbox' || saved.type === 'radio') {
            field.checked = !!saved.checked;
            return;
        }

        if (field instanceof HTMLSelectElement && field.multiple && Array.isArray(saved.value)) {
            Array.from(field.options).forEach(function (option) {
                option.selected = saved.value.includes(option.value);
            });
            return;
        }

        field.value = saved.value ?? '';
    }

    function captureControls(container) {
        return controlsWithin(container).map(readControl);
    }

    function restoreControls(container, savedControls) {
        var controls = controlsWithin(container);

        (savedControls || []).forEach(function (saved, index) {
            writeControl(controls[index], saved);
        });
    }

    function restoreTopControls(config, form, savedControls) {
        var controls = topControlsFor(config, form);

        (savedControls || []).forEach(function (saved, index) {
            writeControl(controls[index], saved);
        });
    }

    function savedValue(savedControls, name) {
        var saved = (savedControls || []).find(function (control) {
            return control.name === name;
        });

        return saved ? saved.value : '';
    }

    function captureDraft(config, form) {
        var rows = rowsFor(config);

        return {
            savedAt: Date.now(),
            path: window.location.pathname,
            top: topControlsFor(config, form).map(readControl),
            rows: rows.map(captureControls),
        };
    }

    function callRefresh(config) {
        var refresh = config.refreshFunction ? window[config.refreshFunction] : null;
        if (typeof refresh === 'function') {
            refresh(document);
        }
    }

    async function ensureRowCount(config, expectedCount) {
        var addLine = config.addLineFunction ? window[config.addLineFunction] : null;
        var safety = 0;

        while (rowsFor(config).length < expectedCount && typeof addLine === 'function' && safety < 100) {
            addLine();
            callRefresh(config);
            safety += 1;
            await wait(20);
        }

        callRefresh(config);
    }

    async function reloadSaleRows(config, draft) {
        var rows = rowsFor(config);

        for (var index = 0; index < rows.length; index += 1) {
            var row = rows[index];
            var savedRow = draft.rows[index] || [];
            var productSelect = row.querySelector('select.product-select');
            var batchSelect = row.querySelector('select.batch-select');
            var batchValue = savedValue(savedRow, 'product_batch_id[]');

            if (productSelect && productSelect.value && typeof window.loadBatches === 'function') {
                await window.loadBatches(productSelect);
            }

            if (batchSelect && batchValue) {
                batchSelect.value = batchValue;
                if (typeof window.applyBatchSelection === 'function') {
                    window.applyBatchSelection(batchSelect);
                }
            }

            restoreControls(row, savedRow);
        }
    }

    async function reloadPurchaseRows(config, draft) {
        var rows = rowsFor(config);

        for (var index = 0; index < rows.length; index += 1) {
            var row = rows[index];
            var savedRow = draft.rows[index] || [];
            var productSelect = row.querySelector('select.product-select');

            if (productSelect && productSelect.value && typeof window.fillProductData === 'function') {
                await window.fillProductData(productSelect);
            }

            restoreControls(row, savedRow);
        }

        if (typeof window.syncAllExpiryPartsToHidden === 'function') {
            window.syncAllExpiryPartsToHidden(false);
        }
    }

    function refreshTotals(config) {
        if (config.mode === 'sale') {
            if (typeof window.showCustomerCreditInfo === 'function') {
                window.showCustomerCreditInfo();
            }

            if (typeof window.showGuideForFirstSelectedProduct === 'function') {
                window.showGuideForFirstSelectedProduct();
            }
        }

        if (typeof window.calculateTotals === 'function') {
            window.calculateTotals();
        }
    }

    async function restoreDraft(config, form, key) {
        var raw = window.sessionStorage.getItem(key);
        if (!raw) {
            return false;
        }

        var draft;
        try {
            draft = JSON.parse(raw);
        } catch (error) {
            window.sessionStorage.removeItem(key);
            return false;
        }

        await ensureRowCount(config, (draft.rows || []).length);

        restoreTopControls(config, form, draft.top);
        rowsFor(config).forEach(function (row, index) {
            restoreControls(row, draft.rows[index] || []);
        });

        callRefresh(config);

        if (config.mode === 'sale') {
            await reloadSaleRows(config, draft);
        } else if (config.mode === 'purchase') {
            await reloadPurchaseRows(config, draft);
        }

        restoreTopControls(config, form, draft.top);
        rowsFor(config).forEach(function (row, index) {
            restoreControls(row, draft.rows[index] || []);
        });

        callRefresh(config);
        refreshTotals(config);
        return true;
    }

    function init(config) {
        var form = formFor(config);
        if (!form || form.dataset.kimrxTabDraftReady === '1') {
            return;
        }

        form.dataset.kimrxTabDraftReady = '1';
        var key = storageKey(config, form);
        var restoring = true;
        var submitting = false;
        var saveTimer = null;

        function saveNow() {
            if (restoring || submitting) {
                return;
            }

            try {
                window.sessionStorage.setItem(key, JSON.stringify(captureDraft(config, form)));
            } catch (error) {
                // Ignore storage quota/private-mode failures; the form still works normally.
            }
        }

        function scheduleSave() {
            if (restoring || submitting) {
                return;
            }

            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(saveNow, 250);
        }

        restoreDraft(config, form, key).finally(function () {
            restoring = false;
        });

        form.addEventListener('input', scheduleSave, true);
        form.addEventListener('change', scheduleSave, true);
        form.addEventListener('click', function (event) {
            if (event.target instanceof Element && event.target.closest('button, a')) {
                window.setTimeout(scheduleSave, 0);
            }
        }, true);

        var rowBody = rowBodyFor(config);
        if (rowBody) {
            new MutationObserver(function () {
                window.setTimeout(scheduleSave, 0);
            }).observe(rowBody, { childList: true, subtree: true });
        }

        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            submitting = true;
            window.clearTimeout(saveTimer);
            window.sessionStorage.removeItem(key);
        });
    }

    window.KimRxTabDrafts = {
        register: function (config) {
            var ready = function () {
                init(config || {});
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ready);
            } else {
                window.setTimeout(ready, 0);
            }
        },
    };
})();
</script>
@endonce
<script>
window.KimRxTabDrafts && window.KimRxTabDrafts.register(@json($draftConfig ?? []));
</script>
