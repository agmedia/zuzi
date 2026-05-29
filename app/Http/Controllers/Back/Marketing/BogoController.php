<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Action;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BogoController extends Controller
{
    public function index()
    {
        $actions = Action::query()
            ->where('group', Action::GROUP_BOGO)
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate(config('settings.pagination.back'));

        return view('back.marketing.bogo.index', compact('actions'));
    }

    public function create()
    {
        $tiers = [
            ['quantity' => 2, 'discount' => 5],
            ['quantity' => 3, 'discount' => 10],
            ['quantity' => 4, 'discount' => 15],
            ['quantity' => 5, 'discount' => 20],
        ];

        return view('back.marketing.bogo.edit', compact('tiers'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $action = Action::query()->create($this->modelPayload($data));

        return redirect()
            ->route('marketing.bogo.edit', ['bogo' => $action])
            ->with(['success' => 'BOGO akcija je uspješno spremljena!']);
    }

    public function edit(Action $bogo)
    {
        $this->ensureBogoAction($bogo);

        $tiers = Action::normalizeBogoTiers(is_array($bogo->data) ? $bogo->data : []);

        return view('back.marketing.bogo.edit', compact('bogo', 'tiers'));
    }

    public function update(Request $request, Action $bogo)
    {
        $this->ensureBogoAction($bogo);

        $data = $this->validatedData($request);

        $bogo->update($this->modelPayload($data));

        return redirect()
            ->route('marketing.bogo.edit', ['bogo' => $bogo])
            ->with(['success' => 'BOGO akcija je uspješno spremljena!']);
    }

    public function destroy(Action $bogo)
    {
        $this->ensureBogoAction($bogo);

        $bogo->delete();

        return redirect()
            ->route('marketing.bogo')
            ->with(['success' => 'BOGO akcija je uspješno izbrisana!']);
    }

    private function validatedData(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'date_start' => ['nullable', 'date_format:d.m.Y'],
            'date_end' => ['nullable', 'date_format:d.m.Y'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'tiers.*.discount' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $start = $this->parseDate($request->input('date_start'));
            $end = $this->parseDate($request->input('date_end'), true);

            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('date_end', 'Datum završetka mora biti nakon datuma početka.');
            }
        });

        $validated = $validator->validate();
        $tiers = Action::normalizeBogoTiers(['tiers' => $request->input('tiers', [])]);

        if (empty($tiers)) {
            throw ValidationException::withMessages([
                'tiers' => 'Dodajte barem jedan BOGO prag.',
            ]);
        }

        $validated['tiers'] = $tiers;
        $validated['status'] = $request->input('status') === 'on' ? 1 : 0;
        $validated['date_start'] = $this->parseDate($request->input('date_start'));
        $validated['date_end'] = $this->parseDate($request->input('date_end'), true);

        return $validated;
    }

    private function modelPayload(array $data): array
    {
        return [
            'title' => $data['title'],
            'type' => 'P',
            'discount' => collect($data['tiers'])->max('discount') ?: 0,
            'group' => Action::GROUP_BOGO,
            'links' => collect([Action::GROUP_BOGO])->toJson(),
            'date_start' => $data['date_start'],
            'date_end' => $data['date_end'],
            'data' => collect(['tiers' => $data['tiers']])->toJson(),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => $data['status'],
        ];
    }

    private function parseDate(?string $date, bool $endOfDay = false): ?Carbon
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        try {
            $carbon = Carbon::createFromFormat('d.m.Y', $date);
        } catch (\Throwable $e) {
            return null;
        }

        return $endOfDay ? $carbon->endOfDay() : $carbon->startOfDay();
    }

    private function ensureBogoAction(Action $action): void
    {
        abort_unless(Action::isBogoGroup((string) $action->group), 404);
    }
}
