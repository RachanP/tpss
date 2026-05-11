<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPSS - {{ $title ?? 'ระบบจัดตารางสอนและฝึกปฏิบัติ' }}</title>
    
    <!-- Vite CSS -->
    @vite(['resources/css/app.css'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- Base Styles -->
    <style>
        /* Fallback if Vite is not ready */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-(--bg) text-(--fg-1) antialiased" x-data="{ sidebarOpen: true }">
    <div class="app-layout">
        <!-- Sidebar Component -->
        <x-sidebar />

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="tb-title">{{ $title ?? 'Dashboard' }}</div>
                
                <div class="tb-right" style="margin-left: auto;">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="tb-btn" style="border: none; background: transparent; cursor: pointer; color: var(--fg-2);">
                            ออกจากระบบ
                        </button>
                    </form>
                </div>
            </div>

            <!-- Page Content -->
            <div class="content-area">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
