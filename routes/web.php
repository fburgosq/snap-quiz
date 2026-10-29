<?php

use App\Services\Claude\ExerciseExtractor;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Route::get('/', function () {
//    $reply = app(ExerciseExtractor::class)->ping('Tell me a one-line sarcastic joke.');
//    dd('really', $reply);
//
//    return $reply;
// });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
