# EMBER — Dokumentasi Sistem, Teknis, dan Metodologi

**Model runtime:** `EMBER-HSE-HBE-SUGENO-DRAFT-1.0.0`  
**Status model:** experimental  
**Wilayah:** 10 provinsi Sumatra  
**Kelas:** 13 kelas MapBiomas Indonesia Landy

> Dokumen ini disusun berdasarkan tiga arsip source code final yang diunggah. Ini adalah dokumentasi implementasi, bukan usulan perubahan. Tidak ada kode atau parameter proyek yang diubah.

## 1. Gambaran umum

EMBER (Early Monitoring for Burning Environment Response) adalah aplikasi web untuk mengelola dan menampilkan observasi hotspot NASA FIRMS, mengaitkan titik dengan wilayah administratif dan kelas tutupan lahan, serta menyajikan Hotspot Evidence (HSE), Historical Burn Evidence (HBE), dan Fire Susceptibility Index (FSI).

Alur: **NASA FIRMS → validasi AOI → upsert database → matching administrasi → sampling satu piksel MapBiomas → crosswalk 13 kelas → HSE/HBE kelas → Zero-Order Sugeno → FSI → peta dan statistik.**

FSI merupakan indeks relatif eksperimental, bukan probabilitas kebakaran, prediksi pasti, atau konfirmasi kejadian kebakaran.

## 2. Teknologi

- PHP `^8.3`, Laravel `^13.8`, Livewire `^4.3`.
- Vite `^8`, Tailwind CSS `^4`.
- Leaflet, PMTiles, Protomaps Leaflet.
- TinyMCE `^8.8`.
- Python + rasterio untuk sampling GeoTIFF.
- Database dan queue mengikuti konfigurasi Laravel; migration menyediakan tabel aplikasi, jobs, cache, dan sync runs.

## 3. Website dan CMS

Halaman publik yang terdaftar: `/`, `/map`, `/data`, `/data/titik-lokasi.csv`, `/statistics`, `/locations/{id}`, `/search`, `/about`, `/team`, `/methodology`, `/faq`, `/activities`, `/activities/{id}`, dan `/map-layers/{id}`.

CMS berada pada prefix `/cms`, login di `/cms/login`, dan dilindungi autentikasi. Modul CMS meliputi About, metodologi, tim, FAQ, aktivitas, referensi, layer GeoJSON, serta titik lokasi. Pengelolaan titik lokasi mencakup daftar, tambah, detail, edit, impor CSV dan template CSV.

## 4. Data dan database

Tabel inti `titik_lokasi` menyimpan:
- Identitas: `external_id`, `data_source`, `source_product`, `is_nrt`, `source_payload`.
- Observasi: tanggal/waktu, koordinat, confidence, satelit, instrumen, day/night, FRP, brightness, scan, track.
- Administrasi: provinsi, kabupaten/kota, kecamatan, desa.
- Klasifikasi: raw MapBiomas ID, `land_cover_id`, nama kelas, status, error, attempts, timestamp.
- Evidence/model: `hotspot_evidence`, `historical_burn_evidence`, `fsi_score`, `fsi_class`, `context_flag`, `model_version`.

Tabel `firms_sync_runs` menyimpan status dan ringkasan setiap proses sinkronisasi. Tabel CMS menyimpan konten halaman, referensi, FAQ, aktivitas, anggota tim, dan layer GeoJSON.

Kolom legacy `prior_lcs`, `empirical_evidence`, dan `hybrid_lcs` bukan input model HSE–HBE–Sugeno saat ini.

## 5. NASA FIRMS

Produk default: `VIIRS_NOAA20_NRT`, `VIIRS_NOAA21_NRT`, `VIIRS_SNPP_NRT`, `MODIS_NRT`. API key dibaca dari `NASA_FIRMS_MAP_KEY`.

Service membentuk bounding box dari AOI, meminta CSV FIRMS, memvalidasi koordinat/tanggal, lalu memeriksa point-in-polygon terhadap AOI sepuluh provinsi. Atribut yang disimpan mencakup confidence, tanggal/waktu, satelit, instrumen, day/night, FRP, brightness, scan, track, payload sumber, dan timestamp sinkronisasi.

