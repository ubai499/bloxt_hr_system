document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const config = window.attendancePage, fmt = HR.format, esc = fmt.escapeHtml;
    const pageError = document.getElementById('attendancePageError');
    const attForm = document.getElementById('attForm'), absForm = document.getElementById('absForm');
    const attModal = new bootstrap.Modal(document.getElementById('attModal'));
    const absModal = new bootstrap.Modal(document.getElementById('absModal'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const params = new URLSearchParams(location.search);
    const filterFields = { date: document.getElementById('attDateFilter'), status: document.getElementById('attStatusFilter'), employee: document.getElementById('attEmployeeFilter') };
    let data = config.initial, activeTab = config.activeTab, editing = null, refreshNumber = 0, openingReview = false;
    const busyForms = new Set();
    const text = (value, type) => type === 'display' ? esc(value ?? '—') : value ?? '';
    const date = (value, type) => type === 'display' ? fmt.date(value) : value || '';
    const attendance = HR.ui.initDataTable('#attendanceTable', {
        data: data.attendance, order: [[1, 'desc']], emptyIcon: 'bi-calendar-check',
        emptyTitle: 'No attendance records match these filters', emptyText: 'Adjust your filters or record a new attendance entry.',
        columns: [
            { data: 'employee', render: (value, type) => type === 'display' ? `<div class="employee-cell"><span class="avatar-circle">${esc(fmt.initials(value.split(' ')[0], value.split(' ').slice(-1)[0]))}</span>${esc(value)}</div>` : value },
            { data: 'date', render: date }, { data: 'expected_start', render: text },
            { data: 'clock_in', render: text }, { data: 'clock_out', render: text },
            { data: 'hours' }, { data: 'work_location', render: text },
            { data: 'status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'notes', className: 'cell-secondary', render: text },
            { data: null, orderable: false, render: row => row.manager_reviewed ? '<i class="bi bi-check-circle text-success" role="img" aria-label="Reviewed"></i>' : `<button type="button" class="btn btn-sm btn-outline-primary" data-review="${row.id}">Mark reviewed</button>` }
        ]
    });
    const absences = HR.ui.initDataTable('#absenceTable', {
        data: data.absences, order: [[1, 'desc']], emptyIcon: 'bi-clipboard-x',
        emptyTitle: 'No absence records', emptyText: 'Absence records will appear here once logged.',
        columns: [
            { data: 'employee', render: (value, type, row) => type === 'display' ? `<button type="button" class="absence-record-trigger border-0 bg-transparent p-0 text-start" data-absence="${row.id}" title="Review absence record" aria-label="Review absence for ${esc(value)}">${esc(value)}</button>` : value },
            { data: 'date', render: date },
            { data: 'absence_type', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'reason', render: text },
            { data: 'reported_date', type: 'string', render: (value, type, row) => type === 'display' ? (value ? `${fmt.date(value)} (${esc(row.how_reported || '—')})` : 'Not reported') : value || '' },
            { data: 'expected_return', type: 'string', render: date },
            { data: 'authorised', render: (value, type) => type === 'display' ? (value ? '<i class="bi bi-check-circle text-success"></i> Yes' : '<i class="bi bi-x-circle text-danger"></i> No') : (value ? 'Yes' : 'No') },
            { data: 'follow_up_required', render: (value, type) => type === 'display' ? (value ? fmt.statusBadge('Follow-up Required') : '—') : (value ? 'Follow-up Required' : '') }
        ]
    });
    const filterState = HR.ui.createFilterState(attendance, 'attendanceTable');

    function message(element, value) { element.textContent = value || ''; element.classList.toggle('d-none', !value); }
    async function api(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || response.redirected) {
            const error = new Error(response.status === 419 || response.status === 401 || response.redirected ? 'Your session has expired. Refresh the page and sign in again.' : result.message || 'Unable to complete the request. Please try again.');
            error.errors = result.errors || {}; throw error;
        }
        return result;
    }
    function updateUrl() {
        const url = new URL(config.indexUrl);
        if (activeTab === 'absence') url.searchParams.set('tab', 'absence');
        Object.entries(filterFields).forEach(([key, field]) => { if (field.value) url.searchParams.set(key, field.value); });
        history.replaceState(null, '', url);
    }
    function applyFilters() {
        Object.entries(filterFields).forEach(([key, field]) => {
            const value = field.value;
            filterState.set(key, !value ? null : row => String(row[key === 'employee' ? 'employee_id' : key]) === value);
        });
        updateUrl();
    }
    function activateTab(tab) {
        activeTab = tab;
        document.querySelectorAll('#attTabs [data-tab]').forEach(button => {
            const selected = button.dataset.tab === tab;
            button.classList.toggle('active', selected); button.setAttribute('aria-selected', selected); button.tabIndex = selected ? 0 : -1;
        });
        ['attendance', 'absence'].forEach(name => document.getElementById(`tab-${name}`).classList.toggle('d-none', name !== tab));
        document.querySelectorAll('.sidebar-nav-link').forEach(link => {
            if (!link.getAttribute('href') || link.getAttribute('href').startsWith('#')) return;
            const url = new URL(link.href);
            if (url.pathname !== new URL(config.indexUrl).pathname) return;
            const selected = (url.searchParams.get('tab') || 'attendance') === tab;
            link.classList.toggle('active', selected);
            if (selected) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        (tab === 'absence' ? absences : attendance).columns.adjust();
        updateUrl();
    }
    function renderAlerts() {
        document.getElementById('alertsList').innerHTML = data.alerts.length ? data.alerts.map((alert, index) => {
            const url = new URL(config.indexUrl);
            url.searchParams.set('tab', alert.absence_id ? 'absence' : 'attendance');
            if (alert.absence_id) url.searchParams.set('highlight', alert.absence_id);
            else { url.searchParams.set('employee', alert.employee_id); if (alert.date) url.searchParams.set('date', alert.date); }
            return `<div class="action-item"><span class="action-priority-dot priority-dot-${alert.tone === 'danger' ? 'high' : alert.tone === 'warning' ? 'medium' : 'low'}"></span><div class="action-body"><div class="action-title">${esc(alert.type)}</div><div class="action-meta"><span><i class="bi bi-person"></i> ${esc(alert.employee)}</span>${alert.date ? `<span><i class="bi bi-calendar-event"></i> ${fmt.date(alert.date)}</span>` : ''}</div></div><a class="btn btn-sm btn-light-custom" href="${esc(url.href)}" data-alert="${index}">Review</a></div>`;
        }).join('') : '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div><div class="empty-state-title">No review alerts</div><div class="empty-state-text">Attendance patterns look consistent nothing needs review right now.</div></div>';
    }
    async function refresh() {
        const sequence = ++refreshNumber;
        try {
            const result = await api(config.indexUrl);
            if (sequence !== refreshNumber) return;
            data = result;
            attendance.clear().rows.add(data.attendance).draw(false);
            absences.clear().rows.add(data.absences).draw(false);
            renderAlerts();
            (activeTab === 'absence' ? absences : attendance).columns.adjust();
            message(pageError, '');
        } catch (error) { message(pageError, error.message); }
    }
    function clearErrors(form) {
        form.querySelectorAll('.field-invalid').forEach(node => node.classList.remove('field-invalid'));
        form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(node => { node.textContent = ''; });
        message(form.querySelector('[data-form-error]'), '');
    }
    function formErrors(form, errors, fallback = '') {
        clearErrors(form);
        let first; const other = [];
        Object.entries(errors).forEach(([name, values]) => {
            const field = form.querySelector(`[name="${name}"]`), feedback = form.querySelector(`[data-error-for="${name}"]`);
            const value = Array.isArray(values) ? values[0] : values;
            if (!field || !feedback) { other.push(value); return; }
            field.closest('.form-field').classList.add('field-invalid'); field.setAttribute('aria-invalid', 'true'); feedback.textContent = value; first ||= field;
        });
        message(form.querySelector('[data-form-error]'), other.join(' ') || (Object.keys(errors).length ? '' : fallback));
        first?.focus();
    }
    function reportingDate() {
        const reported = !!(absForm.elements.how_reported.value.trim() || absForm.elements.reported_to.value.trim() || absForm.elements.reported_date.value || editing);
        document.getElementById('absenceReportingDate').classList.toggle('d-none', !reported);
        absForm.elements.reported_date.required = !!(absForm.elements.how_reported.value.trim() || absForm.elements.reported_to.value.trim());
    }
    function reset(form) {
        form.reset(); clearErrors(form); form.elements.date.value = config.today;
        form.querySelectorAll('option[data-historical]').forEach(option => option.remove());
        const employee = filterFields.employee.value || params.get('employee');
        if ([...form.elements.employee_id.options].some(option => option.value === employee)) form.elements.employee_id.value = employee;
    }
    function openAttendance() { reset(attForm); attModal.show(); }
    function openAbsence() {
        editing = null; reset(absForm);
        document.getElementById('absModalTitle').textContent = 'Record Absence';
        absForm.querySelector('[type="submit"]').textContent = 'Save Absence Record';
        document.getElementById('absenceReviewFields').classList.add('d-none'); reportingDate(); absModal.show();
    }
    async function reviewAbsence(id) {
        if (openingReview) return;
        const row = data.absences.find(row => row.id === Number(id));
        if (!row) { message(pageError, 'This absence record is no longer available. Refresh the page.'); return; }
        openingReview = true;
        try {
            const record = await api(row.details_url);
            reset(absForm); editing = record;
            if (![...absForm.elements.employee_id.options].some(option => option.value === String(record.employee_id))) {
                const option = new Option(record.employee, record.employee_id); option.dataset.historical = '1'; absForm.elements.employee_id.add(option);
            }
            Object.entries(record).forEach(([name, value]) => {
                const field = absForm.elements.namedItem(name);
                if (field) { if (field.type === 'checkbox') field.checked = !!value; else field.value = value ?? ''; }
            });
            document.getElementById('absModalTitle').textContent = 'Review Absence';
            absForm.querySelector('[type="submit"]').textContent = 'Save Review';
            document.getElementById('absenceReviewFields').classList.remove('d-none');
            document.getElementById('absenceReviewMeta').textContent = record.reviewed_by ? `Last reviewed by ${record.reviewed_by} · ${record.reviewed_at}` : '';
            reportingDate(); absModal.show();
        } catch (error) { message(pageError, error.message); }
        finally { openingReview = false; }
    }
    function highlight(table, id) {
        const index = table.rows().indexes().toArray().find(index => table.row(index).data().id === Number(id));
        if (index === undefined) return;
        const position = table.rows({ search: 'applied', order: 'applied' }).indexes().toArray().indexOf(index);
        if (position < 0) return;
        table.page(Math.floor(position / table.page.len())).draw('page');
        const node = table.row(index).node(); node.classList.add('record-highlight'); node.scrollIntoView({ block: 'center' });
    }
    for (const [form, modal] of [[attForm, attModal], [absForm, absModal]]) {
        const element = form.closest('.modal');
        element.addEventListener('hide.bs.modal', event => { if (busyForms.has(form)) event.preventDefault(); });
        element.addEventListener('shown.bs.modal', () => (form.querySelector('[aria-invalid="true"]') || form.elements.employee_id).focus());
        form.addEventListener('submit', async event => {
            event.preventDefault(); if (busyForms.has(form)) return;
            clearErrors(form); busyForms.add(form);
            const button = form.querySelector('[type="submit"]'); button.disabled = true; button.classList.add('is-loading');
            try {
                const body = new FormData(form);
                if (form === absForm) { body.set('authorised', Number(form.elements.authorised.checked)); body.set('follow_up_required', Number(form.elements.follow_up_required.checked)); if (editing) body.set('_method', 'PUT'); }
                const result = await api(form === absForm && editing ? editing.update_url : form.action, { method: 'POST', body });
                busyForms.delete(form); modal.hide();
                HR.ui.toastSuccess(form === absForm ? (editing ? 'Absence reviewed' : 'Absence recorded') : 'Attendance saved', result.message);
                await refresh();
            } catch (error) { formErrors(form, error.errors || {}, error.message); }
            finally { busyForms.delete(form); button.disabled = false; button.classList.remove('is-loading'); }
        });
    }
    document.getElementById('recordAttendanceBtn').addEventListener('click', openAttendance);
    document.getElementById('recordAbsenceBtn').addEventListener('click', openAbsence);
    absForm.addEventListener('input', reportingDate);
    absForm.elements.absence_type.addEventListener('change', () => {
        if (absForm.elements.absence_type.value === 'Authorised Absence') absForm.elements.authorised.checked = true;
        if (absForm.elements.absence_type.value === 'Unauthorised Absence') { absForm.elements.authorised.checked = false; absForm.elements.follow_up_required.checked = true; }
    });
    Object.entries(filterFields).forEach(([key, field]) => { field.value = params.get(key) || ''; field.addEventListener('change', applyFilters); });
    document.querySelectorAll('#attTabs [data-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.tab)));
    document.getElementById('attTabs').addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault(); const tab = event.key === 'Home' ? 'attendance' : event.key === 'End' ? 'absence' : activeTab === 'absence' ? 'attendance' : 'absence';
        activateTab(tab); document.getElementById(`${tab}Tab`).focus();
    });
    document.getElementById('absenceTable').addEventListener('click', event => { const button = event.target.closest('[data-absence]'); if (button) reviewAbsence(button.dataset.absence); });
    document.getElementById('attendanceTable').addEventListener('click', async event => {
        const button = event.target.closest('[data-review]'); if (!button || button.disabled) return;
        button.disabled = true;
        try { const record = data.attendance.find(record => record.id === Number(button.dataset.review)); const result = await api(record.review_url, { method: 'PATCH' }); HR.ui.toastSuccess('Marked reviewed', result.message); await refresh(); }
        catch (error) { message(pageError, error.message); }
        finally { button.disabled = false; }
    });
    document.getElementById('alertsList').addEventListener('click', event => {
        const link = event.target.closest('[data-alert]'); if (!link) return;
        event.preventDefault(); const alert = data.alerts[Number(link.dataset.alert)];
        if (alert.absence_id) { activateTab('absence'); highlight(absences, alert.absence_id); reviewAbsence(alert.absence_id); }
        else { filterFields.employee.value = alert.employee_id; filterFields.date.value = alert.date || ''; filterFields.status.value = ''; applyFilters(); activateTab('attendance'); if (alert.attendance_id) highlight(attendance, alert.attendance_id); }
    });
    document.getElementById('exportAttBtn').addEventListener('click', async event => {
        const button = event.currentTarget; button.disabled = true;
        try {
            const query = new URLSearchParams(); Object.entries(filterFields).forEach(([key, field]) => { if (field.value) query.set(key, field.value); });
            const [column, direction] = attendance.order()[0] || [1, 'desc'];
            query.set('sort', ['employee', 'date', 'expected_start', 'clock_in', 'clock_out', 'hours', 'work_location', 'status', 'notes'][column]); query.set('direction', direction);
            const response = await fetch(`${config.exportUrl}?${query}`, { headers: { Accept: 'text/csv, application/json' } });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Export failed. Refresh the page and try again.');
            const url = URL.createObjectURL(await response.blob()), link = document.createElement('a'); link.href = url; link.download = 'attendance-records.csv'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
            HR.ui.toastSuccess('Export complete', 'attendance-records.csv has been downloaded.');
        } catch (error) { message(pageError, error.message); }
        finally { button.disabled = false; }
    });
    renderAlerts(); applyFilters(); activateTab(config.activeTab);
    if (params.get('highlight')) { highlight(absences, params.get('highlight')); if (params.get('review') === '1') reviewAbsence(params.get('highlight')); }
    if (params.get('new') === '1') activeTab === 'absence' ? openAbsence() : openAttendance();
    if (Object.keys(config.errors).length) {
        const form = config.old._form === 'absence' ? absForm : config.old._form === 'attendance' ? attForm : activeTab === 'absence' ? absForm : attForm;
        form === absForm ? openAbsence() : openAttendance();
        Object.entries(config.old).forEach(([name, value]) => { const field = form.elements.namedItem(name); if (field) { if (field.type === 'checkbox') field.checked = value === '1'; else field.value = value ?? ''; } });
        reportingDate(); formErrors(form, config.errors);
    }
});
