@php
    $user = auth()->user();
    $segments = preg_split('/\s+/', trim($user->name ?? 'System User')) ?: [];
    $initials = collect($segments)->filter()->take(2)->map(fn ($segment) => strtoupper(substr($segment, 0, 1)))->implode('');
    $dashboardRoute = $user?->hasRole('admin') ? route('admin.dashboard') : route('employee.dashboard');
    $quickCreateItems = [
        ['label' => 'Add Employee', 'icon' => 'bi-person-plus', 'href' => route('admin.employees.create'), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Add Department', 'icon' => 'bi-plus-lg', 'href' => route('admin.employees.index', ['tab' => 'departments']), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Record Attendance', 'icon' => 'bi-calendar-check', 'href' => route('admin.attendance.create'), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Record Absence', 'icon' => 'bi-clipboard-x', 'href' => route('admin.attendance.absence.create'), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Create Leave Request', 'icon' => 'bi-airplane', 'href' => $user?->hasRole('admin') ? route('admin.leave.create') : '#', 'visible' => $user?->hasRole('admin')],
        ['label' => 'New Vacancy', 'icon' => 'bi-person-plus', 'href' => route('admin.recruitment.create'), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Record Right-to-Work Check', 'icon' => 'bi-patch-check', 'href' => route('admin.right-to-work.create'), 'visible' => $user?->hasRole('admin')],
        ['label' => 'Upload Document', 'icon' => 'bi-cloud-upload', 'href' => route($user?->hasRole('admin') ? 'admin.documents.create' : 'employee.documents.create'), 'visible' => $user?->hasAnyRole(['admin', 'employee'])],
    ];
@endphp

<header class="app-header">
    <button class="header-menu-toggle" id="sidebarToggle" aria-label="Toggle navigation" aria-expanded="false">
        <i class="bi bi-list"></i>
    </button>

    <div class="header-search">
        <i class="bi bi-search"></i>
        <input type="search" id="globalSearchInput" data-document-search-url="{{ route($user?->hasRole('admin') ? 'admin.documents.search' : 'employee.documents.search') }}" placeholder="Search employees, documents, tasks..." aria-label="Global search" aria-controls="globalSearchResults" aria-expanded="false" autocomplete="off" maxlength="255">
        <div class="dropdown-menu shadow-sm" id="globalSearchResults" style="width:100%; max-height:360px; overflow-y:auto;">
            <div class="px-3 py-3 text-meta">Search suggestions will appear here as more modules are connected.</div>
        </div>
    </div>

    <div class="header-actions">
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">Create</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach ($quickCreateItems as $item)
                    @if ($item['visible'])
                        <li>
                            <a class="dropdown-item" href="{{ $item['href'] }}">
                                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>

        <div class="dropdown">
            <button class="header-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px; max-height:420px; overflow-y:auto;">
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
                    <div class="empty-state-text mb-0">You have no notifications right now.</div>
                </div>
            </div>
        </div>

        @if (Route::has('password.request'))
            <a class="header-icon-btn" href="{{ route('password.request') }}" aria-label="Security tools" title="Security">
                <i class="bi bi-shield-lock"></i>
            </a>
        @endif

        <button class="header-icon-btn" type="button" aria-label="Help" title="Help">
            <i class="bi bi-question-circle"></i>
        </button>

        <div class="dropdown">
            <button class="header-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="header-user-avatar">{{ $initials ?: 'SU' }}</span>
                <span class="header-user-info">
                    <span class="header-user-name d-block">{{ $user->name }}</span>
                    <span class="header-user-role d-block">{{ ucfirst($user->getRoleNames()->first() ?? 'Employee') }}</span>
                </span>
                <i class="bi bi-chevron-down d-none d-sm-inline" style="font-size: .7rem; color: #8994A3;"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Signed in as {{ $user->email }}</h6></li>
                <li><a class="dropdown-item" href="{{ $dashboardRoute }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                @if (Route::has('password.request'))
                    <li><a class="dropdown-item" href="{{ route('password.request') }}"><i class="bi bi-shield-lock me-2"></i>Security</a></li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger" type="button" data-action="logout">
                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>
