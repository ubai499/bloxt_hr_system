document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const config = window.documentPage;
    const esc = HR.format.escapeHtml;
    const form = document.getElementById('docForm');
    const modalEl = document.getElementById('docModal');
    const modal = new bootstrap.Modal(modalEl);
    const pageError = document.getElementById('docPageError');
    const formError = document.getElementById('docFormError');
    const fields = {
        category: document.getElementById('docCategoryFilter'),
        status: document.getElementById('docStatusFilter'),
        employee: document.getElementById('docEmployeeFilter'),
        search: document.getElementById('docSearch'),
    };
    let saving = false;
    let highlighted = Number(new URL(location.href).searchParams.get('highlight'));
    let today = config.today;
    const displayText = (value, type) => type === 'display' ? esc(value || '') : value || '';
    const date = (value, type) => type === 'display' ? (value ? HR.format.date(value) : '—') : value || '';
    const table = HR.ui.initDataTable('#documentsTable', {
        data: config.rows, order: [[4, 'asc']],
        emptyIcon: 'bi-folder2-open', emptyTitle: 'No documents match these filters',
        emptyText: 'No documents expire within the selected period, or none match your search.',
        columns: [
            { data: 'title', render: (value, type, row) => {
                if (type !== 'display') return value;
                const title = row.download_url ? `<a href="${esc(row.download_url)}" class="cell-primary document-download" aria-label="Download ${esc(value)}">${esc(value)}</a>` : `<span class="cell-primary">${esc(value)}</span>`;
                return `<div class="d-flex align-items-center gap-2"><span class="doc-icon" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-file-earmark-text"></i></span>${title}</div>`;
            } },
            { data: 'category', render: displayText }, { data: 'employee', render: displayText },
            { data: 'issue_date', type: 'string', render: date },
            { data: 'expiry_date', type: 'string', render: (value, type) => value ? date(value, type) : (type === 'display' ? 'N/A' : '9999-12-31') },
            { data: 'status', render: (value, type) => type === 'display' ? HR.format.statusBadge(value) : value },
            { data: 'access_classification', render: (value, type) => type === 'display' ? `<span class="data-classification-tag classification-${esc(value.toLowerCase().replaceAll(' ', '-'))}">${esc(value)}</span>` : value },
            { data: 'uploaded_by', className: 'cell-secondary', render: displayText },
        ],
        rowCallback: (element, row) => element.classList.toggle('table-active', row.id === highlighted),
    });
    const filters = HR.ui.createFilterState(table, 'documentsTable');
    function message(element, value) {
        element.textContent = value || '';
        element.classList.toggle('d-none', !value);
    }
    function renderKpis() {
        const rows = table.rows({ search: 'applied' }).data().toArray();
        const cutoff = new Date(today + 'T00:00:00Z');
        cutoff.setUTCDate(cutoff.getUTCDate() + 30);
        const until = cutoff.toISOString().slice(0, 10);
        const expiring = rows.filter(row => row.status !== 'Archived' && row.expiry_date && row.expiry_date >= today && row.expiry_date <= until).length;
        const expired = rows.filter(row => row.status === 'Expired').length;
        const review = rows.filter(row => row.status === 'Review Due').length;
        document.getElementById('docKpiRow').innerHTML = [
            ['Total Documents', rows.length, 'primary'], ['Expiring Within 30 Days', expiring, expiring ? 'warning' : 'neutral'],
            ['Expired', expired, expired ? 'danger' : 'neutral'], ['Review Due', review, review ? 'warning' : 'neutral'],
        ].map(([label, value, accent]) => `<div class="metric-card metric-accent-${accent}"><span class="metric-label">${label}</span><span class="metric-value">${value}</span></div>`).join('');
    }
    function applyFilters() {
        const values = Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, field.value]));
        filters.set('selected', row => {
            for (const key of ['category', 'status', 'employee']) {
                if (values[key] !== 'all' && String(row[key === 'employee' ? 'employee_id' : key]) !== values[key]) return false;
            }
            const text = [row.title, row.category, row.employee, row.issue_date, row.expiry_date, row.status, row.access_classification, row.uploaded_by].join(' ').toLowerCase();
            return !values.search || text.includes(values.search.toLowerCase());
        });
        const url = new URL(config.indexUrl);
        Object.entries(values).forEach(([key, value]) => { if (value) url.searchParams.set(key, value); });
        history.replaceState(null, '', url);
        renderKpis();
    }
    Object.entries(fields).forEach(([key, field]) => {
        // Keep a deleted employee filter instead of silently broadening its results.
        if (key === 'employee' && ![...field.options].some(option => option.value === config.filters[key])) {
            field.add(new Option('Former employee', config.filters[key]));
        }
        field.value = config.filters[key];
        field.addEventListener(key === 'search' ? 'input' : 'change', applyFilters);
    });
    function reveal(id) {
        const row = table.rows().data().toArray().find(row => row.id === Number(id));
        if (row && table.rows({search: 'applied'}).data().toArray().every(item => item.id !== row.id)) {
            fields.category.value = 'all'; fields.status.value = 'all'; fields.search.value = '';
            if (!config.selfService) fields.employee.value = 'all';
            applyFilters();
        }
        highlighted = Number(id);
        const indices = table.rows({ search: 'applied', order: 'applied' }).indexes().toArray();
        const position = indices.findIndex(index => table.row(index).data().id === highlighted);
        if (position >= 0) table.page(Math.floor(position / table.page.len())).draw(false);
    }
    table.on('draw', renderKpis);
    applyFilters();
    if (highlighted) reveal(highlighted);

    function clearErrors() {
        message(formError, '');
        form.querySelectorAll('[data-error-for]').forEach(element => { element.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
        form.querySelectorAll('.field-invalid').forEach(element => element.classList.remove('field-invalid'));
    }
    function showErrors(errors) {
        Object.entries(errors).forEach(([name, messages]) => {
            const field = form.elements.namedItem(name);
            const error = [...form.querySelectorAll('[data-error-for]')].find(element => element.dataset.errorFor === name);
            if (error) error.textContent = messages[0];
            if (field) { field.setAttribute('aria-invalid', 'true'); field.closest('.form-field')?.classList.add('field-invalid'); }
        });
        form.querySelector('[aria-invalid="true"]')?.focus();
    }
    function reset() {
        form.reset(); clearErrors();
        form.elements.category.value = fields.category.value === 'all' ? config.defaultCategory : fields.category.value;
        if ([...form.elements.employee_id.options].some(option => option.value === fields.employee.value)) form.elements.employee_id.value = fields.employee.value;
    }
    document.getElementById('newDocBtn').addEventListener('click', () => { reset(); modal.show(); });
    modalEl.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    modalEl.addEventListener('hidden.bs.modal', () => document.getElementById('newDocBtn').focus());
    modalEl.addEventListener('shown.bs.modal', () => (form.querySelector('[aria-invalid="true"]') || form.elements.title).focus());
    async function api(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, ...options.headers } });
        const result = await response.json().catch(() => null);
        if (response.ok && !response.redirected && !result) {
            throw new Error('The server returned an unreadable response. Reload the page to check whether the document was saved before trying again.');
        }
        if (!response.ok || response.redirected || !result) {
            const error = new Error(response.redirected || [401, 419].includes(response.status) ? 'Your session has expired. Refresh the page and sign in again.' : response.status === 413 ? 'The uploaded file is too large.' : result?.message || 'Unable to complete the request. Please try again.');
            error.errors = result?.errors || {}; throw error;
        }
        return result;
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        clearErrors();
        if (!form.reportValidity()) return;
        saving = true;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true; submit.textContent = 'Saving…';
        message(pageError, '');
        try {
            const result = await api(form.action, { method: 'POST', body: new FormData(form) });
            saving = false; modal.hide();
            HR.toast({ type: 'success', title: 'Document saved', text: result.message });
            fields.category.value = form.elements.category.value;
            fields.status.value = 'all'; fields.search.value = ''; fields.employee.value = config.selfService ? config.filters.employee : 'all';
            reset();
            try {
                const refreshed = await api(config.indexUrl);
                today = refreshed.today;
                table.clear().rows.add(refreshed.rows);
                applyFilters(); reveal(result.id);
            } catch (error) {
                message(pageError, 'The document was saved, but the list could not refresh. Reload the page to see it.');
            }
        } catch (error) {
            message(formError, error.message); showErrors(error.errors || {});
        } finally {
            saving = false; submit.disabled = false; submit.textContent = 'Save Document';
        }
    });
    document.getElementById('exportDocBtn').addEventListener('click', async event => {
        const button = event.currentTarget;
        if (button.disabled) return;
        button.disabled = true; message(pageError, '');
        const url = new URL(config.exportUrl);
        Object.entries(fields).forEach(([key, field]) => url.searchParams.set(key, field.value));
        const [[sort, direction]] = table.order();
        url.searchParams.set('sort', sort); url.searchParams.set('direction', direction);
        try {
            const response = await fetch(url, { headers: { Accept: 'text/csv' } });
            if (!response.ok || response.redirected || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Unable to export documents. Refresh the page and try again.');
            const objectUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a'); link.href = objectUrl; link.download = 'documents.csv';
            document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            HR.toast({ type: 'success', title: 'Export complete', text: 'documents.csv has been downloaded.' });
        } catch (error) { message(pageError, error.message); }
        finally { button.disabled = false; }
    });
    if (config.openNew) {
        reset();
        Object.entries(config.old).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (field && field.type !== 'file' && name !== '_token') field.value = value ?? '';
        });
        if (Object.keys(config.errors).length) {
            message(formError, 'Please check the highlighted fields. If you attached a file, select it again.');
            showErrors(config.errors);
        }
        modal.show();
    }
});
