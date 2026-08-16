<?php

namespace Tests\Feature;

use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_user_can_read_own_school_profile(): void
    {
        [$school, $user] = $this->schoolAndUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/school/profile')
            ->assertOk()
            ->assertJsonPath('id', $school->id)
            ->assertJsonPath('npsn', $school->npsn);
    }

    public function test_curriculum_user_can_update_profile_and_upload_letterhead(): void
    {
        Storage::fake('public');
        [$school, $user] = $this->schoolAndUser();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/school/profile', [
            'name' => 'SMP Teknoplek Indonesia',
            'npsn' => $school->npsn,
            'type' => SchoolType::JuniorHigh->value,
            'address' => 'Jl. Pendidikan No. 10, Bandung',
            'principal_name' => 'Budi Santoso',
            'phone' => '+62 812-3456-7890',
            'email' => $school->email,
            'letterhead' => UploadedFile::fake()->image('kop-surat.png', 1200, 300)->size(500),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('message', 'Profil sekolah berhasil diperbarui.')
            ->assertJsonPath('data.name', 'SMP Teknoplek Indonesia');

        $school->refresh();
        $this->assertNotNull($school->letterhead_path);
        Storage::disk('public')->assertExists($school->letterhead_path);
    }

    public function test_non_curriculum_user_cannot_access_school_settings(): void
    {
        [, $student] = $this->schoolAndUser(UserRole::Siswa);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/school/profile')->assertForbidden();
    }

    public function test_policy_rejects_a_school_from_another_tenant(): void
    {
        [, $user] = $this->schoolAndUser();
        $otherSchool = $this->createSchool('87654321', 'other-school@example.test');

        $this->assertFalse(Gate::forUser($user)->allows('update', $otherSchool));
    }

    private function schoolAndUser(UserRole $role = UserRole::Kurikulum): array
    {
        $school = $this->createSchool('12345678', 'school@example.test');
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => $role,
        ]);

        return [$school, $user];
    }

    private function createSchool(string $npsn, string $email): School
    {
        return School::query()->create([
            'name' => 'SMP Teknoplek',
            'npsn' => $npsn,
            'type' => SchoolType::JuniorHigh,
            'address' => 'Jl. Pendidikan No. 1',
            'principal_name' => 'Siti Aminah',
            'phone' => '081234567890',
            'email' => $email,
        ]);
    }
}
