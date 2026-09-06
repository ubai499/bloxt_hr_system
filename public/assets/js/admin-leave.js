document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.leavePage;
    const fmt = HR.format;
    const escape = fmt.escapeHtml;
    const form = document.getElementById('leaveForm');
    const modalEl = document.getElementById('leaveModal');
    const modal = new bootstrap.Modal(modalEl);
    const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
    const formError = document.getElementById('leaveFormError');
    const pageError = document.getElementById('leavePageError');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const filters = { status: document.getElementById('leaveStatusFilter'), type: document.getElementById('leaveTypeFilter'), employee: document.getElementById('leaveEmployeeFilter') };
    let rows = config.initial.rows;
    let loading;
    let saving = false;
    let deciding = false;
    let appliedFilters = filterParams();

    const textColumn = (value, type) => type === 'display' ? escape(value || '—') : (value || '');
    const dateColumn = (value, type) => type === 'display' ? fmt.date(value) : value;
    const table = HR.ui.initDataTable('#leaveTable', {
        data: rows,
        order: [[2, 'desc']],
        emptyIcon: 'bi-airplane',
        emptyTitle: 'No leave requests found',
        emptyText: 'Leave requests matching your filters will appear here.',
        columns: [
            { data: 'employee', render: (value, type, row) => type === 'display' ? `<button type="button" class="leave-details-trigger border-0 bg-transparent p-0 text-start" data-details="${row.id}" aria-label="View leave request for ${escape(value)}" title="View leave request">${escape(value)}</button>` : value },
            { data: 'leave_type', render: (value, type, row) => type === 'display' ? `<span title="${row.duration} working days${row.partial_day ? ' · Partial day' : ''}">${escape(value)}</span>` : value },
            { data: 'from_date', render: dateColumn },
            { data: 'to_date', render: dateColumn },
            { data: 'reason', className: 'cell-secondary', render: textColumn },
            { data: 'status', render: (value, type) => type === 'display' ? fmt.statusBadge(value) : value },
            { data: 'approved_by', render: textColumn },
            { data: null, orderable: false, className: 'text-end', render: (row, type) => {
                if (type !== 'display') return '';
                if (row.can_approve) return `<button type="button" class="btn btn-sm btn-outline-primary me-1" data-decision="Approved" data-id="${row.id}">Approve</button><button type="button" class="btn btn-sm btn-light-custom" data-decision="Rejected" data-id="${row.id}">Reject</button>`;
                return row.can_cancel ? `<button type="button" class="btn btn-sm btn-light-custom" data-decision="Cancelled" data-id="${row.id}">Cancel</button>` : '';
            } }
        ]
    });

    function filterParams() {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, field]) => { if (field.value) params.set(key, field.value); });
        return params;
    }

    function showError(element, message) {
        element.textContent = message;
        element.classList.toggle('d-none', !message);
    }

    async function api(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || response.redirected) {
            const error = new Error(response.status === 419 || response.status === 401 || response.redirected ? 'Your session has expired. Refresh the page and sign in again.' : data.message || 'Unable to complete the request. Please try again.');
            error.errors = data.errors || {};
            throw error;
        }
        return data;
    }

    async function refresh() {
        if (loading) loading.abort();
        const controller = new AbortController();
        loading = controller;
        const params = filterParams();
        document.getElementById('leaveTable').setAttribute('aria-busy', 'true');
        document.getElementById('exportLeaveBtn').disabled = true;
        try {
            const data = await api(`${config.indexUrl}?${params}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            rows = data.rows;
            table.clear().rows.add(rows).draw();
            Object.entries(data.stats).forEach(([key, value]) => { document.querySelector(`[data-stat="${key}"]`).textContent = value; });
            appliedFilters = params;
            history.replaceState(null, '', `${config.indexUrl}${params.size ? '?' + params : ''}`);
            showError(pageError, '');
        } catch (error) {
            if (error.name === 'AbortError') return;
            Object.entries(filters).forEach(([key, field]) => { field.value = appliedFilters.get(key) || ''; });
            showError(pageError, error.message);
        } finally {
            if (loading === controller) {
                document.getElementById('leaveTable').removeAttribute('aria-busy');
                document.getElementById('exportLeaveBtn').disabled = false;
            }
        }
    }

    Object.values(filters).forEach(field => field.addEventListener('change', refresh));

    function clearValidation() {
        form.querySelectorAll('.field-invalid').forEach(field => field.classList.remove('field-invalid'));
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(field => { field.textContent = ''; });
        showError(formError, '');
    }

    function showValidation(errors, fallback = '') {
        clearValidation();
        let firstField;
        const unplaced = [];
        Object.entries(errors).forEach(([name, messages]) => {
            const field = form.querySelector(`[name="${name}"]`);
            const feedback = form.querySelector(`[data-error-for="${name}"]`);
            const message = Array.isArray(messages) ? messages[0] : messages;
            if (!field || !feedback) { unplaced.push(message); return; }
            field.closest('.form-field').classList.add('field-invalid');
            field.setAttribute('aria-invalid', 'true');
            feedback.textContent = message;
            firstField ||= field;
        });
        showError(formError, unplaced.join(' ') || (Object.keys(errors).length ? '' : fallback));
        if (firstField) firstField.focus();
    }

    function partialHelp() {
        document.getElementById('partialDayHelp').classList.toggle('d-none', !document.getElementById('partialYes').checked);
    }

    document.getElementById('newLeaveBtn').addEventListener('click', () => {
        form.reset();
        // Reset to the prototype defaults even after a server validation redirect.
        form.querySelectorAll('input[type="date"], input[name="reason"], textarea').forEach(field => { field.value = ''; });
        document.getElementById('partialNo').checked = true;
        clearValidation();
        partialHelp();
        modal.show();
    });
    form.addEventListener('change', partialHelp);
    modalEl.addEventListener('shown.bs.modal', () => (form.querySelector('[aria-invalid="true"]') || document.getElementById('leaveEmployeeSelect')).focus());
    modalEl.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        clearValidation();
        const from = form.elements.from_date.value;
        const to = form.elements.to_date.value;
        if (to < from) { showValidation({ to_date: 'End date must be on or after the start date.' }); return; }
        if (document.getElementById('partialYes').checked && from !== to) { showValidation({ to_date: 'Partial-day leave must start and end on the same date.' }); return; }
        saving = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.classList.add('is-loading');
        try {
            const data = await api(form.action, { method: 'POST', body: new FormData(form) });
            saving = false;
            modal.hide();
            HR.ui.toastSuccess('Leave requested', data.message);
            await refresh();
        } catch (error) {
            showValidation(error.errors || {}, error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    });

    document.querySelector('#leaveTable tbody').addEventListener('click', async event => {
        const details = event.target.closest('[data-details]');
        const action = event.target.closest('[data-decision]');
        const row = rows.find(row => row.id === Number(details?.dataset.details || action?.dataset.id));
        if (!row) return;
        if (details) {
            const body = document.getElementById('leaveDetailsBody');
            body.textContent = 'Loading leave request…';
            detailsModal.show();
            try {
                const data = await api(row.details_url);
                const fields = { Employee: data.employee, Type: data.leave_type, From: fmt.date(data.from_date), To: fmt.date(data.to_date), Duration: `${data.duration} working days${data.partial_day ? ' (partial day)' : ''}`, Reason: data.reason, Notes: data.notes, Status: data.status, 'Approved By': data.approved_by, Requested: data.requested_at };
                if (data.rejection_reason) fields['Rejection reason'] = data.rejection_reason;
                body.innerHTML = '<dl class="mb-0">' + Object.entries(fields).map(([label, value]) => `<dt class="text-meta">${escape(label)}</dt><dd>${escape(value || '—')}</dd>`).join('') + '</dl>' +
                    data.balances.map(balance => `<div class="border-top pt-3 mt-3"><div class="fw-medium mb-2">Annual leave balance · ${balance.year}</div><div class="text-meta">Allowance: ${balance.allowance} days · Taken: ${balance.taken} days · Booked: ${balance.booked} days · Remaining: ${balance.remaining} days</div></div>`).join('');
            } catch (error) { body.textContent = error.message; }
            return;
        }
        if (!action || deciding) return;
        deciding = true;
        const status = action.dataset.decision;
        const label = { Approved: 'Approve', Rejected: 'Reject', Cancelled: 'Cancel Request' }[status];
        try {
            const confirmed = await HR.ui.confirmAction({
                title: status === 'Cancelled' ? 'Cancel Leave Request' : `${label} Leave`,
                body: `<p>${status === 'Cancelled' ? 'Are you sure you want to cancel this leave request?' : `${label} this leave request?`}</p><p class="text-meta">${escape(row.employee)} · ${fmt.date(row.from_date)} – ${fmt.date(row.to_date)} · ${row.duration} working days</p>` + (status === 'Rejected' ? '<label for="leaveRejectionReason" class="form-label">Rejection reason (optional)</label><textarea class="form-control" id="leaveRejectionReason" maxlength="2000" rows="2"></textarea>' : ''),
                confirmVariant: status === 'Approved' ? 'primary' : 'danger', confirmLabel: label
            });
            if (!confirmed) return;
            const rejectionReason = document.getElementById('leaveRejectionReason')?.value || null;
            action.disabled = true;
            const data = await api(row.status_url, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status, rejection_reason: rejectionReason }) });
            HR.ui.toastSuccess(`Leave ${status.toLowerCase()}`, data.message);
            await refresh();
        } catch (error) {
            await refresh();
            showError(pageError, Object.values(error.errors || {}).flat().join(' ') || error.message);
        } finally { deciding = false; action.disabled = false; }
    });

    document.getElementById('exportLeaveBtn').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const params = new URLSearchParams(appliedFilters);
            const [column, direction] = table.order()[0] || [2, 'desc'];
            params.set('sort', ['employee', 'leave_type', 'from_date', 'to_date', 'reason', 'status', 'approved_by'][column]);
            params.set('direction', direction);
            const response = await fetch(`${config.exportUrl}?${params}`, { headers: { Accept: 'text/csv, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) throw new Error('Export failed. Refresh the page and try again.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = 'leave-requests.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            HR.ui.toastSuccess('Export complete', 'leave-requests.csv has been downloaded.');
        } catch (error) { showError(pageError, error.message); }
        finally { button.disabled = false; }
    });

    if (config.openNew) {
        partialHelp();
        showValidation(config.errors);
        modal.show();
    }
});
