<?php

namespace App\Console\Commands;

use App\Services\AdministrativeProvinceMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AttributeNrtProvinces extends Command
{
    protected $signature = 'ember:attribute-nrt-provinces
        {--dry-run : Preview spatial attribution without updating database rows}';

    protected $description = 'Attribute missing provinces to existing NRT hotspots using the EMBER province boundary GeoJSON.';

    public function handle(AdministrativeProvinceMatcher $matcher): int
    {
        $rows = DB::table('titik_lokasi')
            ->where('is_nrt', true)
            ->where(function ($query) {
                $query->whereNull('provinsi')->orWhere('provinsi', '');
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'latitude', 'longitude']);

        $success = [];
        $failed = [];

        foreach ($rows as $row) {
            $province = $matcher->match((float) $row->latitude, (float) $row->longitude);
            if ($province === null) {
                $failed[] = (int) $row->id;
                continue;
            }

            $success[(string) $province] = ($success[(string) $province] ?? 0) + 1;
            if (! $this->option('dry-run')) {
                DB::table('titik_lokasi')
                    ->where('id', $row->id)
                    ->where('is_nrt', true)
                    ->update([
                        'provinsi' => $province,
                        'updated_at' => now(),
                    ]);
            }
        }

        $successfulCount = array_sum($success);
        $this->line('NRT berhasil mendapatkan provinsi: '.$successfulCount);
        $this->line('NRT gagal mendapatkan provinsi: '.count($failed));

        foreach ($success as $province => $count) {
            $this->line('- '.$province.': '.$count);
        }

        return count($failed) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
