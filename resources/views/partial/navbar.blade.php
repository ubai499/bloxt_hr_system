@php
    $isAdmin = auth()->user()?->hasRole('admin');
    $dashboardRoute = $isAdmin ? route('admin.dashboard') : route('employee.dashboard');
    $navGroups = [
        [
            'heading' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => $dashboardRoute, 'active' => request()->routeIs('*.dashboard'), 'disabled' => false],
            ],
        ],
        [
            'heading' => 'People',
            'items' => [
                ['label' => 'Employees', 'icon' => 'bi-people', 'href' => route('admin.employees.index'), 'active' => request()->routeIs('admin.employees.*') && request('tab') !== 'departments', 'disabled' => false],
                ['label' => 'Departments', 'icon' => 'bi-diagram-3', 'href' => route('admin.employees.index', ['tab' => 'departments']), 'active' => (request()->routeIs('admin.employees.index') && request('tab') === 'departments') || request()->routeIs('admin.departments.*'), 'disabled' => false],
            ],
        ],
        [
            'heading' => 'Time',
            'items' => [
                ['label' => 'Attendance', 'icon' => 'bi-calendar-check', 'href' => route('admin.attendance.index'), 'active' => request()->routeIs('admin.attendance.*') && request('tab') !== 'absence', 'disabled' => false],
                ['label' => 'Leave', 'icon' => 'bi-airplane', 'href' => route('admin.leave.index'), 'active' => request()->routeIs('admin.leave.*'), 'disabled' => false],
                ['label' => 'Absence', 'icon' => 'bi-clipboard-x', 'href' => route('admin.attendance.index', ['tab' => 'absence']), 'active' => request()->routeIs('admin.attendance.*') && request('tab') === 'absence', 'disabled' => false],
            ],
        ],
        [
            'heading' => 'Employment',
            'items' => [
                ['label' => 'Contracts', 'icon' => 'bi-file-earmark-text', 'href' => $isAdmin ? route('admin.contracts.index') : '#', 'active' => request()->routeIs('admin.contracts.*'), 'disabled' => ! $isAdmin],
                ['label' => 'Documents', 'icon' => 'bi-folder2-open', 'href' => route($isAdmin ? 'admin.documents.index' : 'employee.documents.index'), 'active' => request()->routeIs('admin.documents.*', 'employee.documents.*'), 'disabled' => false],
                ['label' => 'Recruitment', 'icon' => 'bi-person-plus', 'href' => $isAdmin ? route('admin.recruitment.index') : '#', 'active' => request()->routeIs('admin.recruitment.*'), 'disabled' => ! $isAdmin],
            ],
        ],
        [
            'heading' => 'Compliance',
            'items' => [
                ['label' => 'Right to Work', 'icon' => 'bi-patch-check', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Immigration Records', 'icon' => 'bi-passport', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Sponsor Compliance', 'icon' => 'bi-shield-check', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Compliance Calendar', 'icon' => 'bi-calendar-week', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
        [
            'heading' => 'Finance',
            'items' => [
                ['label' => 'Salary Records', 'icon' => 'bi-cash-stack', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Payroll Records', 'icon' => 'bi-receipt', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
        [
            'heading' => 'Insights',
            'items' => [
                ['label' => 'Reports', 'icon' => 'bi-bar-chart', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Audit Log', 'icon' => 'bi-journal-text', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
        [
            'heading' => 'Administration',
            'items' => [
                ['label' => 'Users & Roles', 'icon' => 'bi-person-gear', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Company Settings', 'icon' => 'bi-building', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'HR Settings', 'icon' => 'bi-sliders', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
    ];

    if (! $isAdmin) {
        $navGroups = array_values(array_filter($navGroups, fn ($group) => ! in_array($group['heading'], ['People', 'Compliance', 'Finance', 'Insights', 'Administration'])));
    }
@endphp

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="app-sidebar" id="appSidebar" aria-label="Primary navigation">
    <div class="sidebar-brand">
        <span class="sidebar-brand-plate">
            <img src="{{ asset('assets/images/bloxt-logo.jpg') }}" alt="Bloxt Limited" class="sidebar-brand-logo">
        </span>
        <span class="sidebar-brand-tag">People &amp; Compliance</span>
    </div>

    <nav class="sidebar-nav-scroll">
        @foreach ($navGroups as $group)
            <div class="sidebar-nav-group">
                <div class="sidebar-nav-heading">{{ $group['heading'] }}</div>

                @foreach ($group['items'] as $item)
                    <a
                        class="sidebar-nav-link{{ $item['active'] ? ' active' : '' }}{{ $item['disabled'] ? ' is-disabled' : '' }}"
                        href="{{ $item['href'] }}"
                        @if ($item['active']) aria-current="page" @endif
                        @if ($item['disabled']) aria-disabled="true" tabindex="-1" title="Placeholder for next phase" @endif
                    >
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span class="sidebar-nav-label">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-text">Internal HR system.<br>Authorised company users only.</div>
    </div>
</aside>
