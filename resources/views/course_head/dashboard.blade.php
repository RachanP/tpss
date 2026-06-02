<x-app-layout title="ภาพรวมและแจ้งเตือน — หัวหน้าวิชา">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / หัวหน้าวิชา',
            'title'  => 'ภาพรวมหัวหน้าวิชา',
            'desc'   => 'ติดตามสถานะการจัดตารางสอน รายวิชาที่รับผิดชอบ และรายการแจ้งเตือนการชนของรายวิชา',
        ])

        @include('shared.dashboard.role_empty', [
            'icon'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'title' => 'ภาพรวมหัวหน้าวิชา',
            'desc'  => 'หน้านี้อยู่ระหว่างพัฒนา',
        ])
    </div>
</x-app-layout>
