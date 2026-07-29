<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ContractWithdrawalSettingsService
{
    private const CODE = 'store';
    private const KEY = 'contract_withdrawal';

    public function get(): array
    {
        return $this->normalize($this->stored());
    }

    public function save(array $data): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $payload = $this->normalize($data);
        $value = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $setting = Settings::query()
            ->where('code', self::CODE)
            ->where('key', self::KEY)
            ->first();

        $saved = $setting
            ? Settings::edit($setting->id, self::CODE, self::KEY, $value, true)
            : Settings::insert(self::CODE, self::KEY, $value, true);

        Helper::flushCache('settings', self::CODE.self::KEY);

        return (bool) $saved;
    }

    public function defaults(): array
    {
        return [
            'admin_email' => (string) config('mail.admin', 'info@zuzi.hr'),
            'return_address' => 'Antuna Šoljana 33, 10000 Zagreb',
            'return_cost_policy' => 'consumer',
            'instructions' => 'Robu sigurno zapakirajte i pošaljite bez nepotrebnog odgađanja, a najkasnije u roku od 14 dana od slanja izjave o raskidu. U paket priložite broj narudžbe ili referencu zahtjeva.',
        ];
    }

    public function returnCostText(?array $settings = null): string
    {
        $settings = $settings ?: $this->get();

        return ($settings['return_cost_policy'] ?? 'consumer') === 'merchant'
            ? 'Izravne troškove povrata robe snosi Zuzi Shop.'
            : 'Izravne troškove povrata robe snosite sami.';
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
        $returnCostPolicy = ($data['return_cost_policy'] ?? '') === 'merchant'
            ? 'merchant'
            : 'consumer';

        return [
            'admin_email' => strtolower(trim((string) ($data['admin_email'] ?? ''))),
            'return_address' => trim((string) ($data['return_address'] ?? '')),
            'return_cost_policy' => $returnCostPolicy,
            'instructions' => trim((string) ($data['instructions'] ?? '')),
        ];
    }
}
