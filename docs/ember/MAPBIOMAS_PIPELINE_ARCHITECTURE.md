# MapBiomas Pipeline Architecture

Panduan alur operasional lengkap dari API FIRMS sampai statistik tersedia di [EMBER: Metodologi dan Alur Otomatis End-to-End](EMBER_END_TO_END_METHODOLOGY.md).

## Purpose

The MapBiomas pipeline assigns the land-cover class for each newly ingested NASA FIRMS hotspot from the authoritative 2024 Collection 4 GeoTIFF. It does not infer a province or class from administrative text.

## Components

- `app/Services/NasaFirmsSyncService.php`: fetch, parse, deduplicate, filter, persist, and enqueue.
- `app/Jobs/ClassifyHotspotLandCover.php`: classify one `titik_lokasi` row and persist the result.
- `app/Services/MapBiomasSpatialMatcher.php`: load the crosswalk and communicate with the persistent Python sampler.
- `storage/app/ember/scripts/raster_sample_server.py`: rasterio JSON-line sampling server.
- `storage/app/ember/output/ember_collection4_authoritative_crosswalk.csv`: authoritative raw-ID mapping.
- `storage/app/ember/source/ember_mapbiomas_collection4/`: ten province/merged rasters.

## Data flow

1. NASA FIRMS returns records.
2. The sync service parses latitude, longitude, confidence, acquisition fields, and source.
3. The service deduplicates against existing rows and filters records to authoritative AOI geometry.
4. A new row is inserted in `titik_lokasi`.
5. `ClassifyHotspotLandCover` is dispatched uniquely for that hotspot.
6. Laravel starts or reuses the persistent Python process using the configured absolute virtual-environment executable.
7. The sampler reads every authoritative raster only as a small bounding-box candidate, then samples one pixel.
8. The raw value is mapped through the authoritative CSV.
9. The job stores the mapped land-cover class, class-level HSE/HBE values, and
   the corresponding FSI result. It does not modify confidence or NASA FIRMS
   source fields.

The province matcher uses the included 10-province GeoJSON. The classification
job separately uses `AdministrativeLocationMatcher` and
`storage/app/ember/source/sumatra_admin/village.ndjson` to attribute
regency/city, district, and village when that boundary dataset and its Python
matcher are installed. These administrative results are independent of the
MapBiomas raster sample and may be unavailable when the detailed boundary
source is missing.

## Raster inventory

Nine named AOI rasters (01-06 and 08-10) and the merged Kepulauan Riau raster are authoritative. The four Kepri `_kepri_tile_*` GeoTIFFs and `ember_kepri_2024.vrt` are source backups/exclusions.
