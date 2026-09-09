<div class="table-panel" id="employeeRightToWork">
    <table class="table-app" style="width:100%;">
        <thead>
            <tr>
                <th>Check Date</th>
                <th>Method</th>
                <th>Immigration Category</th>
                <th>Permission Start</th>
                <th>Permission Expiry</th>
                <th>Follow-up</th>
                <th>Status</th>
                <th>Performed By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employee->rightToWorkChecks as $check)
                @php $status = $check->displayStatus(); @endphp
                <tr>
                    <td class="cell-primary">{{ $check->check_date?->format('j M Y') }}</td>
                    <td>{{ $check->check_method }}</td>
                    <td>{{ $check->immigration_category ?: '—' }}</td>
                    <td>{{ $check->permission_start?->format('j M Y') ?: 'N/A' }}</td>
                    <td>{{ $check->permission_expiry?->format('j M Y') ?: 'N/A' }}</td>
                    <td>{{ $check->follow_up_required ? 'Yes'.($check->next_check_date ? ' '.$check->next_check_date->format('j M Y') : '') : 'No' }}</td>
                    <td><span class="status-badge badge-{{ match ($status) { 'Expired', 'Evidence Missing' => 'danger', 'Expiring Soon', 'Review Due', 'Follow-up Required' => 'warning', 'Valid' => 'success', default => 'neutral' } }}">{{ $status }}</span></td>
                    <td>{{ $check->performed_by ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-patch-check"></i></div>
                            <div class="empty-state-title">No right-to-work check on file</div>
                            <div class="empty-state-text">A right-to-work check has not yet been recorded for this employee.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-top">
        <a href="{{ route('admin.right-to-work.create', ['employee' => $employee->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Record New Check</a>
    </div>
</div>
