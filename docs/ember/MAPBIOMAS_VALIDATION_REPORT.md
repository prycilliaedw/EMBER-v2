# MapBiomas Validation Report

## Inventory result

There are exactly ten authoritative province/merged rasters:

| Raster | Province/AOI |
|---|---|
| AOI 01 | Bengkulu |
| AOI 02 | Lampung |
| AOI 03 | Kepulauan Bangka Belitung |
| AOI 04 | Sumatera Barat |
| AOI 05 | Jambi |
| AOI 06 | Aceh |
| Kepulauan Riau merged | Kepulauan Riau |
| AOI 08 | Sumatera Selatan |
| AOI 09 | Riau |
| AOI 10 | Sumatera Utara |

The four original Kepri tiles and Kepri VRT are not authoritative province rasters.

## Metadata

All ten files report:

- CRS EPSG:4326
- one band
- band description `classification_2024`
- UInt16
- pixel size approximately 0.000269494585236 degrees
- NoData metadata `null`

## Samples

Direct real-pixel representatives were extracted for each AOI. A representative AOI 08 pixel contains raw 76; it maps to EMBER 76 (`Hutan Rawa Gambut`) according to the official Collection 4 legend. The live coordinate `-4.74858, 104.74796` samples raw 21, maps to EMBER 21, and is named `Pertanian Lainnya`.

## Observed raw values

Block-level inspection of all ten rasters observed the union `0, 3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76`; each raster has band description `classification_2024`. The official Collection 4 legend identifies raw 35 as Oil Palm, raw 40 as Rice Paddy, and raw 76 as Peat Swamp Forest. They map respectively to EMBER 35, 40, and 76. Raw 41 was not observed and remains unmapped. Zero is returned as `zero_pixel`, never assigned a land-cover class.

Class-code provenance: [MapBiomas Indonesia Collection 4 legend (English PDF)](https://landy.mapbiomas.id/assets/legendcode/Integration%20and%20Layers%20MB%20Indonesia%20-%20Col%204%20-%20EN%20.pdf). The rasters were exported from the public Collection 4 Earth Engine asset recorded in `storage/app/ember/scripts/ember_collection4_2024_export.js`.

## Unresolved coverage questions

Provincial raster rectangular bounds overlap. Matching must therefore use actual pixel/administrative coverage semantics rather than assuming a first match. Coordinate behavior and unmapped/zero pixels remain explicitly reported and never guessed.
