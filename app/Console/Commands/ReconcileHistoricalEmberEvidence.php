<?php

namespace App\Console\Commands;

use App\Services\FireSusceptibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcileHistoricalEmberEvidence extends Command
{
    protected $signature = 'ember:reconcile-historical-fsi
        {--dry-run : Preview reconciliation without database writes (default)}
        {--apply : Apply approved evidence and FSI fields}
        {--limit=0 : Maximum eligible records to preview or reconcile}
        {--batch=1000 : Records per batch}';

    protected $description = 'Dry-run or apply fixed HSE/HBE and current Sugeno FSI reconciliation to classified historical hotspots.';

    public function handle(FireSusceptibilityService $susceptibility): int
    {
        $approvedClassIds = array_values(array_intersect(
            array_keys(FireSusceptibilityService::LAND_COVER_NAMES),
            array_keys(config('ember.class_evidence', []))
        ));

        if (count($approvedClassIds) !== 13) {
            $this->error('Reconciliation requires all 13 approved EMBER land-cover classes.');
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $batchSize = max(1, (int) $this->option('batch'));
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;

        $query = DB::table('titik_lokasi')
            ->whereIn('land_cover_id', $approvedClassIds)
            ->where(function ($query) {
                $query->whereNull('classification_status')
                    ->orWhere('classification_status', '!=', 'unclassified');
            })
            ->whereNotNull('land_cover_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->orderBy('id')->get([
            'id',
            'land_cover_id',
            'hotspot_evidence',
            'historical_burn_evidence',
            'fsi_score',
            'fsi_class',
            'context_flag',
            'model_version',
        ]);

        $summary = [
            'eligible' => $records->count(),
            'missing_hse_hbe' => 0,
            'legacy_fsi' => 0,
            'fsi_changes' => 0,
            'fsi_class_changes' => 0,
            'metadata_changes' => 0,
            'by_class' => collect($approvedClassIds)->mapWithKeys(fn (int $id) => [$id => 0])->all(),
            'final_consistent' => 0,
            'final_inconsistent' => 0,
        ];

        $plans = [];
        $modelVersion = (string) config('ember.model_version', FireSusceptibilityService::MODEL_VERSION);

        foreach ($records as $record) {
            $classId = (int) $record->land_cover_id;
            $evidence = $susceptibility->classEvidence($classId);
            $summary['by_class'][$classId]++;

            if (! is_array($evidence)) {
                continue;
            }

            $hse = (float) $evidence['hotspot_evidence'];
            $hbe = (float) $evidence['historical_burn_evidence'];
            $fsi = $susceptibility->fsi($hse, $hbe);
            $fsiClass = $susceptibility->fsiClass($fsi);
            $contextFlag = $susceptibility->contextFlag($hse, $hbe);

            $missingHseHbe = ! is_numeric($record->hotspot_evidence)
                || ! is_numeric($record->historical_burn_evidence);
            $legacyFsi = is_numeric($record->fsi_score);
            $fsiChanged = ! is_numeric($record->fsi_score)
                || abs((float) $record->fsi_score - (float) $fsi) > 0.005;
            $fsiClassChanged = (string) ($record->fsi_class ?? '') !== (string) $fsiClass;
            $metadataChanged = ! is_numeric($record->hotspot_evidence)
                || ! is_numeric($record->historical_burn_evidence)
                || (string) ($record->model_version ?? '') !== $modelVersion
                || (string) ($record->context_flag ?? '') !== (string) $contextFlag
                || abs((float) ($record->hotspot_evidence ?? 0) - $hse) > 0.0005
                || abs((float) ($record->historical_burn_evidence ?? 0) - $hbe) > 0.0005;

            $summary['missing_hse_hbe'] += $missingHseHbe ? 1 : 0;
            $summary['legacy_fsi'] += $legacyFsi ? 1 : 0;
            $summary['fsi_changes'] += $fsiChanged ? 1 : 0;
            $summary['fsi_class_changes'] += $fsiClassChanged ? 1 : 0;
            $summary['metadata_changes'] += $metadataChanged ? 1 : 0;

            $finalIsConsistent = ! $missingHseHbe
                && abs((float) ($record->hotspot_evidence ?? 0) - $hse) <= 0.0005
                && abs((float) ($record->historical_burn_evidence ?? 0) - $hbe) <= 0.0005
                && is_numeric($record->fsi_score)
                && abs((float) $record->fsi_score - (float) $fsi) <= 0.005
                && (string) ($record->fsi_class ?? '') === (string) $fsiClass
                && (string) ($record->context_flag ?? '') === (string) $contextFlag
                && (string) ($record->model_version ?? '') === $modelVersion;

            if ($finalIsConsistent) {
                $summary['final_consistent']++;
            } else {
                $summary['final_inconsistent']++;
            }

            $plans[] = [
                'id' => (int) $record->id,
                'values' => [
                    'hotspot_evidence' => $hse,
                    'historical_burn_evidence' => $hbe,
                    'fsi_score' => $fsi,
                    'fsi_class' => $fsiClass,
                    'context_flag' => $contextFlag,
                    'model_version' => $modelVersion,
                ],
            ];
        }

        $this->info('EMBER historical FSI reconciliation');
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY-RUN'));
        $this->line('Limit: '.($limit > 0 ? (string) $limit : 'none'));
        $this->line('Batch size: '.$batchSize);
        $this->line('Total eligible records: '.$summary['eligible']);
        $this->line('Records missing HSE/HBE: '.$summary['missing_hse_hbe']);
        $this->line('Records with legacy FSI: '.$summary['legacy_fsi']);
        $this->line('FSI values that would change: '.$summary['fsi_changes']);
        $this->line('FSI classes that would change: '.$summary['fsi_class_changes']);
        $this->line('Records with metadata changes: '.$summary['metadata_changes']);
        $this->line('Expected final consistent: '.$summary['eligible']);
        $this->line('Expected final inconsistent: 0');

        $this->table(
            ['Class ID', 'Name', 'Eligible records'],
            collect($summary['by_class'])->map(function (int $count, int|string $classId) {
                $name = FireSusceptibilityService::LAND_COVER_NAMES[(int) $classId] ?? 'Unknown';
                return [(int) $classId, $name, $count];
            })->values()->all()
        );

        if ($dryRun) {
            $this->info('Dry run complete. No database records were written.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($plans, $batchSize, $approvedClassIds) {
                foreach (array_chunk($plans, $batchSize) as $batch) {
                    foreach ($batch as $plan) {
                        DB::table('titik_lokasi')
                            ->where('id', $plan['id'])
                            ->whereIn('land_cover_id', $approvedClassIds)
                            ->where(function ($query) {
                                $query->whereNull('classification_status')
                                    ->orWhere('classification_status', '!=', 'unclassified');
                            })
                            ->update($plan['values']);
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->error('Apply failed and the transaction was rolled back: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Applied reconciliation to '.$summary['eligible'].' records.');
        return self::SUCCESS;
    }
}
