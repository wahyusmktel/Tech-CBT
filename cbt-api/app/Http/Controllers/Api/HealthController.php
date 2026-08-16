<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $cacheKey = 'health:ready:'.getmypid();

        try {
            DB::select('select 1');
            Cache::put($cacheKey, 'ok', 10);
            $cacheReady = Cache::get($cacheKey) === 'ok';
            Cache::forget($cacheKey);

            if (! $cacheReady) {
                throw new \RuntimeException('Cache readiness check failed.');
            }

            return response()->json(['status' => 'ready', 'services' => ['database' => 'ready', 'cache' => 'ready']]);
        } catch (Throwable $exception) {
            try {
                Cache::forget($cacheKey);
            } catch (Throwable $cacheException) {
                Log::debug('Readiness cache cleanup failed.', ['exception' => $cacheException]);
            }
            Log::warning('Application readiness check failed.', ['exception' => $exception]);

            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
