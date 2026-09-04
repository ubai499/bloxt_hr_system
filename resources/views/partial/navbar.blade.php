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
                ['label' => 'Employees', 'icon' => 'bi-people', 'href' => route('admin.employees.index'), 'active' => request()->routeIs('admin.employees.*'), 'disabled' => false],
                ['label' => 'Departments', 'icon' => 'bi-diagram-3', 'href' => route('admin.departments.index'), 'active' => request()->routeIs('admin.departments.*'), 'disabled' => false],
            ],
        ],
        [
            'heading' => 'Time',
            'items' => [
                ['label' => 'Attendance', 'icon' => 'bi-calendar-check', 'href' => route('admin.attendance.index'), 'active' => request()->routeIs('admin.attendance.*'), 'disabled' => false],
                ['label' => 'Leave', 'icon' => 'bi-airplane', 'href' => route('admin.leave.index'), 'active' => request()->routeIs('admin.leave.*'), 'disabled' => false],
            ],
        ],
        [
            'heading' => 'Employment',
            'items' => [
                ['label' => 'Documents', 'icon' => 'bi-folder2-open', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Recruitment', 'icon' => 'bi-person-plus', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
        [
            'heading' => 'Compliance',
            'items' => [
                ['label' => 'Right to Work', 'icon' => 'bi-patch-check', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Sponsor Compliance', 'icon' => 'bi-shield-check', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
        [
            'heading' => 'Administration',
            'items' => [
                ['label' => 'Users & Roles', 'icon' => 'bi-person-gear', 'href' => '#', 'active' => false, 'disabled' => true],
                ['label' => 'Company Settings', 'icon' => 'bi-building', 'href' => '#', 'active' => false, 'disabled' => true],
            ],
        ],
    ];

    if (! $isAdmin) {
        $navGroups = array_values(array_filter($navGroups, fn ($group) => ! in_array($group['heading'], ['People', 'Compliance', 'Administration'])));
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
