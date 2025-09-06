<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        // First, we validate the incoming request using the rules in LoginRequest.
        // This line is technically redundant because Laravel does it automatically,
        // but it makes the logic clear.
        $credentials = $request->validated();

        // Now, we manually attempt to authenticate the user.
        // We use the Auth facade, which is Laravel's main authentication utility.
        if (!Auth::attempt($credentials)) {
            // If the attempt fails (wrong email or password), we throw a
            // standard validation exception. This will return a 422 error,
            // which our React frontend is already designed to handle.
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        // If authentication succeeds, we get here.
        // We regenerate the session ID to prevent session fixation attacks.
        $request->session()->regenerate();

        // Finally, we return a successful "No Content" response,
        // just like Breeze did.
        return response()->noContent();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
