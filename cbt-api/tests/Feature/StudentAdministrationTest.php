<?php

namespace Tests\Feature;

use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_user_can_manage_and_filter_students(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);

        $classroom = $this->postJson('/api/v1/classrooms', ['name' => 'IX A'])
            ->assertCreated()->json('data');

        $student = $this->postJson('/api/v1/students', [
            'nisn' => '0012345678', 'name' => 'Andi Saputra', 'classroom_id' => $classroom['id'],
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/students?search=Andi&classroom_id='.$classroom['id'])
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $student['id']);

        $this->putJson('/api/v1/students/'.$student['id'], [
            'nisn' => '0012345678', 'name' => 'Andi Pratama', 'classroom_id' => $classroom['id'],
        ])->assertOk()->assertJsonPath('data.name', 'Andi Pratama');

        $this->assertDatabaseHas('students', ['school_id' => $school->id, 'name' => 'Andi Pratama']);
        $this->deleteJson('/api/v1/classrooms/'.$classroom['id'])->assertUnprocessable();
        $this->deleteJson('/api/v1/students/'.$student['id'])->assertOk();
        $this->deleteJson('/api/v1/classrooms/'.$classroom['id'])->assertOk();
    }

    public function test_csv_import_creates_classes_and_upserts_by_tenant_nisn(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'VIII A']);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '001', 'name' => 'Nama Lama']);
        $file = UploadedFile::fake()->createWithContent('siswa.csv', "NISN,Nama,Kelas\n001,Nama Baru,VIII B\n002,Siswa Baru,VIII B\n,Data Rusak,\n");

        $this->post('/api/v1/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.inserted', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.failed', 1);

        $this->assertDatabaseHas('classrooms', ['school_id' => $school->id, 'name' => 'VIII B']);
        $this->assertDatabaseHas('students', ['school_id' => $school->id, 'nisn' => '001', 'name' => 'Nama Baru']);
        $this->assertDatabaseCount('students', 2);
    }

    public function test_import_rejects_an_invalid_header(): void
    {
        [, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->createWithContent('siswa.csv', "Nomor,Nama Lengkap,Rombel\n1,Ani,VII A\n");

        $this->post('/api/v1/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', 'Header file wajib berisi kolom NISN, Nama, dan Kelas.');
    }

    public function test_generated_excel_template_can_be_imported_directly(): void
    {
        [, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $path = storage_path('app/outputs/student-import-template/template-import-siswa.xlsx');
        $file = new UploadedFile($path, 'template-import-data-siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->post('/api/v1/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.inserted', 5)->assertJsonPath('data.failed', 0);

        $this->assertDatabaseCount('students', 5);
        $this->assertDatabaseHas('students', ['nisn' => '1234567890', 'name' => 'Ahmad Fauzan']);
    }

    public function test_tenant_cannot_access_another_tenants_student(): void
    {
        [, $user] = $this->tenant('10000001', 'first@example.test');
        [$otherSchool] = $this->tenant('10000002', 'second@example.test');
        $classroom = Classroom::query()->create(['school_id' => $otherSchool->id, 'name' => 'VII A']);
        $student = Student::query()->create(['school_id' => $otherSchool->id, 'classroom_id' => $classroom->id, 'nisn' => '999', 'name' => 'Tenant Lain']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/students/'.$student->id, ['nisn' => '999', 'name' => 'Disusupi', 'classroom_id' => $classroom->id])
            ->assertUnprocessable();
        $this->deleteJson('/api/v1/students/'.$student->id)->assertNotFound();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Tenant Lain']);
    }

    public function test_student_role_is_forbidden_from_student_administration(): void
    {
        [, $user] = $this->tenant(role: UserRole::Siswa);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/students')->assertForbidden();
        $this->getJson('/api/v1/classrooms')->assertForbidden();
    }

    private function tenant(string $npsn = '12345678', string $email = 'school@example.test', UserRole $role = UserRole::Kurikulum): array
    {
        $school = School::query()->create(['name' => 'Sekolah Test', 'npsn' => $npsn, 'type' => SchoolType::JuniorHigh, 'address' => 'Alamat', 'email' => $email]);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => $role]);

        return [$school, $user];
    }
}
