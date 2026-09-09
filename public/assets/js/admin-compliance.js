document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.compliancePage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const form = document.getElementById('reviewForm');
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    const detailsModal = new bootstrap.Modal(document.getElementById('reviewDetailsModal'));
    const pageError = document.getElementById('compliancePageError');
    const tabs = ['dashboard', 'calendar', 'checklist', 'reviews'];
    let data = config.initial;
    let activeTab = tabs.includes(config.activeTab) ? config.activeTab : 'dashboard';
    let calendarView = 'month';
    let calMonth = new Date();
    calMonth.setDate(1);
    let editing = null;
    let detailsNext = null;
    let saving = false;
    let listTable;

    const cards = [
        ['action_required', 'Employees Requiring Action', 'danger', 'bi-list-check'],
        ['rtw_due', 'Right-to-Work Reviews Due', 'warning', 'bi-patch-check'],
        ['immigration_near_expiry', 'Immigration Permissions Nearing Expiry', 'warning', 'bi-passport'],
        ['missing_info', 'Missing Employee Information', 'info', 'bi-person-exclamation'],
        ['unresolved_attendance', 'Unresolved Attendance Issues', 'warning', 'bi-clipboard-x'],
        ['docs_expiring', 'Documents Nearing Expiry', 'warning', 'bi-file-earmark-text'],
        ['sponsor_events_under_review', 'Sponsor Events Under Review', 'info', 'bi-shield-exclamation'],
        ['overdue_reviews', 'Overdue Internal Reviews', 'danger', 'bi-journal-x'],
    ];

    function showError(element, message) {
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
        showError(document.getElementById('reviewFormError'), '');
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
        showError(document.getElementById('reviewFormError'), other.join(' ') || (Object.keys(errors).length ? '' : fallback));
        if (first) first.focus();
    }

    function fillForm(values) {
        Object.entries(values || {}).forEach(([name, value]) => {
            const field = form.elements[name];
            if (field) field.value = value ?? '';
        });
    }

    function syncUrl(tab) {
        const query = new URLSearchParams();
        if (tab !== 'dashboard') query.set('tab', tab);
        history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
    }

    function activateTab(tab) {
        activeTab = tabs.includes(tab) ? tab : 'dashboard';
        document.querySelectorAll('#complianceTabs [data-tab]').forEach(button => {
            const selected = button.dataset.tab === activeTab;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', selected);
        });
        tabs.forEach(name => document.getElementById(`tab-${name}`).classList.toggle('d-none', name !== activeTab));
        syncUrl(activeTab);
        if (activeTab === 'calendar') renderCalendar();
        if (activeTab === 'reviews' && listTable) listTable.columns.adjust();
    }

    function renderDashboard() {
        const metrics = data.metrics;
        document.getElementById('complianceKpis').innerHTML = cards.map(([key, label, accent, icon]) => {
            const value = metrics[key] || 0;
            return `<div class="metric-card metric-accent-${value > 0 ? accent : 'neutral'}"><span class="metric-icon"><i class="bi ${icon}"></i></span><span class="metric-label">${esc(label)}</span><span class="metric-value">${value}</span><span class="metric-context">${value === 0 ? 'No immediate action' : value + (value === 1 ? ' item' : ' items') + ' to review'}</span></div>`;
        }).join('');
        const items = data.actions || [];
        document.getElementById('complianceActions').innerHTML = items.length
            ? items.map(item => `<div class="action-item"><span class="action-priority-dot priority-dot-${esc(item.priority.toLowerCase())}"></span><div class="action-body"><div class="action-title">${esc(item.issue)}</div><div class="action-meta"><span>${esc(item.employee)}</span>${item.due_date ? `<span>Due ${fmt.date(item.due_date)}</span>` : ''}</div></div><a href="${esc(item.href)}" class="btn btn-sm btn-light-custom">Review</a></div>`).join('')
            : `<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div><div class="empty-state-title">No immediate action required</div><div class="empty-state-text">Internal controls indicate all monitored records are up to date.</div></div>`;
    }

    function renderCalendar() {
        const events = data.events || [];
        const body = document.getElementById('calendarBody');
        document.getElementById('viewMonthBtn').className = `btn btn-sm ${calendarView === 'month' ? 'btn-primary' : 'btn-light-custom'}`;
        document.getElementById('viewListBtn').className = `btn btn-sm ${calendarView === 'list' ? 'btn-primary' : 'btn-light-custom'}`;
        document.getElementById('monthNav').classList.toggle('d-none', calendarView !== 'month');
        if (calendarView === 'list') {
            const upcoming = events.filter(event => {
                const days = fmt.daysUntil(event.date);
                return days !== null && days >= -7;
            });
            body.innerHTML = `<div class="table-panel"><table class="table-app" id="calendarListTable" style="width:100%;"><thead><tr><th>Date</th><th>Type</th><th>Details</th></tr></thead><tbody></tbody></table></div>`;
            HR.ui.initDataTable('#calendarListTable', {
                data: upcoming,
                paging: false,
                info: false,
                order: [[0, 'asc']],
                emptyIcon: 'bi-calendar-week',
                emptyTitle: 'No compliance events scheduled',
                emptyText: 'Upcoming right-to-work, document and review dates will appear here.',
                columns: [
                    { data: 'date', render: (value, type) => type === 'display' ? fmt.date(value) : value },
                    { data: null, render: (row) => fmt.statusBadge(row.tone === 'danger' ? 'Overdue' : row.type) },
                    { data: 'label', render: (value) => esc(value) },
                ],
            });
            return;
        }
        const year = calMonth.getFullYear();
        const month = calMonth.getMonth();
        document.getElementById('monthLabel').textContent = calMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        const startOffset = (new Date(year, month, 1).getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let cells = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => `<div class="cal-day-head">${day}</div>`).join('');
        for (let i = 0; i < startOffset; i += 1) cells += '<div class="cal-day-cell is-muted"></div>';
        for (let day = 1; day <= daysInMonth; day += 1) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = events.filter(event => event.date === dateStr);
            cells += `<div class="cal-day-cell"><div class="cal-day-num">${day}</div>${dayEvents.map(event => `<span class="cal-event-chip chip-${esc(event.tone)}" title="${esc(event.label)}">${esc(event.label)}</span>`).join('')}</div>`;
        }
        body.innerHTML = `<div class="calendar-month-grid">${cells}</div>`;
    }

    function renderChecklist() {
        const select = document.getElementById('checklistEmployeeSelect');
        const employees = data.employees || [];
        const current = select.value;
        select.innerHTML = employees.map(employee => `<option value="${employee.id}">${esc(employee.name)}</option>`).join('') || '<option value="">No current employees</option>';
        if (current && employees.some(employee => String(employee.id) === current)) select.value = current;
        const selected = employees.find(employee => String(employee.id) === select.value);
        document.getElementById('employeeChecklist').innerHTML = (selected?.checklist || []).map(item => `<div class="checklist-item"><input class="form-check-input" type="checkbox" disabled ${item.complete ? 'checked' : ''}><span class="small ${item.complete ? '' : 'text-secondary-custom'}">${esc(item.label)}${item.complete ? '' : ' outstanding'}</span></div>`).join('');
    }

    const reviewsTable = HR.ui.initDataTable('#reviewsTable', {
        data: data.reviews,
        order: [[1, 'desc']],
        emptyIcon: 'bi-journal-check',
        emptyTitle: 'No internal reviews recorded',
        emptyText: 'Internal HR compliance reviews will appear here once logged.',
        columns: [
            { data: 'review_number', className: 'cell-primary' },
            { data: 'review_date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'reviewer', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'area', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'issues_found', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || 'None recorded') : value || '' },
            { data: 'result', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'due_date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
        ],
    });
    listTable = reviewsTable;

    function renderAll() {
        renderDashboard();
        renderChecklist();
        reviewsTable.clear().rows.add(data.reviews).draw();
        if (activeTab === 'calendar') renderCalendar();
    }

    async function refresh() {
        try {
            data = await api(config.indexUrl);
            renderAll();
            showError(pageError, '');
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    function nextReviewNumber() {
        return `REV-${new Date().getFullYear()}-${String((data.reviews || []).length + 1).padStart(3, '0')}`;
    }

    function openCreate() {
        editing = null;
        form.reset();
        form.action = config.storeUrl;
        document.getElementById('reviewModalTitle').textContent = 'New Internal Compliance Review';
        document.getElementById('reviewDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('reviewNumber').value = nextReviewNumber();
        document.getElementById('reviewer').value = config.actor;
        clearValidation();
        modal.show();
    }

    async function openEdit(row) {
        editing = row;
        form.reset();
        form.action = row.update_url;
        document.getElementById('reviewModalTitle').textContent = 'Edit Internal Compliance Review';
        clearValidation();
        modal.show();
        try {
            fillForm(await api(row.details_url));
        } catch (error) {
            showValidation({}, error.message);
        }
    }

    async function openDetails(row) {
        const body = document.getElementById('reviewDetailsBody');
        document.getElementById('reviewDetailsTitle').textContent = row.review_number;
        detailsNext = { edit: () => openEdit(row), remove: () => removeReview(row) };
        body.textContent = 'Loading review…';
        detailsModal.show();
        try {
            const detail = await api(row.details_url);
            const fields = {
                'Review number': detail.review_number, Date: fmt.date(detail.review_date), Reviewer: detail.reviewer,
                Area: detail.area, 'Employees sampled': detail.employees_sampled, 'Records reviewed': detail.records_reviewed,
                'Issues found': detail.issues_found, 'Actions required': detail.actions_required,
                'Responsible person': detail.responsible_person, Result: detail.result,
                'Due date': fmt.date(detail.due_date), 'Completion date': fmt.date(detail.completion_date),
            };
            body.innerHTML = '<dl class="mb-0">' + Object.entries(fields).map(([label, value]) => `<dt class="text-meta">${esc(label)}</dt><dd>${esc(value || '—')}</dd>`).join('') + '</dl>';
        } catch (error) {
            body.textContent = error.message;
        }
    }

    async function removeReview(row) {
        const confirmed = await HR.ui.confirmAction({ title: 'Remove review', body: 'Remove this internal compliance review?', confirmLabel: 'Remove' });
        if (!confirmed) return;
        try {
            const result = await api(row.destroy_url, { method: 'DELETE' });
            detailsModal.hide();
            HR.ui.toastSuccess('Review removed', result.message);
            await refresh();
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    document.querySelectorAll('#complianceTabs [data-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.tab)));
    document.getElementById('viewMonthBtn').addEventListener('click', () => { calendarView = 'month'; renderCalendar(); });
    document.getElementById('viewListBtn').addEventListener('click', () => { calendarView = 'list'; renderCalendar(); });
    document.getElementById('prevMonthBtn').addEventListener('click', () => { calMonth.setMonth(calMonth.getMonth() - 1); renderCalendar(); });
    document.getElementById('nextMonthBtn').addEventListener('click', () => { calMonth.setMonth(calMonth.getMonth() + 1); renderCalendar(); });
    document.getElementById('checklistEmployeeSelect').addEventListener('change', renderChecklist);
    document.getElementById('newReviewBtn').addEventListener('click', openCreate);
    document.querySelector('#reviewsTable tbody').addEventListener('click', event => {
        const row = reviewsTable.row(event.target.closest('tr')).data();
        if (row) openDetails(row);
    });
    document.getElementById('reviewDetailsEdit').addEventListener('click', () => {
        if (!detailsNext) return;
        const next = detailsNext.edit;
        document.getElementById('reviewDetailsModal').addEventListener('hidden.bs.modal', () => next(), { once: true });
        detailsModal.hide();
    });
    document.getElementById('reviewDetailsRemove').addEventListener('click', () => detailsNext?.remove());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        clearValidation();
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        const body = new FormData(form);
        if (editing) body.append('_method', 'PUT');
        try {
            const result = await api(form.action, { method: 'POST', body });
            saving = false;
            modal.hide();
            HR.ui.toastSuccess(editing ? 'Review updated' : 'Review saved', result.message);
            await refresh();
        } catch (error) {
            showValidation(error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    });

    renderAll();
    activateTab(activeTab);
    if (config.openNew) openCreate();
});
