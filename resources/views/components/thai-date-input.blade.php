@props([
    'name',
    'value' => null,
    'required' => false,
    'helper' => 'กรอกวันที่เป็น วว/ดด/พ.ศ. เช่น 23/06/2569',
])

@php
    $displayValue = \App\Support\ThaiDate::formatForInput(old($name, $value));
@endphp

<div x-data="{
    maskThaiDate(value) {
        const raw = String(value || '').trim();
        const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (iso) return iso[3] + '/' + iso[2] + '/' + (parseInt(iso[1], 10) + 543);

        const digits = raw.replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 2) return digits.length === 2 ? digits + '/' : digits;
        if (digits.length <= 4) return digits.slice(0, 2) + '/' + digits.slice(2) + (digits.length === 4 ? '/' : '');
        return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
    }
}">
    <input
        type="text"
        name="{{ $name }}"
        value="{{ $displayValue }}"
        placeholder="วว/ดด/พ.ศ."
        inputmode="numeric"
        autocomplete="off"
        {{ $required ? 'required' : '' }}
        {{ $attributes }}
        @input="$event.target.value = maskThaiDate($event.target.value)"
        @paste="const el = $event.target; $nextTick(() => { el.value = maskThaiDate(el.value) })">
    @if($helper)
        <p style="font-size:11px;color:var(--fg-3);margin-top:4px;line-height:1.55;">{{ $helper }}</p>
    @endif
</div>
