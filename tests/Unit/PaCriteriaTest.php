<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\AdminSettingController;
use PHPUnit\Framework\TestCase;

/**
 * Unit — เกณฑ์ PA (logic ล้วน ไม่แตะ DB)
 *  - paGroup(): map ตำแหน่ง + วุฒิ → กลุ่มเกณฑ์ (ตาราง architecture.md)
 *  - defaultPaCriteria(): โครงสร้าง + invariant ว่าจัดสัดส่วนรวม 100% ได้จริงในทุกกลุ่ม
 */
class PaCriteriaTest extends TestCase
{
    /** ตำแหน่งสายอาจารย์ปกติ → กลุ่ม "อาจารย์" */
    public function test_pa_group_maps_regular_titles_to_lecturer(): void
    {
        foreach (['อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์'] as $title) {
            $this->assertSame('อาจารย์', AlertController::paGroup($title, 'ปริญญาเอก'), "title={$title}");
        }
    }

    public function test_pa_group_assistant_with_bachelor_degree(): void
    {
        $this->assertSame('ผู้ช่วยอาจารย์_ปตรี', AlertController::paGroup('ผู้ช่วยอาจารย์', 'ปริญญาตรี'));
    }

    public function test_pa_group_clinical_assistant_overrides_degree(): void
    {
        // คลินิก มาก่อนการเช็ควุฒิ
        $this->assertSame('ผู้ช่วยอาจารย์_คลินิก', AlertController::paGroup('ผู้ช่วยอาจารย์ (คลินิก)', 'ปริญญาตรี'));
    }

    public function test_pa_group_practical_assistant(): void
    {
        $this->assertSame('ผู้ช่วยอาจารย์_ปฏิบัติ', AlertController::paGroup('ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)', 'ปริญญาโท'));
    }

    public function test_pa_group_assistant_with_other_degree_falls_back(): void
    {
        $this->assertSame('ผู้ช่วยอาจารย์', AlertController::paGroup('ผู้ช่วยอาจารย์', 'ปริญญาโท'));
    }

    public function test_default_pa_criteria_has_all_groups_with_five_axes(): void
    {
        $criteria = AdminSettingController::defaultPaCriteria();

        $expectedGroups = [
            'อาจารย์', 'ผู้ช่วยอาจารย์', 'ผู้ช่วยอาจารย์_ปตรี',
            'ผู้ช่วยอาจารย์_คลินิก', 'ผู้ช่วยอาจารย์_ปฏิบัติ',
        ];

        foreach ($expectedGroups as $group) {
            $this->assertArrayHasKey($group, $criteria, "missing group {$group}");
            foreach (['t', 'r', 's', 'c', 'o'] as $axis) {
                $this->assertArrayHasKey($axis, $criteria[$group], "{$group} missing axis {$axis}");
                $this->assertArrayHasKey('min', $criteria[$group][$axis]);
                $this->assertArrayHasKey('max', $criteria[$group][$axis]);
                $this->assertIsInt($criteria[$group][$axis]['min']);
                $this->assertIsInt($criteria[$group][$axis]['max']);
                $this->assertLessThanOrEqual($criteria[$group][$axis]['max'], $criteria[$group][$axis]['min'], "{$group}.{$axis} min>max");
            }
        }
    }

    /** invariant: ทุกกลุ่มต้องจัดสัดส่วนให้รวม 100% ได้จริง (sum min ≤ 100 ≤ sum max) */
    public function test_default_pa_criteria_allows_a_valid_hundred_percent_split(): void
    {
        foreach (AdminSettingController::defaultPaCriteria() as $group => $axes) {
            $sumMin = array_sum(array_map(fn ($a) => $a['min'], $axes));
            $sumMax = array_sum(array_map(fn ($a) => $a['max'], $axes));
            $this->assertLessThanOrEqual(100, $sumMin, "{$group}: ผลรวม min เกิน 100 → จัด 100% ไม่ได้");
            $this->assertGreaterThanOrEqual(100, $sumMax, "{$group}: ผลรวม max ต่ำกว่า 100 → จัด 100% ไม่ได้");
        }
    }
}
