<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_registration_creates_tenant_and_curriculum_user_with_uuids(): void
    {
        $response = $this->postJson('/api/v1/schools/register', [
            'npsn' => '12345678',
            'school_type' => 'smp_mts',
            'address' => 'Jl. Pendidikan No. 1',
            'email' => 'sekolah@example.test',
            'username' => 'kurikulum01',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Sekolah berhasil didaftarkan.')
            ->assertJsonPath('data.user.role', UserRole::Kurikulum->value)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'school_id', 'school']]]);

        $school = School::query()->sole();
        $user = User::query()->sole();

        $this->assertTrue(Str::isUuid($school->id));
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame($school->id, $user->school_id);
    }

    public function test_user_can_login_read_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'superadmin',
            'password' => 'Rahasia123',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'superadmin',
            'password' => 'Rahasia123',
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'unknown',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'username' => 'unknown',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_registration_rejects_invalid_payload(): void
    {
        $this->postJson('/api/v1/schools/register', [
            'npsn' => '',
            'school_type' => 'universitas',
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['npsn', 'school_type', 'address', 'email', 'username', 'password']);
    }
}
