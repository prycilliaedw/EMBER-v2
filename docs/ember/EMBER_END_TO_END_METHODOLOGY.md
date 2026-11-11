# EMBER: Metodologi dan Alur Otomatis End-to-End

Dokumen ini menjelaskan alur data dari NASA FIRMS sampai penentuan lokasi administratif, tutupan lahan, HSE, HBE, FSI, dan statistik yang ditampilkan aplikasi.

## 1. Ringkasan alur

```text
NASA FIRMS API
  -> ambil produk NRT untuk jendela 2 hari terakhir
  -> validasi CSV, koordinat, dan batas AOI 10 provinsi Sumatra
  -> upsert ke titik_lokasi dengan external_id yang stabil
  -> antrekan job klasifikasi untuk titik baru/perlu dilengkapi
       -> titik koordinat dalam batas administrasi provinsi/kabupaten/kecamatan/desa
       -> sample piksel raster tutupan lahan MapBiomas Collection 4
       -> crosswalk kelas raw ke 13 kelas EMBER
       -> ambil HSE/HBE baseline kelas dan hitung FSI Sugeno
       -> simpan hasil dan status klasifikasi
  -> statistik menggabungkan sumber historis, database NRT/legacy,
     dan titik kandidat sesuai aturan identitas/deduplikasi
```

Alur ini mempunyai dua jenis otomatisasi: scheduler memanggil sinkronisasi FIRMS dan worker mengerjakan klasifikasi asinkron. Keduanya harus aktif untuk alur otomatis penuh.

## 2. NASA FIRMS: sumber, frekuensi, dan jendela data

### 2.1 Produk yang dikonfigurasi

Daftar default pada `config/ember.php` adalah `VIIRS_NOAA20_NRT`, `VIIRS_NOAA21_NRT`, `VIIRS_SNPP_NRT`, dan `MODIS_NRT`. API key dibaca dari `NASA_FIRMS_MAP_KEY`; base URL, timeout, dan periode overlap juga dapat dikonfigurasi di environment.

Sinkronisasi memakai area endpoint FIRMS dengan bounding box yang dihitung dari GeoJSON AOI, kemudian menyaring lagi setiap koordinat terhadap poligon AOI. Bounding box hanya memperkecil respons API; pemeriksaan polygon menentukan apakah titik benar-benar di dalam wilayah analisis.

### 2.2 Makna “2 hari” pada implementasi sekarang

Nilai default `NASA_FIRMS_OVERLAP_DAYS=2` mengatur **panjang jendela data yang diminta**. Jadwal di `bootstrap/app.php` saat ini menjalankan sinkronisasi **setiap jam**, dan pada setiap eksekusi meminta data dari dua hari terakhir. Ini memberi overlap untuk menangkap data terlambat dan tidak berarti proses hanya berjalan sekali setiap dua hari.

Contoh perintah manual yang sama:

```powershell
php artisan ember:sync-firms --days=2
```

Uji tanpa menulis database atau mengantrekan job:

```powershell
php artisan ember:sync-firms --days=2 --dry-run --limit=1
```

`--source=VIIRS_SNPP_NRT` dapat digunakan untuk memilih satu produk; `--source` dapat diulang. `--limit` membatasi record valid yang diproses. Jangan menaruh API key di dokumentasi, command line, atau log.

### 2.3 Agar jadwal otomatis berjalan

Laravel hanya mendefinisikan jadwal. Mesin tempat aplikasi berjalan harus memanggil scheduler setiap menit. Periksa jadwal terdaftar dengan:

```powershell
php artisan schedule:list
```

Untuk instalasi Windows, buat task Task Scheduler yang menjalankan `php artisan schedule:run` setiap menit di direktori proyek. Untuk Linux, gunakan cron Laravel standar. Scheduler kemudian menjalankan sync FIRMS per jam dan worker klasifikasi tiap menit. Tanpa pemanggil scheduler ini, jadwal yang tampil di `schedule:list` tidak otomatis berjalan.

Jika yang dimaksud adalah **menjalankan permintaan API hanya satu kali setiap dua hari**, itu berbeda dari konfigurasi saat ini. Konfigurasi yang sekarang mendukung jadwal per jam atau per hari (`NASA_FIRMS_SCHEDULE_HOURLY`), sementara `overlap_days` hanya mengatur jendela data. Jadwal dua harian memerlukan perubahan jadwal aplikasi/OS dan tidak boleh disamakan dengan jendela `--days=2`.

## 3. Validasi dan penyimpanan record FIRMS

