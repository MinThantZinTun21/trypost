<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Concerns\ThrottlesSignIn;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The Mobile app's sign-in with the Owner's web email and password
 * (ADR 0015), under the web login's own throttle. It checks the password
 * without starting a session: the Mobile API keeps none.
 */
class SignInRequest extends FormRequest
{
    use ThrottlesSignIn;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * The Owner, when the email and password are theirs. Anyone else, even
     * another user with the right password, is refused like a wrong password.
     *
     * @throws ValidationException
     */
    public function owner(): User
    {
        $this->ensureIsNotRateLimited();

        $guard = Auth::guard('web');
        $owner = User::findOwner();

        if (! $guard->validate($this->only('email', 'password')) || $owner === null || ! $owner->is($guard->getLastAttempted())) {
            $this->failSignIn();
        }

        $this->clearSignInAttempts();

        return $owner;
    }
}
