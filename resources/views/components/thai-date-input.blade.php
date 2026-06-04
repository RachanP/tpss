@props([
    'name',
    'value' => null,
    'required' => false,
    'helper' => 'กรอกวันที่เป็น วว/ดด/พ.ศ. เช่น 23/06/2569',
    'calendar' => true,
    'yearPast' => 60,
    'yearFuture' => 1,
    'yearStart' => null,
    'yearEnd' => null,
    'blockWeekends' => false,
])

@php
    // ปฏิทินเลือกวันที่ — JS/CSS ลงทะเบียนไว้ที่ app-layout (Alpine.data('thaiDateInput'))
    $displayValue = \App\Support\ThaiDate::formatForInput(old($name, $value));
    $tdiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $tdiWeekdays = ['จ','อ','พ','พฤ','ศ','ส','อา'];
    $tdiNowYear = (int) date('Y');
    $normalizePickerYear = function ($year) {
        if ($year === null || $year === '') {
            return null;
        }

        $year = (int) $year;

        return $year >= 2400 ? $year - 543 : $year;
    };
    $tdiYearStart = $normalizePickerYear($yearEnd) ?? ($tdiNowYear + (int) $yearFuture);
    $tdiYearEnd = $normalizePickerYear($yearStart) ?? ($tdiNowYear - (int) $yearPast);
@endphp

<div
    class="tdi-wrap"
    x-data="thaiDateInput({ blockWeekends: @js((bool) $blockWeekends) })"
    @if($calendar) @click.outside="tdiClose()" @keydown.escape="tdiClose()" @endif>
    <div class="tdi-control" x-ref="tdiControl">
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
            @input="$event.target.value = maskThaiDate($event.target.value); tdiValidateInput()"
            @change="tdiValidateInput()"
            @blur="tdiValidateInput()"
            @paste="const el = $event.target; $nextTick(() => { el.value = maskThaiDate(el.value); tdiValidateInput() })">

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
            <div class="tdi-pop" x-ref="tdiPop" x-show="calOpen" x-cloak x-transition.opacity :style="tdiPopStyle" role="group" aria-label="ปฏิทินเลือกวันที่">
            <div class="tdi-pop-head">
                <button type="button" class="tdi-pop-nav" @click="tdiShiftMonth(-1)" aria-label="เดือนก่อนหน้า">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="tdi-pop-select tdi-pop-month" @click.outside="tdiMonthOpen = false">
                    <button type="button" class="tdi-pop-sel" @click="tdiToggleMonth()" :aria-expanded="tdiMonthOpen" aria-label="เลือกเดือน">
                        <span class="tdi-pop-sel-label" x-text="@js($tdiMonths)[calMonth]"></span>
                        <span class="tdi-pop-sel-caret" aria-hidden="true"></span>
                    </button>
                    <div class="tdi-pop-menu" x-show="tdiMonthOpen" x-cloak x-transition.opacity>
                    @foreach($tdiMonths as $monthIndex => $monthName)
                            <button type="button"
                                :class="{ 'is-selected': calMonth === {{ $monthIndex }} }"
                                @click="tdiPickMonth({{ $monthIndex }})">{{ $monthName }}</button>
                    @endforeach
                    </div>
                </div>
                <div class="tdi-pop-select tdi-pop-year" @click.outside="tdiYearOpen = false">
                    <button type="button" class="tdi-pop-sel" @click="tdiToggleYear()" :aria-expanded="tdiYearOpen" aria-label="เลือกปี พ.ศ.">
                        <span class="tdi-pop-sel-label" x-text="calYear + 543"></span>
                        <span class="tdi-pop-sel-caret" aria-hidden="true"></span>
                    </button>
                    <div class="tdi-pop-menu" x-show="tdiYearOpen" x-cloak x-transition.opacity>
                    @for($cy = $tdiYearStart; $cy >= $tdiYearEnd; $cy--)
                            <button type="button"
                                :class="{ 'is-selected': calYear === {{ $cy }} }"
                                @click="tdiPickYear({{ $cy }})">{{ $cy + 543 }}</button>
                    @endfor
                    </div>
                </div>
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
                            'is-selected': cell.day && tdiDayIso(cell.day) === tdiSelectedIso && !tdiIsBlockedDay(cell.day),
                            'is-blocked': cell.day && tdiIsBlockedDay(cell.day),
                        }"
                        :disabled="!cell.day || tdiIsBlockedDay(cell.day)"
                        :title="cell.day && tdiIsBlockedDay(cell.day) ? 'ไม่สามารถเลือกวันเสาร์-อาทิตย์ได้' : null"
                        @click="tdiPick(cell.day)"
                        x-text="cell.day || ''"></button>
                </template>
            </div>
            </div>
        @endif
    </div>

    @if($helper)
        <p style="font-size:11px;color:var(--fg-3);margin-top:4px;line-height:1.55;">{{ $helper }}</p>
    @endif
</div>
