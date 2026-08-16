<?php

namespace App\Http\Middleware;

use App\Models\ExamStudentCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExamStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof ExamStudentCredential, 403, 'Sesi peserta tidak valid.');

        return $next($request);
    }
}
