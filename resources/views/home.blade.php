@extends('layouts.master')

@php
    $user = auth()->user();
    $firstName = strtok($user->name ?? 'Team', ' ');
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('title', 'Dashboard | Bloxt HR')
@section('meta_description', 'Bloxt HR workforce and compliance dashboard.')

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
            <div class="alert alert-success mb-4" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <div class="kpi-grid mb-5">
            <div class="metric-card metric-accent-primary">
                <span class="metric-icon"><i class="bi bi-people"></i></span>
                <span class="metric-label">Total Employees</span>
                <span class="metric-value">128</span>
                <span class="metric-context">Across 8 departments</span>
            </div>
            <div class="metric-card metric-accent-success">
                <span class="metric-icon"><i class="bi bi-person-check"></i></span>
                <span class="metric-label">Active Employees</span>
                <span class="metric-value">119</span>
                <span class="metric-context">9 on leave or probation</span>
            </div>
            <div class="metric-card metric-accent-warning">
                <span class="metric-icon"><i class="bi bi-file-earmark-text"></i></span>
                <span class="metric-label">Documents Expiring Soon</span>
                <span class="metric-value">14</span>
                <span class="metric-context">6 within 30 days</span>
            </div>
            <div class="metric-card metric-accent-danger">
                <span class="metric-icon"><i class="bi bi-list-check"></i></span>
                <span class="metric-label">Outstanding HR Actions</span>
                <span class="metric-value">7</span>
                <span class="metric-context">Review the action centre below</span>
            </div>
        </div>

        <div class="dashboard-columns">
            <div>
                <div class="panel mb-5">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">Action Required</div>
                            <div class="panel-desc">Priority workforce and compliance items surfaced from the Bloxt HR theme.</div>
                        </div>
                        <a href="{{ route('password.request') }}" class="btn btn-sm btn-light-custom">Security tools</a>
                    </div>

                    <div class="action-item">
                        <span class="action-priority-dot priority-dot-high"></span>
                        <div class="action-body">
                            <div class="action-title">3 right-to-work checks need review this week.</div>
                            <div class="action-meta">
                                <span><i class="bi bi-person"></i> Compliance team</span>
                                <span><i class="bi bi-calendar-event"></i> Due in 2 days</span>
                                <span><i class="bi bi-person-check"></i> HR Administrator</span>
                            </div>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-sm btn-light-custom">Review</a>
                    </div>

                    <div class="action-item">
                        <span class="action-priority-dot priority-dot-medium"></span>
                        <div class="action-body">
                            <div class="action-title">Update probation notes for the engineering department.</div>
                            <div class="action-meta">
                                <span><i class="bi bi-person"></i> Engineering</span>
                                <span><i class="bi bi-calendar-event"></i> Due tomorrow</span>
                                <span><i class="bi bi-person-check"></i> People Operations</span>
                            </div>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-sm btn-light-custom">Review</a>
                    </div>

                    <div class="action-item">
                        <span class="action-priority-dot priority-dot-low"></span>
                        <div class="action-body">
                            <div class="action-title">Prepare payroll handoff for the September cycle.</div>
                            <div class="action-meta">
                                <span><i class="bi bi-person"></i> Finance</span>
                                <span><i class="bi bi-calendar-event"></i> Due in 5 days</span>
                                <span><i class="bi bi-person-check"></i> Payroll Manager</span>
                            </div>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-sm btn-light-custom">Review</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">Employees by Department</div>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="chartDept"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">Today's Attendance Summary</div>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="chartAttendance"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel mb-4">
                    <div class="panel-header">
                        <div class="panel-title">Upcoming Compliance Events</div>
                        <a href="{{ route('home') }}" class="btn btn-sm btn-ghost">View schedule</a>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-medium small">Visa expiry review</div>
                            <div class="text-meta">Immigration</div>
                        </div>
                        <span class="status-badge badge-warning">08 Sep 2026</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-medium small">Policy acknowledgement chase</div>
                            <div class="text-meta">HR operations</div>
                        </div>
                        <span class="status-badge badge-info">10 Sep 2026</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <div class="fw-medium small">Sponsor compliance audit</div>
                            <div class="text-meta">Compliance</div>
                        </div>
                        <span class="status-badge badge-accent">15 Sep 2026</span>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Recent Activity</div>
                    </div>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date">02 Sep 2026</div>
                            <div class="timeline-title">Updated attendance summary for the London office</div>
                            <div class="timeline-by">by {{ $user->name }}</div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-date">01 Sep 2026</div>
                            <div class="timeline-title">Uploaded sponsor compliance evidence pack</div>
                            <div class="timeline-by">by Compliance Team</div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-date">31 Aug 2026</div>
                            <div class="timeline-title">Completed onboarding for two new starters</div>
                            <div class="timeline-by">by People Operations</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            new Chart(document.getElementById('chartDept'), {
                type: 'bar',
                data: {
                    labels: ['Operations', 'Engineering', 'Commercial', 'Finance', 'Compliance'],
                    datasets: [{
                        data: [28, 34, 19, 11, 16],
                        backgroundColor: '#6B6B24',
                        borderRadius: 4,
                        maxBarThickness: 28
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    maintainAspectRatio: false
                }
            });

            new Chart(document.getElementById('chartAttendance'), {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Remote', 'Office', 'Leave', 'Not recorded'],
                    datasets: [{
                        data: [72, 18, 20, 9, 9],
                        backgroundColor: ['#287A52', '#3169A8', '#6B6B24', '#B7791F', '#8994A3']
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    maintainAspectRatio: false
                }
            });
        });
    </script>
@endpush
