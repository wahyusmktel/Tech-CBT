<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterSchoolRequest;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterSchoolRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $school = School::query()->create([
                'npsn' => $data['npsn'],
                'type' => $data['school_type'],
                'address' => $data['address'],
                'email' => $data['email'],
            ]);

            $user = User::query()->create([
                'school_id' => $school->id,
                'name' => 'Kurikulum',
                'email' => $data['email'],
                'username' => $data['username'],
                'role' => UserRole::Kurikulum,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $token = $user->createToken('web-client')->plainTextToken;
            DB::commit();

            return response()->json([
                'message' => 'Sekolah berhasil didaftarkan.',
                'data' => [
                    'token' => $token,
                    'user' => new UserResource($user->load('school')),
                ],
            ], 201);
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('School registration failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = User::query()->where('username', $data['username'])->first();

            if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Username atau password tidak valid.'], 422);
            }

            $user->tokens()->where('name', 'web-client')->delete();
            $token = $user->createToken('web-client')->plainTextToken;

            return response()->json([
                'message' => 'Login berhasil.',
                'data' => [
                    'token' => $token,
                    'user' => new UserResource($user->load('school')),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Login failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function me(Request $request): UserResource|JsonResponse
    {
        try {
            return new UserResource($request->user()->load('school'));
        } catch (Throwable $exception) {
            Log::error('Loading authenticated user failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()?->delete();

            return response()->json(['message' => 'Logout berhasil.']);
        } catch (Throwable $exception) {
            Log::error('Logout failed.', ['exception' => $exception]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }
}
