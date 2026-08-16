<?php

namespace Tests\Feature;

use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentRoomAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_room_also_creates_uuid_observer_credentials(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/rooms', ['name' => 'Ruang 1'])
            ->assertCreated()->assertJsonPath('data.room.name', 'Ruang 1');

        $password = $response->json('data.observer_credentials.password');
        $observer = User::query()->where('role', UserRole::Pengawas)->sole();
        $this->assertTrue(Str::isUuid($observer->id));
        $this->assertSame($school->id, $observer->school_id);
        $this->assertTrue(Hash::check($password, $observer->password));
        $this->assertSame($password, $observer->generated_password);
    }

    public function test_mapping_whole_classes_moves_students_between_rooms(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $classA = Classroom::query()->create(['school_id' => $school->id, 'name' => 'VII A']);
        $classB = Classroom::query()->create(['school_id' => $school->id, 'name' => 'VII B']);
        $studentsA = collect([1, 2])->map(fn ($number) => Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classA->id, 'nisn' => 'A'.$number, 'name' => 'Siswa A'.$number]));
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classB->id, 'nisn' => 'B1', 'name' => 'Siswa B1']);
        $roomOne = $this->postJson('/api/v1/rooms', ['name' => 'Ruang 1'])->json('data.room.id');
        $roomTwo = $this->postJson('/api/v1/rooms', ['name' => 'Ruang 2'])->json('data.room.id');

        $this->putJson("/api/v1/rooms/{$roomOne}/mapping", ['classroom_ids' => [$classA->id], 'student_ids' => []])
            ->assertOk()->assertJsonPath('data.students_count', 2);
        $this->putJson("/api/v1/rooms/{$roomTwo}/mapping", ['classroom_ids' => [], 'student_ids' => [$studentsA->first()->id]])
            ->assertOk()->assertJsonPath('data.students_count', 1);

        $this->assertDatabaseHas('student_room_assignments', ['room_id' => $roomTwo, 'student_id' => $studentsA->first()->id]);
        $this->assertDatabaseHas('student_room_assignments', ['room_id' => $roomOne, 'student_id' => $studentsA->last()->id]);
        $this->assertTrue(Str::isUuid(StudentRoomAssignment::query()->first()->id));
    }

    public function test_observer_password_can_be_viewed_and_rotated_by_curriculum(): void
    {
        [, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $created = $this->postJson('/api/v1/rooms', ['name' => 'Lab Komputer'])->json('data');
        $oldPassword = $created['observer_credentials']['password'];
        $roomId = $created['room']['id'];

        $this->getJson("/api/v1/rooms/{$roomId}/observer-credentials")
            ->assertOk()->assertJsonPath('data.password', $oldPassword);
        $newPassword = $this->postJson("/api/v1/rooms/{$roomId}/observer-credentials/rotate")
            ->assertOk()->json('data.password');

        $observer = User::query()->where('room_id', $roomId)->sole();
        $this->assertNotSame($oldPassword, $newPassword);
        $this->assertTrue(Hash::check($newPassword, $observer->password));
    }

    public function test_other_tenant_cannot_access_room_or_mapping(): void
    {
        [, $firstUser] = $this->tenant('10000001', 'first-room@example.test');
        [, $secondUser] = $this->tenant('10000002', 'second-room@example.test');
        Sanctum::actingAs($secondUser);
        $roomId = $this->postJson('/api/v1/rooms', ['name' => 'Ruang Tenant 2'])->json('data.room.id');
        Sanctum::actingAs($firstUser);

        $this->deleteJson("/api/v1/rooms/{$roomId}")->assertNotFound();
        $this->getJson("/api/v1/rooms/{$roomId}/mapping")->assertNotFound();
    }

    public function test_curriculum_can_download_student_import_template(): void
    {
        [, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $this->get('/api/v1/students/import-template')->assertOk()->assertDownload('template-import-data-siswa.xlsx');
    }

    private function tenant(string $npsn = '12345678', string $email = 'school-room@example.test'): array
    {
        $school = School::query()->create(['name' => 'Sekolah Test', 'npsn' => $npsn, 'type' => SchoolType::JuniorHigh, 'address' => 'Alamat', 'email' => $email]);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum]);

        return [$school, $user];
    }
}
