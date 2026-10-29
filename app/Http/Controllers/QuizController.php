<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizRequest;
use App\Services\Claude\ExerciseExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
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
     * Extract a fill-in-the-blank quiz from the uploaded image.
     */
    public function store(StoreQuizRequest $request, ExerciseExtractor $extractor): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');

        try {
            $quiz = $extractor->extract($image);
        } catch (Throwable $exception) {
            Log::error('Failed to extract quiz from uploaded image.', [
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'image' => 'We could not extract a quiz from this image. Please try another image.',
            ]);
        }

        /** Store in database quiz data HERE */

        return to_route('home')->with('quiz', $quiz);
    }
}
