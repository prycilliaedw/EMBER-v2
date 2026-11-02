# NASA FIRMS Classification Workflow

## Normal synchronization

Run synchronization with the normal queue environment:

```powershell
php artisan ember:sync-firms --source=VIIRS_SNPP_NRT
php artisan queue:work database --queue=ember-classification --tries=3 --timeout=300
```

`--limit=N` limits records before persistence and dispatch. `--dry-run` performs fetch/parse/AOI/duplicate analysis without writes or job dispatch.

## Queue worker

`.env` uses `QUEUE_CONNECTION=database`. Classification jobs are queued on `ember-classification` and retryable. Duplicate insertion is guarded by the service and job uniqueness.

## Job failure behavior

Failures set a terminal-safe status such as `raster_unavailable`, preserve NASA FIRMS fields, and log the exception. The original hotspot is never deleted. Existing terminal classified records are not normally re-enqueued.

## Safe live verification

Dry-run first:

```powershell
php artisan ember:sync-firms --source=VIIRS_SNPP_NRT --dry-run --limit=1
```

One-record live synchronization must be explicitly approved before executing. Do not use this workflow for historical backfill.
