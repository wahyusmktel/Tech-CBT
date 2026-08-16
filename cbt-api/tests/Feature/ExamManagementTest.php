<?php

namespace Tests\Feature;

use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ExamStudentCredential;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentRoomAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_can_create_exam_and_generate_locked_credentials(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $subjectId = $this->postJson('/api/v1/subjects', ['name' => 'Matematika', 'code' => 'MTK'])->assertCreated()->json('data.id');
        $room = Room::query()->create(['school_id' => $school->id, 'name' => 'Ruang 1']);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX A']);
        $students = collect([1, 2])->map(fn ($number) => Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '100'.$number, 'name' => 'Siswa '.$number]));
        foreach ($students as $student) {
            StudentRoomAssignment::query()->create(['school_id' => $school->id, 'room_id' => $room->id, 'student_id' => $student->id]);
        }

        $examId = $this->postJson('/api/v1/exams', ['name' => 'PTS Matematika', 'subject_id' => $subjectId, 'start_at' => now()->addDay()->toISOString(), 'duration_minutes' => 90, 'status' => 'scheduled', 'room_ids' => [$room->id]])
            ->assertCreated()->assertJsonPath('data.rooms.0.id', $room->id)->json('data.id');
        $options = ['username_strategy' => 'nisn', 'password_type' => 'numeric', 'password_length' => 8];
        $this->postJson("/api/v1/exams/{$examId}/generate-credentials", $options)
            ->assertOk()->assertJsonPath('data.generated_count', 2);
        $this->postJson("/api/v1/exams/{$examId}/generate-credentials", $options)
            ->assertStatus(409)->assertJsonPath('requires_confirmation', true);
        $this->postJson("/api/v1/exams/{$examId}/generate-credentials", [...$options, 'force' => true])
            ->assertOk()->assertJsonPath('data.generated_count', 2);

        $credential = ExamStudentCredential::query()->firstOrFail();
        $this->assertTrue(Str::isUuid($credential->id));
        $this->assertMatchesRegularExpression('/^\d{8}$/', $credential->password);
        $this->assertDatabaseCount('exam_student_credentials', 2);
    }

    public function test_exam_rejects_room_change_after_credentials_exist(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $subjectId = $this->postJson('/api/v1/subjects', ['name' => 'IPA', 'code' => 'IPA'])->json('data.id');
        $roomOne = Room::query()->create(['school_id' => $school->id, 'name' => 'R1']);
        $roomTwo = Room::query()->create(['school_id' => $school->id, 'name' => 'R2']);
        $class = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'nisn' => '2001', 'name' => 'Ani']);
        StudentRoomAssignment::query()->create(['school_id' => $school->id, 'room_id' => $roomOne->id, 'student_id' => $student->id]);
        $payload = ['name' => 'Ujian IPA', 'subject_id' => $subjectId, 'start_at' => now()->addDay()->toISOString(), 'duration_minutes' => 60, 'status' => 'draft', 'room_ids' => [$roomOne->id]];
        $examId = $this->postJson('/api/v1/exams', $payload)->json('data.id');
        $this->postJson("/api/v1/exams/{$examId}/generate-credentials", ['username_strategy' => 'nisn', 'password_type' => 'mixed', 'password_length' => 8])->assertOk();
        $this->putJson("/api/v1/exams/{$examId}", [...$payload, 'room_ids' => [$roomTwo->id]])->assertStatus(409);
    }

    public function test_exam_data_is_isolated_between_tenants(): void
    {
        [, $first] = $this->tenant('30000001', 'exam1@example.test');
        [$secondSchool, $second] = $this->tenant('30000002', 'exam2@example.test');
        Sanctum::actingAs($second);
        $subject = $this->postJson('/api/v1/subjects', ['name' => 'Bahasa', 'code' => 'BIN'])->json('data.id');
        $room = Room::query()->create(['school_id' => $secondSchool->id, 'name' => 'Ruang']);
        $examId = $this->postJson('/api/v1/exams', ['name' => 'Ujian Bahasa', 'subject_id' => $subject, 'start_at' => now()->toISOString(), 'duration_minutes' => 60, 'status' => 'draft', 'room_ids' => [$room->id]])->json('data.id');
        Sanctum::actingAs($first);
        $this->deleteJson("/api/v1/exams/{$examId}")->assertNotFound();
    }

    private function tenant(string $npsn = '22345678', string $email = 'exam@example.test'): array
    {
        $school = School::query()->create(['name' => 'Sekolah Ujian', 'npsn' => $npsn, 'type' => SchoolType::JuniorHigh, 'address' => 'Alamat', 'email' => $email]);

        return [$school, User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum])];
    }
}
