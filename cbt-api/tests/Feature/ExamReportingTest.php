<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamStudentCredential;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionChoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExamReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_can_preview_result_and_question_analysis(): void
    {
        $setup = $this->setupReport();
        Sanctum::actingAs($setup['admin']);

        $this->getJson('/api/v1/reports/exams/'.$setup['exam']->id)
            ->assertOk()
            ->assertJsonPath('data.summary.participant_count', 2)
            ->assertJsonPath('data.summary.finished_count', 1)
            ->assertJsonPath('data.summary.average_score', 50)
            ->assertJsonPath('data.results.0.name', 'Alya Sari')
            ->assertJsonPath('data.question_analysis.0.correct_count', 1)
            ->assertJsonPath('data.question_analysis.0.correct_percentage', 100)
            ->assertJsonPath('data.question_analysis.1.wrong_count', 1);
    }

    public function test_curriculum_can_download_pdf_and_excel_reports(): void
    {
        $setup = $this->setupReport();
        Sanctum::actingAs($setup['admin']);

        $this->get('/api/v1/reports/exams/'.$setup['exam']->id.'/results.pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get('/api/v1/reports/exams/'.$setup['exam']->id.'/analysis.pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $excel = $this->get('/api/v1/reports/exams/'.$setup['exam']->id.'/report.xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = $excel->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith('PK', file_get_contents($path));
        $workbook = IOFactory::load($path);
        $this->assertSame(['Rekap Nilai', 'Analisis Soal'], $workbook->getSheetNames());
        $this->assertSame(DataType::TYPE_STRING, $workbook->getSheetByName('Rekap Nilai')->getCell('B9')->getDataType());
    }

    public function test_curriculum_cannot_access_another_school_report(): void
    {
        $setup = $this->setupReport();
        $otherSchool = School::query()->create(['name' => 'Sekolah Lain', 'npsn' => '87654321', 'type' => SchoolType::SeniorHigh, 'address' => 'Alamat lain', 'email' => 'other@example.test']);
        $otherAdmin = User::factory()->create(['school_id' => $otherSchool->id, 'role' => UserRole::Kurikulum]);
        Sanctum::actingAs($otherAdmin);

        $this->getJson('/api/v1/reports/exams/'.$setup['exam']->id)->assertNotFound();
    }

    private function setupReport(): array
    {
        $school = School::query()->create(['name' => 'Sekolah Laporan', 'npsn' => '12345670', 'type' => SchoolType::JuniorHigh, 'address' => 'Jl. Pendidikan', 'email' => 'report@example.test']);
        $admin = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum]);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'Matematika', 'code' => 'MTK']);
        $bank = QuestionBank::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'title' => 'Paket Laporan', 'validated_at' => now(), 'validated_by' => $admin->id]);
        $questionOne = Question::query()->create(['school_id' => $school->id, 'question_bank_id' => $bank->id, 'number' => 1, 'text' => 'Dua tambah dua?']);
        $oneWrong = QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $questionOne->id, 'label' => 'A', 'text' => 'Tiga', 'is_correct' => false]);
        $oneCorrect = QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $questionOne->id, 'label' => 'B', 'text' => 'Empat', 'is_correct' => true]);
        $questionTwo = Question::query()->create(['school_id' => $school->id, 'question_bank_id' => $bank->id, 'number' => 2, 'text' => 'Tiga tambah tiga?']);
        $twoWrong = QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $questionTwo->id, 'label' => 'A', 'text' => 'Lima', 'is_correct' => false]);
        QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $questionTwo->id, 'label' => 'B', 'text' => 'Enam', 'is_correct' => true]);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX A']);
        $studentOne = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '7001', 'name' => 'Alya Sari']);
        $studentTwo = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '7002', 'name' => 'Bima Jaya']);
        $exam = Exam::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'question_bank_id' => $bank->id, 'name' => 'Ujian Laporan', 'access_code' => 'REPORT01', 'start_at' => now()->subHour(), 'duration_minutes' => 90, 'status' => ExamStatus::Completed]);
        $credentialOne = ExamStudentCredential::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $studentOne->id, 'username' => '7001', 'password' => 'Secret123']);
        ExamStudentCredential::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $studentTwo->id, 'username' => '7002', 'password' => 'Secret123']);
        $attempt = ExamAttempt::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $studentOne->id, 'credential_id' => $credentialOne->id, 'status' => 'finished', 'started_at' => now()->subHour(), 'last_activity_at' => now(), 'finished_at' => now(), 'score' => 50]);
        ExamAnswer::query()->create(['school_id' => $school->id, 'attempt_id' => $attempt->id, 'question_id' => $questionOne->id, 'question_choice_id' => $oneCorrect->id, 'answered_at' => now()]);
        ExamAnswer::query()->create(['school_id' => $school->id, 'attempt_id' => $attempt->id, 'question_id' => $questionTwo->id, 'question_choice_id' => $twoWrong->id, 'answered_at' => now()]);

        return compact('school', 'admin', 'exam', 'oneWrong');
    }
}