`external_id` adalah SHA-256 dari produk, tanggal, waktu akuisisi, latitude enam desimal, dan longitude enam desimal. Record dengan identitas sama di-upsert; observasi di koordinat sama tetapi waktu atau produk berbeda dapat tetap menjadi record berbeda.

Default `NASA_FIRMS_OVERLAP_DAYS=2` berarti jendela data dua hari. `NASA_FIRMS_SCHEDULE_HOURLY=true` berarti sinkronisasi dijadwalkan per jam. Jadi proses berjalan setiap jam dan setiap proses meminta dua hari data terakhir—bukan hanya berjalan sekali dalam dua hari.

Perintah:
```powershell
php artisan ember:sync-firms --days=2 --dry-run --limit=1
php artisan ember:sync-firms --days=2
```
Dry-run mengambil dan mem-parsing data tanpa menulis database dan tanpa dispatch job.

## 6. Atribusi administrasi

`AdministrativeProvinceMatcher` mencocokkan titik dengan poligon provinsi pada GeoJSON AOI. `AdministrativeLocationMatcher` memakai `village.ndjson` dan `administrative_match_server.py` untuk mengisi hierarki provinsi/kabupaten/kecamatan/desa. Ketersediaan nama tingkat bawah mengikuti kelengkapan sumber batas administrasi. Atribusi administrasi dan sampling tutupan lahan adalah tahap terpisah.

## 7. Klasifikasi MapBiomas

Klasifikasi menggunakan `classification_2024` dari MapBiomas Indonesia Collection 4. Sepuluh raster authoritative: AOI 01 Bengkulu, 02 Lampung, 03 Kepulauan Bangka Belitung, 04 Sumatera Barat, 05 Jambi, 06 Aceh, raster gabungan Kepulauan Riau, AOI 08 Sumatera Selatan, 09 Riau, dan 10 Sumatera Utara. Tile Kepri dan VRT bukan raster authoritative matcher.

Sampler Python/rasterio:
1. Memilih raster berdasarkan provinsi.
2. Mengubah koordinat menjadi indeks piksel menggunakan `dataset.index(longitude, latitude)`.
3. Membaca satu piksel pada band pertama.
4. Mengembalikan raw class ID untuk dipetakan melalui crosswalk.

Tidak ada radius/buffer, majority filter, interpolasi, atau pemilihan raster berdasarkan tahun hotspot. Semua hotspot, termasuk historis, menggunakan kelas tahun 2024. Raster ber-CRS EPSG:4326, satu band UInt16, dengan resolusi sekitar `0.000269494585236` derajat.

Crosswalk memvalidasi raw ID dan mengabaikan baris `do not map`, `do not update`, atau `unverified`; raw ID ambigu dikeluarkan. Fallback mapping bawaan tersedia. Tiga belas kelas aktif:

| ID | Kelas |
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

Status: `classified`, `outside_raster`, `zero_pixel`, `no_data`, `unmapped_class`, `raster_unavailable`, `pending_review`, dan `failed`. Nilai raw 0 menghasilkan `zero_pixel`, bukan kelas.

## 8. HSE dan HBE

HSE/HBE adalah baseline **tingkat kelas**. Setelah titik mendapat kelas, service mengambil nilai HSE dan HBE kelas dari `config/ember.php`. Dua titik pada kelas sama memiliki evidence yang sama untuk versi model yang sama.

### HSE
HSE adalah evidence hotspot berbobot confidence untuk kelas berdasarkan data 2021–2023, dengan luas kelas sebagai konteks normalisasi:
\[
W_c=\sum_{i\in c} C_i/100
\]
\[
D_c=(W_c/A_c)\times1000,\quad x_c=\ln(1+D_c)
\]
\[
HSE_c=100\frac{x_c-\min(x)}{\max(x)-\min(x)}
\]
`C_i` adalah confidence NASA; `A_c` adalah luas kelas km². Confidence dipakai upstream untuk HSE, bukan sebagai input Sugeno.

