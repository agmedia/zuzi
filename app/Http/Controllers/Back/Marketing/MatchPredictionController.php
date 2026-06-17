<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MatchPrediction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchPredictionController extends Controller
{
    public function index(Request $request)
    {
        $predictions = MatchPrediction::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($nested) use ($search) {
                    $nested->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');

                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('is_winner')
            ->orderByDesc('created_at')
            ->paginate(config('settings.pagination.back', 30))
            ->withQueryString();

        return view('back.marketing.match-predictions.index', [
            'predictions' => $predictions,
            'deadline' => $this->deadline(),
            'totalPredictions' => MatchPrediction::count(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'match-predictions-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Vrijeme prijave',
                'Ime',
                'Prezime',
                'Email',
                'Hrvatska golovi',
                'Engleska golovi',
                'Minuta prvog gola',
                'Ukupan broj žutih kartona',
                'IP adresa',
                'User agent',
                'Prihvatio pravila',
                'Prihvatio privatnost',
                'Newsletter privola',
                'Winner score',
                'Pobjednik',
                'Kontaktiran',
            ]);

            MatchPrediction::query()
                ->orderBy('created_at')
                ->each(function (MatchPrediction $prediction) use ($handle) {
                    fputcsv($handle, [
                        $prediction->id,
                        optional($prediction->created_at)->format('Y-m-d H:i:s'),
                        $prediction->first_name,
                        $prediction->last_name,
                        $prediction->email,
                        $prediction->croatia_goals,
                        $prediction->england_goals,
                        $prediction->first_goal_minute,
                        $prediction->yellow_cards_total,
                        $prediction->ip_address,
                        $prediction->user_agent,
                        $prediction->accepted_rules ? 'da' : 'ne',
                        $prediction->accepted_privacy ? 'da' : 'ne',
                        $prediction->newsletter_consent ? 'da' : 'ne',
                        $prediction->winner_score,
                        $prediction->is_winner ? 'da' : 'ne',
                        optional($prediction->contacted_at)->format('Y-m-d H:i:s'),
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function calculateWinner(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'croatia_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'england_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'first_goal_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'yellow_cards_total' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $winner = null;

        DB::transaction(function () use ($data, &$winner) {
            MatchPrediction::query()->update([
                'is_winner' => false,
                'winner_score' => null,
            ]);

            $exactPredictions = MatchPrediction::query()
                ->where('croatia_goals', (int) $data['croatia_goals'])
                ->where('england_goals', (int) $data['england_goals'])
                ->get();

            if ($exactPredictions->isEmpty()) {
                return;
            }

            $winner = $exactPredictions
                ->sort(function (MatchPrediction $left, MatchPrediction $right) use ($data) {
                    $firstGoalCompare = $this->tieBreakerDifference($left->first_goal_minute, (int) $data['first_goal_minute'])
                        <=> $this->tieBreakerDifference($right->first_goal_minute, (int) $data['first_goal_minute']);

                    if ($firstGoalCompare !== 0) {
                        return $firstGoalCompare;
                    }

                    $cardsCompare = $this->tieBreakerDifference($left->yellow_cards_total, (int) $data['yellow_cards_total'])
                        <=> $this->tieBreakerDifference($right->yellow_cards_total, (int) $data['yellow_cards_total']);

                    if ($cardsCompare !== 0) {
                        return $cardsCompare;
                    }

                    $createdAtCompare = $this->createdTimestamp($left) <=> $this->createdTimestamp($right);

                    if ($createdAtCompare !== 0) {
                        return $createdAtCompare;
                    }

                    return (int) $left->id <=> (int) $right->id;
                })
                ->first();

            $winner->winner_score = $this->winnerScore($winner, (int) $data['first_goal_minute'], (int) $data['yellow_cards_total']);
            $winner->is_winner = true;
            $winner->save();
        });

        if (! $winner) {
            return back()->with('warning', 'Nema prijava s točnim rezultatom.');
        }

        return back()->with('success', 'Pobjednik je izračunat: ' . $winner->first_name . ' ' . $winner->last_name . ' (' . $winner->email . ').');
    }

    public function markContacted(MatchPrediction $matchPrediction): RedirectResponse
    {
        $matchPrediction->contacted_at = now();
        $matchPrediction->save();

        return back()->with('success', 'Dobitnik je označen kao kontaktiran.');
    }

    private function tieBreakerDifference(?int $submitted, int $official): int
    {
        if ($submitted === null) {
            return 999;
        }

        return abs($submitted - $official);
    }

    private function winnerScore(MatchPrediction $prediction, int $officialFirstGoalMinute, int $officialYellowCardsTotal): int
    {
        return $this->tieBreakerDifference($prediction->first_goal_minute, $officialFirstGoalMinute)
            + $this->tieBreakerDifference($prediction->yellow_cards_total, $officialYellowCardsTotal);
    }

    private function createdTimestamp(MatchPrediction $prediction): int
    {
        return $prediction->created_at ? $prediction->created_at->getTimestamp() : 0;
    }

    private function deadline(): Carbon
    {
        return Carbon::parse(
            (string) config('match_prediction.deadline'),
            (string) config('match_prediction.timezone', config('app.timezone', 'Europe/Zagreb'))
        );
    }
}
