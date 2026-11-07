@props(['name'])

<svg {{ $attributes }} width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 4.6V21" />
            @break
        @case('roles')
            <path d="m12 3-8 3v5c0 5 8 10 8 10s8-5 8-10V6Z" />
            <path d="m8.5 11.5 2.5 2.5 4.5-5" />
            @break
        @case('areas')
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <path d="M8 7h1m6 0h1M8 11h1m6 0h1M10 21v-5h4v5" />
            @break
        @case('groups')
            <rect x="9" y="3" width="6" height="5" rx="1" />
            <rect x="3" y="16" width="6" height="5" rx="1" />
            <rect x="15" y="16" width="6" height="5" rx="1" />
            <path d="M12 8v4M6 16v-4h12v4" />
            @break
        @case('active-users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3 21v-2a6 6 0 0 1 12 0v2m1-10 2 2 4-5" />
            @break
        @case('sessions')
            <rect x="3" y="4" width="18" height="13" rx="2" />
            <path d="M8 21h8m-4-4v4" />
            @break
        @case('alerts')
            <path d="M12 3 2 21h20ZM12 9v4" />
            <circle cx="12" cy="17" r="0.8" fill="currentColor" stroke="none" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('categories')
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
            @break
        @case('forms')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 12h8m-8 4h5" />
            @break
        @case('approvals')
            <path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2" />
            <rect x="9" y="2" width="6" height="4" rx="1" />
            <path d="m8 13 3 3 5-6" />
            @break
        @case('sla')
            <circle cx="12" cy="14" r="8" />
            <path d="M12 6V2M9 2h6m3 5 2-2m-8 5v4l2 2" />
            @break
        @case('technicians')
            <path d="M14 3a6 6 0 0 0-3 8l-7.5 7.5a2.1 2.1 0 0 0 3 3L14 14a6 6 0 0 0 7-8l-4 4-3-3 4-4a6 6 0 0 0-4 0Z" />
            @break
        @case('restricted')
            <rect x="5" y="10" width="14" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3" />
            @break
        @case('specialties')
            <circle cx="12" cy="8" r="5" />
            <path d="m8.8 12-1.3 9 4.5-2.5 4.5 2.5-1.3-9" />
            @break
    @endswitch
</svg>
