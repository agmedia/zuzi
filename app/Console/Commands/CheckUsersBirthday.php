<?php

namespace App\Console\Commands;

use App\Models\Front\Loyalty;
use App\Models\UserDetail;
use Illuminate\Console\Command;

class CheckUsersBirthday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:birthdays';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check users birthdays for loyalty points to be added.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $users = UserDetail::query()
            ->whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day)
            ->get();

        foreach ($users as $user) {
            $received_birthday_points = Loyalty::query()
                ->where('user_id', $user->user_id)
                ->where('reference', 'birthday')
                ->where('target', (string) now()->year);

            if ( ! $received_birthday_points->exists()) {
                $birthday_points = intval(data_get((array) config('settings.loyalty', []), 'birthday_points', 100));

                if ($birthday_points > 0) {
                    Loyalty::addPoints($birthday_points, 0, 'birthday', '', $user->user_id);
                }
            }
        }

        return self::SUCCESS;
    }
}
