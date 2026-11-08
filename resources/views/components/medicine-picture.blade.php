@props(['medicine', 'show' => 'both'])

{{--
    The medicine's picture. A product photo (image_path, under public/) is
    used when staff have added one. Otherwise the pack is drawn: a box in the
    brand's colour with a blister strip in front, holding the right number
    and shape of pills. `show` is "both", "box" or "strip".
--}}
@php
    $palette = ['#d9480f', '#0b7285', '#5f3dc4', '#2b8a3e', '#e67700', '#1864ab', '#a61e4d', '#364fc7', '#087f5b'];
    $color = $palette[crc32($medicine->brand) % count($palette)];

    // Blend a colour toward another: $mix('#d9480f', '#ffffff', 0.9) is a pale tint.
    $mix = function (string $from, string $to, float $weight): string {
        $a = sscanf($from, '#%02x%02x%02x');
        $b = sscanf($to, '#%02x%02x%02x');

        return vsprintf('#%02x%02x%02x', array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $weight), $a, $b));
    };

    $form = Str::lower($medicine->dosage_form);
    $pill = match (true) {
        str_contains($form, 'softgel') => 'softgel',
        str_contains($form, 'capsule') => 'capsule',
        str_contains($form, 'caplet') => 'caplet',
        default => 'tablet',
    };

    // Box: front face W × H with depth D, label band across the middle.
    [$W, $H, $D] = [96, 92, 14];
    $brandSize = round(min(17, 78 / (0.6 * max(1, mb_strlen($medicine->brand)))), 1);
    $strengthSize = round(min(9, 78 / (0.52 * max(1, mb_strlen($medicine->strength)))), 1);

    // Strip: SW × SH, two rows of pockets.
    [$SW, $SH] = [96, 60];
    $pieces = max(1, $medicine->pieces_per_strip);
    $cols = (int) ceil($pieces / 2);
    $cellW = ($SW - 12) / $cols;
    $cellH = ($SH - 12) / 2;

    [$viewBox, $boxAt, $stripAt] = match ($show) {
        'box' => ['0 0 130 124', [12, 8], null],
        'strip' => ['0 0 120 84', null, [12, 12]],
        default => ['0 0 220 132', [26, 10], [106, 60]],
    };
@endphp

