<?php

namespace Tests\Feature;

use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    public function test_csrf_protection_is_not_globally_disabled(): void
    {
        $bootstrapConfig = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringNotContainsString("'*'", $bootstrapConfig);
        $this->assertStringNotContainsString('"*"', $bootstrapConfig);
        $this->assertStringNotContainsString('validateCsrfTokens(except:', $bootstrapConfig);
    }
}
