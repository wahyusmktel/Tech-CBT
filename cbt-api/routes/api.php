<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ObserverMonitoringController;
use App\Http\Controllers\Api\QuestionBankController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SchoolProfileController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentExamController;
use App\Http\Controllers\Api\SubjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/schools/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/student/login', [StudentExamController::class, 'login'])->middleware('throttle:student-login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::middleware('role:platform')->group(function (): void {
            Route::get('/auth/me', [AuthController::class, 'me']);
            Route::post('/auth/logout', [AuthController::class, 'logout']);
        });

        Route::middleware('role:kurikulum')->group(function (): void {
            Route::get('/school/profile', [SchoolProfileController::class, 'show']);
            Route::post('/school/profile', [SchoolProfileController::class, 'update']);
            Route::apiResource('classrooms', ClassroomController::class)->except('show');
            Route::post('/students/import', [StudentController::class, 'import'])->middleware('throttle:10,1');
            Route::get('/students/import-template', [StudentController::class, 'importTemplate']);
            Route::apiResource('students', StudentController::class)->except('show');
            Route::get('/rooms/{room}/mapping', [RoomController::class, 'mapping']);
            Route::put('/rooms/{room}/mapping', [RoomController::class, 'syncMapping']);
            Route::get('/rooms/{room}/observer-credentials', [RoomController::class, 'credentials']);
            Route::post('/rooms/{room}/observer-credentials/rotate', [RoomController::class, 'rotateCredentials']);
            Route::apiResource('rooms', RoomController::class)->except('show');
            Route::apiResource('subjects', SubjectController::class)->except('show');
            Route::post('/exams/{exam}/generate-credentials', [ExamController::class, 'generateCredentials'])->middleware('throttle:10,1');
            Route::apiResource('exams', ExamController::class)->except('show');
            Route::post('/question-banks/{question_bank}/import', [QuestionBankController::class, 'import'])->middleware('throttle:10,1');
            Route::post('/question-banks/{question_bank}/validate', [QuestionBankController::class, 'validateBank']);
            Route::apiResource('question-banks', QuestionBankController::class);
        });

        Route::get('/observer/monitoring', [ObserverMonitoringController::class, 'index'])->middleware('role:pengawas');

        Route::middleware('exam.student')->group(function (): void {
            Route::get('/student/session', [StudentExamController::class, 'session']);
            Route::post('/student/exam/start', [StudentExamController::class, 'start']);
            Route::put('/student/exam/answers/{question}', [StudentExamController::class, 'saveAnswer'])->middleware('throttle:answer-sync');
            Route::post('/student/exam/submit', [StudentExamController::class, 'submit']);
        });
    });
});
