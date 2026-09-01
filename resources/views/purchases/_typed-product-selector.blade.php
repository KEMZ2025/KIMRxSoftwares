<style>
    .kim-hidden-system-select {
        position: absolute !important;
        left: -9999px !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .kim-type-input {
        width: 100%;
        min-width: 150px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 7px 9px;
        font-size: 13px;
        background: #ffffff;
        color: #0f172a;
        box-sizing: border-box;
    }

    .kim-type-input:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.16);
        outline: none;
    }

    body.dark-mode .kim-type-input {
        background: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }

    .kim-type-wrap {
        position: relative;
        width: 100%;
    }

    .kim-type-results {
        display: none;
        position: fixed;
        z-index: 10000;
        top: 0;
        left: 0;
        width: 280px;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #7dd3fc;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.18);
    }

    .kim-type-option,
    .kim-type-empty {
        padding: 8px 10px;
        font-size: 12.5px;
        line-height: 1.35;
    }

    .kim-type-option {
        cursor: pointer;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
    }

    .kim-type-option:last-child {
        border-bottom: 0;
    }

    .kim-type-option:hover,
    .kim-type-option.is-active {
        background: #e0f2fe;
        color: #075985;
        font-weight: 700;
    }

    .kim-type-empty {
        color: #64748b;
    }

    body.dark-mode .kim-type-results {
        background: #111827;
        border-color: #38bdf8;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45);
    }

    body.dark-mode .kim-type-option {
        color: #f8fafc;
        border-color: #334155;
    }

    body.dark-mode .kim-type-option:hover,
    body.dark-mode .kim-type-option.is-active {
        background: #0c4a6e;
        color: #ffffff;
    }

    body.dark-mode .kim-type-empty {
        color: #cbd5e1;
    }
