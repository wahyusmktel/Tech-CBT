<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('room_id')->nullable()->after('school_id')->constrained()->cascadeOnDelete();
            $table->text('generated_password')->nullable()->after('password');
        });

        Schema::create('student_room_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['room_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_room_assignments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn('generated_password');
        });
        Schema::dropIfExists('rooms');
    }
};