`NasaFirmsSyncService` melakukan langkah berikut untuk setiap respons:

1. Meminta CSV dengan retry dan timeout terkonfigurasi.
2. Membaca nama kolom tanpa bergantung pada kapitalisasi header.
3. Mengabaikan baris tanpa koordinat valid atau tanggal akuisisi.
4. Memeriksa point-in-polygon terhadap AOI 10 provinsi Sumatra.
5. Menyimpan confidence, tanggal/waktu akuisisi, satelit, instrumen, day/night, FRP, brightness, scan, track, payload sumber, dan cap waktu sinkronisasi.
6. Membuat identitas stabil (`external_id`) dari produk, tanggal, waktu, dan koordinat enam desimal. Pengambilan ulang baris yang sama memperbarui record, tidak membuat record baru.
7. Menulis ringkasan eksekusi ke `firms_sync_runs` agar jumlah diterima, di dalam AOI, dimasukkan, diperbarui, dan gagal dapat ditelusuri.

Baris FIRMS yang berimpit koordinat tetapi berbeda waktu atau produk dapat tetap menjadi observasi database terpisah. Deduplikasi statistik spasial adalah tahap lain dan tidak menghapus observasi sumber.

## 4. Lokasi administratif: provinsi sampai desa

### 4.1 Provinsi

Saat sync, `AdministrativeProvinceMatcher` mencocokkan titik terhadap poligon provinsi pada `EMBER_AOI_10Provinsi_Sumatra_GAUL2025_v2.geojson`. Kecocokan yang unik mengisi `provinsi`. Ini matching spasial, bukan reverse-geocoding dari teks alamat.

### 4.2 Kabupaten/kota, kecamatan, desa

Job klasifikasi memanggil `AdministrativeLocationMatcher`. Matcher menjalankan sampler Python persisten dengan data batas di:

```text
storage/app/ember/source/sumatra_admin/village.ndjson
storage/app/ember/scripts/administrative_match_server.py
```

Setiap titik dicocokkan ke poligon administrasi yang berisi koordinatnya; hasil hierarki yang tersedia mengisi `kabupaten_kota`, `kecamatan`, dan `desa` (serta dapat mengoreksi `provinsi`). Untuk mengaktifkan tahap ini, kedua file tersebut harus ada, executable Python pada `MAPBIOMAS_PYTHON_EXECUTABLE` harus dapat dijalankan, dan dependensi Python yang dibutuhkan matcher harus terpasang. Matcher mengembalikan hasil kosong bila sumber/skrip tidak ada; kegagalan administrasi dicatat sebagai warning dan tidak membatalkan upaya klasifikasi tutupan lahan.

### 4.3 Batas interpretasi wilayah

Hasil mengikuti versi dan kualitas poligon administrasi yang dipasang. Titik tepat di batas, geometri yang tumpang tindih, atau sumber tanpa desa dapat menghasilkan nama tingkat bawah kosong. Status tutupan lahan `classified` sendiri bukan bukti bahwa semua empat tingkat administrasi terisi; keduanya perlu diperiksa terpisah.

## 5. Klasifikasi tutupan lahan otomatis

`NasaFirmsSyncService` mengantrekan `ClassifyHotspotLandCover` untuk record baru, record yang belum berada pada status terminal, atau record terminal yang masih kurang data administrasi. Job memakai queue `ember-classification`, unik per ID hotspot, maksimal tiga percobaan dengan backoff 30, 120, dan 300 detik.

Urutan kerja job:

1. Validasi lintang dan bujur.
2. Mencoba pencocokan administrasi.
3. Mencari piksel koordinat pada raster MapBiomas Collection 4 di `storage/app/ember/source/ember_mapbiomas_collection4/` melalui sampler Python/rasterio.
4. Mengubah kelas raw raster memakai crosswalk otoritatif `storage/app/ember/output/ember_collection4_authoritative_crosswalk.csv` (dengan mapping terverifikasi yang tersedia sebagai fallback).
5. Menetapkan salah satu dari 13 kelas EMBER yang disetujui.
6. Mengambil HSE dan HBE untuk kelas tersebut dari `config/ember.php`, menghitung FSI dan label kelasnya, kemudian menyimpan seluruh hasil dan `model_version` pada `titik_lokasi`.

