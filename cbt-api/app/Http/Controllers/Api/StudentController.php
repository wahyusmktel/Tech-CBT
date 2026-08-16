<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStudentImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\ListStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class StudentController extends Controller
{
    public function importTemplate(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $this->authorize('import', Student::class);
            $path = storage_path('app/outputs/student-import-template/template-import-siswa.xlsx');
            if (! is_file($path)) {
                throw new \RuntimeException('Student import template is missing.');
            }

            return response()->download($path, 'template-import-data-siswa.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Downloading student import template failed.');
        }
    }

    public function index(ListStudentsRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Student::class);
            $data = $request->validated();
            $students = Student::query()
                ->where('school_id', $request->user()->school_id)
                ->when($data['classroom_id'] ?? null, fn ($query, $id) => $query->where('classroom_id', $id))
                ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%")))
                ->with('classroom')->orderBy('name')->paginate($data['per_page'] ?? 15);

            return response()->json([
                'data' => StudentResource::collection($students->items()),
                'meta' => ['current_page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'per_page' => $students->perPage(), 'total' => $students->total()],
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading students failed.');
        }
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Student::class);
            $student = Student::query()->create([...$request->validated(), 'school_id' => $request->user()->school_id]);

            return response()->json(['message' => 'Siswa berhasil ditambahkan.', 'data' => new StudentResource($student->load('classroom'))], 201);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Creating student failed.');
        }
    }

    public function update(UpdateStudentRequest $request, string $student): JsonResponse
    {
        try {
            $item = $this->find($request, $student);
            $this->authorize('update', $item);
            $item->update($request->validated());

            return response()->json(['message' => 'Siswa berhasil diperbarui.', 'data' => new StudentResource($item->load('classroom'))]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Updating student failed.');
        }
    }

    public function destroy(Request $request, string $student): JsonResponse
    {
        try {
            $item = $this->find($request, $student);
            $this->authorize('delete', $item);
            $item->delete();

            return response()->json(['message' => 'Siswa berhasil dihapus.']);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Deleting student failed.');
        }
    }

    public function import(ImportStudentsRequest $request, StudentImportService $service): JsonResponse
    {
        try {
            $this->authorize('import', Student::class);
            $result = $service->import($request->file('file'), $request->user()->school_id);

            return response()->json(['message' => "Import selesai: {$result['inserted']} data ditambahkan, {$result['updated']} diperbarui, {$result['failed']} gagal.", 'data' => $result]);
        } catch (InvalidStudentImportException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Importing students failed.');
        }
    }

    private function find(Request $request, string $id): Student
    {
        return Student::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
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
