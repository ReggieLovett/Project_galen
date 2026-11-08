@props(['title' => null, 'noindex' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#d71920">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title>{{ $title ? "{$title} · " : '' }}{{ config('kiosk.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body {{ $attributes->merge(['class' => 'min-h-dvh']) }}>
        {{ $slot }}
    </body>
</html>
