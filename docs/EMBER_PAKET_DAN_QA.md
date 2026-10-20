# EMBER Laravel HSE/HBE — Inventaris Paket dan Status QA

## Paket

- Laravel 13 application source; Livewire 4; Tailwind/Vite front-end source and existing compiled assets in `public/build/`.
- Configuration and service for model draft `EMBER-HSE-HBE-SUGENO-DRAFT-1.0.0`.
- Database migration adding `hotspot_evidence`, `historical_burn_evidence`, and `model_version` to `titik_lokasi`.
- Artisan importer: `php artisan ember:import-hotspots <path> [--truncate] [--chunk=1000]`.
- All 13 detailed MapBiomas Landy classes active, including all values in `config/ember.php`.
- Included source datasets under `storage/app/ember/source/`: 33,209 enriched hotspot records, 13-class evidence CSVs, area-by-province/class CSV, GAUL 2025 10-province GeoJSON, MapBiomas Fire workbook/summary CSVs, and three GeoTIFFs.
- Full methodological/technical documentation in Markdown, DOCX and PDF.

## Model statement

HSE and HBE are independent class-level inputs to the current draft Zero-Order Sugeno FIS. MODIS confidence weights HSE upstream and is not a third fuzzy input. All hotspots with the same land-cover class receive the same evidence values and FSI for a fixed model version. FSI is an experimental relative index, not a probability of fire or a claim of causal effect.

## Installation summary (Windows PowerShell)

1. Extract the ZIP; open PowerShell at project root.
2. `Copy-Item .env.example .env`; edit DB values and set `CMS_ADMIN_EMAIL` / `CMS_ADMIN_PASSWORD` before creating the admin. Do not use the example password on a public server.
3. Run:

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
npm run build
```

4. Import source records. Back up the database first. `--truncate` deletes old `titik_lokasi` rows and replaces them; without it the command appends rows and does not deduplicate automatically.

```powershell
php artisan ember:import-hotspots "storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv" --truncate
php artisan serve
```

5. Open the local URL printed by Artisan. CMS login is `/cms/login`.

## QA completed in packaging environment

- `php -l` ran on 56 PHP files in `app`, `config`, migrations, routes, and tests without syntax errors.
- `node --check resources/js/app.js` passed.
- `node --check public/build/assets/app-ClEbLBar.js` passed.
- `php artisan route:list --except-vendor` loaded and listed 54 routes.
- `php artisan ember:import-hotspots --help` passed.
- Standalone service smoke test passed: 13 classes available, FSI stays between 0 and 100, fuzzy output monotonic on a grid of tested HSE/HBE input values.
- CSV structure check: 33,209 rows; expected class identifiers; confidence numeric and required source columns present.

## QA not completed in packaging environment

- `php artisan test` could not start because this container's PHP lacks `dom`, `mbstring`, and `xmlwriter` extensions required by PHPUnit.
- Database migration and real import were not executed because no PDO database driver is enabled in this container. Run `php artisan migrate:status`, migrate, import, and PHPUnit locally with a configured database and complete PHP extensions.
- A clean Vite build could not run in this container because the installed `node_modules` entry point permissions and optional Rolldown native binding were broken. `public/build/` is included as a starting point; after a clean `npm install` on the target machine, run `npm run build`.

## Research limitations retained

1. HBE pra-2024 masih provisional karena direkonstruksi dari raster frekuensi 2000–2024 dan mask raster tahun 2024. Konfirmasi dengan annual burned-area rasters 2000–2023 sebelum publikasi.
2. Hotspot historis saat ini diperkaya dengan kelas referensi MapBiomas 2024. Join ulang ke tutupan lahan tahun kejadian dibutuhkan untuk klaim temporal yang ketat.
3. Crosswalk versi/legenda MapBiomas Landy dan MapBiomas Fire harus didokumentasikan.
4. Korelasi tingkat 13 kelas bukan validasi spasial; FSI tetap indikator eksperimen.
5. Semua 13 kelas tetap aktif. Label QA/konteks tidak mengecualikan kelas dari model.
