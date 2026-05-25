<div class="card" x-data="{ searchQuery: '' }">
    <div class="card-hdr">
        <div class="card-ttl">ภาระงานสอนของอาจารย์</div>
        <div class="card-actions">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" x-model="searchQuery" placeholder="ค้นหาชื่ออาจารย์...">
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ภาควิชา</th>
                    <th style="text-align: right;">ชั่วโมงสอนสะสม</th>
                    <th style="text-align: right; padding-right: 24px;">เกณฑ์ภาระงานสอน</th>
                </tr>
            </thead>
            <tbody>
                @foreach($instructors as $instructor)
                    <tr x-show="!searchQuery || '{{ $instructor->formatted_name }}'.toLowerCase().includes(searchQuery.toLowerCase())">
                        <td style="font-weight: 600; color: var(--fg-2);">
                            {{ $instructor->employee_id ?? '-' }}
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--fg-1);">
                                {{ $instructor->formatted_name }}
                            </div>
                            @if($instructor->instructorProfile && $instructor->instructorProfile->employment_type)
                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 2px;">
                                {{ $instructor->instructorProfile->employment_type }}
                            </div>
                            @endif
                        </td>
                        <td style="color: var(--fg-2); font-size: 13px;">
                            {{ $instructor->instructorProfile->department->name ?? '-' }}
                        </td>
                        <td style="text-align: right; font-weight: 700; color: var(--fg-3);">
                            0.0
                        </td>
                        <td style="text-align: right; padding-right: 24px;">
                            @if($instructor->instructorProfile && $instructor->instructorProfile->teaching_pct)
                                @php
                                    $isGov = ($instructor->instructorProfile->employment_type === 'ข้าราชการ');
                                    $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);
                                    $period = $isGov ? '6 เดือน' : 'ปี';
                                    $quota = ($base * $instructor->instructorProfile->teaching_pct) / 100;
                                @endphp
                                <div style="font-weight: 700; color: var(--brand-navy); font-size: 14px;">
                                    {{ number_format($quota, 1) }}
                                </div>
                                <div style="font-size: 11px; color: var(--fg-3);">ชั่วโมงทำการ / {{ $period }}</div>
                            @else
                                <span style="color: var(--fg-3); font-style: italic;">- ไม่ระบุ -</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($instructors->isEmpty())
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">ไม่พบข้อมูลอาจารย์</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
