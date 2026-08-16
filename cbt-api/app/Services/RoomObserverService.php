<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RoomObserverService
{
    public function create(Room $room): array
    {
        try {
            do {
                $username = 'pengawas-'.Str::lower(Str::random(8));
            } while (User::query()->where('username', $username)->exists());
            $password = Str::password(12, true, true, false, false);
            $user = User::query()->create([
                'school_id' => $room->school_id,
                'room_id' => $room->id,
                'name' => 'Pengawas '.$room->name,
                'email' => $username.'@observer.local',
                'username' => $username,
                'role' => UserRole::Pengawas,
                'password' => $password,
                'generated_password' => $password,
                'is_active' => true,
            ]);

            return ['user' => $user, 'username' => $username, 'password' => $password];
        } catch (Throwable $exception) {
            Log::error('Creating room observer failed.', ['exception' => $exception]);
            throw $exception;
        }
    }

    public function rotate(User $observer): array
    {
        try {
            $password = Str::password(12, true, true, false, false);
            $observer->update(['password' => $password, 'generated_password' => $password]);
            $observer->tokens()->delete();

            return ['username' => $observer->username, 'password' => $password];
        } catch (Throwable $exception) {
            Log::error('Rotating observer credentials failed.', ['exception' => $exception]);
            throw $exception;
        }
    }
}
