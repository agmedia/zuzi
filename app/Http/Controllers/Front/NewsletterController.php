<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Mailchimp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'newsletter_email' => ['required', 'email'],
            'newsletter_consent' => ['accepted'],
        ], [
            'newsletter_email.required' => 'Upišite email adresu za prijavu na newsletter.',
            'newsletter_email.email' => 'Upišite ispravnu email adresu.',
            'newsletter_consent.accepted' => 'Potrebna je privola za prijavu na newsletter.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator)
                ->withFragment('footer-newsletter');
        }

        $validated = $validator->validated();

        $audience_id = config('services.mailchimp.audience_id');
        $api_key = config('services.mailchimp.api_key');
        $server_prefix = config('services.mailchimp.server_prefix');

        if (! $audience_id || ! $api_key || ! $server_prefix) {
            return back()
                ->withInput()
                ->withErrors(['newsletter_form' => 'Newsletter prijava trenutno nije dostupna. Pokušajte ponovno malo kasnije.'])
                ->withFragment('footer-newsletter');
        }

        $subscribed = (new Mailchimp())->addMemberToList(
            $audience_id,
            $validated['newsletter_email']
        );

        if ($subscribed === false) {
            return back()
                ->withInput()
                ->withErrors(['newsletter_form' => 'Prijava na newsletter nije uspjela. Pokušajte ponovno malo kasnije.'])
                ->withFragment('footer-newsletter');
        }

        return back()
            ->with('newsletter_success', 'Uspješno ste prijavljeni na newsletter.')
            ->withFragment('footer-newsletter');
    }
}
