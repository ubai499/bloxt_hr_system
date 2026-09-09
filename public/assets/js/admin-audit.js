document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.auditPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const pageError = document.getElementById('auditPageError');
    const filters = {
        search: document.getElementById('auditSearch'),
        module: document.getElementById('auditModuleFilter'),
        date: document.getElementById('auditDateFilter'),
    };
    let rows = config.initial.rows;
    let loading;
    let appliedFilters = filterParams();

    filters.search.value = config.filters.search || '';
    filters.module.value = config.filters.module || 'all';
    filters.date.value = config.filters.date || '';

    const table = HR.ui.initDataTable('#auditTable', {
        data: rows,
        pageLength: 25,
        order: [[0, 'desc']],
        emptyIcon: 'bi-journal-text',
        emptyTitle: 'No audit events match these filters',
        emptyText: 'System actions will be logged here automatically.',
        columns: [
            { data: 'timestamp', className: 'cell-secondary', render: (value, type) => type === 'display' ? fmt.date(value, { withTime: true }) : value || '' },
            { data: 'user', render: (value, type) => type === 'display' ? esc(value || 'System') : value || '' },
            { data: 'action', className: 'cell-primary', render: (value, type) => type === 'display' ? esc(value) : value },
            { data: 'module', render: (value, type) => type === 'display' ? esc(value) : value },
            { data: 'employee', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'description', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'previous_value', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'new_value', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
        ],
        createdRow: (row, record) => { if (String(config.highlight) === String(record.id)) row.classList.add('table-active'); },
    });

    function showError(message) {
        pageError.textContent = message || '';
        pageError.classList.toggle('d-none', !message);
    }

    function filterParams() {
        const query = new URLSearchParams();
        if (filters.search.value.trim()) query.set('search', filters.search.value.trim());
        if (filters.module.value && filters.module.value !== 'all') query.set('module', filters.module.value);
        if (filters.date.value) query.set('date', filters.date.value);
        return query;
    }

    async function refresh() {
        if (loading) loading.abort();
        const controller = new AbortController();
        loading = controller;
        const query = filterParams();
        document.getElementById('auditTable').setAttribute('aria-busy', 'true');
        document.getElementById('exportAuditBtn').disabled = true;
        try {
            const response = await fetch(`${config.indexUrl}?${query}`, { signal: controller.signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || response.redirected) throw new Error(result.message || 'Unable to load the audit log.');
            if (controller.signal.aborted) return;
            rows = result.rows;
            table.clear().rows.add(rows).draw();
            appliedFilters = query;
            history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
            showError('');
        } catch (error) {
            if (error.name === 'AbortError') return;
            filters.search.value = appliedFilters.get('search') || '';
            filters.module.value = appliedFilters.get('module') || 'all';
            filters.date.value = appliedFilters.get('date') || '';
            showError(error.message);
        } finally {
            if (loading === controller) {
                document.getElementById('auditTable').removeAttribute('aria-busy');
                document.getElementById('exportAuditBtn').disabled = false;
            }
        }
    }

    let timer;
    filters.search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 250); });
    filters.module.addEventListener('change', refresh);
    filters.date.addEventListener('change', refresh);
    document.getElementById('exportAuditBtn').addEventListener('click', () => {
        window.location = `${config.exportUrl}${appliedFilters.size ? '?' + appliedFilters : ''}`;
    });
});
