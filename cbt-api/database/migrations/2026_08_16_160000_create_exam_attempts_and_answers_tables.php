<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignUuid('question_bank_id')->nullable()->after('subject_id')->constrained()->restrictOnDelete();
            $table->string('access_code', 12)->nullable()->unique()->after('name');
        });

        foreach (DB::table('exams')->whereNull('access_code')->get() as $exam) {
            do {
                $code = Str::upper(Str::random(8));
            } while (DB::table('exams')->where('access_code', $code)->exists());
            DB::table('exams')->where('id', $exam->id)->update(['access_code' => $code]);
        }

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('credential_id')->constrained('exam_student_credentials')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
        });

        Schema::create('exam_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('question_choice_id')->constrained()->cascadeOnDelete();
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_bank_id');
            $table->dropUnique(['access_code']);
            $table->dropColumn('access_code');
        });
    }
};
