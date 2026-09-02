@extends('layouts.app')

@section('title', 'Sign In | Bloxt HR')
@section('meta_description', 'Sign in to the Bloxt HR employee and compliance system.')

@section('content')
    <div class="login-page">
        <div class="login-card">
            <div class="login-brand">
                <img src="{{ asset('assets/images/bloxt-logo.jpg') }}" alt="Bloxt Limited" class="login-brand-logo">
                <p>People &amp; Compliance</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success py-2 small mb-3" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2 small mb-3" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="mb-3 form-field">
                    <label class="form-label" for="email">Work email<span class="required-indicator">*</span></label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        autocomplete="email"
                        placeholder="you@bloxthr.com"
                        required
                        autofocus
                    >
                    @error('email')
                        <div class="invalid-feedback-custom d-block">
                            <i class="bi bi-exclamation-circle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="mb-2 form-field">
                    <label class="form-label" for="password">Password<span class="required-indicator">*</span></label>
                    <div class="input-group">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="form-control @error('password') is-invalid @enderror"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            required
                        >
                        <button
                            class="btn btn-light-custom border"
                            type="button"
                            data-password-toggle
                            data-password-target="password"
                            aria-label="Show password"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback-custom d-block">
                            <i class="bi bi-exclamation-circle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign In</button>
            </form>

            <div class="demo-credentials-box">
                This theme is now wired into Laravel auth. Use an account from your application database to sign in.
            </div>

            <p class="login-footer-note">Authorised company users only. Unauthorised access is prohibited.</p>
        </div>
    </div>
@endsection
