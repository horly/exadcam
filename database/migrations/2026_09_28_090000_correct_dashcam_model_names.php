<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rename([
            'JK114' => 'ESTON ES500-603 JK114',
            'ES500-603' => '4G SmartVision JT808/1078',
        ]);
    }

    public function down(): void
    {
        $this->rename([
            'ESTON ES500-603 JK114' => 'JK114',
            '4G SmartVision JT808/1078' => 'ES500-603',
        ]);
    }

    private function rename(array $names): void
    {
        DB::transaction(function () use ($names): void {
            foreach ($names as $previous => $current) {
                // Only the original default names change; custom names survive.
                // Query-builder updates deliberately avoid reconnection hooks,
                // timestamp changes and any rewrite of communication settings.
                DB::table('dashcams')->whereIn('model', [$previous, $current])
                    ->where('name', $previous)->update(['name' => $current]);
                DB::table('dashcams')->where('model', $previous)->update(['model' => $current]);
            }
        });
    }
};
