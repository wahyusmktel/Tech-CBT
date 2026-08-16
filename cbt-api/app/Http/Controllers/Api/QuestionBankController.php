<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQuestionDocumentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportQuestionDocumentRequest;
use App\Http\Requests\StoreQuestionBankRequest;
use App\Http\Requests\UpdateQuestionBankRequest;
use App\Http\Resources\QuestionBankResource;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionChoice;
use App\Services\QuestionDocxParser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', QuestionBank::class);
            $items = QuestionBank::query()->where('school_id', $request->user()->school_id)->with(['subject', 'validator'])->withCount('questions')->latest()->get();

            return response()->json(['data' => QuestionBankResource::collection($items)]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Loading question banks failed.');
        }
    }

    public function show(Request $request, string $question_bank): JsonResponse
    {
        try {
            $item = $this->find($request, $question_bank);
            $this->authorize('view', $item);

            return response()->json(['data' => new QuestionBankResource($item->load(['subject', 'validator', 'questions.choices'])->loadCount('questions'))]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Loading question bank failed.');
        }
    }

    public function store(StoreQuestionBankRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', QuestionBank::class);
            $item = QuestionBank::query()->create([...$request->validated(), 'school_id' => $request->user()->school_id]);

            return response()->json(['message' => 'Bank soal berhasil ditambahkan.', 'data' => new QuestionBankResource($item->load('subject')->loadCount('questions'))], 201);
        } catch (Throwable $e) {
            return $this->failure($e, 'Creating question bank failed.');
        }
    }

    public function update(UpdateQuestionBankRequest $request, string $question_bank): JsonResponse
    {
        try {
            $item = $this->find($request, $question_bank);
            $this->authorize('update', $item);
            $item->update($request->validated());

            return response()->json(['message' => 'Bank soal berhasil diperbarui.', 'data' => new QuestionBankResource($item->load('subject')->loadCount('questions'))]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Updating question bank failed.');
        }
    }

    public function destroy(Request $request, string $question_bank): JsonResponse
    {
        try {
            $item = $this->find($request, $question_bank);
            $this->authorize('delete', $item);
            $item->delete();

            return response()->json(['message' => 'Bank soal berhasil dihapus.']);
        } catch (Throwable $e) {
            return $this->failure($e, 'Deleting question bank failed.');
        }
    }

    public function import(ImportQuestionDocumentRequest $request, string $question_bank, QuestionDocxParser $parser): JsonResponse
    {
        $transactionStarted = false;

        try {
            $item = $this->find($request, $question_bank);
            $this->authorize('import', $item);
            if ($item->questions()->exists() && ! $request->boolean('force')) {
                return response()->json(['message' => 'Bank soal sudah berisi soal.', 'requires_confirmation' => true], 409);
            }
            $parsed = $parser->parse($request->file('file'));
            DB::beginTransaction();
            $transactionStarted = true;
            $item->questions()->delete();
            foreach ($parsed as $data) {
                $question = Question::query()->create(['school_id' => $item->school_id, 'question_bank_id' => $item->id, 'number' => $data['number'], 'text' => $data['text']]);
                foreach ($data['choices'] as $label => $text) {
                    QuestionChoice::query()->create(['school_id' => $item->school_id, 'question_id' => $question->id, 'label' => $label, 'text' => $text, 'is_correct' => $label === $data['answer']]);
                }
            }
            $item->update(['validated_at' => null, 'validated_by' => null]);
            DB::commit();
            $transactionStarted = false;

            return response()->json(['message' => count($parsed).' soal berhasil diimport.', 'data' => ['questions_count' => count($parsed)]]);
        } catch (InvalidQuestionDocumentException $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            return $this->failure($e, 'Importing questions failed.');
        }
    }

    public function validateBank(Request $request, string $question_bank): JsonResponse
    {
        try {
            $item = $this->find($request, $question_bank);
            $this->authorize('validate', $item);
            if (! $item->questions()->exists()) {
                return response()->json(['message' => 'Bank soal belum memiliki soal untuk divalidasi.'], 422);
            }
            $item->update(['validated_at' => now(), 'validated_by' => $request->user()->id]);

            return response()->json(['message' => 'Bank soal berhasil divalidasi.', 'data' => new QuestionBankResource($item->load(['subject', 'validator'])->loadCount('questions'))]);
        } catch (Throwable $e) {
            return $this->failure($e, 'Validating question bank failed.');
        }
    }

    private function find(Request $request, string $id): QuestionBank
    {
        return QuestionBank::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
    }

    private function failure(Throwable $e, string $context): JsonResponse
    {
        if ($e instanceof AuthorizationException || $e instanceof ModelNotFoundException) {
            throw $e;
        } Log::error($context, ['exception' => $e]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
