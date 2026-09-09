document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.sponsorshipPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const pageError = document.getElementById('sponsorPageError');
    const tabs = ['overview', 'workers', 'events', 'changes', 'guidance'];
    let data = config.initial;
    let activeTab = tabs.includes(config.activeTab) ? config.activeTab : 'overview';
    let saving = false;
    let detailsNext = null;
    let workersTable, eventsTable, changesTable, guidanceTable;

    const forms = {
        licence: document.getElementById('licenceForm'),
        worker: document.getElementById('workerForm'),
        event: document.getElementById('eventForm'),
        change: document.getElementById('changeForm'),
        guidance: document.getElementById('guidanceForm'),
    };
    const modals = {
        licence: new bootstrap.Modal(document.getElementById('licenceModal')),
        worker: new bootstrap.Modal(document.getElementById('workerModal')),
        event: new bootstrap.Modal(document.getElementById('eventModal')),
        change: new bootstrap.Modal(document.getElementById('changeModal')),
        guidance: new bootstrap.Modal(document.getElementById('guidanceModal')),
        details: new bootstrap.Modal(document.getElementById('sponsorDetailsModal')),
    };

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

    function clearValidation(form) {
        form.querySelectorAll('.field-invalid').forEach(field => field.classList.remove('field-invalid'));
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(field => { field.textContent = ''; });
        showError(form.querySelector('[id$="FormError"]'), '');
    }

    function showValidation(form, errors, fallback = '') {
        clearValidation(form);
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
        showError(form.querySelector('[id$="FormError"]'), other.join(' ') || (Object.keys(errors).length ? '' : fallback));
        if (first) first.focus();
    }

    function fillForm(form, values) {
        Object.entries(values || {}).forEach(([name, value]) => {
            const field = form.elements[name];
            if (!field || field.type === 'checkbox') return;
            field.value = value ?? '';
        });
        if (form.elements.reported_through_sms) form.elements.reported_through_sms.checked = !!values.reported_through_sms;
        if (form.elements.report_required) form.elements.report_required.checked = !!values.report_required;
        if (form.elements.reported) form.elements.reported.checked = !!values.reported;
    }

    function syncUrl(tab) {
        const query = new URLSearchParams();
        if (tab !== 'overview') query.set('tab', tab);
        history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
    }

    function activateTab(tab) {
        activeTab = tabs.includes(tab) ? tab : 'overview';
        document.querySelectorAll('#sponsorTabs [data-tab]').forEach(button => {
            const selected = button.dataset.tab === activeTab;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', selected);
        });
        tabs.forEach(name => document.getElementById(`tab-${name}`).classList.toggle('d-none', name !== activeTab));
        syncUrl(activeTab);
        if (activeTab === 'workers') workersTable.columns.adjust();
        if (activeTab === 'events') eventsTable.columns.adjust();
        if (activeTab === 'changes') changesTable.columns.adjust();
        if (activeTab === 'guidance') guidanceTable.columns.adjust();
    }

    function currentWorkers() {
        return data.workers.filter(row => row.sponsorship_status === 'Current');
    }

    function renderOverview() {
        document.querySelector('[data-stat="sponsored"]').textContent = data.stats.sponsored;
        document.querySelector('[data-stat="review_required"]').textContent = data.stats.review_required;
        document.querySelector('[data-stat="action_required"]').textContent = data.stats.action_required;
        document.querySelector('[data-stat="rating"]').textContent = data.stats.rating;
        const licence = data.licence;
        document.getElementById('licenceStatusBadge').innerHTML = fmt.statusBadge(licence.status);
        const fields = {
            'Licence reference': licence.reference, 'Licence rating': licence.rating,
            'Licence start date': fmt.date(licence.start_date), 'Renewal review date': fmt.date(licence.renewal_review_date),
            'Worker routes': (licence.worker_routes || []).join(', '), 'Authorising officer': licence.authorising_officer,
            'Key contact': licence.key_contact, 'Level 1 user': licence.level1_user,
            'Level 2 users': (licence.level2_users || []).join(', '),
            'Org details last reviewed': fmt.date(licence.org_details_last_reviewed),
            'Next internal review': fmt.date(licence.next_internal_review_date),
        };
        document.getElementById('licenceDetails').innerHTML = Object.entries(fields).map(([label, value]) => `<div class="detail-item"><dt>${esc(label)}</dt><dd>${esc(value || '—')}</dd></div>`).join('');
        const list = document.getElementById('workerStatusList');
        const current = currentWorkers();
        list.innerHTML = current.length
            ? current.map(row => `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="small">${esc(row.employee)}</span>${fmt.statusBadge(row.overall)}</div>`).join('')
            : '<p class="text-secondary-custom small mb-0">No sponsored workers currently recorded.</p>';
    }

    function refreshEventEmployees() {
        const select = document.getElementById('eventEmployee');
        const current = currentWorkers();
        select.innerHTML = current.length ? current.map(row => `<option value="${row.employee_id}">${esc(row.employee)}</option>`).join('') : '<option value="">No current sponsored workers</option>';
    }

    workersTable = HR.ui.initDataTable('#workersTable', {
        data: data.workers,
        order: [[0, 'asc']],
        emptyIcon: 'bi-shield-check',
        emptyTitle: 'No sponsored workers',
        emptyText: 'Sponsored worker records will appear here once created.',
        columns: [
            { data: 'employee', className: 'cell-primary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'role', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'department', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'soc_code', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'annual_salary', render: (value, type) => type === 'display' ? fmt.currency(value) : value || '' },
            { data: 'rtw_status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'contact_current', orderable: false, className: 'text-center', render: (value, type) => type === 'display' ? (value ? '<i class="bi bi-check-circle text-success" aria-label="Contact verified"></i>' : '<i class="bi bi-exclamation-circle text-warning" aria-label="Contact verification overdue"></i>') : (value ? 'Yes' : 'No') },
            { data: 'overall', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: null, orderable: false, className: 'text-end', render: (row) => row.profile_url ? `<a href="${esc(row.profile_url)}" class="btn btn-sm btn-light-custom" data-history>View</a>` : '' },
        ],
    });

    eventsTable = HR.ui.initDataTable('#eventsTable', {
        data: data.events,
        order: [[2, 'desc']],
        emptyIcon: 'bi-journal-check',
        emptyTitle: 'No sponsor events recorded',
        emptyText: 'Events that may require sponsor duty assessment will be logged here.',
        columns: [
            { data: 'employee', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'event_type' },
            { data: 'date_occurred', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'details', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'assigned_to', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: null, render: (row, type) => type === 'display' ? (row.reported_through_sms ? `Yes ${fmt.date(row.date_reported)}` : 'No') : (row.reported_through_sms ? 'Yes' : 'No') },
            { data: 'status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
        ],
        createdRow: (tr, row) => { if (String(config.highlight) === String(row.id)) tr.classList.add('table-active'); },
    });

    changesTable = HR.ui.initDataTable('#changesTable', {
        data: data.changes,
        order: [[0, 'desc']],
        emptyIcon: 'bi-building',
        emptyTitle: 'No company changes recorded',
        emptyText: 'Significant company changes that may affect sponsor status will appear here.',
        columns: [
            { data: 'date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'change_type' },
            { data: 'description', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'potential_sponsor_impact', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'report_required', render: (value, type) => type === 'display' ? (value ? 'Yes' : 'No') : value },
            { data: null, render: (row, type) => type === 'display' ? (row.reported ? fmt.date(row.reported_date) : 'Not yet') : (row.reported ? row.reported_date : '') },
        ],
    });

    guidanceTable = HR.ui.initDataTable('#guidanceTable', {
        data: data.guidance,
        paging: false,
        info: false,
        order: [[0, 'asc']],
        emptyIcon: 'bi-bookmark',
        emptyTitle: 'No guidance references saved',
        emptyText: 'Add links to authoritative guidance as they are reviewed.',
        columns: [
            { data: 'title', render: (value, type, row) => type === 'display' ? `<a href="${esc(row.url)}" target="_blank" rel="noopener">${esc(value)}</a>` : value },
            { data: 'source', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'last_reviewed', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'reviewed_by', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: 'notes', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
        ],
    });

    function redraw() {
        renderOverview();
        refreshEventEmployees();
        workersTable.clear().rows.add(data.workers).draw();
        eventsTable.clear().rows.add(data.events).draw();
        changesTable.clear().rows.add(data.changes).draw();
        guidanceTable.clear().rows.add(data.guidance).draw();
    }

    async function refresh() {
        try {
            data = await api(config.indexUrl);
            redraw();
            showError(pageError, '');
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    function openLicence() {
        const licence = data.licence;
        fillForm(forms.licence, { ...licence, worker_routes: (licence.worker_routes || []).join(', '), level2_users: (licence.level2_users || []).join(', ') });
        clearValidation(forms.licence);
        modals.licence.show();
    }

    function openWorker(row) {
        forms.worker.reset();
        forms.worker.action = row ? row.update_url : config.storeWorkerUrl;
        document.getElementById('workerModalTitle').textContent = row ? 'Edit Sponsored Worker' : 'Record Sponsored Worker';
        document.getElementById('workerEmployee').disabled = !!row;
        document.getElementById('workerHr').value = config.actor;
        document.getElementById('workerLicence').value = data.licence.reference || '';
        if (row) document.getElementById('workerEmployee').value = String(row.employee_id);
        else if (config.employee) document.getElementById('workerEmployee').value = String(config.employee);
        clearValidation(forms.worker);
        modals.worker.show();
        if (row) api(row.details_url).then(detail => fillForm(forms.worker, detail)).catch(error => showValidation(forms.worker, {}, error.message));
    }

    function openEvent(row) {
        refreshEventEmployees();
        forms.event.reset();
        forms.event.action = row ? row.update_url : config.storeEventUrl;
        document.getElementById('eventModalTitle').textContent = row ? 'Edit Sponsor Event' : 'Record Sponsor Event';
        document.getElementById('eventEmployee').disabled = !!row;
        document.getElementById('eventOccurred').value = new Date().toISOString().slice(0, 10);
        document.getElementById('eventAware').value = new Date().toISOString().slice(0, 10);
        document.getElementById('eventAssigned').value = config.actor;
        if (row) document.getElementById('eventEmployee').value = String(row.employee_id);
        clearValidation(forms.event);
        modals.event.show();
        if (row) api(row.details_url).then(detail => fillForm(forms.event, detail)).catch(error => showValidation(forms.event, {}, error.message));
    }

    function openChange(row) {
        forms.change.reset();
        forms.change.action = row ? row.update_url : config.storeChangeUrl;
        document.getElementById('changeModalTitle').textContent = row ? 'Edit Company Change' : 'Record Company Change';
        document.getElementById('changeDate').value = new Date().toISOString().slice(0, 10);
        clearValidation(forms.change);
        modals.change.show();
        if (row) api(row.details_url).then(detail => fillForm(forms.change, detail)).catch(error => showValidation(forms.change, {}, error.message));
    }

    function openGuidance(row) {
        forms.guidance.reset();
        forms.guidance.action = row ? row.update_url : config.storeGuidanceUrl;
        document.getElementById('guidanceModalTitle').textContent = row ? 'Edit Guidance Reference' : 'Add Guidance Reference';
        document.getElementById('guidanceReviewer').value = config.actor;
        document.getElementById('guidanceReviewed').value = new Date().toISOString().slice(0, 10);
        clearValidation(forms.guidance);
        modals.guidance.show();
        if (row) api(row.details_url).then(detail => fillForm(forms.guidance, detail)).catch(error => showValidation(forms.guidance, {}, error.message));
    }

    function fieldMap(kind, detail) {
        if (kind === 'worker') {
            return {
                Employee: detail.employee, Route: detail.worker_route, Status: detail.sponsorship_status,
                'Licence ref': detail.sponsor_licence_ref, 'CoS reference': detail.cos_reference,
                'CoS assigned': fmt.date(detail.cos_assigned_date), 'CoS start / end': `${fmt.date(detail.cos_start_date)} – ${fmt.date(detail.cos_end_date)}`,
                'SOC code': [detail.soc_code, detail.soc_title].filter(Boolean).join(' '),
                'Annual salary': fmt.currency(detail.annual_salary), 'Weekly hours': detail.weekly_hours,
                'Work pattern': detail.work_pattern, 'HR responsible': detail.hr_responsible_person,
                'Next review': fmt.date(detail.next_review_date), Notes: detail.notes, Overall: detail.overall,
            };
        }
        if (kind === 'event') {
            return {
                Worker: detail.employee, Type: detail.event_type, 'Date occurred': fmt.date(detail.date_occurred),
                'Date aware': fmt.date(detail.date_aware), Details: detail.details, 'Assigned to': detail.assigned_to,
                Status: detail.status, 'Reported via SMS': detail.reported_through_sms ? `Yes ${fmt.date(detail.date_reported)}` : 'No',
                Notes: detail.notes,
            };
        }
        if (kind === 'change') {
            return {
                Date: fmt.date(detail.date), Type: detail.change_type, Description: detail.description,
                Impact: detail.potential_sponsor_impact, 'Report required': detail.report_required ? 'Yes' : 'No',
                Reported: detail.reported ? fmt.date(detail.reported_date) : 'Not yet', Notes: detail.notes,
            };
        }
        return {
            Title: detail.title, Source: detail.source, URL: detail.url,
            'Last reviewed': fmt.date(detail.last_reviewed), 'Reviewed by': detail.reviewed_by, Notes: detail.notes,
        };
    }

    async function openDetails(kind, row) {
        const body = document.getElementById('sponsorDetailsBody');
        const profile = document.getElementById('sponsorDetailsProfile');
        document.getElementById('sponsorDetailsTitle').textContent = row.employee || row.title || row.change_type || 'Record details';
        profile.classList.toggle('d-none', !row.profile_url);
        if (row.profile_url) profile.href = row.profile_url;
        detailsNext = {
            edit: () => ({ worker: openWorker, event: openEvent, change: openChange, guidance: openGuidance }[kind](row)),
            remove: () => removeRecord(kind, row),
        };
        body.textContent = 'Loading record…';
        modals.details.show();
        try {
            const detail = await api(row.details_url);
            body.innerHTML = '<dl class="mb-0">' + Object.entries(fieldMap(kind, detail)).map(([label, value]) => `<dt class="text-meta">${esc(label)}</dt><dd>${esc(value || '—')}</dd>`).join('') + '</dl>';
        } catch (error) {
            body.textContent = error.message;
        }
    }

    async function removeRecord(kind, row) {
        const confirmed = await HR.ui.confirmAction({
            title: 'Remove record',
            body: 'Remove this record from the sponsor compliance workspace?',
            confirmLabel: 'Remove',
        });
        if (!confirmed) return;
        try {
            const result = await api(row.destroy_url, { method: 'DELETE' });
            modals.details.hide();
            HR.ui.toastSuccess('Record removed', result.message);
            await refresh();
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    async function submitForm(event, form, kind, successTitle) {
        event.preventDefault();
        if (saving) return;
        clearValidation(form);
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        const body = new FormData(form);
        if (form.action.includes('/licence') || form.querySelector('[name="_method"]')) {
            if (![...body.keys()].includes('_method')) body.append('_method', 'PUT');
        } else if (!form.action.endsWith('/workers') && !form.action.endsWith('/events') && !form.action.endsWith('/changes') && !form.action.endsWith('/guidance')) {
            body.append('_method', 'PUT');
        }
        try {
            const result = await api(form.action, { method: 'POST', body });
            saving = false;
            (modals[kind] || modals.details).hide();
            HR.ui.toastSuccess(successTitle, result.message);
            await refresh();
        } catch (error) {
            showValidation(form, error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    document.querySelectorAll('#sponsorTabs [data-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.tab)));
    document.getElementById('editLicenceBtn').addEventListener('click', openLicence);
    document.getElementById('newWorkerBtn').addEventListener('click', () => openWorker(null));
    document.getElementById('newEventBtn').addEventListener('click', () => openEvent(null));
    document.getElementById('newChangeBtn').addEventListener('click', () => openChange(null));
    document.getElementById('newGuidanceBtn').addEventListener('click', () => openGuidance(null));
    forms.licence.addEventListener('submit', event => submitForm(event, forms.licence, 'licence', 'Licence updated'));
    forms.worker.addEventListener('submit', event => submitForm(event, forms.worker, 'worker', 'Sponsorship record saved'));
    forms.event.addEventListener('submit', event => submitForm(event, forms.event, 'event', 'Sponsor event saved'));
    forms.change.addEventListener('submit', event => submitForm(event, forms.change, 'change', 'Company change saved'));
    forms.guidance.addEventListener('submit', event => submitForm(event, forms.guidance, 'guidance', 'Guidance saved'));
    document.getElementById('sponsorDetailsEdit').addEventListener('click', () => {
        if (!detailsNext) return;
        const next = detailsNext.edit;
        document.getElementById('sponsorDetailsModal').addEventListener('hidden.bs.modal', () => next(), { once: true });
        modals.details.hide();
    });
    document.getElementById('sponsorDetailsRemove').addEventListener('click', () => detailsNext?.remove());
    document.querySelector('#workersTable tbody').addEventListener('click', event => {
        if (event.target.closest('[data-history]')) return;
        const row = workersTable.row(event.target.closest('tr')).data();
        if (row) openDetails('worker', row);
    });
    document.querySelector('#eventsTable tbody').addEventListener('click', event => {
        const row = eventsTable.row(event.target.closest('tr')).data();
        if (row) openDetails('event', row);
    });
    document.querySelector('#changesTable tbody').addEventListener('click', event => {
        const row = changesTable.row(event.target.closest('tr')).data();
        if (row) openDetails('change', row);
    });
    document.querySelector('#guidanceTable tbody').addEventListener('click', event => {
        if (event.target.closest('a[href]')) return;
        const row = guidanceTable.row(event.target.closest('tr')).data();
        if (row) openDetails('guidance', row);
    });
    document.getElementById('openSmsBtn').addEventListener('click', async () => {
        const ok = await HR.ui.confirmAction({
            title: 'Open Sponsor Management System',
            body: `This will open the official Home Office Sponsor Management System in a new tab (${esc(data.licence.sms_url)}). This system does not integrate directly with SMS any updates must be made there separately.`,
            confirmLabel: 'Continue',
            confirmVariant: 'primary',
        });
        if (ok) window.open(data.licence.sms_url, '_blank', 'noopener');
    });

    renderOverview();
    refreshEventEmployees();
    activateTab(activeTab);
    if (config.openNew === 'worker' || config.openNew === '1' && activeTab === 'workers') openWorker(null);
    if (config.openNew === 'event' || (config.openNew === '1' && activeTab === 'events')) openEvent(null);
});
