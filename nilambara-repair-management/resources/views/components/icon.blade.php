@props(['name', 'size' => 20])

<svg {{ $attributes->merge(['width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.65', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3" /><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5.65" />
            @break
        @case('receipt')
            <path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3ZM9 7h6M9 11h6M9 15h3" />
            @break
        @case('tool')
            <path d="m14.5 6.5 3 3 3.25-3.25a6 6 0 0 1-7.68 7.68L6.5 20.5a2.12 2.12 0 0 1-3-3l6.57-6.57A6 6 0 0 1 17.75 3.25L14.5 6.5Z" />
            @break
        @case('clipboard')
            <rect x="5" y="4" width="14" height="17" rx="2" /><rect x="9" y="2" width="6" height="4" rx="1" /><path d="M9 11h6M9 15h6" />
            @break
        @case('document')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6ZM14 2v6h6M8 13h8M8 17h5" />
            @break
        @case('activity')
            <path d="M3 12h4l3-8 4 16 3-8h4" />
            @break
        @case('invoice')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 7h6M9 11h6M9 15h2" />
            @break
        @case('wallet')
            <path d="M20 8V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V7M20 12h-5v5h5" /><path d="M17 14.5h.01" />
            @break
        @case('credit')
            <rect x="2" y="4" width="20" height="16" rx="3" /><path d="M2 9h20M6 15h4M15 15h3" />
            @break
        @case('pos')
            <rect x="4" y="3" width="16" height="12" rx="2" /><path d="M9 15v5M15 15v5M6 21h12M8 7h8M8 11h4" />
            @break
        @case('box')
            <path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5M12 13v9M7.5 5.5l9 5" />
            @break
        @case('truck')
            <path d="M1 4h13v13H1V4ZM14 9h5l3 4v4h-8M3 17h1M9 17h8" /><circle cx="6.5" cy="18" r="2.5" /><circle cx="19.5" cy="18" r="2.5" />
            @break
        @case('bag')
            <path d="M5 7h14l2 14H3L5 7ZM8 8V6a4 4 0 0 1 8 0v2" />
            @break
        @case('badge')
            <rect x="4" y="5" width="16" height="16" rx="2" /><path d="M9 3h6v4H9V3M8 17a4 4 0 0 1 8 0" /><circle cx="12" cy="12" r="2" />
            @break
        @case('shield')
            <path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4ZM8 12l3 3 5-6" />
            @break
        @case('chart')
            <path d="M3 3v18h18M7 16v-4M12 16V7M17 16v-7" />
            @break
        @case('database')
            <ellipse cx="12" cy="5" rx="9" ry="3" /><path d="M3 5v7c0 4 18 4 18 0V5M3 12v7c0 4 18 4 18 0v-7" />
            @break
        @case('settings')
            <path d="m9 3-.6 2.3-2 .9-2.2-.6-2 3.4 1.6 1.7v2.6L2.2 15l2 3.4 2.2-.6 2 .9L9 21h4l.6-2.3 2-.9 2.2.6 2-3.4-1.6-1.7v-2.6L19.8 9l-2-3.4-2.2.6-2-.9L13 3H9Z" /><circle cx="11" cy="12" r="3" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4M17 3v4M3 11h18M8 15h2M14 15h2" />
            @break
        @case('logout')
            <path d="M9 4H4v16h5M14 8l4 4-4 4M8 12h13" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @case('arrow-right')
            <path d="M4 12h16M14 6l6 6-6 6" />
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3" />
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9" /><ellipse cx="12" cy="12" rx="4" ry="9" /><path d="M3 12h18" />
            @break
        @case('eye')
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" />
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.1 3.7M6.1 6.1A18.4 18.4 0 0 0 2 12s3.5 7 10 7a11.7 11.7 0 0 0 5.1-1.2M10 10a3 3 0 0 0 4 4" />
            @break
        @default
            <circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" />
    @endswitch
</svg>
