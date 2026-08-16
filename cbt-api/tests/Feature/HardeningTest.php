<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_checks_database_and_cache_with_security_headers(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('services.database', 'ready')
            ->assertJsonPath('services.cache', 'ready')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_high_load_composite_indexes_are_available(): void
    {
        $attemptIndexes = collect(Schema::getIndexes('exam_attempts'))->pluck('name');
        $examIndexes = collect(Schema::getIndexes('exams'))->pluck('name');

        $this->assertContains('attempts_credential_status_idx', $attemptIndexes);
        $this->assertContains('attempts_exam_status_idx', $attemptIndexes);
        $this->assertContains('attempts_tenant_status_finished_idx', $attemptIndexes);
        $this->assertContains('exams_tenant_status_start_idx', $examIndexes);
    }
}
