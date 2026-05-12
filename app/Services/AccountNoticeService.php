<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountNoticeService
{
    private const CODE = 'marketing';
    private const KEY = 'account_notice';

    public function get(): array
    {
        return $this->normalize($this->stored());
    }

    public function save(array $data): bool
    {
        $payload = $this->normalize($data);
        $value = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (! Schema::hasTable('settings')) {
            return false;
        }

        $setting = Settings::where('code', self::CODE)->where('key', self::KEY)->first();

        if ($setting && $setting->value === $value && (bool) $setting->json) {
            Helper::flushCache('settings', self::CODE . self::KEY);

            return true;
        }

        $stored = $setting
            ? Settings::edit($setting->id, self::CODE, self::KEY, $value, true)
            : Settings::insert(self::CODE, self::KEY, $value, true);

        Helper::flushCache('settings', self::CODE . self::KEY);

        return (bool) $stored;
    }

    public function defaults(): array
    {
        return [
            'active' => true,
            'title' => 'Hvala što ste kupovali kod nas!',
            'intro' => 'Kao mali znak zahvale za vaše povjerenje ove godine, pripremili smo vam poseban kupon za sljedeću kupnju.',
            'coupon_label' => 'Vaš kupon kod:',
            'coupon_code' => 'HVALA20',
            'discount_text' => 'ostvaruje 20% popusta',
            'outro' => 'Iskoristite kupon prilikom sljedeće narudžbe i razveselite se nečim novim iz Zuzi Shop ponude.',
            'button_text' => 'Iskoristi popust',
            'button_url' => route('index'),
            'valid_until' => '2026-06-01',
        ];
    }

    public function formattedValidUntil(array $notice): ?string
    {
        if (empty($notice['valid_until'])) {
            return null;
        }

        try {
            return Carbon::parse($notice['valid_until'])->format('d.m.Y.');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function stored(): array
    {
        $setting = Settings::get(self::CODE, self::KEY);

        if ($setting instanceof Collection) {
            return $setting->toArray();
        }

        if (is_array($setting)) {
            return $setting;
        }

        if (is_object($setting)) {
            return json_decode(json_encode($setting), true) ?: [];
        }

        if (is_string($setting)) {
            return json_decode($setting, true) ?: [];
        }

        return [];
    }

    private function normalize(array $data): array
    {
        $data = array_merge($this->defaults(), $data);

        $button_url = trim((string) $data['button_url']);

        if (! $this->isSafeUrl($button_url)) {
            $button_url = route('index');
        }

        return [
            'active' => filter_var($data['active'], FILTER_VALIDATE_BOOLEAN),
            'title' => trim((string) $data['title']),
            'intro' => trim((string) $data['intro']),
            'coupon_label' => trim((string) $data['coupon_label']),
            'coupon_code' => Str::upper(trim((string) $data['coupon_code'])),
            'discount_text' => trim((string) $data['discount_text']),
            'outro' => trim((string) $data['outro']),
            'button_text' => trim((string) $data['button_text']),
            'button_url' => $button_url,
            'valid_until' => $this->normalizeDate($data['valid_until'] ?? null),
        ];
    }

    private function normalizeDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (Str::startsWith($url, '//')) {
            return false;
        }

        return Str::startsWith($url, ['/', '#', 'http://', 'https://']);
    }
}
