@php
    // Reusable landing hero — คุมโทน navy เดียวกับ admin_hero (เวอร์ชันเบา)
    // props: $kicker, $title, $desc (optional), $badge (optional), $testid (optional)
    $kicker = $kicker ?? '';
    $title  = $title ?? '';
    $desc   = $desc ?? null;
    $badge  = $badge ?? null;
    $testid = $testid ?? 'role-hero';
@endphp

<div class="card role-hero-card" data-testid="{{ $testid }}">
    <div class="role-hero-top">
        <div class="role-hero-copy">
            @if($kicker)
                <div class="role-hero-kicker">{{ $kicker }}</div>
            @endif
            <h1>{{ $title }}</h1>
            @if($desc)
                <p class="role-hero-desc">{{ $desc }}</p>
            @endif
        </div>

        @if($badge)
            <span class="role-hero-badge">{{ $badge }}</span>
        @endif
    </div>
</div>

<style>
    .role-dashboard {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: clamp(14px, 2vw, 28px) clamp(14px, 2vw, 28px) clamp(22px, 2.4vw, 32px);
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
        gap: clamp(16px, 1.8vw, 24px);
        background:
            radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                var(--bg) 100%);
    }

    .role-hero-card {
        padding: 24px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 30%),
            var(--surface);
        border-color: color-mix(in oklch, var(--brand-navy) 26%, var(--border));
        overflow: hidden;
    }

    .role-hero-card:hover,
    .role-hero-card:focus-within {
        transform: none;
    }

    .role-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: nowrap;
    }

    .role-hero-copy {
        flex: 1 1 0;
        min-width: 0;
    }

    .role-hero-kicker {
        margin-bottom: 4px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
        color: color-mix(in oklch, var(--brand-navy) 52%, var(--fg-3));
    }

    .role-hero-copy h1 {
        margin: 0;
        font-family: var(--font-display);
        font-size: 24px;
        font-weight: 800;
        line-height: 1.25;
        color: var(--fg-1);
    }

    .role-hero-desc {
        margin: 10px 0 0;
        max-width: 72ch;
        color: var(--fg-2);
        font-size: 13px;
        line-height: 1.6;
    }

    .role-hero-badge {
        display: inline-flex;
        align-items: center;
        align-self: flex-start;
        min-height: 36px;
        padding: 7px 16px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        border-radius: var(--r-pill);
        background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
        color: var(--brand-navy);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.2;
        white-space: nowrap;
        flex-shrink: 0;
    }

    @media (max-width: 720px) {
        .role-hero-card {
            padding: 18px;
        }
        .role-hero-top {
            flex-direction: column;
            align-items: stretch;
        }
        .role-hero-copy h1 {
            font-size: 22px;
        }
        .role-hero-badge {
            align-self: stretch;
            justify-content: center;
        }
    }
</style>