Status raster seperti `raster_unavailable`, `zero_pixel`, `no_data`, `outside_raster`, `unmapped_class`, `pending_review`, dan `failed` menunjukkan bahwa titik belum memperoleh kelas terverifikasi. NASA source fields tidak dihapus ketika proses klasifikasi gagal. Record yang sudah `classified` dan memiliki `land_cover_id` dilewati job; perubahan konfigurasi model tidak otomatis menghitung ulang record lama.

Rasters, crosswalk, Python, dan boundary files merupakan prasyarat operasional. Jika salah satu tidak tersedia, status record perlu ditinjau; keberadaan scheduler saja tidak menjamin klasifikasi sukses.

## 6. HSE, HBE, dan FSI

### 6.1 HSE dan HBE adalah baseline tingkat kelas

Nilai runtime berasal dari `config/ember.php`, satu pasangan HSE/HBE untuk setiap kelas. Karena itu dua titik pada kelas tutupan lahan yang sama menerima nilai HSE, HBE, dan FSI yang sama untuk versi model yang sama. Nilai ini bukan perhitungan lokal dari piksel sekitar tiap titik dan bukan hasil penjumlahan seluruh titik FIRMS baru.

- **HSE (Hotspot Evidence):** baseline evidence hotspot berbobot confidence untuk kelas, dengan sumber hotspot 2021–2023 dan luas kelas sebagai konteks normalisasi.
- **HBE (Historical Burn Evidence):** baseline evidence historis kelas dari footprint terbakar dan frekuensi kebakaran; nilai pra-2024 saat ini direkonstruksi dari raster frekuensi kumulatif dan mask 2024.
- **FSI:** output Zero-Order Sugeno dari HSE dan HBE baseline kelas.

NASA confidence pada record baru tetap disimpan sebagai atribut observasi. Ia tidak mengganti HSE/HBE kelas dan tidak masuk sebagai input fuzzy Sugeno tersendiri. Jika baseline HSE/HBE atau aturan Sugeno berubah, hasil statistik tingkat kelas dapat berubah saat dihitung ulang oleh service, tetapi kolom hasil pada record lama tetap memerlukan proses rekonsiliasi/backfill untuk diperbarui.

### 6.2 Sugeno runtime

Service `FireSusceptibilityService` menggunakan empat set linguistik pada tiap input: Very Low, Low, Moderate, High; membership dibentuk dari anchor Q1/Q2/Q3 di config. Kombinasi kedua input memilih konsekuen aturan dengan konstanta 10, 30, 50, 70, atau 90. Nilai akhir adalah rata-rata tertimbang konsekuen (Zero-Order Sugeno), dijepit pada 0–100 dan dibulatkan dua desimal. Label: `<20` Very Low, `<40` Low, `<60` Moderate, `<80` High, selain itu Very High. Flag context menandai selisih HSE/HBE sesuai threshold konfigurasi.

FSI adalah indeks relatif eksperimental, bukan probabilitas kebakaran, prediksi bahwa satu titik akan terbakar, atau ukuran keyakinan NASA. Dasar data dan keterbatasan metodologis terperinci dijelaskan di [Dokumentasi teknis HSE/HBE](../EMBER_Dokumentasi_Teknis_dan_Metodologi_HSE_HBE.md).

## 7. Statistik dan aturan agar angka dapat direkonsiliasi

Statistik tidak menghitung semua angka dari satu tabel mentah yang sama. `EmberStatisticsService` membangun komposisi spasial dari sumber otoritatif berikut:

1. **Historis:** `storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv`; identitas observasi yang sama dihapus berdasarkan satelit, instrumen, tanggal, waktu, latitude, longitude.
2. **Non-historis database:** record NRT dan legacy pada `titik_lokasi`; record NASA historis tidak ditambahkan lagi karena sudah diwakili sumber CSV historis.
3. **Titik kandidat Collection 4:** CSV kandidat/crosswalk. Koordinat kandidat yang berimpit dengan record database dianggap overlap dan tidak menambah total spasial; confidence kandidat dicocokkan ke berkas FIRMS enriched bila tersedia.

### 7.1 Persamaan yang perlu cocok

Untuk dataset/filter yang sama:

```text
total spasial unik = total historis + total record non-historis database + kandidat tambahan non-overlap
total spasial unik = kelas terpetakan + titik belum terpetakan
total confidence terklasifikasi = High + Nominal + Low
confidence tidak tersedia = max(0, total spasial unik - (High + Nominal + Low))
```

