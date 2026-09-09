<div class="panel" id="employeeActivity">
    <div class="panel-header"><div class="panel-title">Activity</div></div>
    @if ($employee->auditEvents->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-text"></i></div>
            <div class="empty-state-title">No activity recorded</div>
            <div class="empty-state-text">Significant changes to this employee will appear here automatically.</div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-app mb-0">
                <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Description</th></tr></thead>
                <tbody>
                    @foreach ($employee->auditEvents->take(25) as $event)
                        <tr>
                            <td class="cell-secondary">{{ $event->occurred_at?->format('j M Y H:i') }}</td>
                            <td>{{ $event->user_name ?: 'System' }}</td>
                            <td class="cell-primary">{{ $event->action }}</td>
                            <td>{{ $event->module }}</td>
                            <td class="cell-secondary">{{ $event->description ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3"><a href="{{ route('admin.audit-log.index', ['search' => $employee->name]) }}" class="btn btn-sm btn-outline-primary">Open Audit Log</a></div>
    @endif
</div>
