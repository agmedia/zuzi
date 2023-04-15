<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Author;
use Illuminate\Console\Command;

class CleanAuthors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:authors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check & set authors status based on product availability.';

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
        return Author::checkStatuses_CRON();
    }
}
