<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Subject::class);
            $items = Subject::query()->where('school_id', $request->user()->school_id)->withCount('exams')->orderBy('name')->get();

            return response()->json(['data' => SubjectResource::collection($items)]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Loading subjects failed.');
        }
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Subject::class);
            $item = Subject::query()->create([...$request->validated(), 'school_id' => $request->user()->school_id]);

            return response()->json(['message' => 'Mata pelajaran berhasil ditambahkan.', 'data' => new SubjectResource($item)], 201);
        } catch (Throwable $e) {
            return $this->failure($e, 'Creating subject failed.');
        }
    }

    public function update(UpdateSubjectRequest $request, string $subject): JsonResponse
    {
        try {
            $item = $this->find($request, $subject);
            $this->authorize('update', $item);
            $item->update($request->validated());

            return response()->json(['message' => 'Mata pelajaran berhasil diperbarui.', 'data' => new SubjectResource($item)]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Updating subject failed.');
        }
    }

    public function destroy(Request $request, string $subject): JsonResponse
    {
        try {
            $item = $this->find($request, $subject);
            $this->authorize('delete', $item);
            if ($item->exams()->exists()) {
                return response()->json(['message' => 'Mata pelajaran masih digunakan oleh ujian.'], 422);
            } $item->delete();

            return response()->json(['message' => 'Mata pelajaran berhasil dihapus.']);
        } catch (Throwable $e) {
            return $this->failure($e, 'Deleting subject failed.');
        }
    }

    private function find(Request $request, string $id): Subject
    {
        return Subject::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
    }

    private function failure(Throwable $e, string $context): JsonResponse
    {
        if ($e instanceof AuthorizationException || $e instanceof ModelNotFoundException) {
            throw $e;
        } Log::error($context, ['exception' => $e]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
