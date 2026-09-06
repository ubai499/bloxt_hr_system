{{-- Shared feedback for redirect responses and AJAX callers using HR.toast(). --}}
@once
    <div class="toast-stack" aria-live="polite" aria-relevant="additions"></div>
    <script src="{{ asset('assets/js/notifications.js') }}"></script>
    <script>
        @foreach (['success' => 'Success', 'error' => 'Unable to complete action', 'warning' => 'Please check', 'info' => 'Information'] as $type => $title)
            @if (session()->has($type))
                HR.toast({
                    type: {{ Illuminate\Support\Js::from($type === 'error' ? 'danger' : $type) }},
                    title: {{ Illuminate\Support\Js::from(session('toast_title', $title)) }},
                    text: {{ Illuminate\Support\Js::from(session($type)) }}
                });
            @endif
        @endforeach
    </script>
@endonce
