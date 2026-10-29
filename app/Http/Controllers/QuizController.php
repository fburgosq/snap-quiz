<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Quiz;
use App\Services\Claude\Exceptions\InvalidImageException;
use App\Services\Claude\ExerciseExtractor;
use App\Services\Quiz\QuizImporter;
use BadMethodCallException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

class QuizController extends Controller
{
    /**
     * Display the SnapQuiz landing page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'quiz' => $request->session()->get('quiz'),
        ]);
    }

    /**
     * Extract and persist a fill-in-the-blank quiz from the uploaded image.
     */
    public function store(
        StoreQuizRequest $request,
        ExerciseExtractor $extractor,
        QuizImporter $importer,
    ): JsonResponse|RedirectResponse {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        $userId = $request->user()?->id;
        $imageHash = (string) hash_file('sha256', (string) ($image->getRealPath() ?: $image->path()));

        if (($existingQuiz = QuizImporter::findDuplicate($imageHash, $userId)) !== null) {
            return $this->respondWithQuiz($request, $existingQuiz, duplicate: true);
        }

        // TODO: Move the AI extraction and database import steps into a queued job for background processing.
        $storedPath = null;

        try {
            $extracted = $extractor->extract($image);
            $storedPath = $this->storeSourceImage($image);
            $usage = $this->resolveUsage($extractor);

            $quiz = $importer->import($extracted, $userId, [
                'source_image_path' => $storedPath,
                'source_image_hash' => $imageHash,
                'model' => $usage['model'],
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
            ]);
        } catch (Throwable $exception) {
            $this->deleteStoredImage($storedPath);

            return $this->handleStoreException($request, $exception, $userId, $imageHash);
        }

        return $this->respondWithQuiz($request, $quiz, duplicate: false);
    }

    private function storageDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function storeSourceImage(UploadedFile $image): string
    {
        $extension = $image->extension() ?: 'bin';
        $directory = 'quizzes/'.now()->format('Y/m');
        $filename = Str::uuid().'.'.$extension;
        $storedPath = $image->storeAs($directory, $filename, ['disk' => $this->storageDisk()]);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new InvalidImageException('Failed to store the uploaded image.');
        }

        return $storedPath;
    }

    private function deleteStoredImage(?string $storedPath): void
    {
        if ($storedPath !== null && $storedPath !== '') {
            Storage::disk($this->storageDisk())->delete($storedPath);
        }
    }

    /**
     * @return array{model: ?string, input_tokens: ?int, output_tokens: ?int}
     */
    private function resolveUsage(ExerciseExtractor $extractor): array
    {
        try {
            return $extractor->getLastUsage();
        } catch (BadMethodCallException) {
            return [
                'model' => null,
                'input_tokens' => null,
                'output_tokens' => null,
            ];
        }
    }

    private function respondWithQuiz(Request $request, Quiz $quiz, bool $duplicate): JsonResponse|RedirectResponse
    {
        $quiz->load('items.blanks.options');

        if ($request->expectsJson()) {
            return (new QuizResource($quiz))
                ->markDuplicate($duplicate)
                ->response()
                ->setStatusCode($duplicate ? 200 : 201);
        }

        return to_route('home')
            ->with('quiz', $quiz->toExerciseArray())
            ->with('quiz_uuid', $quiz->uuid)
            ->with('duplicate', $duplicate)
            ->with('status', $duplicate ? 'Existing quiz loaded.' : 'Quiz created successfully.');
    }

    private function handleStoreException(
        Request $request,
        Throwable $exception,
        ?int $userId,
        string $imageHash,
    ): JsonResponse|RedirectResponse {
        Log::error('Failed to extract or persist quiz from uploaded image.', [
            'user_id' => $userId,
            'image_hash' => $imageHash,
            'exception_class' => $exception::class,
            'exception' => $exception->getMessage(),
        ]);

        $isValidationError = $exception instanceof InvalidImageException
            || $exception instanceof InvalidArgumentException;

        $userMessage = $isValidationError
            ? 'The uploaded image could not be processed. Please upload a valid JPEG, PNG, or WebP image.'
            : 'We could not extract a quiz from this image. Please try another image.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $userMessage,
                'errors' => $isValidationError ? ['image' => [$userMessage]] : [],
            ], $isValidationError ? 422 : 502);
        }

        return back()->withErrors([
            'image' => $userMessage,
        ]);
    }
}
