document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.immigrationPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const form = document.getElementById('immForm');
    const modal = new bootstrap.Modal(document.getElementById('immModal'));
    const detailsModal = new bootstrap.Modal(document.getElementById('immDetailsModal'));
    const pageError = document.getElementById('immPageError');
    const formError = document.getElementById('immFormError');
    const filters = { search: document.getElementById('immSearch'), status: document.getElementById('immStatusFilter') };
    let rows = config.initial.rows;
    let editing = null;
    let detailsEditHandler = null;
    let loading;
    let saving = false;
    let appliedFilters = filterParams();

    filters.search.value = config.filters.search || '';
    filters.status.value = config.filters.status || 'all';

    const table = HR.ui.initDataTable('#immTable', {
        data: rows,
        pageLength: 25,
        order: [[0, 'asc']],
        emptyIcon: 'bi-passport',
        emptyTitle: 'No immigration permissions recorded',
        emptyText: 'Time-limited immigration permissions will appear here once recorded.',
        columns: [
            { data: 'employee', className: 'cell-primary', render: (value, type, row) => type === 'display' ? `<button type="button" class="imm-details-trigger border-0 bg-transparent p-0 text-start" data-view="${row.employee_id}" aria-label="View immigration details for ${esc(value)}">${esc(value)}</button>` : value },
            { data: 'nationality', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'immigration_category', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'permission_start', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'permission_expiry', render: (value, type) => type === 'display' ? (value ? fmt.date(value) : 'N/A') : (value || '9999-99-99') },
            { data: 'status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'restrictions', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'responsible', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
        ],
    });

    function showError(element, message) {
        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
    }

    function filterParams() {
        const query = new URLSearchParams();
        if (filters.search.value.trim()) query.set('search', filters.search.value.trim());
        if (filters.status.value && filters.status.value !== 'all') query.set('status', filters.status.value);
        return query;
    }

    async function api(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || response.redirected) {
            const error = new Error(response.status === 419 || response.status === 401 || response.redirected ? 'Your session has expired. Refresh the page and sign in again.' : result.message || 'Unable to complete the request. Please try again.');
            error.errors = result.errors || {};
            throw error;
        }
        return result;
    }

    function clearValidation() {
        form.querySelectorAll('.field-invalid').forEach(field => field.classList.remove('field-invalid'));
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(field => { field.textContent = ''; });
        showError(formError, '');
    }

    function showValidation(errors, fallback = '') {
        clearValidation();
        let first;
        const other = [];
        Object.entries(errors).forEach(([name, messages]) => {
            const field = form.querySelector(`[name="${name}"]`);
            const feedback = form.querySelector(`[data-error-for="${name}"]`);
            const message = Array.isArray(messages) ? messages[0] : messages;
            if (!field || !feedback) { other.push(message); return; }
            field.closest('.form-field')?.classList.add('field-invalid');
            field.setAttribute('aria-invalid', 'true');
            feedback.textContent = message;
            first ||= field;
        });
        showError(formError, other.join(' ') || (Object.keys(errors).length ? '' : fallback));
        if (first) first.focus();
    }

    function renderStats(stats) {
        const tones = { total: 'primary', expiring: 'warning', expired: 'danger', indefinite: 'neutral' };
        Object.entries(stats).forEach(([key, value]) => {
            const node = document.querySelector(`[data-stat="${key}"]`);
            if (!node) return;
            node.textContent = value;
            node.closest('.metric-card')?.classList.remove('metric-accent-primary', 'metric-accent-warning', 'metric-accent-danger', 'metric-accent-neutral');
            node.closest('.metric-card')?.classList.add(`metric-accent-${value && tones[key] !== 'neutral' ? tones[key] : 'neutral'}`);
        });
    }

    async function refresh() {
        if (loading) loading.abort();
        const controller = new AbortController();
        loading = controller;
        const query = filterParams();
        document.getElementById('immTable').setAttribute('aria-busy', 'true');
        document.getElementById('exportImmBtn').disabled = true;
        try {
            const data = await api(`${config.indexUrl}?${query}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            rows = data.rows;
            table.clear().rows.add(rows).draw();
            renderStats(data.stats);
            appliedFilters = query;
            history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
            showError(pageError, '');
        } catch (error) {
            if (error.name === 'AbortError') return;
            filters.search.value = appliedFilters.get('search') || '';
            filters.status.value = appliedFilters.get('status') || 'all';
            showError(pageError, error.message);
        } finally {
            if (loading === controller) {
                document.getElementById('immTable').removeAttribute('aria-busy');
                document.getElementById('exportImmBtn').disabled = false;
            }
        }
    }

    function openCreate(employeeId) {
        editing = null;
        form.reset();
        form.action = config.storeUrl;
        document.getElementById('immModalTitle').textContent = 'Record Immigration Permission';
        document.getElementById('immSubmit').textContent = 'Save Permission';
        document.getElementById('immFormHelp').classList.remove('d-none');
        document.getElementById('immEmployeeSelect').disabled = false;
        document.getElementById('immCheckDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('immPerformedBy').value = config.actor;
        document.getElementById('immFollowUpNo').checked = true;
        if (employeeId) document.getElementById('immEmployeeSelect').value = String(employeeId);
        clearValidation();
        modal.show();
    }

    function fillForm(data) {
        Object.entries(data).forEach(([name, value]) => {
            const field = form.elements[name];
            if (!field || field.type === 'radio') return;
            field.value = value ?? '';
        });
        document.getElementById(data.follow_up_required ? 'immFollowUpYes' : 'immFollowUpNo').checked = true;
    }

    async function openEdit(row) {
        if (!row.details_url) return;
        editing = row;
        form.reset();
        form.action = row.update_url;
        document.getElementById('immModalTitle').textContent = 'Edit Immigration Permission';
        document.getElementById('immSubmit').textContent = 'Save Changes';
        document.getElementById('immFormHelp').classList.add('d-none');
        document.getElementById('immEmployeeSelect').disabled = true;
        document.getElementById('immEmployeeSelect').value = String(row.employee_id);
        clearValidation();
        modal.show();
        try {
            fillForm(await api(row.details_url));
        } catch (error) {
            showValidation({}, error.message);
        }
    }

    async function openDetails(row) {
        const body = document.getElementById('immDetailsBody');
        const profile = document.getElementById('immDetailsProfile');
        document.getElementById('immDetailsTitle').textContent = row.employee;
        profile.href = row.profile_url;
        document.getElementById('immDetailsEdit').classList.toggle('d-none', !row.check_id);
        detailsEditHandler = row.check_id ? () => openEdit(row) : null;
        body.textContent = 'Loading immigration permission…';
        detailsModal.show();
        try {
            const data = await api(row.details_url);
            const fields = {
                Employee: data.employee, 'Immigration category': data.immigration_category,
                'Check date': fmt.date(data.check_date), Method: data.check_method,
                'Performed by': data.performed_by, Restrictions: data.restrictions,
                'Permission start': data.permission_start ? fmt.date(data.permission_start) : null,
                'Permission expiry': data.permission_expiry ? fmt.date(data.permission_expiry) : null,
                'Follow-up required': data.follow_up_required ? 'Yes' : 'No',
                'Next check date': data.next_check_date ? fmt.date(data.next_check_date) : null,
                'Evidence reference': data.evidence_reference, Notes: data.notes, Status: data.status,
            };
            body.innerHTML = '<dl class="mb-0">' + Object.entries(fields).map(([label, value]) => `<dt class="text-meta">${esc(label)}</dt><dd>${esc(value || '—')}</dd>`).join('') + '</dl>';
        } catch (error) { body.textContent = error.message; }
    }

    document.getElementById('newImmBtn').addEventListener('click', () => openCreate(config.filters.employee || null));
    document.getElementById('immModal').addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    document.getElementById('immDetailsEdit').addEventListener('click', () => {
        if (!detailsEditHandler) return;
        const next = detailsEditHandler;
        document.getElementById('immDetailsModal').addEventListener('hidden.bs.modal', () => next(), { once: true });
        detailsModal.hide();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        clearValidation();
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        const body = new FormData(form);
        if (editing) {
            body.append('_method', 'PUT');
            body.set('employee_id', String(editing.employee_id));
        }
        try {
            const result = await api(form.action, { method: 'POST', body });
            saving = false;
            modal.hide();
            HR.ui.toastSuccess(editing ? 'Permission updated' : 'Immigration permission saved', result.message);
            await refresh();
        } catch (error) {
            showValidation(error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    });

    document.querySelector('#immTable tbody').addEventListener('click', event => {
        const tr = event.target.closest('tr');
        const data = tr && table.row(tr).data();
        if (data) openDetails(data);
    });

    let searchTimer;
    filters.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(refresh, 250);
    });
    filters.status.addEventListener('change', refresh);

    document.getElementById('exportImmBtn').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const query = new URLSearchParams(appliedFilters);
            const [column, direction] = table.order()[0] || [0, 'asc'];
            query.set('sort', ['employee', 'nationality', 'immigration_category', 'permission_start', 'permission_expiry', 'status', 'responsible'][column] || 'employee');
            query.set('direction', direction);
            const response = await fetch(`${config.exportUrl}?${query}`, { headers: { Accept: 'text/csv, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Export failed. Refresh the page and try again.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = 'immigration-records.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            HR.ui.toastSuccess('Export complete', 'immigration-records.csv has been downloaded.');
        } catch (error) { showError(pageError, error.message); }
        finally { button.disabled = false; }
    });

    if (config.openNew) {
        openCreate(config.filters.employee || config.old.employee_id || null);
        Object.entries(config.old || {}).forEach(([name, value]) => {
            const field = form.elements[name];
            if (field && value != null && field.type !== 'radio') field.value = value;
        });
        if (config.old.follow_up_required == 1 || config.old.follow_up_required === '1') document.getElementById('immFollowUpYes').checked = true;
        showValidation(config.errors);
    }
});
