@props(['title' => 'TPSS'])

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPSS — {{ $title }}</title>

    <!-- Google Fonts: IBM Plex Sans Thai (UI) + Kanit (Headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Design System CSS -->
    @vite(['resources/css/app.css'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        @include('components.sidebar')

        <!-- Main -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <span class="tb-title">{{ $title }}</span>
            </div>

            <!-- Content -->
            <div class="content-area">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
