<script data-kimrx-unsaved-changes-script>
(() => {
    const promptMessage = 'You have unsaved changes on this screen. Leave without saving?';
    const trackedForms = new Map();
    let allowNavigation = false;
    let sequence = 0;

    function editableFields(form) {
        return Array.from(form?.elements ?? []).filter((field) => {
            if (!(field instanceof HTMLElement)) {
                return false;
            }

            if (field.disabled || field.dataset.unsavedIgnore === 'true') {
                return false;
            }

            const tagName = field.tagName.toLowerCase();

            if (tagName === 'textarea') {
                return !field.readOnly;
            }

            if (tagName === 'select') {
                return true;
            }

            if (tagName !== 'input') {
                return false;
            }

            if (field.readOnly) {
                return false;
            }

            const type = (field.getAttribute('type') || 'text').toLowerCase();

            return !['hidden', 'submit', 'button', 'reset', 'image'].includes(type);
        });
    }

    function shouldTrackForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        if (form.dataset.unsavedWarning === 'false') {
            return false;
        }

        if (form.dataset.unsavedWarning === 'true') {
            return true;
        }

        const method = (form.getAttribute('method') || 'GET').toUpperCase();

        if (method === 'GET') {
            return false;
        }

        return editableFields(form).length > 0;
    }

    function fieldState(field, index) {
        const tagName = field.tagName.toLowerCase();
        const type = tagName === 'input'
            ? (field.getAttribute('type') || 'text').toLowerCase()
            : tagName;
        const fieldKey = field.name || field.id || `${tagName}-${index}`;

        if (type === 'checkbox' || type === 'radio') {
            return `${fieldKey}:${type}:${field.checked ? '1' : '0'}:${field.value}`;
        }

        if (type === 'file') {
            const files = Array.from(field.files ?? [])
                .map((file) => `${file.name}:${file.size}:${file.lastModified}`)
                .join('|');

            return `${fieldKey}:${type}:${files}`;
        }

        if (tagName === 'select' && field.multiple) {
            const selectedValues = Array.from(field.selectedOptions ?? [])
                .map((option) => option.value)
                .join('|');

            return `${fieldKey}:${type}:${selectedValues}`;
        }

        return `${fieldKey}:${type}:${field.value}`;
    }

    function captureFormState(form) {
        return editableFields(form)
            .map((field, index) => fieldState(field, index))
            .join('||');
    }

    function ensureTrackedForm(form) {
        if (!shouldTrackForm(form)) {
            return null;
        }

        if (!form.dataset.unsavedFormId) {
            sequence += 1;
            form.dataset.unsavedFormId = `kimrx-unsaved-${sequence}`;
        }

        const existingEntry = trackedForms.get(form.dataset.unsavedFormId);

        if (existingEntry) {
            return existingEntry;
        }

        const entry = {
            form,
            initialState: captureFormState(form),
            dirty: false,
        };

        trackedForms.set(form.dataset.unsavedFormId, entry);
        form.dataset.unsavedDirty = 'false';

        return entry;
    }

    function refreshDirtyState(form) {
        const entry = ensureTrackedForm(form);

        if (!entry) {
            return false;
        }

        entry.dirty = captureFormState(form) !== entry.initialState;
        form.dataset.unsavedDirty = entry.dirty ? 'true' : 'false';

        return entry.dirty;
    }

    function resetTrackedForm(form) {
        const entry = ensureTrackedForm(form);

        if (!entry) {
            return;
        }

        entry.initialState = captureFormState(form);
        entry.dirty = false;
        form.dataset.unsavedDirty = 'false';
    }

    function hasDirtyForms(exceptForm = null) {
        for (const entry of trackedForms.values()) {
            if (entry.form !== exceptForm && entry.dirty) {
                return true;
            }
        }

        return false;
    }

    function shouldIgnoreLink(link, event) {
        if (!(link instanceof HTMLAnchorElement) || event.defaultPrevented) {
            return true;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return true;
        }

        if (link.dataset.unsavedIgnore === 'true') {
            return true;
        }

        const rawHref = link.getAttribute('href') || '';

        if (rawHref === '' || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) {
            return true;
        }

        if (link.hasAttribute('download')) {
            return true;
        }

        const target = (link.getAttribute('target') || '').toLowerCase();

        if (target && target !== '_self') {
            return true;
        }

        try {
            const url = new URL(link.href, window.location.href);

            if (url.pathname === window.location.pathname
                && url.search === window.location.search
                && url.hash !== ''
                && url.hash !== window.location.hash) {
                return true;
            }
        } catch (error) {
            return true;
        }

        return false;
    }

    function confirmNavigation() {
        return window.confirm(promptMessage);
    }

    function initializeTrackedForms() {
        document.querySelectorAll('form').forEach((form) => {
            resetTrackedForm(form);
        });
    }

    document.addEventListener('input', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form') : null;

        if (form) {
            refreshDirtyState(form);
        }
    }, true);

    document.addEventListener('change', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form') : null;

        if (form) {
            refreshDirtyState(form);
        }
    }, true);

    document.addEventListener('reset', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;

        if (!form) {
            return;
        }

        window.setTimeout(() => {
            refreshDirtyState(form);
        }, 0);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;

        if (!form) {
            return;
        }

        if (shouldTrackForm(form)) {
            if (hasDirtyForms(form) && !confirmNavigation()) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                return;
            }

            allowNavigation = true;
            return;
        }

        if (!hasDirtyForms()) {
            allowNavigation = true;
            return;
        }

        if (!confirmNavigation()) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            return;
        }

        allowNavigation = true;
    }, true);

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (!link || shouldIgnoreLink(link, event) || !hasDirtyForms()) {
            return;
        }

        if (!confirmNavigation()) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            return;
        }

        allowNavigation = true;
    }, true);

    window.addEventListener('beforeunload', (event) => {
        if (allowNavigation || !hasDirtyForms()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    window.addEventListener('pageshow', () => {
        allowNavigation = false;
    });

    document.addEventListener('DOMContentLoaded', () => {
        window.setTimeout(() => {
            initializeTrackedForms();
        }, 0);
    });

    window.KimRxUnsavedChanges = {
        reset(formOrSelector) {
            const form = typeof formOrSelector === 'string'
                ? document.querySelector(formOrSelector)
                : formOrSelector;

            if (form instanceof HTMLFormElement) {
                resetTrackedForm(form);
            }
        },
        isDirty() {
            return hasDirtyForms();
        },
    };
})();
</script>
