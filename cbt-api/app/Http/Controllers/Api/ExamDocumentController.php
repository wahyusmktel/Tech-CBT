<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExamCredentialsMissingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamReportRequest;
use App\Models\Exam;
use App\Services\ExamDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExamDocumentController extends Controller
{
    public function attendance(ExamReportRequest $request, string $exam, ExamDocumentService $service): Response|JsonResponse
    {
        try {
            $examModel = $this->examFor($request, $exam);
            $data = $service->attendance($examModel);

            return Pdf::loadView('documents.attendance', $data)->setPaper('a4', 'portrait')->download($this->filename($examModel, 'daftar-hadir'));
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Creating exam attendance PDF failed.');
        }
    }

    public function minutes(ExamReportRequest $request, string $exam, ExamDocumentService $service): Response|JsonResponse
    {
        try {
            $examModel = $this->examFor($request, $exam);
            $data = $service->minutes($examModel);

            return Pdf::loadView('documents.minutes', $data)->setPaper('a4', 'portrait')->download($this->filename($examModel, 'berita-acara'));
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Creating exam minutes PDF failed.');
        }
    }

    public function cards(ExamReportRequest $request, string $exam, ExamDocumentService $service): Response|JsonResponse
    {
        try {
            $examModel = $this->examFor($request, $exam);
            $data = $service->cards($examModel);

            return Pdf::loadView('documents.cards', $data)->setPaper('a4', 'portrait')->download($this->filename($examModel, 'kartu-ujian'));
        } catch (ExamCredentialsMissingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Creating exam cards PDF failed.');
        }
    }

    private function examFor(ExamReportRequest $request, string $exam): Exam
    {
        $examModel = Exam::query()->where('school_id', $request->user()->school_id)->findOrFail($exam);
        $this->authorize('view', $examModel);

        return $examModel;
    }

    private function filename(Exam $exam, string $suffix): string
    {
        return Str::slug($exam->name).'-'.$suffix.'.pdf';
    }

    private function failure(Throwable $exception, string $context): JsonResponse
    {
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
            throw $exception;
        }
        Log::error($context, ['exception' => $exception]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
