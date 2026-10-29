<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QuizController::class, 'index'])->name('home');
Route::get('/exercises', [ExerciseController::class, 'index'])->name('exercises.index');
Route::get('/exercises/{quiz:uuid}/image', [QuizImageController::class, 'show'])->name('exercises.image');
Route::inertia('/how-to-add', 'HowToAdd')->name('how-to-add');
Route::post('/quiz', [QuizController::class, 'store'])->name('quiz.store');
Route::get('/quiz/{quiz}', [QuizController::class, 'show'])->name('quiz.show');

// Route::middleware(['auth', 'verified'])->group(function () {
//    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
// });
//
// require __DIR__.'/settings.php';
