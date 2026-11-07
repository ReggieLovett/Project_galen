{{-- A price in the kiosk's currency: <x-money :amount="$medicine->strip_price" /> → ₱50.00 --}}
@props(['amount'])
<span {{ $attributes->class('tabular-nums') }}>{{ \App\Support\Money::format($amount) }}</span>