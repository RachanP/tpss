@php
    // Reusable empty/placeholder card — คุมโทน navy เดียวกับ admin
    // props: $icon (svg inner markup), $title, $desc (optional)
    $icon  = $icon ?? '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>';
    $title = $title ?? 'อยู่ระหว่างพัฒนา';
    $desc  = $desc ?? 'หน้านี้อยู่ระหว่างพัฒนา';
@endphp

<div class="card role-empty-card">
    <div class="role-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
    </div>
    <div class="role-empty-title">{{ $title }}</div>
    <div class="role-empty-desc">{{ $desc }}</div>
</div>

<style>
    .role-empty-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        padding: 48px 24px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 40%),
            var(--surface);
        border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
    }

    .role-empty-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin-bottom: 8px;
        border-radius: 50%;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        color: var(--brand-navy);
    }

    .role-empty-title {
        font-family: var(--font-display);
        font-size: 17px;
        font-weight: 800;
        color: var(--fg-1);
    }

    .role-empty-desc {
        max-width: 52ch;
        color: var(--fg-3);
        font-size: 13px;
        line-height: 1.55;
    }
</style>
