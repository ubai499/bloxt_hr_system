<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partial.head')
<body class="@yield('body_class', 'portal-page')">
    <a class="skip-link" href="#mainContent">Skip to main content</a>

    <div class="app-shell d-flex">
        @include('partial.navbar')

        <div class="app-main">
            @include('partial.header')

            <main id="mainContent" class="@yield('main_class')">
                @yield('content')
            </main>

            @include('partial.footer')
        </div>
    </div>

    @include('partial.scripts')
</body>
</html>
