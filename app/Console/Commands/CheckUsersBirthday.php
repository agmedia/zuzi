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
        $users = UserDetail::query()->whereDate('birthday', now()->isToday())->get();

        foreach ($users as $user) {
            $received_birthday_points = Loyalty::query()->where('user_id', $user->id)
                                                        ->where('reference', 'birthday')
                                                        ->where('target', now()->year)
                                                        ->first();

            if ( ! $received_birthday_points->exists()) {
                Loyalty::addPoints(config('settings.loyalty.birthday_points'), 0, 'birthday', '', $user->id);
            }
        }

        return 1;
    }
}
