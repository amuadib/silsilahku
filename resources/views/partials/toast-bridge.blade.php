@if (session('success'))
    <div x-data x-init="$dispatch('notify', { message: '{{ session('success') }}', type: 'success' })" class="hidden"></div>
@endif
@if (session('error'))
    <div x-data x-init="$dispatch('notify', { message: '{{ session('error') }}', type: 'error' })" class="hidden"></div>
@endif
