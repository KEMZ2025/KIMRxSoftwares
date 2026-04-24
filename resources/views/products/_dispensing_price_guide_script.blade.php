<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rowsWrap = document.getElementById('dispensing-guide-rows');
        const addButton = document.getElementById('add-dispensing-guide-row');
        const template = document.getElementById('dispensing-guide-row-template');

        if (!rowsWrap || !addButton || !template) {
            return;
        }

        function syncRemoveButtons() {
            const rows = rowsWrap.querySelectorAll('.guide-row');

            rows.forEach((row, index) => {
                const button = row.querySelector('.remove-dispensing-guide-row');
                if (!button) {
                    return;
                }

                button.disabled = rows.length === 1 && index === 0;
                button.style.opacity = rows.length === 1 && index === 0 ? '0.6' : '1';
                button.style.cursor = rows.length === 1 && index === 0 ? 'not-allowed' : 'pointer';
            });
        }

        addButton.addEventListener('click', function () {
            rowsWrap.appendChild(template.content.cloneNode(true));
            syncRemoveButtons();
        });

        rowsWrap.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-dispensing-guide-row');
            if (!button) {
                return;
            }

            const rows = rowsWrap.querySelectorAll('.guide-row');
            if (rows.length === 1) {
                rows[0].querySelectorAll('input').forEach(input => {
                    input.value = '';
                });
                syncRemoveButtons();
                return;
            }

            button.closest('.guide-row')?.remove();
            syncRemoveButtons();
        });

        syncRemoveButtons();
    });
</script>
