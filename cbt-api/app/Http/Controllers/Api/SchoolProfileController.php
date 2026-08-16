<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSchoolProfileRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SchoolProfileController extends Controller
{
    public function show(Request $request): SchoolResource|JsonResponse
    {
        try {
            $school = $this->schoolFor($request);
            $this->authorize('view', $school);

            return new SchoolResource($school);
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }

            Log::error('Loading school profile failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function update(UpdateSchoolProfileRequest $request): JsonResponse
    {
        $newLetterheadPath = null;
        DB::beginTransaction();

        try {
            $school = $this->schoolFor($request);
            $this->authorize('update', $school);
            $data = $request->safe()->except('letterhead');
            $oldLetterheadPath = $school->letterhead_path;

            if ($request->hasFile('letterhead')) {
                $newLetterheadPath = $request->file('letterhead')->store("letterheads/{$school->id}", 'public');
                if (! $newLetterheadPath) {
                    throw new RuntimeException('Failed to store school letterhead.');
                }
                $data['letterhead_path'] = $newLetterheadPath;
            }

            $school->update($data);
            DB::commit();

            if ($newLetterheadPath && $oldLetterheadPath && $oldLetterheadPath !== $newLetterheadPath) {
                Storage::disk('public')->delete($oldLetterheadPath);
            }

            return response()->json([
                'message' => 'Profil sekolah berhasil diperbarui.',
                'data' => new SchoolResource($school->refresh()),
            ]);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($newLetterheadPath) {
                Storage::disk('public')->delete($newLetterheadPath);
            }
            if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }

            Log::error('Updating school profile failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function schoolFor(Request $request): School
    {
        return School::query()->findOrFail($request->user()->school_id);
    }
}
