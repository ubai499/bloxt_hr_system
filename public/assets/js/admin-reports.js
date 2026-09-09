document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.reportsPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const form = document.getElementById('taskForm');
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    const pageError = document.getElementById('reportsPageError');
    const tabs = ['catalogue', 'tasks', 'notifications'];
    let data = config.initial;
    let activeTab = tabs.includes(config.activeTab) ? config.activeTab : 'catalogue';
    let editing = null;
    let saving = false;
    let reportTable;
    let tasksTable;
    let notificationsTable;

    function showError(element, message) {
        if (!element) return;
        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
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
        showError(document.getElementById('taskFormError'), '');
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
        showError(document.getElementById('taskFormError'), other.join(' ') || (Object.keys(errors).length ? '' : fallback));
        if (first) first.focus();
    }

    function activateTab(tab) {
        activeTab = tabs.includes(tab) ? tab : 'catalogue';
        document.querySelectorAll('#reportsTabs [data-tab]').forEach(button => {
            const on = button.dataset.tab === activeTab;
            button.classList.toggle('active', on);
            button.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        tabs.forEach(name => document.getElementById(`tab-${name}`).classList.toggle('d-none', name !== activeTab));
        const url = new URL(config.indexUrl, window.location.origin);
        if (activeTab !== 'catalogue') url.searchParams.set('tab', activeTab);
        history.replaceState(null, '', url);
    }

    async function renderReport(id) {
        const output = document.getElementById('reportOutput');
        output.innerHTML = '<div class="text-meta">Loading report…</div>';
        try {
            const report = await api(config.showUrl.replace('__id__', id));
            document.getElementById('printMeta').textContent = `${report.title} generated ${fmt.date(new Date().toISOString(), { withTime: true })} by ${esc(config.actor)}`;
            output.innerHTML = `
                <div class="table-panel">
                    <div class="table-toolbar">
                        <div><span class="fw-semibold">${esc(report.title)}</span><div class="text-meta">${report.rows.length} record(s)</div></div>
                        <div class="table-toolbar-actions">
                            <button type="button" class="btn btn-sm btn-light-custom" id="printReportBtn"><i class="bi bi-printer"></i> Print</button>
                            <button type="button" class="btn btn-sm btn-light-custom" id="exportReportBtn"><i class="bi bi-download"></i> Export CSV</button>
                        </div>
                    </div>
                    <table class="table-app" id="dynamicReportTable" style="width:100%;">
                        <thead><tr>${report.columns.map(column => `<th>${esc(column)}</th>`).join('')}</tr></thead>
                        <tbody></tbody>
                    </table>
                </div>`;
            if (reportTable) reportTable.destroy();
            reportTable = HR.ui.initDataTable('#dynamicReportTable', {
                data: report.rows,
                emptyIcon: 'bi-bar-chart',
                emptyTitle: 'No records for this report',
                emptyText: 'There is currently no data matching this report.',
                columns: report.columns.map((_, index) => ({
                    data: index,
                    render: (value, type) => type === 'display' ? esc(value || '—') : (value || ''),
                })),
            });
            document.getElementById('exportReportBtn').addEventListener('click', () => {
                window.location = `${config.exportUrl.replace('__id__', id)}${window.location.search}`;
            });
            document.getElementById('printReportBtn').addEventListener('click', () => HR.ui.printPage());
            output.scrollIntoView({ behavior: 'smooth' });
        } catch (error) {
            output.innerHTML = '';
            showError(pageError, error.message);
        }
    }

    function renderTasks() {
        if (tasksTable) {
            tasksTable.clear().rows.add(data.tasks).draw();
            return;
        }
        tasksTable = HR.ui.initDataTable('#tasksTable', {
            data: data.tasks,
            order: [[5, 'asc']],
            emptyIcon: 'bi-list-task',
            emptyTitle: 'No HR tasks',
            emptyText: 'Tasks created for HR follow-up will appear here.',
            columns: [
                { data: 'title', className: 'cell-primary', render: (value, type) => type === 'display' ? esc(value) : value },
                { data: 'employee', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
                { data: 'category', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
                { data: 'assigned_to', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
                { data: 'priority', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
                { data: 'due_date', render: (value, type) => type === 'display' ? (value ? fmt.date(value) : '—') : (value || '9999-99-99') },
                { data: 'status', orderable: false, render: (value, type, row) => type === 'display'
                    ? `<select class="form-select form-select-sm task-status-select" data-url="${esc(row.status_url)}" aria-label="Change status for ${esc(row.title)}">${config.statuses.map(status => `<option ${status === value ? 'selected' : ''}>${esc(status)}</option>`).join('')}</select>`
                    : value },
            ],
            createdRow: (row, record) => { if (String(config.highlight) === String(record.id)) row.classList.add('table-active'); },
        });
    }

    function renderNotifications() {
        if (notificationsTable) {
            notificationsTable.clear().rows.add(data.notifications).draw();
            return;
        }
        notificationsTable = HR.ui.initDataTable('#notificationsTable', {
            data: data.notifications,
            order: [[2, 'desc']],
            emptyIcon: 'bi-bell',
            emptyTitle: 'No notifications',
            emptyText: 'You have no notifications right now.',
            columns: [
                { data: 'title', className: 'cell-primary', render: (value, type, row) => type === 'display'
                    ? (row.href ? `<a href="${esc(row.href)}">${esc(value)}</a>` : esc(value)) : value },
                { data: 'body', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '') : value || '' },
                { data: 'created_at', render: (value, type) => type === 'display' ? fmt.date(value, { withTime: true }) : value || '' },
                { data: 'status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
                { data: 'id', orderable: false, className: 'text-end', render: (value, type, row) => type === 'display' && row.resolve_url
                    ? `<button type="button" class="btn btn-sm btn-light-custom" data-resolve="${esc(row.resolve_url)}">Mark Resolved</button>` : '' },
            ],
        });
    }

    async function refresh() {
        try {
            data = await api(config.indexUrl);
            renderTasks();
            renderNotifications();
            showError(pageError, '');
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    function openTask(row) {
        editing = row;
        form.reset();
        clearValidation();
        document.getElementById('taskModalTitle').textContent = row ? 'Edit HR Task' : 'Create HR Task';
        form.action = row ? row.update_url : config.storeTaskUrl;
        if (row) {
            form.elements.title.value = row.title || '';
            form.elements.employee_id.value = row.employee_id || '';
            form.elements.category.value = row.category || '';
            form.elements.assigned_to.value = row.assigned_to || config.actor;
            form.elements.priority.value = row.priority || 'Medium';
            form.elements.due_date.value = row.due_date || '';
            form.elements.status.value = row.status || 'Open';
            form.elements.description.value = row.description || '';
        } else {
            form.elements.assigned_to.value = config.actor;
            form.elements.priority.value = 'Medium';
            form.elements.status.value = 'Open';
        }
        modal.show();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        try {
            const body = new FormData(form);
            if (editing) body.append('_method', 'PUT');
            await api(form.action, { method: 'POST', body });
            modal.hide();
            HR.ui.toastSuccess(editing ? 'Task updated' : 'Task created', editing ? 'The HR task has been updated.' : 'The HR task has been added.');
            editing = null;
            await refresh();
        } catch (error) {
            showValidation(error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    });

    document.querySelectorAll('#reportsTabs [data-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.tab)));
    document.querySelectorAll('[data-report]').forEach(button => button.addEventListener('click', () => renderReport(button.dataset.report)));
    document.getElementById('newTaskBtn').addEventListener('click', () => openTask(null));
    document.getElementById('taskStatusFilter').addEventListener('change', event => {
        tasksTable.column(6).search(event.target.value === 'all' ? '' : `^${event.target.value}$`, true, false).draw();
    });
    document.querySelector('#tasksTable tbody').addEventListener('change', async event => {
        const select = event.target.closest('.task-status-select');
        if (!select || saving) return;
        saving = true;
        try {
            const body = new FormData();
            body.append('_method', 'PATCH');
            body.append('status', select.value);
            await api(select.dataset.url, { method: 'POST', body });
            HR.ui.toastSuccess('Task updated', 'Status changed to ' + select.value + '.');
            await refresh();
        } catch (error) {
            showError(pageError, error.message);
        } finally {
            saving = false;
        }
    });
    document.querySelector('#tasksTable tbody').addEventListener('click', event => {
        if (event.target.closest('.task-status-select')) return;
        const row = tasksTable.row(event.target.closest('tr')).data();
        if (row) openTask(row);
    });
    document.querySelector('#notificationsTable tbody').addEventListener('click', async event => {
        const button = event.target.closest('[data-resolve]');
        if (!button || saving) return;
        saving = true;
        try {
            const body = new FormData();
            body.append('_method', 'PATCH');
            await api(button.dataset.resolve, { method: 'POST', body });
            HR.ui.toastSuccess('Notification resolved', '');
            await refresh();
        } catch (error) {
            showError(pageError, error.message);
        } finally {
            saving = false;
        }
    });

    renderTasks();
    renderNotifications();
    activateTab(activeTab);
    if (config.openNew) openTask(null);
});
