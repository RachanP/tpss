{{-- ฟิลด์เทอม 1 บล็อก (ใช้ index ตายตัว — name ต้อง static) · V2 Master Data Cleanup
     เรียกผ่าน @include(['index'=>..,'seq'=>..,'label'=>..]) --}}
<div style="border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:10px;background:var(--surface);">
    <input type="hidden" name="terms[{{ $index }}][sequence]" value="{{ $seq }}">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="var(--brand-navy)" stroke-width="2" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="text" name="terms[{{ $index }}][name]" x-model="currentYear.terms[{{ $index }}].name"
            placeholder="{{ $label }}"
            style="font-weight:600;color:var(--fg-1);font-size:13px;border:none;background:transparent;flex:1;outline:none;">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label style="font-size:12px;">วันเริ่มเทอม</label>
            <x-thai-date-input name="terms[{{ $index }}][start_date]" x-model="currentYear.terms[{{ $index }}].start_date" helper="" />
        </div>
        <div class="form-group">
            <label style="font-size:12px;">วันสิ้นสุดเทอม</label>
            <x-thai-date-input name="terms[{{ $index }}][end_date]" x-model="currentYear.terms[{{ $index }}].end_date" helper="" />
        </div>
    </div>
    <div class="form-row" style="margin-top:8px;">
        <div class="form-group">
            <label style="font-size:12px;color:var(--fg-3);">สอบกลางภาค — เริ่ม</label>
            <x-thai-date-input name="terms[{{ $index }}][midterm_start]" x-model="currentYear.terms[{{ $index }}].midterm_start" helper="" />
        </div>
        <div class="form-group">
            <label style="font-size:12px;color:var(--fg-3);">สอบกลางภาค — สิ้นสุด</label>
            <x-thai-date-input name="terms[{{ $index }}][midterm_end]" x-model="currentYear.terms[{{ $index }}].midterm_end" helper="" />
        </div>
    </div>
    <div class="form-row" style="margin-top:8px;">
        <div class="form-group">
            <label style="font-size:12px;color:var(--fg-3);">สอบปลายภาค — เริ่ม</label>
            <x-thai-date-input name="terms[{{ $index }}][final_start]" x-model="currentYear.terms[{{ $index }}].final_start" helper="" />
        </div>
        <div class="form-group">
            <label style="font-size:12px;color:var(--fg-3);">สอบปลายภาค — สิ้นสุด</label>
            <x-thai-date-input name="terms[{{ $index }}][final_end]" x-model="currentYear.terms[{{ $index }}].final_end" helper="" />
        </div>
    </div>
</div>
