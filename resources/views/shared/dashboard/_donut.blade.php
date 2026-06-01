{{--
    Reusable SVG donut chart (Impeccable — pure SVG, ไม่มี lib/animation หรูหรา)
    props:
      $title    string
      $segments array<['label'=>, 'count'=>, 'color'=>]>
      $unit     string (ใต้ยอดรวมตรงกลาง)
    CSS อยู่ใน admin_visual_overview.blade.php (ตัวที่ include)
--}}
@php
    $segTotal = array_sum(array_map(fn ($s) => (int) $s['count'], $segments));
    $radius = 54;
    $circ = 2 * M_PI * $radius;
    $acc = 0.0;
@endphp
<div class="dash-chart-card dash-donut-card">
    <div class="dash-chart-title">{{ $title }}</div>
    <div class="dash-donut-body">
        <svg viewBox="0 0 140 140" class="dash-donut" role="img" aria-label="{{ $title }}">
            <circle cx="70" cy="70" r="{{ $radius }}" fill="none" stroke="color-mix(in oklch, var(--brand-navy) 8%, var(--bg-2))" stroke-width="18"/>
            @if($segTotal > 0)
                @foreach($segments as $s)
                    @php $count = (int) $s['count']; @endphp
                    @if($count > 0)
                        @php
                            $len = $count / $segTotal * $circ;
                            $dash = round($len, 2) . ' ' . round($circ - $len, 2);
                            $dashOffset = round(-$acc, 2);
                            $acc += $len;
                        @endphp
                        <circle cx="70" cy="70" r="{{ $radius }}" fill="none"
                            stroke="{{ $s['color'] }}" stroke-width="18"
                            stroke-dasharray="{{ $dash }}" stroke-dashoffset="{{ $dashOffset }}"
                            stroke-linecap="round" transform="rotate(-90 70 70)"/>
                    @endif
                @endforeach
            @endif
            <text x="70" y="66" text-anchor="middle" class="dash-donut-total">{{ number_format($segTotal) }}</text>
            <text x="70" y="84" text-anchor="middle" class="dash-donut-unit">{{ $unit ?? '' }}</text>
        </svg>
        <ul class="dash-legend">
            @foreach($segments as $s)
                <li class="dash-legend-row">
                    <span class="dash-legend-dot" style="background: {{ $s['color'] }};"></span>
                    <span class="dash-legend-label">{{ $s['label'] }}</span>
                    <span class="dash-legend-val">
                        {{ number_format((int) $s['count']) }}<small>{{ $segTotal > 0 ? round($s['count'] / $segTotal * 100) : 0 }}%</small>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
