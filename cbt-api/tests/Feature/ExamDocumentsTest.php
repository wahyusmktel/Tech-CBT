<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamRoomAssignment;
use App\Models\ExamStudentCredential;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentRoomAssignment;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_can_download_all_exam_administration_documents(): void
    {
        $setup = $this->setupExam(withCredentials: true);
        Sanctum::actingAs($setup['admin']);

        foreach (['attendance', 'minutes', 'cards'] as $document) {
            $response = $this->get('/api/v1/exams/'.$setup['exam']->id.'/documents/'.$document.'.pdf')
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $cards = app(ExamDocumentService::class)->cards($setup['exam']);
        $this->assertSame('student-secret', $cards['cards']->first()['password']);
        $this->assertSame('Ruang 1', $cards['cards']->first()['room']);
    }

    public function test_exam_cards_require_generated_credentials(): void
    {
        $setup = $this->setupExam(withCredentials: false);
        Sanctum::actingAs($setup['admin']);

        $this->getJson('/api/v1/exams/'.$setup['exam']->id.'/documents/cards.pdf')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kredensial peserta harus digenerate sebelum kartu ujian dapat dicetak.');
    }

    public function test_curriculum_cannot_download_another_school_exam_document(): void
    {
        $setup = $this->setupExam(withCredentials: true);
        $otherSchool = School::query()->create(['name' => 'Sekolah Lain', 'npsn' => '91234567', 'type' => SchoolType::SeniorHigh, 'address' => 'Alamat lain', 'email' => 'other-doc@example.test']);
        $otherAdmin = User::factory()->create(['school_id' => $otherSchool->id, 'role' => UserRole::Kurikulum]);
        Sanctum::actingAs($otherAdmin);

        $this->getJson('/api/v1/exams/'.$setup['exam']->id.'/documents/attendance.pdf')->assertNotFound();
    }

    private function setupExam(bool $withCredentials): array
    {
        $school = School::query()->create(['name' => 'Sekolah Dokumen', 'npsn' => '71234567', 'type' => SchoolType::JuniorHigh, 'address' => 'Bandar Lampung', 'principal_name' => 'Kepala Sekolah', 'email' => 'document@example.test']);
        $admin = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum]);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'Bahasa Indonesia', 'code' => 'BIN']);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '0012345678', 'name' => 'Alya Sari']);
        $room = Room::query()->create(['school_id' => $school->id, 'name' => 'Ruang 1']);
        User::factory()->create(['school_id' => $school->id, 'room_id' => $room->id, 'role' => UserRole::Pengawas, 'name' => 'Pengawas Satu']);
        StudentRoomAssignment::query()->create(['school_id' => $school->id, 'room_id' => $room->id, 'student_id' => $student->id]);
        $exam = Exam::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'name' => 'PTS Ganjil', 'access_code' => 'DOCS1234', 'start_at' => now()->addDay(), 'duration_minutes' => 90, 'status' => ExamStatus::Scheduled]);
        ExamRoomAssignment::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'room_id' => $room->id]);

        if ($withCredentials) {
            ExamStudentCredential::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $student->id, 'username' => '0012345678', 'password' => 'student-secret']);
            $exam->update(['credentials_generated_at' => now()]);
        }

        return compact('school', 'admin', 'student', 'room', 'exam');
    }
}
