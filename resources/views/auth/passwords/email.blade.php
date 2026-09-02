@extends('layouts.app')

@section('title', 'Forgot Password | Bloxt HR')
@section('meta_description', 'Request a password reset for your Bloxt HR account.')

@section('content')
    <div class="login-page">
        <div class="login-card">
            <div class="login-brand">
                <img src="{{ asset('assets/images/bloxt-logo.jpg') }}" alt="Bloxt Limited" class="login-brand-logo">
                <p>People &amp; Compliance</p>
            </div>

            <p class="text-secondary-custom small mb-4">Enter your work email and we will send you a password reset link.</p>

            @if (session('status'))
                <div class="alert alert-success py-2 small mb-3" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="mb-3 form-field">
                    <label class="form-label" for="email">Work email<span class="required-indicator">*</span></label>
                    <input
                        id="email"
                        type="email"
                        class="form-control @error('email') is-invalid @enderror"
                        name="email"
                        value="{{ old('email') }}"
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

                <button type="submit" class="btn btn-primary w-100 mb-3">Send Password Reset Link</button>
            </form>

            <a href="{{ route('login') }}" class="small">Back to sign in</a>
        </div>
    </div>
@endsection
