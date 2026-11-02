# EMBER Operations Runbook

## Install

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
```

## Configure the raster executable

Set an absolute project virtual-environment executable:

```text
MAPBIOMAS_LAND_COVER_RASTER_DIRECTORY=storage/app/ember/source/ember_mapbiomas_collection4
MAPBIOMAS_PYTHON_EXECUTABLE=D:\capstone\EMBER-v2-main\.venv\Scripts\python.exe
QUEUE_CONNECTION=database
```

Verify:

```powershell
& .\.venv\Scripts\python.exe -c "import rasterio; print(rasterio.__version__)"
php artisan config:clear
```

## Run

```powershell
php artisan serve
php artisan queue:work database --queue=ember-classification --tries=3 --timeout=300
```

For safe real FIRMS connectivity:

```powershell
php artisan ember:sync-firms --source=VIIRS_SNPP_NRT --dry-run --limit=1
```

Subsequent live one-record execution requires explicit approval:

```powershell
php artisan ember:sync-firms --source=VIIRS_SNPP_NRT --limit=1
```

## Prohibited operations for this workflow

Do not run historical backfills, mass classification updates, database resets, or destructive migrations while verifying classification. Do not modify HSE, HBE, FSI, confidence, or Sugeno values.
