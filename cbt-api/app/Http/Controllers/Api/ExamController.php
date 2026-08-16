<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CredentialsAlreadyGeneratedException;
use App\Exceptions\InvalidCredentialGenerationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateExamCredentialsRequest;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Models\ExamRoomAssignment;
use App\Services\ExamCredentialService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Exam::class);
            $items = Exam::query()->where('school_id', $request->user()->school_id)->with(['subject', 'questionBank', 'roomAssignments.room'])->withCount('credentials')->orderByDesc('start_at')->get();

            return response()->json(['data' => ExamResource::collection($items)]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Loading exams failed.');
        }
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $this->authorize('create', Exam::class);
            $data = $request->safe()->except('room_ids');
            do {
                $accessCode = Str::upper(Str::random(8));
            } while (Exam::query()->where('access_code', $accessCode)->exists());
            $exam = Exam::query()->create([...$data, 'school_id' => $request->user()->school_id, 'access_code' => $accessCode]);
            $this->saveRooms($exam, $request->validated('room_ids'));
            DB::commit();

            return response()->json(['message' => 'Ujian berhasil ditambahkan.', 'data' => new ExamResource($this->loaded($exam))], 201);
        } catch (Throwable $e) {
            return $this->transactionFailure($e, 'Creating exam failed.');
        }
    }

    public function update(UpdateExamRequest $request, string $exam): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = $this->find($request, $exam);
            $this->authorize('update', $item);
            $roomIds = collect($request->validated('room_ids'))->sort()->values();
            $currentRoomIds = $item->roomAssignments()->pluck('room_id')->sort()->values();
            if ($item->credentials()->exists() && $roomIds->all() !== $currentRoomIds->all()) {
                DB::rollBack();

                return response()->json(['message' => 'Ruang tidak dapat diubah setelah kredensial dibuat. Generate ulang kredensial terlebih dahulu.'], 409);
            }
            $item->update($request->safe()->except('room_ids'));
            $this->saveRooms($item, $roomIds->all());
            DB::commit();

            return response()->json(['message' => 'Ujian berhasil diperbarui.', 'data' => new ExamResource($this->loaded($item))]);
        } catch (Throwable $e) {
            return $this->transactionFailure($e, 'Updating exam failed.');
        }
    }

    public function destroy(Request $request, string $exam): JsonResponse
    {
        try {
            $item = $this->find($request, $exam);
            $this->authorize('delete', $item);
            $item->delete();

            return response()->json(['message' => 'Ujian berhasil dihapus.']);
        } catch (Throwable $e) {
            return $this->failure($e, 'Deleting exam failed.');
        }
    }

    public function generateCredentials(GenerateExamCredentialsRequest $request, string $exam, ExamCredentialService $service): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = $this->find($request, $exam);
            $this->authorize('generateCredentials', $item);
            $count = $service->generate($item, $request->validated());
            DB::commit();

            return response()->json(['message' => "Kredensial berhasil dibuat untuk {$count} siswa.", 'data' => ['generated_count' => $count, 'generated_at' => $item->fresh()->credentials_generated_at?->toISOString()]]);
        } catch (CredentialsAlreadyGeneratedException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json(['message' => $e->getMessage(), 'requires_confirmation' => true], 409);
        } catch (InvalidCredentialGenerationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->transactionFailure($e, 'Generating credentials failed.');
        }
    }

    private function saveRooms(Exam $exam, array $roomIds): void
    {
        $exam->roomAssignments()->delete();
        $now = now();
        ExamRoomAssignment::query()->insert(collect($roomIds)->map(fn ($roomId) => ['id' => (string) Str::uuid(), 'school_id' => $exam->school_id, 'exam_id' => $exam->id, 'room_id' => $roomId, 'created_at' => $now, 'updated_at' => $now])->all());
    }

    private function loaded(Exam $exam): Exam
    {
        return $exam->load(['subject', 'questionBank', 'roomAssignments.room'])->loadCount('credentials');
    }

    private function find(Request $request, string $id): Exam
    {
        return Exam::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
    }

    private function transactionFailure(Throwable $e, string $context): JsonResponse
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        return $this->failure($e, $context);
    }

    private function failure(Throwable $e, string $context): JsonResponse
    {
        if ($e instanceof AuthorizationException || $e instanceof ModelNotFoundException) {
            throw $e;
        } Log::error($context, ['exception' => $e]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
