<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:prune-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired rows from the database-backed sessions table.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $driver = (string) config('session.driver');

        if ($driver !== 'database') {
            $this->info(sprintf('Skipping expired session pruning because the session driver is [%s].', $driver));

            return self::SUCCESS;
        }

        $connection = config('session.connection') ?: config('database.default');
        $table = (string) config('session.table', 'sessions');
        $lifetime = (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetime)->getTimestamp();

        try {
            $deleted = DB::connection($connection)->table($table)
                ->where('last_activity', '<', $cutoff)
                ->delete();
        } catch (Throwable $exception) {
            $this->error('Failed to prune expired sessions: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Pruned %d expired sessions from [%s] using connection [%s].', $deleted, $table, $connection));

        return self::SUCCESS;
    }
}
