<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Publisher;
use Illuminate\Console\Command;

class CleanPublishers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:publishers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check & set publishers status based on product availability.';

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
    public function handle()
    {
        return Publisher::checkStatuses_CRON();
    }
}
