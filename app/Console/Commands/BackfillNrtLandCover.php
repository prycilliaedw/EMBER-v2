<?php

namespace App\Console\Commands;

use App\Services\MapBiomasSpatialMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillNrtLandCover extends Command
{
    protected $signature = 'ember:backfill-nrt-land-cover
        {--execute : Execute the idempotent NRT-only update; preview is the default}
        {--csv=storage/app/ember/output/nrt_621_collection4_final_verified.csv : Validated sampling CSV}';

    protected $description = 'Preview or apply the validated Collection 4 backfill to NRT hotspots only.';

    public function handle(MapBiomasSpatialMatcher $matcher): int
    {
        $path = base_path($this->option('csv'));
        if (! is_file($path)) {
            $this->error('Validated CSV not found: '.$path);
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('Unable to open validated CSV.');
            return self::FAILURE;
        }

        $header = fgetcsv($handle, escape: '');
        $header = $header === false ? [] : array_map(function ($value) {
            $value = str_replace("\xEF\xBB\xBF", '', (string) $value);
            $value = trim((string) $value, " \t\n\r\0\x0B\"'");
            return strtolower($value);
        }, $header);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === []) {
                continue;
            }
            if (count($values) < count($header)) {
                continue;
            }
            $values = array_slice($values, 0, count($header));
            $row = array_combine($header, $values);
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['hotspot_id']) ? (int) $row['hotspot_id'] : 0;
            if ($id > 0) {
                $rows[$id] = $row;
            }
        }
        fclose($handle);

        $plannedUpdate = 0;
        $skipped = 0;
        $unresolved = 0;
        $historicalProtected = 0;
        $alreadyCurrent = 0;

        foreach ($rows as $id => $row) {
            $hotspot = DB::table('titik_lokasi')->where('id', $id)->first();
            if (! $hotspot) {
                $skipped++;
                continue;
            }
            if ((bool) $hotspot->is_nrt !== true) {
                $historicalProtected++;
                continue;
            }

            $rawClassId = isset($row['raw_class_id']) ? (int) $row['raw_class_id'] : null;
            $emberClassId = $rawClassId === null ? null : $matcher->mapRawClassId($rawClassId);
            if ($emberClassId === null) {
                $unresolved++;
                if ($this->option('execute')) {
                    DB::table('titik_lokasi')->where('id', $id)->where('is_nrt', true)->update([
                        'raw_mapbiomas_class_id' => $rawClassId,
                        'classification_status' => 'pending_review',
                        'classification_error' => 'Raw Collection 4 class has no verified EMBER mapping',
                        'classified_at' => null,
                        'updated_at' => now(),
                    ]);
                }
                continue;
            }

            $class = config("ember.class_evidence.{$emberClassId}");
            if (! is_array($class) || ! isset($class['hotspot_evidence'], $class['historical_burn_evidence'])) {
                $unresolved++;
                continue;
            }

            $name = (string) ($class['name'] ?? '');
            $hse = (float) $class['hotspot_evidence'];
            $hbe = (float) $class['historical_burn_evidence'];
            $same = (int) ($hotspot->land_cover_id ?? 0) === $emberClassId
                && (int) ($hotspot->raw_mapbiomas_class_id ?? 0) === $rawClassId
                && $hotspot->classification_status === 'classified';

            if ($same) {
                $alreadyCurrent++;
                continue;
            }
            $plannedUpdate++;

            if ($this->option('execute')) {
                $susceptibility = app(\App\Services\FireSusceptibilityService::class);
                $fsi = $susceptibility->fsi($hse, $hbe);
                DB::table('titik_lokasi')->where('id', $id)->where('is_nrt', true)->update([
                    'raw_mapbiomas_class_id' => $rawClassId,
                    'land_cover_id' => $emberClassId,
                    'land_cover' => $name,
                    'hotspot_evidence' => $hse,
                    'historical_burn_evidence' => $hbe,
                    'fsi_score' => $fsi,
                    'fsi_class' => $susceptibility->fsiClass($fsi),
                    'context_flag' => $susceptibility->contextFlag($hse, $hbe),
                    'model_version' => \App\Services\FireSusceptibilityService::MODEL_VERSION,
                    'classification_status' => 'classified',
                    'classification_error' => null,
                    'classification_attempts' => 0,
                    'classified_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info('Backfill mode: '.($this->option('execute') ? 'EXECUTE' : 'PREVIEW'));
        $this->line('CSV rows: '.count($rows));
        $this->line('Records to update: '.$plannedUpdate);
        $this->line('Already current/skipped: '.$alreadyCurrent);
        $this->line('Missing hotspot IDs: '.$skipped);
        $this->line('Unresolved raw IDs: '.$unresolved);
        $this->line('Historical records protected: '.$historicalProtected);
        return self::SUCCESS;
    }
}
