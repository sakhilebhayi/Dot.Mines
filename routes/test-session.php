<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session and CSRF Testing Routes
|--------------------------------------------------------------------------
|
| These routes are for debugging session and CSRF token issues.
| Remove or comment out in production.
|
*/

Route::middleware(['web'])->group(function () {

    // Test session functionality
    Route::get('/test-session', function (Request $request) {
        $data = [
            'session_id' => session()->getId(),
            'csrf_token' => csrf_token(),
            'session_driver' => config('session.driver'),
            'session_cookie' => config('session.cookie'),
            'session_path' => config('session.path'),
            'session_domain' => config('session.domain'),
            'session_secure' => config('session.secure'),
            'session_same_site' => config('session.same_site'),
            'cookies' => $request->cookies->all(),
            'session_data' => session()->all(),
        ];

        return response()->json($data);
    })->name('test.session');

    // Test CSRF form
    Route::get('/test-csrf-form', function () {
        return view('test-csrf-form');
    })->name('test.csrf.form');

    // Test CSRF submission
    Route::post('/test-csrf-submit', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'CSRF validation passed!',
            'data' => $request->all(),
        ]);
    })->name('test.csrf.submit');

});
