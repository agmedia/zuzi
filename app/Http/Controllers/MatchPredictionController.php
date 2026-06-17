<?php

namespace App\Http\Controllers;

use App\Helpers\Recaptcha;
use App\Models\MatchPrediction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MatchPredictionController extends Controller
{
    private const SUCCESS_MESSAGE = 'Hvala! Tvoja prognoza je zaprimljena.';
    private const CLOSED_MESSAGE = 'Prijave za promotivno natjecanje su završile.';

    public function create(): View
    {
        return view('front.match-predictions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isOpen()) {
            return back()
                ->withErrors(['match_prediction' => self::CLOSED_MESSAGE], 'matchPrediction')
                ->withInput()
                ->withFragment('pogodi-rezultat');
        }

        if ($request->filled('website')) {
            return back()
                ->with('match_prediction_success', self::SUCCESS_MESSAGE)
                ->withFragment('pogodi-rezultat');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:match_predictions,email'],
            'croatia_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'england_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'first_goal_minute' => ['nullable', 'integer', 'min:1', 'max:120'],
            'yellow_cards_total' => ['nullable', 'integer', 'min:0', 'max:30'],
            'accepted_rules' => ['accepted'],
            'accepted_privacy' => ['accepted'],
            'newsletter_consent' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Za ovaj email već postoji zaprimljena prognoza.',
            'accepted_rules.accepted' => 'Potrebno je prihvatiti pravila promotivnog natjecanja.',
            'accepted_privacy.accepted' => 'Potrebno je prihvatiti obradu osobnih podataka.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'matchPrediction')
                ->withInput()
                ->withFragment('pogodi-rezultat');
        }

        $recaptcha = (new Recaptcha())->check($request->toArray());

        if (! $recaptcha || ! $recaptcha->ok()) {
            return back()
                ->withErrors(['recaptcha' => 'ReCaptcha provjera nije uspjela. Pokušajte ponovno.'], 'matchPrediction')
                ->withInput()
                ->withFragment('pogodi-rezultat');
        }

        $data = $validator->validated();

        MatchPrediction::create([
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'email' => strtolower(trim((string) $data['email'])),
            'croatia_goals' => (int) $data['croatia_goals'],
            'england_goals' => (int) $data['england_goals'],
            'first_goal_minute' => isset($data['first_goal_minute']) ? (int) $data['first_goal_minute'] : null,
            'yellow_cards_total' => isset($data['yellow_cards_total']) ? (int) $data['yellow_cards_total'] : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accepted_rules' => true,
            'accepted_privacy' => true,
            'newsletter_consent' => $request->boolean('newsletter_consent'),
        ]);

        return back()
            ->with('match_prediction_success', self::SUCCESS_MESSAGE)
            ->withFragment('pogodi-rezultat');
    }

    public function rules(): View
    {
        return view('front.match-predictions.rules', [
            'deadline' => $this->deadline(),
        ]);
    }

    private function isOpen(): bool
    {
        if (! (bool) config('match_prediction.enabled')) {
            return false;
        }

        return Carbon::now($this->timezone())->lt($this->deadline());
    }

    private function deadline(): Carbon
    {
        return Carbon::parse((string) config('match_prediction.deadline'), $this->timezone());
    }

    private function timezone(): string
    {
        return (string) config('match_prediction.timezone', config('app.timezone', 'Europe/Zagreb'));
    }
}
