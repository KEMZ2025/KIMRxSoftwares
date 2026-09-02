(() => {
    const pickers = new Map();
    document.querySelectorAll('[data-stock-product-picker]').forEach((picker) => {
        const search = picker.querySelector('[data-product-search]');
        const productId = picker.querySelector('[data-product-id]');
        const list = picker.querySelector('[role="listbox"]');
        const status = picker.querySelector('[data-product-status]');
        let timer, controller, revision = 0, active = -1, results = [];
        function hide() {
            list.hidden = true;
            search.setAttribute('aria-expanded', 'false');
            search.removeAttribute('aria-activedescendant');
            active = -1;
        }
        function invalidate() {
            clearTimeout(timer);
            controller?.abort();
            revision++;
            hide();
        }
        function select(product) {
            invalidate();
            search.value = product.name;
            productId.value = product.id;
            status.textContent = [product.strength, product.unit_name].filter(Boolean).join(' | ');
            picker.dispatchEvent(new CustomEvent('stock-product-selected', {bubbles: true, detail: product}));
            productId.dispatchEvent(new Event('change', {bubbles: true}));
        }
        async function lookup() {
            invalidate();
            const text = search.value.trim();
            status.textContent = '';
            if (text.length < 2 || search.disabled) return;
            const current = revision;
            controller = new AbortController();
            status.textContent = 'Searching...';
            try {
                const url = new URL(picker.dataset.url, window.location.href);
                url.searchParams.set('q', text);
                const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: controller.signal, credentials: 'same-origin'});
                if (!response.ok) throw new Error('Medicine search is unavailable. Try again.');
                const data = await response.json();
                if (current !== revision || search.disabled) return;
                results = data.products;
                list.replaceChildren();
                results.forEach((product, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'sr-product-option';
                    option.id = list.id + '-' + index;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = product.name;
                    const detail = document.createElement('small');
                    detail.className = 'sr-muted';
                    detail.textContent = [product.strength, product.unit_name].filter(Boolean).join(' | ');
                    option.append(detail);
                    option.addEventListener('click', () => select(product));
                    list.append(option);
                });
                status.textContent = results.length ? '' : 'No matching medicines.';
                list.hidden = !results.length;
                search.setAttribute('aria-expanded', String(Boolean(results.length)));
            } catch (error) {
                if (error.name !== 'AbortError' && current === revision) status.textContent = error.message;
            }
        }
        search.addEventListener('input', () => {
            invalidate();
            productId.value = '';
            status.textContent = '';
            picker.dispatchEvent(new CustomEvent('stock-product-cleared', {bubbles: true}));
            timer = setTimeout(lookup, 220);
        });
        search.addEventListener('focus', () => { if (search.value.trim().length >= 2 && !productId.value) lookup(); });
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); event.stopPropagation(); invalidate(); return; }
            if (list.hidden) return;
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                active = active < 0 ? (event.key === 'ArrowDown' ? 0 : results.length - 1)
                    : (active + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
                Array.from(list.children).forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
                search.setAttribute('aria-activedescendant', list.children[active].id);
                list.children[active].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault(); select(results[active]);
            }
        });
        document.addEventListener('pointerdown', (event) => { if (!picker.contains(event.target)) invalidate(); });
        pickers.set(picker, {lookup, invalidate, reset() { invalidate(); productId.value = ''; search.value = ''; status.textContent = ''; }});
    });

    const dialog = document.getElementById('stock-request-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('[data-stock-request-form]');
    const errorBox = form.querySelector('[data-request-error]');
    const save = form.querySelector('[data-request-save]');
    const picker = dialog.querySelector('[data-stock-product-picker]');
    const unit = form.elements.unit_name;
    let saving = false, lastSaleSearch = '', savedOnce = false, closeTrigger;
    document.addEventListener('focusout', (event) => {
        if (event.target.matches?.('.kim-type-input, .product-search-input, #quick-search-input')) lastSaleSearch = event.target.value;
    });
    function setMode() {
        const isNew = form.elements.request_mode.value === 'new';
        const searchText = picker.querySelector('[data-product-search]').value;
        pickers.get(picker).invalidate();
        form.querySelector('[data-existing-fields]').hidden = isNew;
        form.querySelector('[data-new-fields]').hidden = !isNew;
        picker.querySelectorAll('input').forEach((input) => input.disabled = isNew);
        form.querySelectorAll('[data-new-fields] input').forEach((input) => input.disabled = !isNew);
        unit.readOnly = false;
        if (isNew) {
            if (!form.elements.medicine_name.value) form.elements.medicine_name.value = searchText;
            form.elements.medicine_name.focus();
        } else {
            pickers.get(picker).reset();
            unit.value = '';
            picker.querySelector('[data-product-search]').focus();
        }
        errorBox.hidden = true;
    }
    form.querySelectorAll('[name="request_mode"]').forEach((radio) => radio.addEventListener('change', setMode));
    picker.addEventListener('stock-product-selected', (event) => {
        unit.value = event.detail.unit_name || '';
        unit.readOnly = Boolean(event.detail.unit_name);
    });
    picker.addEventListener('stock-product-cleared', () => { unit.value = ''; unit.readOnly = false; });
    document.querySelectorAll('[data-stock-request-open]').forEach((button) => button.addEventListener('click', () => {
        closeTrigger = button;
        dialog.showModal();
        if (!picker.querySelector('[data-product-search]').value && form.elements.request_mode.value === 'existing') {
            picker.querySelector('[data-product-search]').value = lastSaleSearch || document.getElementById('quick-search-input')?.value || '';
            pickers.get(picker).lookup();
        }
    }));
    function close() {
        if (saving) return;
        pickers.get(picker).invalidate();
        dialog.close();
        closeTrigger?.focus();
        if (savedOnce && document.querySelector('[data-request-book]')) window.location.reload();
    }
    dialog.querySelectorAll('[data-stock-request-close]').forEach((button) => button.addEventListener('click', close));
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); close(); });

    async function record() {
        if (saving) return;
        errorBox.hidden = true;
        if (!form.reportValidity()) return;
        if (form.elements.request_mode.value === 'existing' && !form.elements.product_id.value) {
            errorBox.textContent = 'Select a medicine, or choose New Medicine.';
            errorBox.hidden = false;
            return;
        }
        const payload = Object.fromEntries(new FormData(form).entries());
        saving = true;
        save.textContent = 'Recording...';
        const disabled = Array.from(form.elements).map((input) => [input, input.disabled]);
        let completed = false;
        disabled.forEach(([input]) => input.disabled = true);
        try {
            const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {
                Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token,
            }, body: JSON.stringify(payload)});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !Number.isInteger(data.id)) throw new Error(response.redirected || response.status === 419 || response.status === 401
                ? 'Your session expired. Sign in again before recording this request.'
                : Object.values(data.errors || {}).flat().join('\n') || data.message || 'Request could not be recorded. Try again.');
            savedOnce = true;
            completed = true;
            disabled.forEach(([input, wasDisabled]) => input.disabled = wasDisabled);
            form.reset();
            form.elements.submission_token.value = crypto.randomUUID();
            pickers.get(picker).reset();
            setMode();
            lastSaleSearch = '';
            const toast = document.getElementById('stock-request-toast');
            toast.textContent = 'Request recorded.';
            toast.hidden = false;
            setTimeout(() => { toast.hidden = true; }, 4500);
            saving = false;
            close();
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
        } finally {
            if (!completed) disabled.forEach(([input, wasDisabled]) => input.disabled = wasDisabled);
            saving = false;
            save.textContent = 'Record Request';
        }
    }
    // Stop this AJAX form before the shared navigation guard handles normal sale submissions.
    window.addEventListener('submit', (event) => {
        if (event.target !== form) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        record();
    }, true);
})();
