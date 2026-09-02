@php
    $user = auth()->user();
    $segments = preg_split('/\s+/', trim($user->name ?? 'System User')) ?: [];
    $initials = collect($segments)->filter()->take(2)->map(fn ($segment) => strtoupper(substr($segment, 0, 1)))->implode('');
@endphp

<header class="app-header">
    <button class="header-menu-toggle" id="sidebarToggle" aria-label="Toggle navigation" aria-expanded="false">
        <i class="bi bi-list"></i>
    </button>

    <div class="header-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search employees, documents, tasks..." aria-label="Global search" disabled>
    </div>

    <div class="header-actions">
        <a class="btn btn-primary btn-sm" href="{{ route('home') }}">
            <i class="bi bi-speedometer2"></i>
            <span class="d-none d-sm-inline ms-1">Dashboard</span>
        </a>

        @if (Route::has('password.request'))
            <a class="header-icon-btn" href="{{ route('password.request') }}" aria-label="Security tools">
                <i class="bi bi-shield-lock"></i>
            </a>
        @endif

        <div class="dropdown">
            <button class="header-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="header-user-avatar">{{ $initials ?: 'SU' }}</span>
                <span class="header-user-info">
                    <span class="header-user-name d-block">{{ $user->name }}</span>
                    <span class="header-user-role d-block">System User</span>
                </span>
                <i class="bi bi-chevron-down d-none d-sm-inline" style="font-size: .7rem; color: #8994A3;"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Signed in as {{ $user->email }}</h6></li>
                <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                @if (Route::has('password.request'))
                    <li><a class="dropdown-item" href="{{ route('password.request') }}"><i class="bi bi-key me-2"></i>Reset Password</a></li>
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
