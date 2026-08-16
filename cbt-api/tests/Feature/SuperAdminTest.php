<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamStudentCredential;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_monitor_all_schools_and_school_detail(): void
    {
        $first = $this->setupSchool('Sekolah Alpha', '10000001', 82.5);
        $this->setupSchool('Sekolah Beta', '10000002', 70);
        $superAdmin = User::factory()->create(['school_id' => null, 'role' => UserRole::SuperAdmin]);
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/v1/super-admin/schools')
            ->assertOk()
            ->assertJsonPath('data.summary.schools_count', 2)
            ->assertJsonPath('data.summary.students_count', 2)
            ->assertJsonPath('data.summary.exams_count', 2)
            ->assertJsonPath('data.summary.finished_attempts_count', 2)
            ->assertJsonPath('data.summary.average_score', 76.25)
            ->assertJsonCount(2, 'data.schools');

        $this->getJson('/api/v1/super-admin/schools/'.$first['school']->id)
            ->assertOk()
            ->assertJsonPath('data.school.name', 'Sekolah Alpha')
            ->assertJsonPath('data.subjects.0.name', 'Matematika')
            ->assertJsonPath('data.subjects.0.average_score', 82.5)
            ->assertJsonPath('data.recent_exams.0.finished_count', 1)
            ->assertJsonPath('data.recent_scores.0.student', 'Siswa Sekolah Alpha');
    }

    public function test_curriculum_cannot_access_super_admin_endpoints(): void
    {
        $setup = $this->setupSchool('Sekolah Terbatas', '10000003', 80);
        Sanctum::actingAs($setup['curriculum']);

        $this->getJson('/api/v1/super-admin/schools')->assertForbidden();
        $this->getJson('/api/v1/super-admin/schools/'.$setup['school']->id)->assertForbidden();
        $this->postJson('/api/v1/super-admin/schools/'.$setup['school']->id.'/reset-curriculum-password')->assertForbidden();
    }

    public function test_super_admin_can_reset_curriculum_password_and_action_is_audited(): void
    {
        $setup = $this->setupSchool('Sekolah Reset', '10000004', 90);
        $setup['curriculum']->createToken('web-client');
        $superAdmin = User::factory()->create(['school_id' => null, 'role' => UserRole::SuperAdmin]);
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/super-admin/schools/'.$setup['school']->id.'/reset-curriculum-password')
            ->assertOk()
            ->assertJsonPath('data.username', $setup['curriculum']->username);
        $temporaryPassword = $response->json('data.temporary_password');

        $this->assertGreaterThanOrEqual(12, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $setup['curriculum']->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $audit = SuperAdminAuditLog::query()->sole();
        $this->assertSame('curriculum_password_reset', $audit->action);
        $this->assertSame($setup['curriculum']->id, $audit->metadata['target_user_id']);
        $this->assertArrayNotHasKey('password', $audit->metadata);
    }

    private function setupSchool(string $name, string $npsn, float $score): array
    {
        $school = School::query()->create(['name' => $name, 'npsn' => $npsn, 'type' => SchoolType::JuniorHigh, 'address' => 'Jl. Pendidikan', 'email' => strtolower(str_replace(' ', '-', $name)).'@example.test']);
        $curriculum = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum]);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => $npsn.'01', 'name' => 'Siswa '.$name]);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'Matematika', 'code' => 'MTK']);
        $exam = Exam::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'name' => 'Ujian Matematika', 'access_code' => substr($npsn, 0, 8), 'start_at' => now()->subDay(), 'duration_minutes' => 90, 'status' => ExamStatus::Completed]);
        $credential = ExamStudentCredential::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $student->id, 'username' => $student->nisn, 'password' => 'Secret123']);
        ExamAttempt::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $student->id, 'credential_id' => $credential->id, 'status' => 'finished', 'started_at' => now()->subDay(), 'last_activity_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'score' => $score]);

        return compact('school', 'curriculum', 'student', 'subject', 'exam');
    }
}
