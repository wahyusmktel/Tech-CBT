<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->dateTime('start_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('draft');
            $table->timestamp('credentials_generated_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'start_at']);
        });

        Schema::create('exam_room_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'room_id']);
        });

        Schema::create('exam_student_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('username', 80);
            $table->text('password');
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
            $table->unique(['exam_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_student_credentials');
        Schema::dropIfExists('exam_room_assignments');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('subjects');
    }
};