### HBE
HBE menggabungkan proporsi footprint terbakar dan frekuensi pada footprint terbakar:
\[
r_c=A_{ever-burned,c}/A_{class,c}
\]
\[
F_c=MinMax_{13}(\ln(1+r_c))\times100
\]
\[
R_c=MinMax_{13}(\ln(1+M_c))\times100
\]
\[
HBE_c=0.5F_c+0.5R_c
\]
`M_c` adalah rata-rata frekuensi pada footprint terbakar. `r_c` harus berupa proporsi 0–1. HBE pra-2024 direkonstruksi dari raster frekuensi kumulatif 2000–2024 dan mask 2024; validasi dengan raster tahunan independen masih diperlukan.

### Baseline runtime

| ID | Kelas | HSE | HBE |
|---:|---|---:|---:|
| 3 | Formasi Hutan | 22.356 | 8.691 |
| 5 | Mangrove | 0.000 | 1.975 |
| 9 | Kebun Kayu | 78.568 | 59.421 |
| 13 | Tumbuhan Non-Hutan Lainnya | 82.601 | 88.477 |
| 21 | Pertanian Lainnya | 54.712 | 32.208 |
| 24 | Pemukiman | 54.834 | 18.229 |
| 25 | Non-Vegetasi Lainnya | 100.000 | 99.172 |
| 30 | Lubang Tambang | 65.941 | 21.043 |
| 31 | Tambak | 4.482 | 3.448 |
| 33 | Sungai, Danau, Laut | 68.291 | 24.794 |
| 35 | Sawit | 36.773 | 39.725 |
| 40 | Sawah | 44.870 | 55.473 |
| 76 | Hutan Rawa Gambut | 17.389 | 5.986 |

## 9. Zero-Order Sugeno dan FSI

Input FIS hanya dua: HSE dan HBE (0–100). NASA confidence bukan input ketiga.

Anchor membership:

| Input | Q1 | Q2 | Q3 |
|---|---:|---:|---:|
| HSE | 22.356 | 54.712 | 68.291 |
| HBE | 8.691 | 24.794 | 55.473 |

Empat set tiap input: Very Low (left shoulder), Low (triangle), Moderate (triangle), High (right shoulder).

Rule base:

| HSE \ HBE | Very Low | Low | Moderate | High |
|---|---|---|---|---|
| Very Low | Very Low | Low | Moderate | Moderate |
| Low | Low | Low | Moderate | High |
| Moderate | Moderate | Moderate | High | Very High |
| High | Moderate | High | Very High | Very High |

Konstanta konsekuen: Very Low=10, Low=30, Moderate=50, High=70, Very High=90.

\[
w_j=\mu_{HSE,j}\times\mu_{HBE,j}
\]
\[
FSI=\frac{\sum_j w_jz_j}{\sum_j w_j}
\]

FSI dibatasi 0–100 dan dibulatkan dua desimal. Label: `<20` Very Low, `<40` Low, `<60` Moderate, `<80` High, selain itu Very High. `context_flag` menjadi `Evidence Divergence` jika `|HSE-HBE| >= 35`, selain itu `Evidence Aligned`. Flag ini tidak menjadi penalti FSI.

## 10. Statistik dan deduplikasi

`EmberStatisticsService` menggabungkan:
1. CSV hotspot historis `EMBER_Hotspots_Enriched_Evidence_v2.csv`, dideduplikasi berdasarkan satelit, instrumen, tanggal, waktu, latitude, longitude.
2. Record non-historis database NRT/legacy. Record NASA historis di database tidak dihitung lagi karena diwakili CSV historis.
3. Titik kandidat Collection 4. Kandidat yang berimpit koordinat database tidak menambah total; kandidat duplikat menggunakan coordinate key delapan desimal.

Total spasial unik = historis + database non-historis + kandidat tambahan non-overlap. Total spasial unik = kelas terpetakan + titik belum terpetakan. Confidence terklasifikasi = High + Nominal + Low; confidence tidak tersedia dihitung sebagai selisih non-negatif terhadap total spasial. Persentase kelas dan confidence menggunakan penyebut berbeda, sehingga angkanya tidak selalu sama.

## 11. Queue dan scheduler

