# MapBiomas Testing Guide

## Focused PHP tests

```powershell
php artisan test tests/Unit/LandCoverClassificationTest.php
php artisan test tests/Feature/LandCoverClassificationPipelineTest.php
```

## Syntax checks

```powershell
php -l app\Services\MapBiomasSpatialMatcher.php
php -l app\Services\NasaFirmsSyncService.php
php -l app\Jobs\ClassifyHotspotLandCover.php
& .\.venv\Scripts\python.exe -m py_compile storage\app\ember\scripts\raster_sample_server.py
```

## Direct raster sample

```powershell
& .\.venv\Scripts\python.exe -c "import rasterio; p=r'storage/app/ember/source/ember_mapbiomas_collection4/ember_mapbiomas_collection4_2024_aoi_02.tif'; ds=rasterio.open(p); print(ds.read(1)[ds.index(104.74796,-4.74858)])"
```

## Coverage of tests

Tests cover inventory, virtual-environment executable resolution, crosswalk mapping, outside rasters, zero pixels, unmapped values, persistence, duplicate ingestion, dry-run, limits, queue dispatch, failures, and real representative pixels where production rasters are installed. Full production rasters are not required for unit/mock tests.
