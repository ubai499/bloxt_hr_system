@php $record = $employee->sponsorshipRecord; @endphp
@if ($record)
    <div class="panel mb-4" id="employeeSponsorship">
        <div class="panel-header"><div class="panel-title">Sponsorship Record</div>{{-- status --}}<span class="status-badge badge-{{ $record->sponsorship_status === 'Current' ? 'success' : 'neutral' }}">{{ $record->sponsorship_status }}</span></div>
        <dl class="detail-grid">
            <div class="detail-item"><dt>Worker route</dt><dd>{{ $record->worker_route }}</dd></div>
            <div class="detail-item"><dt>CoS reference</dt><dd>{{ $record->cos_reference ?: '—' }}</dd></div>
            <div class="detail-item"><dt>CoS assigned</dt><dd>{{ $record->cos_assigned_date?->format('j M Y') ?: '—' }}</dd></div>
            <div class="detail-item"><dt>CoS start / end</dt><dd>{{ $record->cos_start_date?->format('j M Y') ?: '—' }} – {{ $record->cos_end_date?->format('j M Y') ?: '—' }}</dd></div>
            <div class="detail-item"><dt>SOC code</dt><dd>{{ trim(($record->soc_code.' '.$record->soc_title)) ?: '—' }}</dd></div>
            <div class="detail-item"><dt>Annual salary</dt><dd>{{ $record->annual_salary !== null ? '£'.number_format((float) $record->annual_salary, 2) : '—' }}</dd></div>
            <div class="detail-item"><dt>Weekly hours</dt><dd>{{ $record->weekly_hours !== null ? rtrim(rtrim(number_format((float) $record->weekly_hours, 2, '.', ''), '0'), '.') : '—' }}</dd></div>
            <div class="detail-item"><dt>Work pattern</dt><dd>{{ $record->work_pattern ?: '—' }}</dd></div>
            <div class="detail-item"><dt>HR responsible person</dt><dd>{{ $record->hr_responsible_person ?: '—' }}</dd></div>
            <div class="detail-item"><dt>Next review date</dt><dd>{{ $record->next_review_date?->format('j M Y') ?: '—' }}</dd></div>
        </dl>
        @if ($record->notes)
            <p class="text-meta mt-3 mb-0">{{ $record->notes }}</p>
        @endif
        <div class="mt-3"><a href="{{ route('admin.sponsorship.index', ['tab' => 'workers']) }}" class="btn btn-sm btn-outline-primary">Open Sponsor Compliance Workspace</a></div>
    </div>
@else
    <div class="panel" id="employeeSponsorship">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-shield"></i></div>
            <div class="empty-state-title">Not a sponsored worker</div>
            <div class="empty-state-text">This employee does not currently hold a sponsored worker record.</div>
            @if ($employee->status !== 'Left')
                <a href="{{ route('admin.sponsorship.create', ['tab' => 'workers', 'type' => 'worker', 'employee' => $employee->id]) }}" class="btn btn-outline-primary btn-sm">Record Sponsored Worker</a>
            @endif
        </div>
    </div>
@endif
