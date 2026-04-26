<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Services\Front\CuratedCollectionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class QuickMenuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function cache(CuratedCollectionService $curatedCollectionService)
    {
        $failedTasks = [];

        foreach ([
            'cache:clear' => 'application cache',
            'config:clear' => 'config cache',
            'view:clear' => 'compiled views',
            'route:clear' => 'route cache',
        ] as $command => $label) {
            try {
                $exitCode = Artisan::call($command);

                if ($exitCode !== 0) {
                    $failedTasks[] = $label;

                    Log::warning('Quick cache clean command returned a non-zero exit code.', [
                        'command' => $command,
                        'label' => $label,
                        'exit_code' => $exitCode,
                        'output' => trim(Artisan::output()),
                    ]);
                }
            } catch (\Throwable $e) {
                $failedTasks[] = $label;

                Log::warning('Quick cache clean command failed.', [
                    'command' => $command,
                    'label' => $label,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $curatedCollectionService->clearHomepageWidgetState();
        } catch (\Throwable $e) {
            $failedTasks[] = 'homepage widget cache';

            Log::warning('Quick cache clean failed while clearing curated homepage widget state.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        if ($failedTasks === []) {
            return redirect()->back()->with('success', 'Cache je uspješno očišćen.');
        }

        $failedList = collect($failedTasks)->unique()->implode(', ');

        return redirect()->back()->with(
            'error',
            'Djelomično očišćeno. Nije uspjelo: ' . $failedList . '. Redis vjerojatno trenutno odbija write operacije (MISCONF).'
        );
    }
    
    
    /**
     * Maintenance Mode ON.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function maintenanceModeON()
    {
        Artisan::call('down');
        
        return redirect()->back()->with('success', 'Application is now in maintenance mode.');
    }
    
    
    /**
     * Maintenance Mode OFF.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function maintenanceModeOFF()
    {
        Artisan::call('up');
        
        return redirect()->back()->with('success', 'Application is now live.');
    }
    
}