<div {{ $attributes->class('relative flex items-center justify-center overflow-hidden') }} style="background-color: {{ $mix($color, '#ffffff', 0.9) }}">
    @if ($medicine->imageUrl())
        <img src="{{ $medicine->imageUrl() }}" alt="{{ $medicine->fullName() }}, {{ $medicine->drugLabel() }}" class="size-full object-contain p-2" loading="lazy">
    @else
        <svg viewBox="{{ $viewBox }}" class="h-full max-w-full" aria-hidden="true" focusable="false" font-family="Figtree, ui-sans-serif, system-ui, sans-serif">
            @if ($boxAt)
                <g transform="translate({{ $boxAt[0] }} {{ $boxAt[1] }})">
                    <ellipse cx="{{ $W / 2 + 6 }}" cy="{{ $H + $D + 3 }}" rx="{{ $W / 2 + 4 }}" ry="4" fill="#000" opacity="0.08" />
                    <path d="M0 {{ $D }} L{{ $D }} 0 L{{ $W + $D }} 0 L{{ $W }} {{ $D }} Z" fill="{{ $mix($color, '#ffffff', 0.25) }}" />
                    <path d="M{{ $W }} {{ $D }} L{{ $W + $D }} 0 L{{ $W + $D }} {{ $H }} L{{ $W }} {{ $H + $D }} Z" fill="{{ $mix($color, '#000000', 0.3) }}" />
                    <rect x="0" y="{{ $D }}" width="{{ $W }}" height="{{ $H }}" fill="{{ $color }}" />
                    <rect x="0" y="{{ $D + $H - 16 }}" width="{{ $W }}" height="5" fill="#fff" opacity="0.3" />
                    <rect x="7" y="{{ $D + 24 }}" width="{{ $W - 14 }}" height="38" rx="4" fill="#fff" />
                    <text x="{{ $W / 2 }}" y="{{ $D + 43 }}" text-anchor="middle" font-size="{{ $brandSize }}" font-weight="800" fill="{{ $mix($color, '#000000', 0.15) }}">{{ $medicine->brand }}</text>
                    <text x="{{ $W / 2 }}" y="{{ $D + 56 }}" text-anchor="middle" font-size="{{ $strengthSize }}" font-weight="600" fill="#495057">{{ $medicine->strength }}</text>
                    @if ($medicine->requiresPrescription())
                        <circle cx="{{ $W - 13 }}" cy="{{ $D + 12 }}" r="8.5" fill="#fff" />
                        <text x="{{ $W - 13 }}" y="{{ $D + 15.5 }}" text-anchor="middle" font-size="9.5" font-weight="800" fill="{{ $color }}">Rx</text>
                    @endif
                </g>
            @endif

            @if ($stripAt)
                <g transform="translate({{ $stripAt[0] }} {{ $stripAt[1] }}) rotate(-5 {{ $SW / 2 }} {{ $SH / 2 }})">
                    <rect x="2" y="4" width="{{ $SW }}" height="{{ $SH }}" rx="8" fill="#000" opacity="0.1" />
                    <rect width="{{ $SW }}" height="{{ $SH }}" rx="8" fill="#eef0f3" stroke="#c4c9d0" stroke-width="1" />
                    <rect x="0" y="{{ $SH / 2 - 0.5 }}" width="{{ $SW }}" height="1" fill="#c4c9d0" opacity="0.6" />
                    @for ($i = 0; $i < $pieces; $i++)
                        @php
                            $cx = round(6 + $cellW * ($i % $cols) + $cellW / 2, 1);
                            $cy = round(6 + $cellH * intdiv($i, $cols) + $cellH / 2, 1);
                            $long = round(min($cellH * 0.78, $cellW * 1.1), 1);
                            $thick = round(min($cellW * 0.5, $cellH * 0.4), 1);
                        @endphp
                        <rect x="{{ $cx - $cellW / 2 + 2 }}" y="{{ $cy - $cellH / 2 + 2 }}" width="{{ $cellW - 4 }}" height="{{ $cellH - 4 }}" rx="5" fill="#dde1e6" />
                        @switch ($pill)
                            @case ('softgel')
                                <ellipse cx="{{ $cx }}" cy="{{ $cy }}" rx="{{ $thick / 2 + 0.5 }}" ry="{{ $long / 2 }}" fill="#f4b740" stroke="#d99a1c" stroke-width="0.8" />
                                <ellipse cx="{{ $cx - 1 }}" cy="{{ $cy - $long / 5 }}" rx="1.2" ry="2.4" fill="#fff" opacity="0.7" />
                                @break
                            @case ('capsule')
                                <g transform="rotate(90 {{ $cx }} {{ $cy }})">
                                    <rect x="{{ $cx - $long / 2 }}" y="{{ $cy - $thick / 2 }}" width="{{ $long }}" height="{{ $thick }}" rx="{{ $thick / 2 }}" fill="#fff" />
                                    <rect x="{{ $cx - $long / 2 }}" y="{{ $cy - $thick / 2 }}" width="{{ $long / 2 }}" height="{{ $thick }}" rx="{{ $thick / 2 }}" fill="{{ $color }}" />
                                    <rect x="{{ $cx - $long / 4 }}" y="{{ $cy - $thick / 2 }}" width="{{ $long / 4 }}" height="{{ $thick }}" fill="{{ $color }}" />
                                    <rect x="{{ $cx - $long / 2 }}" y="{{ $cy - $thick / 2 }}" width="{{ $long }}" height="{{ $thick }}" rx="{{ $thick / 2 }}" fill="none" stroke="#adb5bd" stroke-width="0.8" />
                                </g>
                                @break
                            @case ('caplet')
                                <rect x="{{ $cx - $thick / 2 }}" y="{{ $cy - $long / 2 }}" width="{{ $thick }}" height="{{ $long }}" rx="{{ $thick / 2 }}" fill="#fff" stroke="#adb5bd" stroke-width="0.8" />
                                @break
                            @default
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ round(min($cellW, $cellH) * 0.33, 1) }}" fill="#fff" stroke="#adb5bd" stroke-width="0.8" />
                                <line x1="{{ $cx - min($cellW, $cellH) * 0.18 }}" y1="{{ $cy }}" x2="{{ $cx + min($cellW, $cellH) * 0.18 }}" y2="{{ $cy }}" stroke="#ced4da" stroke-width="0.8" />
                        @endswitch
                    @endfor
                </g>
            @endif
        </svg>
    @endif
</div>
