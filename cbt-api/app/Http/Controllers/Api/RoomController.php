<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapRoomStudentsRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentRoomAssignment;
use App\Services\RoomObserverService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Room::class);
            $rooms = Room::query()->where('school_id', $request->user()->school_id)->withCount('assignments')->with('observers')->orderBy('name')->get();

            return response()->json(['data' => RoomResource::collection($rooms)]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading rooms failed.');
        }
    }

    public function store(StoreRoomRequest $request, RoomObserverService $observerService): JsonResponse
    {
        DB::beginTransaction();
        try {
            $this->authorize('create', Room::class);
            $room = Room::query()->create(['school_id' => $request->user()->school_id, 'name' => $request->validated('name')]);
            $credentials = $observerService->create($room);
            DB::commit();

            return response()->json(['message' => 'Ruang dan akun Pengawas berhasil dibuat.', 'data' => ['room' => new RoomResource($room->load('observers')->loadCount('assignments')), 'observer_credentials' => ['username' => $credentials['username'], 'password' => $credentials['password']]]], 201);
        } catch (Throwable $exception) {
            return $this->transactionFailure($exception, 'Creating room failed.');
        }
    }

    public function update(UpdateRoomRequest $request, string $room): JsonResponse
    {
        try {
            $item = $this->find($request, $room);
            $this->authorize('update', $item);
            $item->update($request->validated());
            $item->observers()->update(['name' => 'Pengawas '.$item->name]);

            return response()->json(['message' => 'Ruang berhasil diperbarui.', 'data' => new RoomResource($item->load('observers')->loadCount('assignments'))]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Updating room failed.');
        }
    }

    public function destroy(Request $request, string $room): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = $this->find($request, $room);
            $this->authorize('delete', $item);
            foreach ($item->observers as $observer) {
                $observer->tokens()->delete();
            }
            $item->delete();
            DB::commit();

            return response()->json(['message' => 'Ruang berhasil dihapus.']);
        } catch (Throwable $exception) {
            return $this->transactionFailure($exception, 'Deleting room failed.');
        }
    }

    public function mapping(Request $request, string $room): JsonResponse
    {
        try {
            $item = $this->find($request, $room);
            $this->authorize('mapStudents', $item);
            $students = Student::query()->whereHas('roomAssignments', fn ($query) => $query->where('room_id', $item->id))->get(['id', 'classroom_id']);

            return response()->json(['data' => ['student_ids' => $students->pluck('id'), 'classroom_ids' => $students->pluck('classroom_id')->unique()->values()]]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading room mapping failed.');
        }
    }

    public function syncMapping(MapRoomStudentsRequest $request, string $room): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = $this->find($request, $room);
            $this->authorize('mapStudents', $item);
            $data = $request->validated();
            $studentIds = Student::query()->where('school_id', $request->user()->school_id)
                ->whereIn('classroom_id', $data['classroom_ids'])->pluck('id')->merge($data['student_ids'])->unique()->values();
            StudentRoomAssignment::query()->where('room_id', $item->id)->delete();
            if ($studentIds->isNotEmpty()) {
                StudentRoomAssignment::query()->where('school_id', $request->user()->school_id)->whereIn('student_id', $studentIds)->delete();
                $now = now();
                StudentRoomAssignment::query()->insert($studentIds->map(fn ($id) => ['id' => (string) Str::uuid(), 'school_id' => $request->user()->school_id, 'room_id' => $item->id, 'student_id' => $id, 'created_at' => $now, 'updated_at' => $now])->all());
            }
            DB::commit();

            return response()->json(['message' => "Mapping ruang berhasil disimpan untuk {$studentIds->count()} siswa.", 'data' => ['students_count' => $studentIds->count()]]);
        } catch (Throwable $exception) {
            return $this->transactionFailure($exception, 'Mapping room students failed.');
        }
    }

    public function credentials(Request $request, string $room): JsonResponse
    {
        try {
            $item = $this->find($request, $room);
            $this->authorize('viewCredentials', $item);
            $observer = $item->observers()->firstOrFail();

            return response()->json(['data' => ['username' => $observer->username, 'password' => $observer->generated_password]]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading observer credentials failed.');
        }
    }

    public function rotateCredentials(Request $request, string $room, RoomObserverService $service): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = $this->find($request, $room);
            $this->authorize('viewCredentials', $item);
            $credentials = $service->rotate($item->observers()->firstOrFail());
            DB::commit();

            return response()->json(['message' => 'Password Pengawas berhasil diperbarui.', 'data' => $credentials]);
        } catch (Throwable $exception) {
            return $this->transactionFailure($exception, 'Rotating observer credentials failed.');
        }
    }

    private function find(Request $request, string $id): Room
    {
        return Room::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
    }

    private function transactionFailure(Throwable $exception, string $context): JsonResponse
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        return $this->failure($exception, $context);
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
