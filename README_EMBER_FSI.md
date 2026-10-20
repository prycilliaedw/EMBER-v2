# EMBER Laravel - HSE + HBE + Zero-Order Sugeno (Draft)

## Model purpose
EMBER links each NASA FIRMS/MODIS hotspot to one of 13 detailed MapBiomas Landy classes, then reports two **class-level** evidence values:

- **Hotspot Evidence (HSE):** confidence-weighted hotspot evidence aggregated by class for 2021-2023 and normalized to 0-100.
- **Historical Burn Evidence (HBE):** evidence of historical burned footprint and frequency prior to 2024, normalized to 0-100. Current scores are provisional because pre-2024 evidence was reconstructed using the 2000-2024 frequency raster and the 2024 annual mask; use annual maps to independently verify before publication.

The two values enter a zero-order Sugeno FIS. FSI is a relative class-context index, **not** the probability that a particular hotspot will become a fire, and not a fire-causality claim. All 13 classes remain active. Class type is metadata/interpretation only, never an exclusion filter.

## 13 active Landy classes

| ID | Class |
|---:|---|
| 3 | Formasi Hutan |
| 5 | Mangrove |
| 9 | Kebun Kayu |
| 13 | Tumbuhan Non-Hutan Lainnya |
| 21 | Pertanian Lainnya |
| 24 | Pemukiman |
| 25 | Non-Vegetasi Lainnya |
| 30 | Lubang Tambang |
| 31 | Tambak |
| 33 | Sungai, Danau, Laut |
| 35 | Sawit |
| 40 | Sawah |
| 76 | Hutan Rawa Gambut |

Scores and quantile anchors currently reside in `config/ember.php`, with the source table packaged at `storage/app/ember/source/EMBER_Final_Evidence_13Classes_All13.csv`.

## Formula

For a land-cover class c:

`HSE_c = min-max normalized confidence-weighted hotspot evidence for 2021-2023 (0-100)`

`HBE_c = 0.5 * FootprintScore_c + 0.5 * FrequencyScore_c`

`FootprintScore` transforms the pre-2024 ever-burned class proportion `r_c = ever_burned_area / class_area` using `ln(1 + r_c)` then min-max normalizes over all 13 classes. `FrequencyScore` applies `ln(1 + mean frequency on burned footprint)` then min-max normalizes over the same 13 classes. Current pre-2024 burned footprint is reconstructed and remains experimental.

Membership Q1/Q2/Q3 anchors, calculated over 13 class-level scores (linear quantiles), are:
- HSE: 22.356, 54.712, 68.291.
- HBE: 8.691, 24.794, 55.473.

Membership functions: Very Low shoulder; Low triangle `(0,Q1,Q2)`; Moderate triangle `(Q1,Q2,Q3)`; High right shoulder from `(Q2,Q3)`. For each rule, firing strength is `w = mu_HSE * mu_HBE`. Consequents are 10, 30, 50, 70, 90 for Very Low, Low, Moderate, High, Very High. Defuzzification is `FSI = sum(w*z)/sum(w)`.

The 4x4 monotonic rule matrix is an EMBER design draft, not a rule base derived verbatim from a cited paper. It must be reviewed and validated.

## Map and statistics dashboards

The interactive map (`/map`) and analytical statistics page (`/statistics`) use `App\Services\EmberStatisticsService` for shared 13-class definitions, active-filter aggregation, NASA confidence distribution, and class-level HSE/HBE/FSI values. Hotspot totals and class percentages use georeferenced `titik_lokasi` rows that pass the selected year, province, land-cover, confidence, and FSI filters. Class-distribution percentages use only rows assigned to one of the 13 recognized classes; unclassified rows remain in the total hotspot count and are reported separately.

HSE, HBE, and FSI remain class-level scores and are not aggregated across classes or recomputed for a selected year or province. HSE represents its 2021-2023 source period; HBE uses the provisional pre-2024 reconstruction described above. Configured class area and 2024 burn rate are 2024 source values and are not recalculated for the selected year or province. The statistics table reports historical burn-rate and frequency metrics separately from HBE.

## Runtime behaviour

`ImportEmberHotspots` assigns HSE/HBE by the hotspot's `LAND_COVER_ID` (or `MAPBIOMAS_ID`), so every hotspot in the same class has the same class-level FSI under a given model version. This is intentional for the current class-level model. NASA confidence is used upstream in HSE; it is not a direct Sugeno input.

Legacy `prior_lcs`, `empirical_evidence`, and `hybrid_lcs` fields remain in the database for compatibility but the new import command sets old prior/evidence fields to `NULL`. New values use `hotspot_evidence`, `historical_burn_evidence`, and `model_version`.

## Setup

1. Install prerequisites: PHP version and extensions required by `composer.json`, Composer, Node.js/npm, and a supported database.
2. Copy `.env.example` to `.env`; set database credentials and app settings.
3. Run:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
# Set CMS_ADMIN_PASSWORD in .env to a strong local password first
php artisan db:seed --class=AdminUserSeeder
npm run build
```

4. Import the packaged enriched hotspot CSV (path is relative to project root):

```bash
php artisan ember:import-hotspots storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv --truncate
```

`--truncate` removes existing `titik_lokasi` rows before re-importing, so only use it when the database contents should be replaced. Without it, rows are appended and duplicate prevention is not automatic.

5. Start locally:

```bash
php artisan serve
```

Open the local URL reported by Artisan. CMS login is `/cms/login`; the seeder reads `CMS_ADMIN_EMAIL` and `CMS_ADMIN_PASSWORD` from `.env`. Change the example password before seeding, and never use it on a public server. If CSS/JS assets need development serving, use `npm run dev` in a second terminal.

## Included data

The project includes the 13-class evidence source table, hotspot CSV, selected MapBiomas area and fire summary tables, the GAUL 2025 10-province GeoJSON, and three MapBiomas Fire GeoTIFFs currently available:
- `burned_coverage_2024.tif`
- `fire_accumulated_2000_2024.tif`
- `fire_frequency_2000_2024.tif`

These rasters are **reference/source files**; Laravel does not run raster processing at web request time. The precomputed class evidence is represented in `config/ember.php`. Annual pre-2024 raster maps, a separate MCD64A1 validation run, and re-attribution to yearly Landy maps remain follow-up analyses.

## Quality checks and limitations

- All 13 class IDs must exist in `config/ember.php`; import fails explicitly if a class is absent.
- The model version is saved with each imported row.
- Re-import after migration is required if existing rows need HSE/HBE/FSI fields populated.
- The current HBE time split is provisional due to reconstruction from cumulative frequency and 2024 mask. It should be verified with annual 2000-2023 burned-area rasters.
- The hotspot table's class label is based on the provided enrichment. Current source assignment uses MapBiomas 2024 for historical hotspots; for historical year-specific land cover, re-join each hotpot to its corresponding yearly land-cover raster.
- Product classes/legend versions (Landy/MapBiomas Land Cover Collection 4 versus Fire Collection 2) require a documented crosswalk.
- FSI is relative and experimental; do not label it probability, forecast, or confirmed fire.

## Operational commands

```bash
php artisan migrate:status
php artisan ember:import-hotspots --help
php artisan test
npm run build
```

PHPUnit/runtime tests should be executed in a local environment with the extensions and services required by the app. The packaging environment used to prepare this bundle lacks some PHP extensions, so final feature-test execution must be repeated locally.
