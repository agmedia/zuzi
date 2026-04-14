<?php

namespace App\Actions\Fortify;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    use PasswordValidationRules;

    public function showForgetPasswordForm()
    {
        return view('auth.forgetPassword');
    }

    public function submitForgetPasswordForm(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [$this->passwordBrokerMessage($status)],
            ]);
        }

        return back()->with('status', 'Poslali smo vam link za resetiranje lozinke na navedeni email!');
    }

    public function showResetPasswordForm($token)
    {
        return view('auth.forgetPasswordResetLink', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function submitResetPasswordForm(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                app(ResetUserPassword::class)->reset($user, $request->only('password', 'password_confirmation'));

                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$this->passwordBrokerMessage($status)],
            ]);
        }

        return redirect()
            ->route('index', ['auth' => 'signin'])
            ->with('auth_status', 'Lozinka je uspješno promijenjena. Prijavite se novom lozinkom.');
    }

    private function passwordBrokerMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER => 'Ne postoji korisnik s tom email adresom.',
            Password::INVALID_TOKEN => 'Link za reset lozinke nije valjan ili je istekao.',
            Password::RESET_THROTTLED => 'Zahtjev je već poslan. Pričekajte malo prije novog pokušaja.',
            default => 'Dogodila se greška prilikom obrade zahtjeva za reset lozinke.',
        };
    }
}
