@php $current = $employee->compensations->first(); @endphp
@if ($current)
    <div class="panel mb-4">
        <div class="panel-header"><div class="panel-title">Current Salary</div></div>
        <dl class="detail-grid">
            <div class="detail-item"><dt>Salary</dt><dd>{{ '£'.number_format((float) $current->annual_salary, 2) }} ({{ $current->salary_frequency }})</dd></div>
            <div class="detail-item"><dt>Contracted hours</dt><dd>{{ $current->contracted_hours !== null ? rtrim(rtrim(number_format((float) $current->contracted_hours, 2, '.', ''), '0'), '.').'/week' : '—' }}</dd></div>
            <div class="detail-item"><dt>Effective from</dt><dd>{{ $current->effective_date?->format('j M Y') ?: '—' }}</dd></div>
            <div class="detail-item"><dt>Approved by</dt><dd>{{ $current->authorised_by ?: '—' }}</dd></div>
        </dl>
    </div>
@endif
<div class="table-panel" id="employeeSalary">
    <div class="panel-header px-4 pt-4"><div class="panel-title">Salary History</div></div>
    <table class="table-app" style="width:100%;">
        <thead>
            <tr>
                <th>Effective Date</th>
                <th>Previous</th>
                <th>New</th>
                <th>Reason</th>
                <th>Approved By</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employee->compensations as $record)
                <tr>
                    <td class="cell-primary">{{ $record->effective_date?->format('j M Y') }}</td>
                    <td>{{ $record->previous_salary !== null ? '£'.number_format((float) $record->previous_salary, 2) : '—' }}</td>
                    <td class="cell-primary">{{ '£'.number_format((float) $record->annual_salary, 2) }}</td>
                    <td>{{ $record->reason ?: '—' }}</td>
                    <td>{{ $record->authorised_by ?: '—' }}</td>
                    <td>{{ $record->recorded_by ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="empty-state-title">No salary records</div>
                            <div class="empty-state-text">A salary has not yet been recorded for this employee.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($employee->status !== 'Left')
        <div class="p-4 border-top">
            <a href="{{ route('admin.payroll.salaries.create', ['employee' => $employee->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Record Salary Change</a>
        </div>
    @endif
</div>
