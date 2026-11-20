<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            foreach ($this->throttleKeys() as $key) {
                RateLimiter::hit($key, 900);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        foreach ($this->throttleKeys() as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        foreach ($this->throttleKeys() as $key) {
            if (! RateLimiter::tooManyAttempts($key, 5)) {
                continue;
            }

            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * The account key does not depend on the request IP, so rotating
     * X-Forwarded-For (or any pool of source addresses) cannot reset the
     * per-account budget. The source key is kept as a secondary signal.
     */
    public function throttleKey(): string
    {
        return $this->throttleKeys()[0];
    }

    /**
     * @return array<int, string>
     */
    private function throttleKeys(): array
    {
        $email = Str::transliterate(Str::lower((string) $this->string('email')));

        return [
            'login:account:'.$email,
            'login:source:'.$email.'|'.$this->ip(),
        ];
    }
}
