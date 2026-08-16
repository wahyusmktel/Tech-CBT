<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->index(['school_id', 'status', 'start_at'], 'exams_tenant_status_start_idx');
        });
        Schema::table('exam_room_assignments', function (Blueprint $table) {
            $table->index(['room_id', 'exam_id'], 'exam_rooms_room_exam_idx');
        });
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(['credential_id', 'status'], 'attempts_credential_status_idx');
            $table->index(['exam_id', 'status'], 'attempts_exam_status_idx');
            $table->index(['school_id', 'status', 'finished_at'], 'attempts_tenant_status_finished_idx');
        });
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->index(['school_id', 'question_id'], 'answers_tenant_question_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->dropIndex('answers_tenant_question_idx');
        });
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('attempts_credential_status_idx');
            $table->dropIndex('attempts_exam_status_idx');
            $table->dropIndex('attempts_tenant_status_finished_idx');
        });
        Schema::table('exam_room_assignments', function (Blueprint $table) {
            $table->dropIndex('exam_rooms_room_exam_idx');
        });
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_tenant_status_start_idx');
        });
    }
};
