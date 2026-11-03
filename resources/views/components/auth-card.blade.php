@props(['heading'])

{{-- Centered card used by the login page --}}
<div class="mx-auto w-full max-w-md rounded-2xl border-2 border-line bg-surface p-8">
    <h1 class="mb-6 text-center text-2xl font-bold">{{ $heading }}</h1>

    {{ $slot }}
</div>
