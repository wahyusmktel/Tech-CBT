<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamReportRequest;
use App\Models\Exam;
use App\Models\School;
use App\Services\ExamReportExportService;
use App\Services\ExamReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExamReportController extends Controller
{
    public function show(ExamReportRequest $request, string $exam, ExamReportService $reportService): JsonResponse
    {
        try {
            $examModel = $this->examFor($request, $exam);
            $this->authorize('view', $examModel);

            return response()->json(['data' => $reportService->build($examModel)]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'Loading exam report failed.');
        }
    }

    public function resultsPdf(ExamReportRequest $request, string $exam, ExamReportService $reportService, ExamReportExportService $exportService): Response|JsonResponse
    {
        try {
            [$examModel, $school, $report] = $this->reportContext($request, $exam, $reportService);
            $pdf = Pdf::loadView('reports.results', [
                'report' => $report,
                'school' => $school,
                'letterhead' => $exportService->letterheadDataUri($school),
            ])->setPaper('a4', 'portrait');

            return $pdf->download($this->filename($examModel, 'rekap-nilai', 'pdf'));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'Creating result PDF failed.');
        }
    }

    public function analysisPdf(ExamReportRequest $request, string $exam, ExamReportService $reportService, ExamReportExportService $exportService): Response|JsonResponse
    {
        try {
            [$examModel, $school, $report] = $this->reportContext($request, $exam, $reportService);
            $pdf = Pdf::loadView('reports.analysis', [
                'report' => $report,
                'school' => $school,
                'letterhead' => $exportService->letterheadDataUri($school),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($this->filename($examModel, 'analisis-soal', 'pdf'));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'Creating question analysis PDF failed.');
        }
    }

    public function excel(ExamReportRequest $request, string $exam, ExamReportService $reportService, ExamReportExportService $exportService): BinaryFileResponse|JsonResponse
    {
        try {
            [$examModel, $school, $report] = $this->reportContext($request, $exam, $reportService);
            $path = $exportService->createExcel($report, $school);

            return response()->download($path, $this->filename($examModel, 'laporan-ujian', 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'Creating exam Excel report failed.');
        }
    }

    private function reportContext(ExamReportRequest $request, string $exam, ExamReportService $reportService): array
    {
        $examModel = $this->examFor($request, $exam);
        $this->authorize('view', $examModel);
        $school = School::query()->findOrFail($request->user()->school_id);

        return [$examModel, $school, $reportService->build($examModel)];
    }

    private function examFor(ExamReportRequest $request, string $exam): Exam
    {
        return Exam::query()
            ->where('school_id', $request->user()->school_id)
            ->findOrFail($exam);
    }

    private function filename(Exam $exam, string $suffix, string $extension): string
    {
        return Str::slug($exam->name).'-'.$suffix.'.'.$extension;
    }

    private function errorResponse(Throwable $exception, string $logMessage): JsonResponse
    {
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
            throw $exception;
        }

        Log::error($logMessage, ['exception' => $exception]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
