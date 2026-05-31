<x-app-layout title="รายงาน Staff">
    <style>
        .staff-reports {
            padding: 28px;
            color: var(--fg-1);
        }
        .staff-report-panel {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 20px;
        }
        .staff-report-title {
            margin: 0 0 6px;
            color: var(--fg-1);
            font-size: 20px;
            font-weight: 950;
            line-height: 1.35;
        }
        .staff-report-note {
            margin: 0 0 18px;
            color: var(--fg-2);
            font-size: 14px;
            font-weight: 750;
            line-height: 1.6;
        }
        .staff-report-dev {
            min-height: 186px;
            display: grid;
            place-items: center;
            padding: 28px;
            border: 1px dashed color-mix(in oklch, var(--brand-navy) 32%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            text-align: center;
        }
        .staff-report-dev strong {
            display: block;
            margin-bottom: 14px;
            color: var(--brand-navy);
            font-size: 28px;
            font-weight: 950;
            line-height: 1.35;
        }
        .staff-report-dev span {
            display: block;
            max-width: 620px;
            color: var(--fg-2);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.75;
        }
        @media (max-width: 760px) {
            .staff-reports {
                padding: 18px;
            }
            .staff-report-panel {
                padding: 18px;
            }
            .staff-report-dev {
                min-height: 160px;
                padding: 22px;
            }
            .staff-report-dev strong {
                font-size: 23px;
            }
        }
    </style>

    <div class="staff-reports">
        <section class="staff-report-panel" aria-label="รายงาน Staff">
            <h1 class="staff-report-title">รายงาน</h1>
            <p class="staff-report-note">ส่วนรายงานเต็มจะเปิดใช้งานใน phase ถัดไป</p>

            <div class="staff-report-dev" role="status">
                <div>
                    <strong>กำลังอยู่ในช่วงพัฒนา</strong>
                    <span>ขณะนี้ยังซ่อนสรุปรายงานและ export สำหรับ Staff ไว้ก่อน เพื่อรอขอบเขตข้อมูลรายงานที่ชัดเจนใน phase ถัดไป</span>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
