<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassroomRequest;
use App\Http\Requests\UpdateClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Classroom::class);
            $items = Classroom::query()->where('school_id', $request->user()->school_id)->withCount('students')->orderBy('name')->get();

            return response()->json(['data' => ClassroomResource::collection($items)]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading classrooms failed.');
        }
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Classroom::class);
            $item = Classroom::query()->create(['school_id' => $request->user()->school_id, 'name' => $request->validated('name')]);

            return response()->json(['message' => 'Kelas berhasil ditambahkan.', 'data' => new ClassroomResource($item)], 201);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Creating classroom failed.');
        }
    }

    public function update(UpdateClassroomRequest $request, string $classroom): JsonResponse
    {
        try {
            $item = $this->find($request, $classroom);
            $this->authorize('update', $item);
            $item->update($request->validated());

            return response()->json(['message' => 'Kelas berhasil diperbarui.', 'data' => new ClassroomResource($item)]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Updating classroom failed.');
        }
    }

    public function destroy(Request $request, string $classroom): JsonResponse
    {
        try {
            $item = $this->find($request, $classroom);
            $this->authorize('delete', $item);
            if ($item->students()->exists()) {
                return response()->json(['message' => 'Kelas masih memiliki siswa dan tidak dapat dihapus.'], 422);
            }
            $item->delete();

            return response()->json(['message' => 'Kelas berhasil dihapus.']);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Deleting classroom failed.');
        }
    }

    private function find(Request $request, string $id): Classroom
    {
        return Classroom::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
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
