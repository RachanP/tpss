<?php

namespace Tests\Unit;

use App\Support\ThaiDate;
use PHPUnit\Framework\TestCase;

class ThaiDateTest extends TestCase
{
    public function test_parse_to_iso_accepts_buddhist_year_slashes(): void
    {
        $this->assertSame('2026-06-23', ThaiDate::parseToIso('23/06/2569'));
    }

    public function test_parse_to_iso_accepts_gregorian_year_slashes(): void
    {
        $this->assertSame('2026-06-23', ThaiDate::parseToIso('23/06/2026'));
    }

    public function test_parse_to_iso_accepts_buddhist_year_dashes(): void
    {
        $this->assertSame('2026-06-23', ThaiDate::parseToIso('23-06-2569'));
    }

    public function test_parse_to_iso_accepts_compact_display_digits(): void
    {
        $this->assertSame('2026-06-23', ThaiDate::parseToIso('23062569'));
    }

    public function test_parse_to_iso_accepts_iso_date(): void
    {
        $this->assertSame('2026-06-23', ThaiDate::parseToIso('2026-06-23'));
    }

    public function test_parse_to_iso_returns_null_for_invalid_date(): void
    {
        $this->assertNull(ThaiDate::parseToIso('31/02/2569'));
        $this->assertNull(ThaiDate::parseToIso('23/06/2200'));
    }

    public function test_format_for_input_displays_buddhist_year(): void
    {
        $this->assertSame('23/06/2569', ThaiDate::formatForInput('2026-06-23'));
    }

    public function test_format_date_time_thai_displays_buddhist_year_with_time(): void
    {
        $this->assertSame('23/06/2569 14:05:06', ThaiDate::formatDateTimeThai('2026-06-23 14:05:06'));
    }
}
