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

    function ensureDatalist(select) {
        if (!select.dataset.kimPurchaseListId) {
            select.dataset.kimPurchaseListId = 'kim-purchase-product-list-' + Math.random().toString(36).slice(2);
        }

        var list = document.getElementById(select.dataset.kimPurchaseListId);
        if (!list) {
            list = document.createElement('datalist');
            list.id = select.dataset.kimPurchaseListId;
            select.insertAdjacentElement('afterend', list);
        }

        list.innerHTML = '';
        realOptions(select).forEach(function (option) {
            var item = document.createElement('option');
            item.value = labelFor(option);
            list.appendChild(item);
        });

        return list;
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

    function chooseOption(select, input, option) {
        if (!option) {
            return false;
        }

        if (select.value !== option.value) {
            select.value = option.value;
            triggerNativeChange(select);
        }

        input.value = labelFor(option);
        input.classList.remove('input-error');
        return true;
    }

    function resolveTypedSelect(select, allowLoose) {
        var input = select ? select._kimTypedInput : null;
        if (!input) {
            return !!(select && select.value);
        }

        var option = matchOption(select, input.value, allowLoose);
        if (option) {
            return chooseOption(select, input, option);
        }

        if (allowLoose) {
            if (select.value) {
                select.value = '';
                triggerNativeChange(select);
            }

            input.classList.toggle('input-error', input.value.trim().length > 0 || select.dataset.kimWasRequired === '1');
        }

        return false;
    }

    function syncInput(select) {
        var input = select ? select._kimTypedInput : null;
        if (!input) {
            return;
        }

        ensureDatalist(select);
        input.value = currentLabel(select);
        input.classList.remove('input-error');
    }

    function enhanceSelect(select) {
        if (!select) {
            return null;
        }

        if (select.dataset.kimTypedPurchaseReady === '1') {
            ensureDatalist(select);
            return select._kimTypedInput || null;
        }

        var list = ensureDatalist(select);
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'kim-type-input kim-product-type-input';
        input.placeholder = 'Type Product Name';
        input.setAttribute('list', list.id);
        input.autocomplete = 'off';
        input.required = select.hasAttribute('required');
        input.value = currentLabel(select);

        select.insertAdjacentElement('beforebegin', input);
        hideSelect(select);
        select.dataset.kimTypedPurchaseReady = '1';
        select._kimTypedInput = input;
        input._kimTypedSelect = select;

        input.addEventListener('input', function () {
            input.classList.remove('input-error');

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

    document.addEventListener('DOMContentLoaded', function () {
        refreshTypedPurchaseProducts(document);
        bindSubmitValidation();
    });

    refreshTypedPurchaseProducts(document);
    bindSubmitValidation();
})();
</script>