</style>
<script>
(function () {
    if (window.__kimTypedPurchaseProductsReady) {
        return;
    }
    window.__kimTypedPurchaseProductsReady = true;

    function normalise(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function realOptions(select) {
        return Array.from(select ? select.options : []).filter(function (option) {
            return option.value;
        });
    }

    function labelFor(option) {
        return option ? String(option.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function matchOption(select, typed, allowLoose) {
        var needle = normalise(typed);
        if (!needle) {
            return null;
        }

        var options = realOptions(select);
        var exact = options.find(function (option) {
            return normalise(labelFor(option)) === needle;
        });
        if (exact) {
            return exact;
        }

        if (!allowLoose || needle.length < 2) {
            return null;
        }

        var tokens = needle.split(' ').filter(Boolean);
        return options.find(function (option) {
            return normalise(labelFor(option)).indexOf(needle) === 0;
        }) || options.find(function (option) {
            var haystack = normalise(labelFor(option));
            return tokens.every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
        }) || null;
    }

    function matchingOptions(select, typed) {
        var needle = normalise(typed);
        if (!needle) {
            return [];
        }

        var tokens = needle.split(' ').filter(Boolean);
        return realOptions(select).filter(function (option) {
            var haystack = normalise(labelFor(option));
            return tokens.every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
        }).slice(0, 12);
    }

    function hidePanel(panel) {
        if (!panel) {
            return;
        }

        panel.style.display = 'none';
        panel.innerHTML = '';
        panel._kimAnchorInput = null;
    }

    function closeTypePanels(except) {
        document.querySelectorAll('.kim-type-results').forEach(function (panel) {
            if (panel !== except) {
                hidePanel(panel);
            }
        });
    }

    function positionPanel(input, panel) {
        var rect = input.getBoundingClientRect();
        var viewportWidth = document.documentElement.clientWidth || window.innerWidth || 320;
        var viewportHeight = document.documentElement.clientHeight || window.innerHeight || 480;
        var width = Math.min(Math.max(rect.width, 280), Math.max(240, viewportWidth - 24));
        var left = Math.min(Math.max(12, rect.left), Math.max(12, viewportWidth - width - 12));
        var top = rect.bottom + 4;

        if (top > viewportHeight - 80) {
            top = Math.max(12, rect.top - 224);
        }

        panel.style.width = width + 'px';
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    function showPanel(input, panel) {
        panel._kimAnchorInput = input;
        positionPanel(input, panel);
        panel.style.display = 'block';
        closeTypePanels(panel);
    }

    function repositionOpenPanels() {
        document.querySelectorAll('.kim-type-results').forEach(function (panel) {
            if (panel.style.display !== 'block') {
                return;
            }

            var input = panel._kimAnchorInput;
            if (!input || !document.contains(input) || !input.value.trim()) {
                hidePanel(panel);
                return;
            }

            positionPanel(input, panel);
        });
    }

    var repositionQueued = false;
    function queuePanelReposition() {
        if (repositionQueued) {
            return;
        }

        repositionQueued = true;
        window.requestAnimationFrame(function () {
            repositionQueued = false;
            repositionOpenPanels();
        });
    }

    function renderResults(select, input, panel) {
        var query = input.value.trim();
        var matches = matchingOptions(select, query);

        if (!query) {
            hidePanel(panel);
            return;
        }

        panel.innerHTML = '';

        if (!matches.length) {
            var empty = document.createElement('div');
            empty.className = 'kim-type-empty';
            empty.textContent = 'No matching product found';
            panel.appendChild(empty);
            showPanel(input, panel);
            return;
        }

        matches.forEach(function (option) {
            var item = document.createElement('div');
            item.className = 'kim-type-option';
            item.textContent = labelFor(option);
            item.addEventListener('mousedown', function (event) {
                event.preventDefault();
                chooseOption(select, input, panel, option);
            });
            panel.appendChild(item);
        });

        showPanel(input, panel);
    }

    function moveActiveResult(panel, direction) {
        var items = Array.from(panel.querySelectorAll('.kim-type-option'));
        if (!items.length) {
            return;
        }

        var current = panel.querySelector('.kim-type-option.is-active');
        var index = current ? items.indexOf(current) : -1;
        index = Math.max(0, Math.min(items.length - 1, index + direction));

        items.forEach(function (item) {
            item.classList.remove('is-active');
        });

        items[index].classList.add('is-active');
        items[index].scrollIntoView({ block: 'nearest' });
    }

    function hideSelect(select) {
        if (!select.dataset.kimWasRequired) {
            select.dataset.kimWasRequired = select.hasAttribute('required') ? '1' : '0';
        }

        if (!select.classList.contains('kim-hidden-system-select')) {
            select.classList.add('kim-hidden-system-select');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');
        }

        select.required = false;
    }

    function currentLabel(select) {
        var option = select.options[select.selectedIndex];
        return option && option.value ? labelFor(option) : '';
    }

    function triggerNativeChange(select) {
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function chooseOption(select, input, panel, option) {
        if (!option) {
            return false;
        }

        if (select.value !== option.value) {
            select.value = option.value;
            triggerNativeChange(select);
        }

        input.value = labelFor(option);
        input.classList.remove('input-error');
        hidePanel(panel);
        return true;
    }

    function resolveTypedSelect(select, allowLoose) {
        var input = select ? select._kimTypedInput : null;
        if (!input) {
            return !!(select && select.value);
        }

        var option = matchOption(select, input.value, allowLoose);
        if (option) {
            return chooseOption(select, input, input._kimResultsPanel, option);
        }

        if (allowLoose) {
            if (select.value) {
                select.value = '';
                triggerNativeChange(select);
            }

            input.classList.toggle('input-error', input.value.trim().length > 0 || select.dataset.kimWasRequired === '1');
            hidePanel(input._kimResultsPanel);
        }

        return false;
    }

    function syncInput(select) {
        var input = select ? select._kimTypedInput : null;
        if (!input) {
            return;
        }

        input.value = currentLabel(select);
        input.classList.remove('input-error');
        hidePanel(input._kimResultsPanel);
    }

    function enhanceSelect(select) {
        if (!select) {
            return null;
        }

        if (select.dataset.kimTypedPurchaseReady === '1') {
            return select._kimTypedInput || null;
        }

        var wrap = document.createElement('div');
        wrap.className = 'kim-type-wrap';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'kim-type-input kim-product-type-input';
        input.placeholder = 'Type Product Name';
        input.autocomplete = 'off';
        input.required = select.hasAttribute('required');
        input.value = currentLabel(select);

        var panel = document.createElement('div');
        panel.className = 'kim-type-results';

        wrap.appendChild(input);
        select.parentNode.insertBefore(wrap, select);
        document.body.appendChild(panel);
        hideSelect(select);
        select.dataset.kimTypedPurchaseReady = '1';
        select._kimTypedInput = input;
        input._kimTypedSelect = select;
        input._kimResultsPanel = panel;

        input.addEventListener('input', function () {
            input.classList.remove('input-error');

            if (!input.value.trim()) {
                hidePanel(panel);
            } else {
                renderResults(select, input, panel);
            }

            if (!resolveTypedSelect(select, false) && !input.value.trim() && select.value) {
                select.value = '';
                triggerNativeChange(select);
            }
        });

        input.addEventListener('change', function () {
            resolveTypedSelect(select, true);
        });

        input.addEventListener('blur', function () {
            resolveTypedSelect(select, true);
        });

        input.addEventListener('keydown', function (event) {
            if (!input.value.trim()) {
                hidePanel(panel);
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (panel.style.display !== 'block') {
                    renderResults(select, input, panel);
                }
                moveActiveResult(panel, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (panel.style.display !== 'block') {
                    renderResults(select, input, panel);
                }
                moveActiveResult(panel, -1);
            } else if (event.key === 'Enter' && panel.style.display === 'block') {
                var active = panel.querySelector('.kim-type-option.is-active') || panel.querySelector('.kim-type-option');
                if (active) {
                    event.preventDefault();
                    active.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else if (event.key === 'Escape') {
                hidePanel(panel);
            }
        });

        select.addEventListener('change', function () {
            syncInput(select);
        });

        return input;
    }

    function refreshTypedPurchaseProducts(root) {
        var scope = root || document;
        scope.querySelectorAll('select.product-select').forEach(enhanceSelect);
    }

    function validateTypedProducts(event) {
        var form = event.currentTarget;
        var firstInvalid = null;

        form.querySelectorAll('select.product-select').forEach(function (select) {
            var input = enhanceSelect(select);
            if (!input) {
                return;
            }

            if (!select.value || normalise(input.value) !== normalise(currentLabel(select))) {
                resolveTypedSelect(select, true);
            }

            if (select.dataset.kimWasRequired === '1' && !select.value) {
                input.classList.add('input-error');
                firstInvalid = firstInvalid || input;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            alert('Please type and choose a product from the suggestions before saving the purchase.');
            firstInvalid.focus();
        }
    }

    function bindSubmitValidation() {
        ['purchase-form', 'add-items-form'].forEach(function (formId) {
            var form = document.getElementById(formId);
            if (!form || form.dataset.kimTypedProductsValidationReady === '1') {
                return;
            }

            form.dataset.kimTypedProductsValidationReady = '1';
            form.addEventListener('submit', validateTypedProducts);
        });
    }

    var originalAddLine = window.addLine;
    if (typeof originalAddLine === 'function' && !originalAddLine.__kimTypedPurchaseWrapped) {
        window.addLine = function () {
            var result = originalAddLine.apply(this, arguments);
            setTimeout(function () {
                refreshTypedPurchaseProducts(document);
            }, 0);
            return result;
        };
        window.addLine.__kimTypedPurchaseWrapped = true;
    }

    var originalAppendProductOption = window.appendProductOption;
    if (typeof originalAppendProductOption === 'function' && !originalAppendProductOption.__kimTypedPurchaseWrapped) {
        window.appendProductOption = function () {
            var result = originalAppendProductOption.apply(this, arguments);
            refreshTypedPurchaseProducts(document);
            return result;
        };
        window.appendProductOption.__kimTypedPurchaseWrapped = true;
    }

    var originalFillProductData = window.fillProductData;
    if (typeof originalFillProductData === 'function' && !originalFillProductData.__kimTypedPurchaseWrapped) {
        window.fillProductData = async function (selectElement) {
            var result = await originalFillProductData.apply(this, arguments);
            syncInput(selectElement);
            return result;
        };
        window.fillProductData.__kimTypedPurchaseWrapped = true;
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.kim-type-wrap') && !event.target.closest('.kim-type-results')) {
            closeTypePanels(null);
        }
    });

    window.addEventListener('scroll', queuePanelReposition, true);
    window.addEventListener('resize', queuePanelReposition);

    window.KimRxRefreshTypedPurchaseProducts = refreshTypedPurchaseProducts;

    document.addEventListener('DOMContentLoaded', function () {
        refreshTypedPurchaseProducts(document);
        bindSubmitValidation();
    });

    refreshTypedPurchaseProducts(document);
    bindSubmitValidation();
})();
</script>
@include('layouts.tab-draft-script', [
    'draftConfig' => [
        'key' => 'vip-purchase-entry:' . request()->path(),
        'formSelector' => '#purchase-form, #add-items-form',
        'rowBodySelector' => '#purchase-items-body',
        'rowSelector' => '.purchase-row',
        'addLineFunction' => 'addLine',
        'refreshFunction' => 'KimRxRefreshTypedPurchaseProducts',
        'mode' => 'purchase',
    ],
])
