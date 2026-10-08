<?php

namespace App\Jobs;

use App\Models\FleetReportRun;
use App\Models\User;
use App\Services\FleetReportBuilder;
use App\Services\FleetReportScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GenerateFleetReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 75;

    public int $tries = 1;

    public function __construct(public string $runId) {}

    public function handle(FleetReportScope $scope, FleetReportBuilder $builder): void
    {
        $run = FleetReportRun::find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }
        if (! FleetReportRun::whereKey($run->id)->where('status', 'queued')->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }
        $run->refresh();
        try {
            $user = User::findOrFail($run->user_id);
            $vehicles = $scope->checkRun($user, $run);
            $result = $builder->build($vehicles, $run->filters, $run->created_at->copy());
            $scope->checkRun($user->fresh(), $run->fresh());
            $json = json_encode($result, JSON_THROW_ON_ERROR);
            if (strlen($json) > 24 * 1024 * 1024) {
                throw new \RuntimeException('too_large');
            }
            if (! Storage::disk('local')->put($run->resultPath(), $json)) {
                throw new \RuntimeException('write_failed');
            }
            $run->update(['status' => 'ready']);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($run->resultPath());
            $reason = $error->getMessage() === 'too_large' ? 'too_large' : ($error instanceof HttpException ? 'scope_changed' : 'failed');
            $run->update(['status' => 'failed', 'error_code' => $reason]);
            if ($reason === 'failed') {
                report($error);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($run = FleetReportRun::find($this->runId)) {
            Storage::disk('local')->delete($run->resultPath());
            $run->update(['status' => 'failed', 'error_code' => 'failed']);
        }
    }
}
