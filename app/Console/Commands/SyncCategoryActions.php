<?php

namespace App\Console\Commands;

use App\Models\Back\Marketing\Action;
use Illuminate\Console\Command;

class SyncCategoryActions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:category-actions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh scheduled category and combined category actions on products.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = Action::syncScheduledCategoryActions();

        $this->info('Refreshed category action candidates: ' . $count);

        return self::SUCCESS;
    }
}
