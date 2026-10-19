<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The sign-in throttle the web login and the Mobile app share: five wrong
 * tries per email and IP, then a pause, both reported on `email`. Use it in
 * a FormRequest: it reads the request's `email` and IP.
 */
trait ThrottlesSignIn
{
    /**
     * Ensure the sign-in request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Count a wrong try and refuse it without saying which field was wrong.
     *
     * @throws ValidationException
     */
    protected function failSignIn(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    protected function clearSignInAttempts(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $email = Str::lower($this->string('email'));

        return Str::transliterate("{$email}|{$this->ip()}");
    }
}
