{{-- A price in the kiosk's currency: <x-money :amount="$medicine->piece_price" /> → ₱5.00 --}}
@props(['amount'])
<span {{ $attributes->class('tabular-nums') }}>{{ \App\Support\Money::format($amount) }}</span>