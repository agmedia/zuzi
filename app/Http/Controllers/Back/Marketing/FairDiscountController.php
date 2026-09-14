<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Action;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FairDiscountController extends Controller
{
    public function index()
    {
        $actions = Action::query()
            ->where('group', Action::GROUP_FAIR_DISCOUNT)
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate(config('settings.pagination.back'));

        return view('back.marketing.fair-discount.index', compact('actions'));
    }

    public function create()
    {
        $year = now()->year;
        $tiers = [
            ['min_total' => 0, 'max_total' => 50, 'discount' => 10],
            ['min_total' => 50.01, 'max_total' => 99.99, 'discount' => 15],
            ['min_total' => 100, 'max_total' => null, 'discount' => 20],
        ];
        $defaults = [
            'title' => 'Sajamski popust ' . $year,
            'date_start' => Carbon::create($year, 9, 23)->format('d.m.Y'),
            'date_end' => Carbon::create($year, 9, 28)->format('d.m.Y'),
            'free_boxnow' => true,
        ];

        return view('back.marketing.fair-discount.edit', compact('tiers', 'defaults'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $action = Action::query()->create($this->modelPayload($data));

        return redirect()
            ->route('marketing.fair-discounts.edit', ['fairDiscount' => $action])
            ->with(['success' => 'Sajamska akcija je uspješno spremljena!']);
    }

    public function edit(Action $fairDiscount)
    {
        $this->ensureFairDiscountAction($fairDiscount);

        $actionData = is_array($fairDiscount->data) ? $fairDiscount->data : [];
        $tiers = Action::normalizeFairDiscountTiers($actionData);

        return view('back.marketing.fair-discount.edit', compact('fairDiscount', 'tiers'));
    }

    public function update(Request $request, Action $fairDiscount)
    {
        $this->ensureFairDiscountAction($fairDiscount);

        $data = $this->validatedData($request);
        $fairDiscount->update($this->modelPayload($data));

        return redirect()
            ->route('marketing.fair-discounts.edit', ['fairDiscount' => $fairDiscount])
            ->with(['success' => 'Sajamska akcija je uspješno spremljena!']);
    }

    public function destroy(Action $fairDiscount)
    {
        $this->ensureFairDiscountAction($fairDiscount);
        $fairDiscount->delete();

        return redirect()
            ->route('marketing.fair-discounts')
            ->with(['success' => 'Sajamska akcija je uspješno izbrisana!']);
    }

    private function validatedData(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'date_start' => ['nullable', 'date_format:d.m.Y'],
            'date_end' => ['nullable', 'date_format:d.m.Y'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min_total' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'tiers.*.max_total' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'tiers.*.discount' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $start = $this->parseDate($request->input('date_start'));
            $end = $this->parseDate($request->input('date_end'), true);

            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('date_end', 'Datum završetka mora biti nakon datuma početka.');
            }

            foreach ((array) $request->input('tiers', []) as $index => $tier) {
                $min = $this->decimalValue(data_get($tier, 'min_total'));
                $max = $this->nullableDecimalValue(data_get($tier, 'max_total'));

                if ($max !== null && $max < $min) {
                    $validator->errors()->add(
                        'tiers.' . $index . '.max_total',
                        'Završni iznos mora biti jednak ili veći od početnog iznosa.'
                    );
                }
            }
        });

        $validated = $validator->validate();
        $tiers = Action::normalizeFairDiscountTiers(['tiers' => $request->input('tiers', [])]);

        if (empty($tiers)) {
            throw ValidationException::withMessages([
                'tiers' => 'Dodajte barem jedan prag sajamskog popusta.',
            ]);
        }

        $validated['tiers'] = $tiers;
        $validated['status'] = $request->input('status') === 'on' ? 1 : 0;
        $validated['free_boxnow'] = $request->input('free_boxnow') === 'on';
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
            'group' => Action::GROUP_FAIR_DISCOUNT,
            'links' => collect([Action::GROUP_FAIR_DISCOUNT])->toJson(),
            'date_start' => $data['date_start'],
            'date_end' => $data['date_end'],
            'data' => collect([
                'tiers' => $data['tiers'],
                'free_boxnow' => $data['free_boxnow'],
            ])->toJson(),
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

    private function decimalValue($value): float
    {
        return (float) str_replace(',', '.', (string) $value);
    }

    private function nullableDecimalValue($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->decimalValue($value);
    }

    private function ensureFairDiscountAction(Action $action): void
    {
        abort_unless(Action::isFairDiscountGroup((string) $action->group), 404);
    }
}
