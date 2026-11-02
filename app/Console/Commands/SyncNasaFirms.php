<?php

namespace App\Console\Commands;

use App\Services\NasaFirmsSyncService;
use Illuminate\Console\Command;

class SyncNasaFirms extends Command
{
    protected $signature = 'ember:sync-firms
        {--days=2 : Number of NASA FIRMS day-range days to request (1-10)}
        {--source=* : Optional product source; repeat to select multiple products}
        {--limit= : Maximum number of valid in-AOI records to process}
        {--dry-run : Fetch and parse real data without database writes or job dispatch}';

    protected $description = 'Synchronize NASA FIRMS NRT hotspot data into the EMBER hotspot database.';

    public function handle(NasaFirmsSyncService $syncService): int
    {
        $days = max(1, min(10, (int) $this->option('days')));
        $limitOption = $this->option('limit');
        $limit = $limitOption === null || $limitOption === '' ? null : max(0, (int) $limitOption);
        $dryRun = (bool) $this->option('dry-run');
        $sources = $this->option('source');
        if (! is_array($sources) || $sources === []) {
            $sources = config('ember.firms.sources', []);
        }

        if ($limit !== null && $limit === 0) {
            $this->error('Opsi --limit harus bernilai lebih besar dari nol.');
            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Dry run NASA FIRMS untuk rentang '.$days.' hari (tanpa penulisan database)...'
            : 'Memulai sinkronisasi NASA FIRMS untuk rentang '.$days.' hari...');

        try {
            $summary = $syncService->sync(
                array_values(array_unique(array_map('strval', $sources))),
                $days,
                $dryRun,
                $limit
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->line('Respons diterima: '.$summary['received']);
        $this->line('Di dalam AOI: '.$summary['inside_aoi']);
        $this->line('Diproses: '.$summary['processed']);
        $this->line('Dimasukkan: '.$summary['inserted']);
        $this->line('Diperbarui: '.$summary['updated']);
        $this->line('Sumber gagal: '.$summary['failed']);
        if ($dryRun && $summary['first_record']) {
            $record = $summary['first_record'];
            $this->line('Catatan valid pertama:');
            $this->line('  latitude='.$record['latitude']);
            $this->line('  longitude='.$record['longitude']);
            $this->line('  acquisition='.($record['acq_date'] ?? '').' '.($record['acq_time'] ?? ''));
            $this->line('  confidence='.($record['confidence'] ?? ''));
            $this->line('  source='.$record['source']);
        }

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
