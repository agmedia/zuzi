<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $group_list = [
            0 => [
                'id' => 'product',
                'title' => 'Artikl'
            ],
            1 => [
                'id' => 'category',
                'title' => 'Kategorija'
            ],
            2 => [
                'id' => 'publisher',
                'title' => 'Nakladnik'
            ],
            3 => [
                'id' => 'author',
                'title' => 'Autor'
            ]
        ];

        $type_list = [
            0 => [
                'id' => 'P',
                'title' => 'Postotak'
            ],
            1 => [
                'id' => 'F',
                'title' => 'Fiksni'
            ]
        ];

        Log::info(collect($group_list)->toJson());

        //
        DB::insert(
            "INSERT INTO `settings` (`user_id`, `code`, `key`, `value`, `json`, `created_at`, `updated_at`) VALUES
              (null, 'product', 'letter_styles', '" . '["Latinica", "Glagoljica"]' . "', 1, NOW(), NOW()),
              (null, 'product', 'condition_styles', '" . '["Odlično", "Dobro"]' . "', 1, NOW(), NOW()),
              (null, 'product', 'binding_styles', '" . '["Tvrdi", "Meki"]' . "', 1, NOW(), NOW()),
              (null, 'action', 'group_list', '" . collect($group_list)->toJson() . "', 1, NOW(), NOW()),
              (null, 'action', 'type_list', '" . collect($type_list)->toJson() . "', 1, NOW(), NOW())"
        );
    }
}
