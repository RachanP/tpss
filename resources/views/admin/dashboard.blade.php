<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    @php
        $phaseMeta = match($currentAcademicYear?->phase) {
            'scheduling' => [
                'label' => 'เปิดจัดตาราง',
                'style' => 'background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);',
                'dot' => 'oklch(50% 0.2 145)',
            ],
            'published' => [
                'label' => 'เผยแพร่แล้ว',
                'class' => 'badge-primary',
            ],
            default => [
                'label' => 'เตรียมข้อมูล',
                'class' => 'badge-gray',
            ],
        };
    @endphp
    <div style="padding: 2rem;">
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--brand-navy);">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--fg-1);">ภาพรวมผู้ดูแลระบบ</div>
            </div>
            <div style="color: var(--fg-3);">แสดงข้อมูลสรุปสถานะการทำงานและภาระงานสอนของอาจารย์ทั้งหมด</div>
        </div>

        <div class="card" data-testid="admin-phase-card" style="margin-bottom:18px;border-left:4px solid var(--brand-navy);">
            <div style="padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <div style="width:40px;height:40px;border-radius:10px;background:color-mix(in oklch,var(--brand-navy) 10%,white);display:flex;align-items:center;justify-content:center;color:var(--brand-navy);flex-shrink:0;">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.04em;">สถานะระบบปัจจุบัน</div>
                        @if($currentAcademicYear)
                            <div style="margin-top:5px;font-size:20px;font-weight:800;color:var(--fg-1);font-family:var(--font-display);">
                                ปีการศึกษา {{ $currentAcademicYear->name }} / เทอม {{ $currentAcademicYear->semester }}
                            </div>
                            <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                @if(isset($phaseMeta['style']))
                                    <span class="badge" style="{{ $phaseMeta['style'] }}">
                                        <span style="width:6px;height:6px;border-radius:50%;background:{{ $phaseMeta['dot'] }};margin-right:6px;display:inline-block;"></span>
                                        {{ $phaseMeta['label'] }}
                                    </span>
                                @else
                                    <span class="badge {{ $phaseMeta['class'] }}">{{ $phaseMeta['label'] }}</span>
                                @endif
                                <span class="caption">ใช้ข้อมูลจากปีการศึกษาที่กำลังใช้งาน</span>
                            </div>
                        @else
                            <div style="margin-top:5px;font-size:20px;font-weight:800;color:var(--fg-1);font-family:var(--font-display);">
                                ยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน
                            </div>
                            <div class="caption" style="margin-top:8px;">กรุณาเพิ่มหรือเปิดใช้งานปีการศึกษาในหน้าตั้งค่าระบบ</div>
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.settings', ['tab' => 'academic']) }}" class="btn btn-primary" data-testid="system-settings-shortcut" style="white-space:nowrap;text-decoration:none;">
                    จัดการสถานะระบบ
                </a>
            </div>
        </div>

        @include('shared.dashboard.master_data_alerts')
        @include('shared.dashboard.instructors_workload')
    </div>
</x-app-layout>
