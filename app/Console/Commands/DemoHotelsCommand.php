<?php

namespace App\Console\Commands;

use App\Services\DemoHotelService;
use Illuminate\Console\Command;

class DemoHotelsCommand extends Command
{
    protected $signature = 'lg:demo-hotels
        {--remove : Delete every demo hotel and its rates}';

    protected $description = 'Add fictional, clearly flagged demo hotels with rates in every district and near every place (for demonstrations)';

    public function handle(DemoHotelService $demo): int
    {
        if ($this->option('remove')) {
            $deleted = $demo->remove();
            $this->components->info("Removed {$deleted} demo hotels and their room rates.");

            return self::SUCCESS;
        }

        $created = $demo->create();
        $this->components->info("Demo hotels ready: {$created['hotels']} new hotels and {$created['rates']} room rates in {$created['towns']} towns.");
        $this->line('  They are published, marked "Demo listing", and removed with: php artisan lg:demo-hotels --remove');

        return self::SUCCESS;
    }
}
