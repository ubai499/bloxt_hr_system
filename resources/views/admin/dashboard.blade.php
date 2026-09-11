@extends('layouts.master')

@php
    $user = auth()->user();
    $firstName = strtok($user->name ?? 'Team', ' ');
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('title', 'Dashboard Bloxt People & Compliance')
@section('meta_description', 'Workforce and compliance overview for Bloxt.')

@section('content')
    <div class="page-header-bar">
        <div class="greeting-header">
            <div>
                <h1 class="page-title">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="page-subtitle">Overview of your workforce and compliance activity.</p>
            </div>
            <span class="text-meta">{{ now()->format('D, d M Y') }}</span>
        </div>
    </div>

    <div class="app-content">
        @if (session('status'))
            <div class="alert alert-success mb-4" role="alert">{{ session('status') }}</div>
        @endif

        <div class="kpi-grid mb-5">
            @foreach ($payload['metrics'] as $metric)
                <div class="metric-card metric-accent-{{ $metric['accent'] }}">
                    <span class="metric-icon"><i class="bi {{ $metric['icon'] }}"></i></span>
                    <span class="metric-label">{{ $metric['label'] }}</span>
                    <span class="metric-value">{{ $metric['value'] }}</span>
                    <span class="metric-context">{{ $metric['context'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="dashboard-columns">
            <div>
                <div class="panel mb-5">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">Action Required</div>
                            <div class="panel-desc">Items identified from current employee, document and compliance records.</div>
                        </div>
                        <a href="{{ route('admin.reports.index', ['tab' => 'tasks']) }}" class="btn btn-sm btn-light-custom">View all tasks{{ $payload['open_tasks'] ? ' ('.$payload['open_tasks'].')' : '' }}</a>
                    </div>
                    @forelse ($payload['actions'] as $item)
                        <div class="action-item">
                            <span class="action-priority-dot priority-dot-{{ strtolower($item['priority']) }}"></span>
                            <div class="action-body">
                                <div class="action-title">{{ $item['issue'] }}</div>
                                <div class="action-meta">
                                    <span><i class="bi bi-person"></i> {{ $item['employee'] }}</span>
                                    @if ($item['due_date'])
                                        <span><i class="bi bi-calendar-event"></i> Due {{ \Illuminate\Support\Carbon::parse($item['due_date'])->format('j M Y') }}</span>
                                    @endif
                                    <span><i class="bi bi-person-check"></i> {{ $item['responsible'] ?: 'Unassigned' }}</span>
                                </div>
                            </div>
                            <a href="{{ $item['href'] }}" class="btn btn-sm btn-light-custom">Review</a>
                        </div>
                    @empty
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div>
                            <div class="empty-state-title">No immediate action required</div>
                            <div class="empty-state-text">All monitored employee records are up to date. New items will appear here automatically.</div>
                        </div>
                    @endforelse
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="panel">
                            <div class="panel-header"><div class="panel-title">Employees by Department</div></div>
                            @if (count($payload['charts']['departments']['labels']))
                                <div class="chart-wrap"><canvas id="chartDept"></canvas></div>
                            @else
                                <p class="text-secondary-custom small mb-0">No current employees are assigned to a department yet.</p>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel">
                            <div class="panel-header"><div class="panel-title">Today's Attendance Summary</div></div>
                            @if (count($payload['charts']['attendance']['labels']))
                                <div class="chart-wrap"><canvas id="chartAttendance"></canvas></div>
                            @else
                                <p class="text-secondary-custom small mb-0">No attendance has been recorded today.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel mb-4">
                    <div class="panel-header">
                        <div class="panel-title">Upcoming Compliance Events</div>
                        <a href="{{ route('admin.compliance.index', ['tab' => 'calendar']) }}" class="btn btn-sm btn-ghost">Full calendar</a>
                    </div>
                    @forelse ($payload['upcoming'] as $event)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-medium small">{{ $event['label'] }}</div>
                                <div class="text-meta">{{ $event['type'] }}</div>
                            </div>
                            <span class="status-badge badge-{{ $event['tone'] }}">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('j M Y') }}</span>
                        </div>
                    @empty
                        <p class="text-secondary-custom small mb-0">No compliance events are scheduled in the near term.</p>
                    @endforelse
                </div>

                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Recent Activity</div></div>
                    @if ($payload['activity']->isEmpty())
                        <p class="text-secondary-custom small mb-0">No recent activity recorded.</p>
                    @else
                        <div class="timeline">
                            @foreach ($payload['activity'] as $event)
                                <div class="timeline-item">
                                    <div class="timeline-date">{{ $event['date'] ? \Illuminate\Support\Carbon::parse($event['date'])->format('j M Y') : '—' }}</div>
                                    <div class="timeline-title">{{ $event['title'] }}</div>
                                    <div class="timeline-by">by {{ $event['by'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            const charts = {{ Illuminate\Support\Js::from($payload['charts']) }};
            const dept = document.getElementById('chartDept');
            if (dept && charts.departments.labels.length) {
                new Chart(dept, {
                    type: 'bar',
                    data: { labels: charts.departments.labels, datasets: [{ data: charts.departments.data, backgroundColor: '#6B6B24', borderRadius: 4, maxBarThickness: 28 }] },
                    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, maintainAspectRatio: false }
                });
            }
            const attendance = document.getElementById('chartAttendance');
            if (attendance && charts.attendance.labels.length) {
                new Chart(attendance, {
                    type: 'doughnut',
                    data: { labels: charts.attendance.labels, datasets: [{ data: charts.attendance.data, backgroundColor: ['#287A52', '#6B6B24', '#B7791F', '#B83A3A', '#8994A3', '#B6B247', '#3169A8'] }] },
                    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }, maintainAspectRatio: false }
                });
            }
        });
    </script>
@endpush
