document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.recruitmentPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const vacancyForm = document.getElementById('vacancyForm');
    const candidateForm = document.getElementById('candidateForm');
    const vacancyModal = new bootstrap.Modal(document.getElementById('vacancyModal'));
    const candidateModal = new bootstrap.Modal(document.getElementById('candidateModal'));
    const detailsModal = new bootstrap.Modal(document.getElementById('recruitmentDetailsModal'));
    const detailsEdit = document.getElementById('recruitmentDetailsEdit');
    let detailsEditHandler = null;
    const pageError = document.getElementById('recruitmentPageError');
    const vacancyFormError = document.getElementById('vacancyFormError');
    const candidateFormError = document.getElementById('candidateFormError');
    const filters = {
        search: document.getElementById('vacancySearch'),
        status: document.getElementById('vacancyStatusFilter'),
        department: document.getElementById('vacancyDepartmentFilter'),
    };
    const params = new URLSearchParams(location.search);
    let data = config.initial;
    let tables = [];
    let editingVacancy = null;
    let editingCandidate = null;
    let loading;
    let saving = false;
    let appliedFilters = filterParams();

    if (params.get('search')) filters.search.value = params.get('search');
    if (params.get('status')) filters.status.value = params.get('status');
    if (params.get('department')) filters.department.value = params.get('department');

    function showError(element, message) {
        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
    }

    async function api(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || response.redirected) {
            const error = new Error(response.status === 419 || response.status === 401 || response.redirected ? 'Your session has expired. Refresh the page and try again.' : result.message || 'Unable to complete the request. Please try again.');
            error.errors = result.errors || {};
            throw error;
        }
        return result;
    }

    function filterParams() {
        const query = new URLSearchParams();
        if (filters.search.value.trim()) query.set('search', filters.search.value.trim());
        if (filters.status.value) query.set('status', filters.status.value);
        if (filters.department.value) query.set('department', filters.department.value);
        return query;
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

    function salaryRange(vacancy) {
        if (vacancy.salary_range_min == null && vacancy.salary_range_max == null) return null;
        return `${fmt.currency(vacancy.salary_range_min)} – ${fmt.currency(vacancy.salary_range_max)}`;
    }

    function vacancyMeta(vacancy) {
        return [vacancy.employment_type, vacancy.location, salaryRange(vacancy), vacancy.closing_date ? `Closes ${fmt.date(vacancy.closing_date)}` : null].filter(Boolean).join(' · ');
    }

    function destroyTables() {
        tables.forEach(table => table.destroy());
        tables = [];
    }

    function renderStats() {
        Object.entries(data.stats).forEach(([key, value]) => {
            const node = document.querySelector(`[data-stat="${key}"]`);
            if (node) node.textContent = value;
        });
    }

    function renderVacancies() {
        destroyTables();
        const list = document.getElementById('vacancyList');
        const highlight = Number(config.highlight);
        if (!data.vacancies.length) {
            list.innerHTML = '<div class="col-12"><div class="panel"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-person-plus"></i></div><div class="empty-state-title">No open vacancies</div><div class="empty-state-text">Create a vacancy to begin tracking candidates for a role.</div></div></div></div>';
            return;
        }
        list.innerHTML = data.vacancies.map(vacancy => `
            <div class="col-12">
                <div class="panel${vacancy.id === highlight ? ' vacancy-highlight' : ''}" data-vacancy="${vacancy.id}">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">${esc(vacancy.job_title)}${vacancy.department ? ` <span class="text-meta">${esc(vacancy.department)}</span>` : ''}</div>
                            <div class="panel-desc">${esc(vacancyMeta(vacancy) || 'Vacancy details will appear here once recorded.')}</div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            ${fmt.statusBadge(vacancy.status)}
                            <button type="button" class="btn btn-sm btn-light-custom" data-view-vacancy="${vacancy.id}">View</button>
                            <button type="button" class="btn btn-sm btn-light-custom" data-edit-vacancy="${vacancy.id}">Edit</button>
                            ${vacancy.accepts_candidates ? `<button type="button" class="btn btn-sm btn-outline-primary add-candidate-btn" data-vac="${vacancy.id}"><i class="bi bi-person-plus"></i> Add Candidate</button>` : ''}
                            <button type="button" class="btn btn-sm btn-light-custom" data-delete-vacancy="${vacancy.id}">Remove</button>
                        </div>
                    </div>
                    <table class="table-app" id="candidatesTable-${vacancy.id}" style="width:100%;">
                        <thead><tr><th>Candidate</th><th>Applied</th><th>Source</th><th>Interview Notes</th><th>Outcome</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>`).join('');

        data.vacancies.forEach(vacancy => {
            tables.push(HR.ui.initDataTable(`#candidatesTable-${vacancy.id}`, {
                data: vacancy.candidates,
                paging: false,
                info: false,
                order: [[1, 'desc']],
                emptyIcon: 'bi-person',
                emptyTitle: 'No candidates yet',
                emptyText: 'Candidates who apply for this vacancy will appear here.',
                columns: [
                    { data: 'name', render: (value, type, row) => type === 'display' ? `<button type="button" class="candidate-details-trigger border-0 bg-transparent p-0 text-start" data-view-candidate="${row.id}" data-vacancy="${vacancy.id}" aria-label="View details for ${esc(value)}" title="View candidate details">${esc(value)}</button>` : value },
                    { data: 'application_date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
                    { data: 'source', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
                    { data: 'interview_records', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
                    { data: 'outcome', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
                    { data: null, orderable: false, className: 'text-end', render: (row, type) => type === 'display' ? `<button type="button" class="btn btn-sm btn-light-custom" data-view-candidate="${row.id}" data-vacancy="${vacancy.id}">View</button><button type="button" class="btn btn-sm btn-light-custom" data-edit-candidate="${row.id}" data-vacancy="${vacancy.id}">Edit</button><button type="button" class="btn btn-sm btn-light-custom" data-delete-candidate="${row.id}" data-vacancy="${vacancy.id}">Remove</button>` : '' },
                ],
            }));
        });
    }

    async function refresh() {
        if (loading) loading.abort();
        const controller = new AbortController();
        loading = controller;
        const query = filterParams();
        document.getElementById('exportRecruitmentBtn').disabled = true;
        try {
            data = await api(`${config.indexUrl}?${query}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            appliedFilters = query;
            history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
            renderStats();
            renderVacancies();
            showError(pageError, '');
        } catch (error) {
            if (error.name === 'AbortError') return;
            filters.search.value = appliedFilters.get('search') || '';
            filters.status.value = appliedFilters.get('status') || '';
            filters.department.value = appliedFilters.get('department') || '';
            showError(pageError, error.message);
        } finally {
            if (loading === controller) document.getElementById('exportRecruitmentBtn').disabled = false;
        }
    }

    function openVacancy(vacancy) {
        editingVacancy = vacancy;
        vacancyForm.reset();
        vacancyForm.action = vacancy ? vacancy.update_url : config.storeUrl;
        document.getElementById('vacancyModalTitle').textContent = vacancy ? 'Edit Vacancy' : 'New Vacancy';
        document.getElementById('vacancySubmit').textContent = vacancy ? 'Save Changes' : 'Save Vacancy';
        document.getElementById('vacancyStatusField').classList.toggle('d-none', !vacancy);
        document.getElementById('vacancyStatus').disabled = !vacancy;
        const values = vacancy || { job_title: '', department_id: '', hiring_manager: '', employment_type: '', opening_date: new Date().toISOString().slice(0, 10), closing_date: '', salary_range_min: '', salary_range_max: '', location: '', recruitment_channel: '', reason_for_vacancy: '', status: 'Open' };
        Object.entries(values).forEach(([name, value]) => {
            const field = vacancyForm.elements[name];
            if (field) field.value = value ?? '';
        });
        clearValidation(vacancyForm);
        vacancyModal.show();
    }

    function openCandidate(vacancy, candidate) {
        editingCandidate = candidate;
        candidateForm.reset();
        candidateForm.action = candidate ? candidate.update_url : vacancy.candidates_url;
        document.getElementById('candidateVacancyId').value = vacancy.id;
        document.getElementById('candidateModalTitle').textContent = candidate ? 'Edit Candidate' : 'Add Candidate';
        document.getElementById('candidateSubmit').textContent = candidate ? 'Save Changes' : 'Save Candidate';
        const values = candidate || { name: '', application_date: new Date().toISOString().slice(0, 10), source: vacancy.recruitment_channel || '', interview_records: '', outcome: 'In Progress' };
        Object.entries(values).forEach(([name, value]) => {
            const field = candidateForm.elements[name];
            if (field) field.value = value ?? '';
        });
        clearValidation(candidateForm);
        candidateModal.show();
    }

    function showDetails(title, fields, onEdit) {
        document.getElementById('recruitmentDetailsTitle').textContent = title;
        document.getElementById('recruitmentDetailsBody').innerHTML = '<dl class="mb-0">' + Object.entries(fields).map(([label, value]) => `<dt class="text-meta">${esc(label)}</dt><dd>${esc(value || '—')}</dd>`).join('') + '</dl>';
        detailsEdit.classList.toggle('d-none', !onEdit);
        detailsEditHandler = onEdit || null;
        detailsModal.show();
    }

    function vacancyFields(vacancy) {
        return {
            'Job title': vacancy.job_title,
            Department: vacancy.department,
            'Hiring manager': vacancy.hiring_manager,
            'Employment type': vacancy.employment_type,
            'Opening date': fmt.date(vacancy.opening_date),
            'Closing date': vacancy.closing_date ? fmt.date(vacancy.closing_date) : null,
            'Salary range': salaryRange(vacancy),
            Location: vacancy.location,
            'Recruitment channel': vacancy.recruitment_channel,
            'Reason for vacancy': vacancy.reason_for_vacancy,
            Status: vacancy.status,
            Candidates: String(vacancy.candidates.length),
        };
    }

    function candidateFields(vacancy, candidate) {
        return {
            Candidate: candidate.name,
            Vacancy: vacancy.job_title,
            Department: vacancy.department,
            Applied: fmt.date(candidate.application_date),
            Source: candidate.source,
            'Interview records': candidate.interview_records,
            Outcome: candidate.outcome,
        };
    }

    async function submitForm(event, form, title) {
        event.preventDefault();
        if (saving) return;
        clearValidation(form);
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        const body = new FormData(form);
        if (form === vacancyForm && editingVacancy) body.append('_method', 'PUT');
        if (form === candidateForm && editingCandidate) body.append('_method', 'PUT');
        try {
            const result = await api(form.action, { method: 'POST', body });
            saving = false;
            bootstrap.Modal.getInstance(form.closest('.modal')).hide();
            HR.ui.toastSuccess(title, result.message);
            await refresh();
        } catch (error) {
            showValidation(form, error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    document.getElementById('newVacancyBtn').addEventListener('click', () => openVacancy(null));
    vacancyForm.addEventListener('submit', event => submitForm(event, vacancyForm, editingVacancy ? 'Vacancy updated' : 'Vacancy created'));
    candidateForm.addEventListener('submit', event => submitForm(event, candidateForm, editingCandidate ? 'Candidate updated' : 'Candidate saved'));
    document.getElementById('vacancyModal').addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    document.getElementById('candidateModal').addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    document.getElementById('vacancyModal').addEventListener('shown.bs.modal', () => (vacancyForm.querySelector('[aria-invalid="true"]') || document.getElementById('vacancyJobTitle')).focus());
    document.getElementById('candidateModal').addEventListener('shown.bs.modal', () => (candidateForm.querySelector('[aria-invalid="true"]') || document.getElementById('candidateName')).focus());

    detailsEdit.addEventListener('click', () => {
        if (!detailsEditHandler) return;
        const next = detailsEditHandler;
        document.getElementById('recruitmentDetailsModal').addEventListener('hidden.bs.modal', () => next(), { once: true });
        detailsModal.hide();
    });

    document.getElementById('vacancyList').addEventListener('click', async event => {
        const add = event.target.closest('[data-vac]');
        const viewVacancy = event.target.closest('[data-view-vacancy]');
        const editVacancy = event.target.closest('[data-edit-vacancy]');
        const deleteVacancy = event.target.closest('[data-delete-vacancy]');
        const viewCandidate = event.target.closest('[data-view-candidate]');
        const editCandidate = event.target.closest('[data-edit-candidate]');
        const deleteCandidate = event.target.closest('[data-delete-candidate]');
        const vacancy = data.vacancies.find(row => row.id === Number(add?.dataset.vac || viewVacancy?.dataset.viewVacancy || editVacancy?.dataset.editVacancy || deleteVacancy?.dataset.deleteVacancy || viewCandidate?.dataset.vacancy || editCandidate?.dataset.vacancy || deleteCandidate?.dataset.vacancy));
        if (!vacancy) return;
        if (add) return openCandidate(vacancy, null);
        if (viewVacancy) return showDetails(vacancy.job_title, vacancyFields(vacancy), () => openVacancy(vacancy));
        if (editVacancy) return openVacancy(vacancy);
        if (viewCandidate) {
            const candidate = vacancy.candidates.find(row => row.id === Number(viewCandidate.dataset.viewCandidate));
            if (!candidate) return;
            return showDetails(candidate.name, candidateFields(vacancy, candidate), () => openCandidate(vacancy, candidate));
        }
        if (editCandidate) return openCandidate(vacancy, vacancy.candidates.find(row => row.id === Number(editCandidate.dataset.editCandidate)));
        if (deleteVacancy) {
            const confirmed = await HR.ui.confirmAction({
                title: 'Remove Vacancy',
                body: `<p>Remove ${esc(vacancy.job_title)} and its candidate records?</p>`,
                confirmLabel: 'Remove Vacancy',
            });
            if (!confirmed) return;
            try {
                await api(vacancy.destroy_url, { method: 'DELETE' });
                HR.ui.toastSuccess('Vacancy deleted', 'The vacancy has been removed.');
                await refresh();
            } catch (error) { showError(pageError, Object.values(error.errors || {}).flat().join(' ') || error.message); }
            return;
        }
        if (deleteCandidate) {
            const candidate = vacancy.candidates.find(row => row.id === Number(deleteCandidate.dataset.deleteCandidate));
            if (!candidate) return;
            const confirmed = await HR.ui.confirmAction({
                title: 'Remove Candidate',
                body: `<p>Remove ${esc(candidate.name)} from ${esc(vacancy.job_title)}?</p>`,
                confirmLabel: 'Remove Candidate',
            });
            if (!confirmed) return;
            try {
                await api(candidate.destroy_url, { method: 'DELETE' });
                HR.ui.toastSuccess('Candidate removed', 'The candidate has been removed.');
                await refresh();
            } catch (error) { showError(pageError, Object.values(error.errors || {}).flat().join(' ') || error.message); }
        }
    });

    let searchTimer;
    filters.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(refresh, 250);
    });
    filters.status.addEventListener('change', refresh);
    filters.department.addEventListener('change', refresh);

    document.getElementById('exportRecruitmentBtn').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const query = new URLSearchParams(appliedFilters);
            query.set('sort', 'job_title');
            query.set('direction', 'asc');
            const response = await fetch(`${config.exportUrl}?${query}`, { headers: { Accept: 'text/csv, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Export failed. Refresh the page and try again.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = 'recruitment.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            HR.ui.toastSuccess('Export complete', 'recruitment.csv has been downloaded.');
        } catch (error) { showError(pageError, error.message); }
        finally { button.disabled = false; }
    });

    renderStats();
    renderVacancies();

    if (config.openNew) {
        openVacancy(null);
        Object.entries(config.old || {}).forEach(([name, value]) => {
            const field = vacancyForm.elements[name];
            if (field && value != null) field.value = value;
        });
        showValidation(vacancyForm, config.errors);
    } else if (config.openCandidate) {
        const vacancy = data.vacancies.find(row => String(row.id) === String(config.old.vacancy_id)) || data.vacancies[0];
        if (vacancy) {
            openCandidate(vacancy, null);
            Object.entries(config.old || {}).forEach(([name, value]) => {
                const field = candidateForm.elements[name];
                if (field && value != null) field.value = value;
            });
            showValidation(candidateForm, config.errors);
        }
    }
});
