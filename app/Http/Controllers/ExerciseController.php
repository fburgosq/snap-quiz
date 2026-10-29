<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\QuizSummaryResource;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    private const int PER_PAGE = 24;

    /**
     * Display a paginated list of saved exercises for the current user (or guest session).
     */
    public function index(Request $request): JsonResponse|RedirectResponse|Response
    {
        $paginator = Quiz::query()
            ->select(['id', 'uuid', 'title', 'created_at'])
            ->forUser($request->user())
            ->withCount('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            return to_route('exercises.index', array_merge(
                $request->query(),
                ['page' => $paginator->lastPage()],
            ));
        }

        $resourceCollection = QuizSummaryResource::collection($paginator);

        if ($request->expectsJson()) {
            return $resourceCollection->response($request);
        }

        return Inertia::render('exercises/Index', [
            'quizzes' => $resourceCollection->response($request)->getData(true),
        ]);
    }
}
