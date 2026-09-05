<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="@yield('meta_description', 'Bloxt HR people and compliance management system.')">
    <title>@yield('title', config('app.name', 'Bloxt HR'))</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">

    <style>
        .skip-link {
            position: absolute;
            top: -40px;
            left: 1rem;
            z-index: 1100;
            background: #111827;
            color: #fff;
            padding: 0.65rem 0.9rem;
            border-radius: 0.5rem;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 1rem;
        }

        .sidebar-nav-link.is-disabled {
            opacity: 0.58;
            pointer-events: none;
        }

        .header-search input[disabled] {
            cursor: not-allowed;
        }

        .header-user-link {
            color: inherit;
            text-decoration: none;
        }

        .header-user-link:hover {
            color: inherit;
        }

        .portal-footer {
            padding: 0 1.5rem 1.5rem;
        }

        /* Keep adjacent row actions visually distinct without widening tables unnecessarily. */
        .table-app td.text-end > .btn + .btn,
        .table-app td.text-end > .btn + form,
        .table-app td.text-end > form + .btn,
        .table-app td.text-end > form + form {
            margin-left: 0.35rem;
        }

        @media (min-width: 992px) {
            .portal-footer {
                padding: 0 2rem 2rem;
            }
        }
    </style>

    @stack('styles')
</head>
