# MapBiomas Data Dictionary

## Classification fields

| Field | Meaning |
|---|---|
| `mapbiomas_raw_class_id` | UInt16 value read directly from raster band 1; not assumed to be an EMBER ID |
| `land_cover_class_id` | Verified EMBER class ID mapped by the authoritative crosswalk |
| `land_cover_name` | Class name from `config/ember.php` |
| `classification_status` | `classified`, `zero_pixel`, `no_data`, `unmapped_class`, `outside_raster`, or `raster_unavailable` |
| `classification_error` | Diagnostic only; null for successful classification |
| `classified_at` | Timestamp of successful or terminal classification attempt |

## Raster metadata

All ten authoritative rasters are GeoTIFF, EPSG:4326, single band, band description `classification_2024`, UInt16, with approximately 0.000269494585236-degree pixel size. Raster NoData metadata is null in each file. Zero is therefore an observed real/coverage pixel value, not NoData.

## Zero and NoData rules

- `zero_pixel`: raster value 0; it is never assigned a class.
- `no_data`: metadata NoData, masked/non-numeric value, or detected unmapped actual NoData condition.
- `unmapped_class`: a numeric value with no verified crosswalk mapping.
- `outside_raster`: coordinate does not fall in any authoritative raster bound.
- `raster_unavailable`: file, executable, import, service, or process failure.

## Crosswalk

The Collection 4 `classification_2024` band uses raw IDs 3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, and 76 for the 13 approved EMBER classes. The official Collection 4 legend identifies raw 35 as Oil Palm, raw 40 as Rice Paddy, and raw 76 as Peat Swamp Forest; each maps to the EMBER class with the same ID. These three values were observed in the local authoritative rasters. Raw 41 was not observed and remains unmapped. See the [official Collection 4 legend](https://landy.mapbiomas.id/assets/legendcode/Integration%20and%20Layers%20MB%20Indonesia%20-%20Col%204%20-%20EN%20.pdf) and `MAPBIOMAS_VALIDATION_REPORT.md`.
