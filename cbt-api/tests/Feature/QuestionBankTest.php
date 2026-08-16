<?php

namespace Tests\Feature;

use App\Enums\SchoolType;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_can_import_preview_and_validate_docx_questions(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'Matematika', 'code' => 'MTK']);
        $bankId = $this->postJson('/api/v1/question-banks', ['title' => 'Paket A', 'subject_id' => $subject->id])->assertCreated()->json('data.id');
        $file = $this->questionDocument([
            '1. Hasil dari 2 + 2 adalah ...', 'A. 3', 'B. 4', 'C. 5', 'ANS : B',
            '2. Bilangan prima terkecil adalah ...', 'A. 1', 'B. 2', 'C. 3', 'ANS : B',
        ]);

        $this->post("/api/v1/question-banks/{$bankId}/import", ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.questions_count', 2);
        $this->getJson("/api/v1/question-banks/{$bankId}")->assertOk()
            ->assertJsonPath('data.questions.0.choices.1.is_correct', true);
        $this->postJson("/api/v1/question-banks/{$bankId}/validate")->assertOk()
            ->assertJsonPath('data.validated_by', $user->name);

        $this->assertDatabaseCount('questions', 2);
        $this->assertDatabaseCount('question_choices', 6);
        $this->assertSame(2, Question::query()->count());
        $this->assertSame(2, QuestionChoice::query()->where('is_correct', true)->count());
    }

    public function test_import_is_locked_and_invalid_answer_is_rejected(): void
    {
        [$school, $user] = $this->tenant();
        Sanctum::actingAs($user);
        $subject = Subject::query()->create(['school_id' => $school->id, 'name' => 'IPA', 'code' => 'IPA']);
        $bankId = $this->postJson('/api/v1/question-banks', ['title' => 'Paket IPA', 'subject_id' => $subject->id])->json('data.id');
        $valid = $this->questionDocument(['1. Air membeku pada ...', 'A. 0 C', 'B. 100 C', 'ANS : A']);
        $this->post("/api/v1/question-banks/{$bankId}/import", ['file' => $valid], ['Accept' => 'application/json'])->assertOk();
        $locked = $this->questionDocument(['1. Soal baru', 'A. Ya', 'B. Tidak', 'ANS : A']);
        $this->post("/api/v1/question-banks/{$bankId}/import", ['file' => $locked], ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('requires_confirmation', true);

        $invalid = $this->questionDocument(['1. Soal salah', 'A. Ya', 'B. Tidak', 'ANS : C']);
        $this->post("/api/v1/question-banks/{$bankId}/import", ['file' => $invalid, 'force' => '1'], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_question_bank_is_hidden_from_other_tenants(): void
    {
        [$firstSchool, $first] = $this->tenant('40000001', 'bank1@example.test');
        [, $second] = $this->tenant('40000002', 'bank2@example.test');
        $subject = Subject::query()->create(['school_id' => $firstSchool->id, 'name' => 'Bahasa', 'code' => 'BIN']);
        Sanctum::actingAs($first);
        $bankId = $this->postJson('/api/v1/question-banks', ['title' => 'Rahasia', 'subject_id' => $subject->id])->json('data.id');
        Sanctum::actingAs($second);
        $this->getJson("/api/v1/question-banks/{$bankId}")->assertNotFound();
        $this->deleteJson("/api/v1/question-banks/{$bankId}")->assertNotFound();
    }

    private function questionDocument(array $lines): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'questions_');
        $document = new PhpWord;
        $section = $document->addSection();
        foreach ($lines as $line) {
            $section->addText($line);
        }
        IOFactory::createWriter($document, 'Word2007')->save($path);

        return new UploadedFile($path, 'soal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    private function tenant(string $npsn = '42345678', string $email = 'bank@example.test'): array
    {
        $school = School::query()->create(['name' => 'Sekolah Soal', 'npsn' => $npsn, 'type' => SchoolType::JuniorHigh, 'address' => 'Alamat', 'email' => $email]);

        return [$school, User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Kurikulum])];
    }
}
