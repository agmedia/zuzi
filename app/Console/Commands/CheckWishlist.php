<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Author;
use App\Models\Back\Marketing\Wishlist;
use Illuminate\Console\Command;

class CheckWishlist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:wishlist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check wishlist & send emails if product is available.';

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
        return Wishlist::check_CRON();
    }
}
