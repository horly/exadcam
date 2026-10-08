<?php

namespace App\Console\Commands;

use App\Models\FleetReportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PruneFleetReports extends Command
{
    protected $signature = 'reports:prune';

    protected $description = 'Delete expired private report results';

    public function handle(): int
    {
        $count = 0;
        FleetReportRun::where('expires_at', '<', now())->chunkById(100, function ($runs) use (&$count) {
            foreach ($runs as $run) {
                Storage::disk('local')->delete($run->resultPath());
                $run->delete();
                $count++;
            }
        });
        // User deletion cascades the row; remove its now-unreferenced private file as well.
        $disk = Storage::disk('local');
        foreach ($disk->files('reports') as $path) {
            $id = pathinfo($path, PATHINFO_FILENAME);
            if (Str::isUuid($id) && $disk->lastModified($path) < now()->subDay()->timestamp && ! FleetReportRun::whereKey($id)->exists()) {
                $disk->delete($path);
            }
        }
        $this->info('Expired reports removed: '.$count);

        return self::SUCCESS;
    }
}
