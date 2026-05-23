@props([
    'name',
    'value' => null,
    'required' => false,
    'helper' => 'กรอกวันที่เป็น วว/ดด/พ.ศ. เช่น 23/06/2569',
    'calendar' => true,
])

@php
    // ปฏิทินเลือกวันที่ — JS/CSS ลงทะเบียนไว้ที่ app-layout (Alpine.data('thaiDateInput'))
    $displayValue = \App\Support\ThaiDate::formatForInput(old($name, $value));
    $tdiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $tdiWeekdays = ['จ','อ','พ','พฤ','ศ','ส','อา'];
    $tdiNowYear = (int) date('Y');
@endphp

<div
    class="tdi-wrap"
    x-data="thaiDateInput()"
    @if($calendar) @click.outside="calOpen = false" @keydown.escape="calOpen = false" @endif>
    <input
        x-ref="thaiInput"
        type="text"
        name="{{ $name }}"
        value="{{ $displayValue }}"
        placeholder="วว/ดด/พ.ศ."
        inputmode="numeric"
        autocomplete="off"
        {{ $required ? 'required' : '' }}
        {{ $calendar ? $attributes->merge(['class' => 'tdi-input-cal']) : $attributes }}
        @input="$event.target.value = maskThaiDate($event.target.value)"
        @paste="const el = $event.target; $nextTick(() => { el.value = maskThaiDate(el.value) })">

    @if($calendar)
        <button type="button" class="tdi-cal-btn" tabindex="-1"
            @click="tdiToggle()" :aria-expanded="calOpen" aria-label="เปิดปฏิทินเลือกวันที่">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </button>
        <div class="tdi-pop" x-show="calOpen" x-cloak x-transition.opacity role="dialog" aria-label="ปฏิทินเลือกวันที่">
            <div class="tdi-pop-head">
                <button type="button" class="tdi-pop-nav" @click="tdiShiftMonth(-1)" aria-label="เดือนก่อนหน้า">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <select class="tdi-pop-sel tdi-pop-month" x-model.number="calMonth" aria-label="เลือกเดือน">
                    @foreach($tdiMonths as $monthIndex => $monthName)
                        <option value="{{ $monthIndex }}">{{ $monthName }}</option>
                    @endforeach
                </select>
                <select class="tdi-pop-sel tdi-pop-year" x-model.number="calYear" aria-label="เลือกปี พ.ศ.">
                    @for($cy = $tdiNowYear + 1; $cy >= $tdiNowYear - 60; $cy--)
                        <option value="{{ $cy }}">{{ $cy + 543 }}</option>
                    @endfor
                </select>
                <button type="button" class="tdi-pop-nav" @click="tdiShiftMonth(1)" aria-label="เดือนถัดไป">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
            <div class="tdi-pop-grid">
                @foreach($tdiWeekdays as $weekday)
                    <span class="tdi-pop-dow">{{ $weekday }}</span>
                @endforeach
                <template x-for="(cell, cellIndex) in tdiGrid" :key="cellIndex">
                    <button type="button" class="tdi-pop-day"
                        :class="{
                            'is-blank': !cell.day,
                            'is-today': cell.day && tdiDayIso(cell.day) === tdiTodayIso,
                            'is-selected': cell.day && tdiDayIso(cell.day) === tdiSelectedIso,
                        }"
                        :disabled="!cell.day"
                        @click="tdiPick(cell.day)"
                        x-text="cell.day || ''"></button>
                </template>
            </div>
        </div>
    @endif

    @if($helper)
        <p style="font-size:11px;color:var(--fg-3);margin-top:4px;line-height:1.55;">{{ $helper }}</p>
    @endif
</div>
