<x-app-layout title="กำลังพัฒนา">
    <div class="coming-soon-page">
        <div class="coming-soon-panel">
            <div class="coming-soon-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div>
                <div class="coming-soon-kicker">สถานะฟีเจอร์</div>
                <h1>กำลังอยู่ในช่วงพัฒนา</h1>
                <p>หน้านี้ยังไม่เปิดให้ใช้งานในการทดสอบรอบนี้</p>
            </div>
        </div>
    </div>

    <style>
        .coming-soon-page {
            min-height: calc(100vh - 120px);
            display: grid;
            place-items: center;
            padding: clamp(16px, 3vw, 32px);
        }

        .coming-soon-panel {
            width: min(100%, 520px);
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .coming-soon-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 8px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            border: 1px solid var(--status-warning-border);
        }

        .coming-soon-kicker {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .coming-soon-panel h1 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 24px;
            line-height: 1.2;
        }

        .coming-soon-panel p {
            margin: 7px 0 0;
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.55;
        }

        @media (max-width: 540px) {
            .coming-soon-panel {
                flex-direction: column;
            }
        }
    </style>
</x-app-layout>
