<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamRoomAssignment;
use App\Models\ExamStudentCredential;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionChoice;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentRoomAssignment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_restore_answer_and_submit_exam(): void
    {
        $setup = $this->setupExam();
        $login = $this->postJson('/api/v1/student/login', ['access_code' => $setup['exam']->access_code, 'username' => 'student001', 'password' => 'Secret123'])
            ->assertOk()->assertJsonPath('data.user.role', 'siswa');
        $token = $login->json('data.token');
        $start = $this->withToken($token)->postJson('/api/v1/student/exam/start')->assertOk();
        $this->assertFalse($start->json('data.questions.0.choices.0.is_correct') ?? false);

        $this->withToken($token)->putJson('/api/v1/student/exam/answers/'.$setup['question']->id, ['question_choice_id' => $setup['correct']->id])
            ->assertOk()->assertJsonPath('message', 'Jawaban tersimpan.');
        $this->withToken($token)->postJson('/api/v1/student/exam/start')
            ->assertOk()->assertJsonPath('data.attempt.answers.'.$setup['question']->id, $setup['correct']->id);
        $this->withToken($token)->postJson('/api/v1/student/exam/submit')
            ->assertOk()->assertJsonPath('data.score', 100);

        $this->assertDatabaseHas('exam_attempts', ['exam_id' => $setup['exam']->id, 'student_id' => $setup['student']->id, 'status' => 'finished']);
        $this->assertDatabaseCount('exam_answers', 1);
    }

    public function test_student_token_cannot_access_curriculum_endpoints(): void
    {
        $setup = $this->setupExam();
        $token = $setup['credential']->createToken('exam-session', ['exam:take'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/students')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/exams')->assertForbidden();
    }

    public function test_answer_cannot_be_changed_after_attempt_is_finished(): void
    {
        $setup = $this->setupExam();
        $login = $this->postJson('/api/v1/student/login', ['access_code' => $setup['exam']->access_code, 'username' => 'student001', 'password' => 'Secret123']);
        $token = $login->json('data.token');
        $this->withToken($token)->postJson('/api/v1/student/exam/start')->assertOk();
        $this->withToken($token)->postJson('/api/v1/student/exam/submit')->assertOk();

        Sanctum::actingAs($setup['credential']);
        $this->putJson('/api/v1/student/exam/answers/'.$setup['question']->id, ['question_choice_id' => $setup['correct']->id])
            ->assertConflict()
            ->assertJsonPath('message', 'Ujian sudah diselesaikan.');
        $this->assertDatabaseCount('exam_answers', 0);
    }

    public function test_observer_only_sees_participants_in_assigned_room(): void
    {
        $setup = $this->setupExam();
        $observer = User::factory()->create(['school_id' => $setup['school']->id, 'room_id' => $setup['room']->id, 'role' => UserRole::Pengawas]);
        Sanctum::actingAs($observer);
        $this->getJson('/api/v1/observer/monitoring')->assertOk()
            ->assertJsonPath('data.0.id', $setup['exam']->id)
            ->assertJsonPath('data.0.participants.0.id', $setup['student']->id)
            ->assertJsonPath('data.0.participants.0.status', 'not_logged_in');
    }

    private function setupExam(): array
    {
        $school = School::query()->create(['name' => 'Sekolah CBT', 'npsn' => '52345678', 'type' => SchoolType::JuniorHigh, 'address' => 'Alamat', 'email' => 'execution@example.test']);
        $admin = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum]);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'Matematika', 'code' => 'MTK']);
        $bank = QuestionBank::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'title' => 'Paket Aktif', 'validated_at' => now(), 'validated_by' => $admin->id]);
        $question = Question::query()->create(['school_id' => $school->id, 'question_bank_id' => $bank->id, 'number' => 1, 'text' => 'Dua tambah dua?']);
        $wrong = QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $question->id, 'label' => 'A', 'text' => 'Tiga', 'is_correct' => false]);
        $correct = QuestionChoice::query()->create(['school_id' => $school->id, 'question_id' => $question->id, 'label' => 'B', 'text' => 'Empat', 'is_correct' => true]);
        $classroom = Classroom::query()->create(['school_id' => $school->id, 'name' => 'IX A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'nisn' => '5001', 'name' => 'Peserta Satu']);
        $room = Room::query()->create(['school_id' => $school->id, 'name' => 'Ruang 1']);
        StudentRoomAssignment::query()->create(['school_id' => $school->id, 'room_id' => $room->id, 'student_id' => $student->id]);
        $exam = Exam::query()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'question_bank_id' => $bank->id, 'name' => 'Ujian Aktif', 'access_code' => 'EXAM1234', 'start_at' => now()->subMinute(), 'duration_minutes' => 60, 'status' => ExamStatus::Active]);
        ExamRoomAssignment::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'room_id' => $room->id]);
        $credential = ExamStudentCredential::query()->create(['school_id' => $school->id, 'exam_id' => $exam->id, 'student_id' => $student->id, 'username' => 'student001', 'password' => 'Secret123']);

        return compact('school', 'admin', 'subject', 'bank', 'question', 'wrong', 'correct', 'classroom', 'student', 'room', 'exam', 'credential');
    }
}