`ClassifyHotspotLandCover` berjalan pada queue `ember-classification`, unik per ID hotspot, maksimal tiga percobaan, dengan backoff 30, 120, 300 detik. Scheduler pada `bootstrap/app.php`:
- Sync FIRMS hourly (atau daily bila `NASA_FIRMS_SCHEDULE_HOURLY=false`).
- Worker queue singkat setiap menit dengan `--stop-when-empty`.
- Menggunakan `withoutOverlapping` dan `onOneServer`.

Laravel scheduler perlu dipanggil oleh OS setiap menit. Windows dapat menggunakan Task Scheduler untuk menjalankan `php artisan schedule:run`.

## 12. Instalasi dan konfigurasi

Manifest proyek mensyaratkan PHP `^8.3`, Composer, Node/npm, database Laravel, dan Python dengan rasterio.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
npm install
npm run build
php artisan serve
```

Environment penting: `NASA_FIRMS_MAP_KEY`, `NASA_FIRMS_BASE_URL`, `NASA_FIRMS_TIMEOUT_SECONDS`, `NASA_FIRMS_OVERLAP_DAYS`, `NASA_FIRMS_SCHEDULE_HOURLY`, `MAPBIOMAS_LAND_COVER_RASTER_DIRECTORY`, `MAPBIOMAS_PYTHON_EXECUTABLE`, `QUEUE_CONNECTION`, `CMS_ADMIN_EMAIL`, dan `CMS_ADMIN_PASSWORD`.

**Catatan:** `MAPBIOMAS_PYTHON_EXECUTABLE` perlu diisi path absolut ke `python.exe` yang dapat mengimpor rasterio. Matcher memeriksa path file tersebut; nilai default literal `python` tidak otomatis lolos validasi.

Perintah operasional:
```powershell
php artisan schedule:list
php artisan ember:sync-firms --days=2 --dry-run --limit=1
php artisan ember:sync-firms --days=2
php artisan queue:work --queue=ember-classification --tries=3 --timeout=45 --memory=512
php artisan schedule:run
```

## 13. Pengujian dan QA

Dokumen paket menyatakan: lint PHP pada 56 file berhasil; pemeriksaan JavaScript berhasil; route:list memuat 54 route; command help importer berhasil; smoke test service menunjukkan 13 kelas dan FSI 0–100; struktur CSV 33.209 baris diperiksa.

Belum selesai pada lingkungan packaging: PHPUnit penuh (ekstensi `dom`, `mbstring`, `xmlwriter` tidak tersedia), migration/import database nyata (PDO driver tidak tersedia), dan build Vite bersih (masalah permission/native binding Rolldown). Ini adalah batas QA packaging, bukan pernyataan bahwa seluruh test suite sudah lulus.

## 14. Batasan

- FIRMS hotspot bukan perimeter kebakaran atau validasi lapangan.
- HSE/HBE/FSI adalah baseline eksperimental tingkat kelas.
- HBE pra-2024 masih memerlukan validasi raster tahunan independen.
- Hotspot historis menggunakan peta tutupan lahan 2024, bukan peta year-matched.
- Atribusi desa bergantung pada data batas administratif.
- Perubahan baseline/crosswalk tidak otomatis memperbarui record lama.
- Semua 13 kelas dipertahankan tanpa pengecualian.

## 15. Referensi implementasi

| Komponen | Lokasi |
|---|---|
| Scheduler dan routing | `bootstrap/app.php`, `routes/web.php` |
| Konfigurasi model/FIRMS | `config/ember.php` |
| FIRMS command/service | `app/Console/Commands/SyncNasaFirms.php`, `app/Services/NasaFirmsSyncService.php` |
| Admin matching | `app/Services/AdministrativeProvinceMatcher.php`, `AdministrativeLocationMatcher.php` |
| Job klasifikasi | `app/Jobs/ClassifyHotspotLandCover.php` |
| MapBiomas | `app/Services/MapBiomasSpatialMatcher.php` |
| FSI | `app/Services/FireSusceptibilityService.php` |
| Statistik | `app/Services/EmberStatisticsService.php` |
| Importer historis | `app/Console/Commands/ImportEmberHotspots.php` |
| CMS/public views | `app/Http/Controllers`, `resources/views` |
| Pengujian | `tests/Feature`, `tests/Unit` |
