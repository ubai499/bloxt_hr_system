<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partial.head')
<body class="@yield('body_class', 'auth-page')">
    @yield('content')

    @include('partial.scripts')
</body>
</html>
