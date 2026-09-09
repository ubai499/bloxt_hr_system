document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.payrollPage;
    const fmt = HR.format;
    const esc = fmt.escapeHtml;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const salaryForm = document.getElementById('salaryForm');
    const payrollForm = document.getElementById('payrollForm');
    const salaryModal = new bootstrap.Modal(document.getElementById('salaryModal'));
    const payrollModal = new bootstrap.Modal(document.getElementById('payrollModal'));
    const detailsModal = new bootstrap.Modal(document.getElementById('payrollDetailsModal'));
    const pageError = document.getElementById('payrollPageError');
    const salarySearch = document.getElementById('salarySearch');
    const payrollSearch = document.getElementById('payrollSearch');
    const newSalaryBtn = document.getElementById('newSalaryBtn');
    const newPayrollBtn = document.getElementById('newPayrollBtn');
    let salaryRows = config.initial.salary_rows;
    let payrollRows = config.initial.payroll_rows;
    let activeTab = config.activeTab === 'payroll' ? 'payroll' : 'salary';
    let editing = null;
    let editingKind = null;
    let detailsNext = null;
    let loading;
    let saving = false;

    salarySearch.value = activeTab === 'salary' ? (config.filters.search || '') : '';
    payrollSearch.value = activeTab === 'payroll' ? (config.filters.search || '') : '';

    const salaryTable = HR.ui.initDataTable('#salaryTable', {
        data: salaryRows,
        pageLength: 25,
        order: [[0, 'asc']],
        emptyIcon: 'bi-cash-stack',
        emptyTitle: 'No salary records',
        emptyText: 'Current salaries will appear here once recorded.',
        columns: [
            { data: 'employee', className: 'cell-primary', render: (value, type, row) => type === 'display' ? `<button type="button" class="payroll-details-trigger border-0 bg-transparent p-0 text-start" data-view-salary="${row.id}" aria-label="View current salary for ${esc(value)}">${esc(value)}</button>` : value },
            { data: 'salary', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'basis' },
            { data: 'contracted_hours', render: (value, type) => type === 'display' ? (value == null ? '—' : value) : (value ?? '') },
            { data: 'effective_date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'approved_by', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
            { data: null, orderable: false, className: 'text-end', render: (row) => `<a href="${esc(row.profile_url)}" class="btn btn-sm btn-light-custom" data-history>View History</a>` },
        ],
    });

    const payrollTable = HR.ui.initDataTable('#payrollTable', {
        data: payrollRows,
        pageLength: 25,
        order: [[7, 'desc']],
        emptyIcon: 'bi-receipt',
        emptyTitle: 'No payroll records',
        emptyText: 'Payroll evidence will appear here once recorded.',
        columns: [
            { data: 'employee', render: (value, type, row) => type === 'display' ? `<button type="button" class="payroll-details-trigger border-0 bg-transparent p-0 text-start" data-view-payroll="${row.id}" aria-label="View payroll evidence for ${esc(value)}">${esc(value)}</button>` : value },
            { data: 'payroll_period' },
            { data: 'gross_salary', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'overtime', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'bonus', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'deductions', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'net_amount', className: 'cell-primary', render: (value, type) => type === 'display' ? fmt.currency(value) : value },
            { data: 'payment_date', render: (value, type) => type === 'display' ? fmt.date(value) : value || '' },
            { data: 'payroll_reference', className: 'cell-secondary', render: (value, type) => type === 'display' ? esc(value || '—') : value || '' },
        ],
    });

    function showError(element, message) {
        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
    }

    function tabQuery(tab, includeSearch) {
        const query = new URLSearchParams();
        if (tab === 'payroll') query.set('tab', 'payroll');
        if (includeSearch) {
            const search = (tab === 'payroll' ? payrollSearch : salarySearch).value.trim();
            if (search) query.set('search', search);
        }
        if (config.filters.employee) query.set('employee', config.filters.employee);
        return query;
    }

    function syncUrl(tab) {
        const query = tabQuery(tab, true);
        history.replaceState(null, '', `${config.indexUrl}${query.size ? '?' + query : ''}`);
    }

    function activateTab(tab) {
        activeTab = tab === 'payroll' ? 'payroll' : 'salary';
        document.querySelectorAll('#payrollTabs [data-tab]').forEach(button => {
            const selected = button.dataset.tab === activeTab;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', selected);
            button.tabIndex = selected ? 0 : -1;
        });
        document.getElementById('tab-salary').classList.toggle('d-none', activeTab !== 'salary');
        document.getElementById('tab-payroll').classList.toggle('d-none', activeTab !== 'payroll');
        newSalaryBtn.classList.toggle('d-none', activeTab !== 'salary');
        newPayrollBtn.classList.toggle('d-none', activeTab !== 'payroll');
        syncUrl(activeTab);
        (activeTab === 'payroll' ? payrollTable : salaryTable).columns.adjust();
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

    async function refresh() {
        if (loading) loading.abort();
        const controller = new AbortController();
        loading = controller;
        document.getElementById('salaryTable').setAttribute('aria-busy', 'true');
        document.getElementById('payrollTable').setAttribute('aria-busy', 'true');
        try {
            const query = new URLSearchParams();
            if (config.filters.employee) query.set('employee', config.filters.employee);
            const data = await api(`${config.indexUrl}${query.size ? '?' + query : ''}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            salaryRows = data.salary_rows;
            payrollRows = data.payroll_rows;
            salaryTable.clear().rows.add(salaryRows).draw();
            payrollTable.clear().rows.add(payrollRows).draw();
            salaryTable.search(salarySearch.value).draw();
            payrollTable.search(payrollSearch.value).draw();
            showError(pageError, '');
        } catch (error) {
            if (error.name === 'AbortError') return;
            showError(pageError, error.message);
        } finally {
            if (loading === controller) {
                document.getElementById('salaryTable').removeAttribute('aria-busy');
                document.getElementById('payrollTable').removeAttribute('aria-busy');
            }
        }
    }

    function latestSalary(employeeId) {
        return salaryRows.find(row => String(row.employee_id) === String(employeeId)) || null;
    }

    function suggestedGross(employeeId) {
        const current = latestSalary(employeeId);
        if (!current) return '';
        if (current.basis === 'Monthly') return current.salary;
        if (current.basis === 'Hourly') return '';
        return Math.round((current.salary / 12) * 100) / 100;
    }

    function moneyValue(id) {
        const value = parseFloat(document.getElementById(id).value);
        return Number.isFinite(value) ? value : 0;
    }

    function updateNetPreview() {
        const net = moneyValue('payrollGross') + moneyValue('payrollOvertime') + moneyValue('payrollBonus') - moneyValue('payrollDeductions');
        document.getElementById('payrollNetPreview').value = Number.isFinite(net) ? fmt.currency(Math.round(net * 100) / 100) : '—';
    }

    function defaultPeriod() {
        return new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    }

    function defaultReference(employeeId) {
        const option = document.getElementById('payrollEmployeeSelect').selectedOptions[0];
        const number = option?.dataset.number || employeeId || 'EMP';
        const now = new Date();
        return `PR-${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${number}`;
    }

    function fillForm(form, data) {
        Object.entries(data).forEach(([name, value]) => {
            const field = form.elements[name];
            if (!field || field.type === 'checkbox') return;
            field.value = value ?? '';
        });
        if (form.elements.evidence_uploaded) form.elements.evidence_uploaded.checked = !!data.evidence_uploaded;
    }

    function openSalaryCreate(employeeId) {
        editing = null;
        editingKind = 'salary';
        salaryForm.reset();
        salaryForm.action = config.storeSalaryUrl;
        document.getElementById('salaryModalTitle').textContent = 'Record Salary Change';
        document.getElementById('salarySubmit').textContent = 'Save Salary';
        document.getElementById('salaryFormHelp').classList.remove('d-none');
        document.getElementById('salaryEmployeeSelect').disabled = false;
        document.getElementById('salaryEffectiveDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('salaryApprovedBy').value = config.actor;
        document.getElementById('salaryReason').value = 'Salary change';
        if (employeeId) document.getElementById('salaryEmployeeSelect').value = String(employeeId);
        const selected = document.getElementById('salaryEmployeeSelect').selectedOptions[0];
        if (selected?.dataset.hours) document.getElementById('salaryHours').value = selected.dataset.hours;
        clearValidation(salaryForm);
        salaryModal.show();
    }

    function openPayrollCreate(employeeId) {
        editing = null;
        editingKind = 'payroll';
        payrollForm.reset();
        payrollForm.action = config.storePayrollUrl;
        document.getElementById('payrollModalTitle').textContent = 'Record Payroll Evidence';
        document.getElementById('payrollSubmit').textContent = 'Save Record';
        document.getElementById('payrollFormHelp').classList.remove('d-none');
        document.getElementById('payrollEmployeeSelect').disabled = false;
        document.getElementById('payrollPeriod').value = defaultPeriod();
        document.getElementById('payrollPaymentDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('payrollOvertime').value = '0';
        document.getElementById('payrollBonus').value = '0';
        document.getElementById('payrollDeductions').value = '0';
        if (employeeId) document.getElementById('payrollEmployeeSelect').value = String(employeeId);
        const selectedId = document.getElementById('payrollEmployeeSelect').value;
        const suggested = suggestedGross(selectedId);
        if (suggested !== '') {
            document.getElementById('payrollGross').value = suggested;
            document.getElementById('payrollBasic').value = suggested;
        }
        document.getElementById('payrollReference').value = defaultReference(selectedId);
        updateNetPreview();
        clearValidation(payrollForm);
        payrollModal.show();
    }

    async function openSalaryEdit(row) {
        editing = row;
        editingKind = 'salary';
        salaryForm.reset();
        salaryForm.action = row.update_url;
        document.getElementById('salaryModalTitle').textContent = 'Edit Salary Record';
        document.getElementById('salarySubmit').textContent = 'Save Changes';
        document.getElementById('salaryFormHelp').classList.add('d-none');
        document.getElementById('salaryEmployeeSelect').disabled = true;
        document.getElementById('salaryEmployeeSelect').value = String(row.employee_id);
        clearValidation(salaryForm);
        salaryModal.show();
        try {
            fillForm(salaryForm, await api(row.details_url));
        } catch (error) {
            showValidation(salaryForm, {}, error.message);
        }
    }

    async function openPayrollEdit(row) {
        editing = row;
        editingKind = 'payroll';
        payrollForm.reset();
        payrollForm.action = row.update_url;
        document.getElementById('payrollModalTitle').textContent = 'Edit Payroll Record';
        document.getElementById('payrollSubmit').textContent = 'Save Changes';
        document.getElementById('payrollFormHelp').classList.add('d-none');
        document.getElementById('payrollEmployeeSelect').disabled = true;
        document.getElementById('payrollEmployeeSelect').value = String(row.employee_id);
        clearValidation(payrollForm);
        payrollModal.show();
        try {
            fillForm(payrollForm, await api(row.details_url));
            updateNetPreview();
        } catch (error) {
            showValidation(payrollForm, {}, error.message);
        }
    }

    function detailFields(kind, data) {
        if (kind === 'salary') {
            return {
                Employee: data.employee, Salary: fmt.currency(data.annual_salary), Basis: data.salary_frequency,
                'Hourly rate': data.hourly_rate != null ? fmt.currency(data.hourly_rate) : null,
                'Contracted hours': data.contracted_hours != null ? data.contracted_hours : null,
                'Previous salary': data.previous_salary != null ? fmt.currency(data.previous_salary) : null,
                'Effective date': fmt.date(data.effective_date), Reason: data.reason,
                'Approved by': data.authorised_by, 'Recorded by': data.recorded_by,
            };
        }
        return {
            Employee: data.employee, Period: data.payroll_period, Gross: fmt.currency(data.gross_salary),
            Basic: fmt.currency(data.basic_salary), Overtime: fmt.currency(data.overtime), Bonus: fmt.currency(data.bonus),
            Deductions: fmt.currency(data.deductions), Net: fmt.currency(data.net_amount),
            'Payment date': fmt.date(data.payment_date), Reference: data.payroll_reference,
            Evidence: data.evidence_uploaded ? 'Uploaded' : 'Not uploaded', Notes: data.notes,
        };
    }

    async function openDetails(kind, row) {
        const body = document.getElementById('payrollDetailsBody');
        const profile = document.getElementById('payrollDetailsProfile');
        const removeBtn = document.getElementById('payrollDetailsRemove');
        document.getElementById('payrollDetailsTitle').textContent = kind === 'salary' ? row.employee : `${row.employee} · ${row.payroll_period}`;
        profile.href = row.profile_url;
        removeBtn.classList.remove('d-none');
        detailsNext = {
            edit: () => kind === 'salary' ? openSalaryEdit(row) : openPayrollEdit(row),
            remove: () => removeRecord(kind, row),
        };
        body.textContent = 'Loading record…';
        detailsModal.show();
        try {
            const data = await api(row.details_url);
            body.innerHTML = '<dl class="mb-0">' + Object.entries(detailFields(kind, data)).map(([label, value]) => `<dt class="text-meta">${esc(label)}</dt><dd>${esc(value || '—')}</dd>`).join('') + '</dl>';
        } catch (error) {
            body.textContent = error.message;
        }
    }

    async function removeRecord(kind, row) {
        const confirmed = await HR.ui.confirmAction({
            title: kind === 'salary' ? 'Remove salary record' : 'Remove payroll record',
            body: kind === 'salary' ? 'Remove this salary entry from the employee history?' : 'Remove this payroll evidence record?',
            confirmLabel: 'Remove',
        });
        if (!confirmed) return;
        try {
            const result = await api(row.destroy_url, { method: 'DELETE' });
            detailsModal.hide();
            HR.ui.toastSuccess(kind === 'salary' ? 'Salary record removed' : 'Payroll record removed', result.message);
            await refresh();
        } catch (error) {
            showError(pageError, error.message);
        }
    }

    async function submitForm(event, form, kind) {
        event.preventDefault();
        if (saving) return;
        clearValidation(form);
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        const body = new FormData(form);
        if (editing && editingKind === kind) {
            body.append('_method', 'PUT');
            body.set('employee_id', String(editing.employee_id));
        }
        try {
            const result = await api(form.action, { method: 'POST', body });
            saving = false;
            (kind === 'salary' ? salaryModal : payrollModal).hide();
            HR.ui.toastSuccess(editing ? (kind === 'salary' ? 'Salary record updated' : 'Payroll record updated') : (kind === 'salary' ? 'Salary record saved' : 'Payroll record saved'), result.message);
            await refresh();
        } catch (error) {
            showValidation(form, error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    async function exportTable(event, kind) {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const query = tabQuery(kind, true);
            const table = kind === 'payroll' ? payrollTable : salaryTable;
            const [column, direction] = table.order()[0] || [0, 'asc'];
            const salarySort = ['employee', 'salary', 'basis', 'contracted_hours', 'effective_date', 'approved_by'];
            const payrollSort = ['employee', 'payroll_period', 'gross_salary', 'overtime', 'bonus', 'deductions', 'net_amount', 'payment_date', 'payroll_reference'];
            query.set('sort', (kind === 'payroll' ? payrollSort : salarySort)[column] || 'employee');
            query.set('direction', direction);
            query.delete('tab');
            const url = kind === 'payroll' ? config.exportPayrollUrl : config.exportSalaryUrl;
            const filename = kind === 'payroll' ? 'payroll-records.csv' : 'salary-records.csv';
            const response = await fetch(`${url}?${query}`, { headers: { Accept: 'text/csv, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Export failed. Refresh the page and try again.');
            const objectUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            HR.ui.toastSuccess('Export complete', `${filename} has been downloaded.`);
        } catch (error) {
            showError(pageError, error.message);
        } finally {
            button.disabled = false;
        }
    }

    document.querySelectorAll('#payrollTabs [data-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.tab)));
    newSalaryBtn.addEventListener('click', () => openSalaryCreate(config.filters.employee || null));
    newPayrollBtn.addEventListener('click', () => openPayrollCreate(config.filters.employee || null));
    salaryForm.addEventListener('submit', event => submitForm(event, salaryForm, 'salary'));
    payrollForm.addEventListener('submit', event => submitForm(event, payrollForm, 'payroll'));
    document.getElementById('salaryModal').addEventListener('hide.bs.modal', event => { if (saving && editingKind === 'salary') event.preventDefault(); });
    document.getElementById('payrollModal').addEventListener('hide.bs.modal', event => { if (saving && editingKind === 'payroll') event.preventDefault(); });
    document.getElementById('payrollDetailsEdit').addEventListener('click', () => {
        if (!detailsNext) return;
        const next = detailsNext.edit;
        document.getElementById('payrollDetailsModal').addEventListener('hidden.bs.modal', () => next(), { once: true });
        detailsModal.hide();
    });
    document.getElementById('payrollDetailsRemove').addEventListener('click', () => detailsNext?.remove());
    document.querySelector('#salaryTable tbody').addEventListener('click', event => {
        if (event.target.closest('[data-history]')) return;
        const tr = event.target.closest('tr');
        const data = tr && salaryTable.row(tr).data();
        if (data) openDetails('salary', data);
    });
    document.querySelector('#payrollTable tbody').addEventListener('click', event => {
        const tr = event.target.closest('tr');
        const data = tr && payrollTable.row(tr).data();
        if (data) openDetails('payroll', data);
    });
    salarySearch.addEventListener('input', () => { salaryTable.search(salarySearch.value).draw(); syncUrl('salary'); });
    payrollSearch.addEventListener('input', () => { payrollTable.search(payrollSearch.value).draw(); syncUrl('payroll'); });
    document.getElementById('exportSalaryBtn').addEventListener('click', event => exportTable(event, 'salary'));
    document.getElementById('exportPayrollBtn').addEventListener('click', event => exportTable(event, 'payroll'));
    document.getElementById('salaryEmployeeSelect').addEventListener('change', () => {
        if (editing) return;
        const selected = document.getElementById('salaryEmployeeSelect').selectedOptions[0];
        if (selected?.dataset.hours && !document.getElementById('salaryHours').value) document.getElementById('salaryHours').value = selected.dataset.hours;
    });
    document.getElementById('payrollEmployeeSelect').addEventListener('change', () => {
        if (editing) return;
        const selectedId = document.getElementById('payrollEmployeeSelect').value;
        const suggested = suggestedGross(selectedId);
        if (suggested !== '' && !document.getElementById('payrollGross').value) {
            document.getElementById('payrollGross').value = suggested;
            document.getElementById('payrollBasic').value = suggested;
        }
        if (!document.getElementById('payrollReference').value) document.getElementById('payrollReference').value = defaultReference(selectedId);
        updateNetPreview();
    });
    ['payrollGross', 'payrollOvertime', 'payrollBonus', 'payrollDeductions'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateNetPreview);
    });

    if (config.filters.search) {
        (activeTab === 'payroll' ? payrollTable : salaryTable).search(config.filters.search).draw();
    }
    activateTab(activeTab);

    if (config.openNew === 'salary' || (config.errors && config.old.annual_salary !== undefined && config.openNew !== 'payroll')) {
        openSalaryCreate(config.filters.employee || config.old.employee_id || null);
        fillForm(salaryForm, config.old || {});
        showValidation(salaryForm, config.errors || {});
    } else if (config.openNew === 'payroll' || (config.errors && config.old.payroll_period !== undefined)) {
        openPayrollCreate(config.filters.employee || config.old.employee_id || null);
        fillForm(payrollForm, config.old || {});
        updateNetPreview();
        showValidation(payrollForm, config.errors || {});
    }
});