Jumlah kelas per kelas dijumlahkan menjadi `mapped_total`; titik tanpa kelas resmi masuk `unclassified_total`, tidak dipaksakan masuk kategori kelas. Persentase kelas menggunakan `total spasial unik` sebagai penyebut. Persentase diagram confidence memakai jumlah High+Nominal+Low sebagai penyebut kategori confidence. Oleh karena itu kedua diagram hanya setara bila semua titik spasial mempunyai confidence kategoris. Record tanpa confidence tidak boleh disamarkan menjadi kategori buatan.

Angka “titik dengan kelas” mengukur penetapan kelas tutupan lahan; angka distribusi confidence mengukur record yang mempunyai atribut confidence NASA yang dapat dikategorikan. Keduanya mengukur atribut berbeda dan tidak seharusnya disamakan tanpa memeriksa penyebutnya.

### 7.2 Menghindari hitung ganda

- Re-fetch FIRMS menggunakan `external_id` untuk upsert observasi, bukan insert salinan untuk identitas yang sama.
- Sumber historis dipisahkan dari record NASA historis di database saat komposisi spasial dibangun.
- Kandidat dengan koordinat berimpit database dicatat untuk QA, tetapi tidak ditambahkan ke denominator spasial kedua kali.
- Kandidat duplikat dalam CSV diabaikan dengan coordinate key delapan desimal.
- Rekaman FIRMS berbeda waktu/produk tetap merupakan observasi sumber terpisah; jangan mencampur hitung observasi dengan hitung lokasi unik.

**Catatan implementasi:** komposisi menyatukan beberapa sumber berdasarkan aturan identitas di atas; ia bukan query `COUNT(DISTINCT latitude, longitude)` atas semua row database. Dashboard peta juga dapat memfilter year/province/class/confidence/FSI, sementara kartu total spasial menggambarkan komposisi sumber lengkap. Saat membandingkan dua angka, pastikan halaman, filter, waktu refresh, sumber data, dan definisi record sama.

### 7.3 Pemeriksaan selisih

Jika nilai tidak seimbang, periksa secara berurutan:

1. Status run terbaru di tabel `firms_sync_runs`: `received`, `inside_aoi`, `inserted`, `updated`, `failed`.
2. Antrean `ember-classification` dan `failed_jobs`; bedakan queue kosong dari worker tidak berjalan.
3. Jumlah dan status `classification_status`; hitung nilai kosong untuk `land_cover_id`, administrasi, `hotspot_evidence`, `historical_burn_evidence`, dan `fsi_score` secara terpisah.
4. Jumlah confidence yang tidak kosong dan hasil normalisasi ke High/Nominal/Low.
5. Keberadaan historical source CSV, candidate CSV, confidence-enriched CSV, raster, crosswalk, batas desa, dan script sampler.
6. Kesamaan filter statistik serta definisi “record observasi” versus “titik spasial unik”.

Jangan memperbaiki selisih dengan memaksa denominator atau mengisi confidence yang tidak diterima dari NASA. Koreksi sumber/queue/status, lalu bangun ulang perhitungan melalui alur yang sesuai.

Pemeriksaan read-only untuk kelengkapan hasil pada record database:

```sql
SELECT
  COALESCE(is_nrt, 0) AS is_nrt,
  COALESCE(classification_status, '(null)') AS classification_status,
  COUNT(*) AS records,
  SUM(land_cover_id IS NULL) AS missing_land_cover,
  SUM(provinsi IS NULL OR provinsi = '') AS missing_province,
  SUM(kabupaten_kota IS NULL OR kabupaten_kota = '') AS missing_regency,
  SUM(kecamatan IS NULL OR kecamatan = '') AS missing_district,
  SUM(desa IS NULL OR desa = '') AS missing_village,
  SUM(hotspot_evidence IS NULL) AS missing_hse,
  SUM(historical_burn_evidence IS NULL) AS missing_hbe,
  SUM(fsi_score IS NULL OR fsi_class IS NULL) AS missing_fsi,
  SUM(confidence IS NULL OR confidence = '') AS missing_confidence
FROM titik_lokasi
GROUP BY COALESCE(is_nrt, 0), COALESCE(classification_status, '(null)')
ORDER BY is_nrt DESC, classification_status;

SELECT queue, COUNT(*) AS pending_jobs FROM jobs GROUP BY queue;
SELECT COUNT(*) AS failed_jobs FROM failed_jobs;
```

Query pertama hanya memeriksa baris database; ia tidak menghitung record pada CSV historis/kandidat yang ikut membentuk statistik. Jangan gunakan `db:wipe`, truncate, atau reset sebagai langkah rekonsiliasi.

## 8. Menjalankan dan memeriksa instalasi

### Konfigurasi minimum `.env`

```dotenv
NASA_FIRMS_MAP_KEY=isi_key_di_environment
NASA_FIRMS_OVERLAP_DAYS=2
NASA_FIRMS_SCHEDULE_HOURLY=true
QUEUE_CONNECTION=database
MAPBIOMAS_LAND_COVER_RASTER_DIRECTORY=storage/app/ember/source/ember_mapbiomas_collection4
MAPBIOMAS_PYTHON_EXECUTABLE=D:\\capstone\\EMBER-v2-main\\.venv\\Scripts\\python.exe
```

Sesuaikan path Python untuk instalasi masing-masing. Jangan commit `.env` atau membagikan key.

### Urutan operasional

```powershell
# Periksa task yang didefinisikan
php artisan schedule:list

# Periksa koneksi Python/rasterio sebelum data masuk
& .\.venv\Scripts\python.exe -c "import rasterio; print(rasterio.__version__)"

# Coba request nyata tanpa menulis/mengantrekan
php artisan ember:sync-firms --days=2 --dry-run --limit=1

# Jalankan satu sync langsung (di lingkungan yang dikonfigurasi)
php artisan ember:sync-firms --days=2

# Jalankan worker secara manual untuk memproses queue klasifikasi
php artisan queue:work --queue=ember-classification --tries=3 --timeout=45 --memory=512

# Di terminal/Task Scheduler terpisah, panggil scheduler setiap menit
php artisan schedule:run
```

Untuk produksi jangan jalankan `schedule:run` sekali lalu menganggap jadwal terus hidup; OS harus memanggilnya setiap menit. Worker manual di atas berjalan sebagai proses tunggu. Jadwal worker yang sudah disediakan menjalankan worker singkat tiap menit dengan `--stop-when-empty`.

## 9. Komponen kode dan data

| Bagian | Path |
|---|---|
| Jadwal sync dan worker | `bootstrap/app.php` |
| Konfigurasi FIRMS, kelas, HSE/HBE, Sugeno | `config/ember.php` |
| Perintah sinkronisasi | `app/Console/Commands/SyncNasaFirms.php` |
| Fetch, AOI, upsert, dispatch | `app/Services/NasaFirmsSyncService.php` |
| Matching provinsi | `app/Services/AdministrativeProvinceMatcher.php` |
| Matching kabupaten sampai desa | `app/Services/AdministrativeLocationMatcher.php` |
| Job klasifikasi titik | `app/Jobs/ClassifyHotspotLandCover.php` |
| Raster/crosswalk MapBiomas | `app/Services/MapBiomasSpatialMatcher.php` |
| HSE/HBE/FSI | `app/Services/FireSusceptibilityService.php` |
| Agregasi statistik dan deduplikasi komposisi | `app/Services/EmberStatisticsService.php` |
| Endpoint/pemuatan peta & statistik | `app/Http/Controllers/User/ContentController.php` |
| Sumber/raster/crosswalk/helper | `storage/app/ember/` |

## 10. Batasan dan interpretasi

- FIRMS hotspot adalah deteksi satelit, bukan perimeter kebakaran atau konfirmasi lapangan.
- HSE/HBE/FSI runtime merupakan baseline eksperimental tingkat kelas; seluruh titik pada kelas yang sama berbagi nilai.
- HBE pra-2024 masih rekonstruksi dari produk raster yang tersedia dan perlu validasi dengan raster tahunan independen.
- Atribusi kelas historis dapat memakai peta kelas Collection 4/2024, bukan kelas pada tanggal historis titik.
- Kecocokan desa bergantung pada kelengkapan dan versi `village.ndjson`.
- Kegagalan API, geometri, raster, crosswalk, Python, scheduler, atau queue dapat menghentikan satu tahap tanpa menghapus record NASA yang tersimpan.
- Nilai pada record lama tidak otomatis berubah saat baseline/metodologi diperbarui; lakukan rekonsiliasi terkontrol dan versioning bila metode berubah.

## 11. Dokumen terkait

- [Alur klasifikasi FIRMS](NASA_FIRMS_CLASSIFICATION_WORKFLOW.md)
- [Arsitektur pipeline MapBiomas](MAPBIOMAS_PIPELINE_ARCHITECTURE.md)
- [Runbook operasi](EMBER_OPERATIONS_RUNBOOK.md)
- [Dokumentasi teknis metodologi HSE/HBE](../EMBER_Dokumentasi_Teknis_dan_Metodologi_HSE_HBE.md)
