# Metode dan Algoritma Pipeline MapBiomas

Dokumen ini menjelaskan metode klasifikasi spasial yang benar-benar dipakai EMBER, dari koordinat hotspot sampai kelas tutupan lahan dan nilai evidence. Alur operasional yang lebih luas—termasuk NASA FIRMS, queue, statistik, serta konfigurasi deployment—ada di [Metodologi dan Alur Otomatis End-to-End](EMBER_END_TO_END_METHODOLOGY.md).

## 1. Tujuan dan unit analisis

Untuk setiap observasi hotspot dengan koordinat lintang/bujur valid, EMBER mengambil **nilai satu piksel** dari band `classification_2024` pada raster MapBiomas Indonesia Collection 4. Nilai piksel adalah ID kelas raw MapBiomas. ID tersebut dipetakan melalui crosswalk yang disetujui ke salah satu dari 13 kelas EMBER.

Unit input adalah satu rekaman hotspot (`titik_lokasi`), bukan kejadian kebakaran terverifikasi atau area terbakar. Kelas mewakili tutupan lahan pada lokasi piksel menurut peta tahun 2024. Metode ini tidak menggunakan radius/buffer, mayoritas kelas di sekitar titik, interpolasi, atau tanggal hotspot untuk memilih versi raster.

## 2. Komponen implementasi

| Komponen | Peran |
|---|---|
| `app/Jobs/ClassifyHotspotLandCover.php` | Validasi koordinat, atribusi administrasi, orkestrasi klasifikasi, penyimpanan hasil dan status. |
| `app/Services/MapBiomasSpatialMatcher.php` | Memilih raster berdasarkan provinsi, mengirim koordinat ke sampler, menerapkan crosswalk dan memeriksa konfigurasi kelas. |
| `storage/app/ember/scripts/raster_sample_server.py` | Proses Python persisten yang membaca GeoTIFF dengan rasterio dan mengembalikan satu sampel per baris JSON. |
| `storage/app/ember/output/ember_collection4_authoritative_crosswalk.csv` | Crosswalk raw Collection 4 ke ID kelas EMBER beserta status verifikasinya. |
| `config/ember.php` | Nama kelas, HSE/HBE kelas, anchor membership, basis aturan dan konstanta Sugeno. |

## 3. Metode pencocokan koordinat ke kelas

### 3.1 Pemilihan raster

Matcher terlebih dahulu menggunakan `AdministrativeProvinceMatcher` untuk mencocokkan titik ke poligon provinsi. Jika hasilnya salah satu dari sepuluh wilayah analisis, nama provinsi dipetakan ke satu raster otoritatif: sembilan raster AOI bernomor 01–06 dan 08–10, serta raster gabungan Kepulauan Riau. Raster Kepri gabungan dipakai; tile Kepri individual dan VRT tidak dipakai.

Sampler memeriksa kelengkapan seluruh sepuluh raster authoritative pada setiap permintaan. Bila nama raster provinsi diketahui, hanya raster itu yang dipertimbangkan untuk sampel. Bila tidak diketahui, sampler mencoba raster authoritative yang mencakup koordinat dalam urutan nama file. Semua raster memiliki CRS EPSG:4326, sehingga lintang/bujur FIRMS dapat dipakai langsung tanpa reproyeksi.

### 3.2 Dari koordinat ke piksel

Untuk raster terpilih, sampler:

1. Memeriksa apakah koordinat berada dalam bounding box raster (`left <= longitude < right`, `bottom <= latitude < top`).
2. Mengubah koordinat ke indeks baris dan kolom dengan `dataset.index(longitude, latitude)`. Transformasi affine GeoTIFF menentukan piksel yang memuat koordinat; tidak ada pembulatan atau pemilihan tetangga terdekat yang ditambahkan oleh aplikasi.
3. Membaca jendela 1 baris × 1 kolom dari band 1 (`classification_2024`). Pembacaan jendela kecil menghindari pemuatan seluruh raster untuk setiap titik.
4. Mengubah nilai numerik piksel menjadi integer raw ID, lalu menerapkan pemeriksaan NoData dan nilai nol.

Resolusi raster sekitar 0.000269494585236 derajat. Karena raster geografis, ukuran sel dalam meter berubah menurut lintang. Resolusi ini bukan radius ketelitian hotspot dan tidak menghilangkan ketidakpastian posisi sumber FIRMS.

### 3.3 Raw ID ke ID EMBER

Raw ID tidak otomatis dianggap sebagai ID EMBER. Loader membaca CSV crosswalk, menormalkan nama header, mengabaikan baris bertanda `do not map`, `do not update`, atau `unverified`, lalu menerima pasangan ID yang integer dan ID EMBER-nya terdapat pada daftar 13 kelas. Satu raw ID yang terhubung ke dua kelas berbeda ditandai ambigu dan dikeluarkan. Jika file tidak ada/tidak dapat dibaca atau tidak menghasilkan mapping valid, implementasi menggunakan mapping terverifikasi bawaan.

Mapping saat ini adalah 1:1 untuk raw ID `3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76`. Raw ID lain tetap `unmapped_class`; kelas tidak ditebak dari nama provinsi, nilai tetangga, ataupun kelas dengan kode terdekat. Nilai 0 secara khusus adalah `zero_pixel`, bukan kelas.

### 3.4 Pseudocode

```text
classify(latitude, longitude):
    reject missing/non-finite coordinates
    province = point_in_polygon(province_boundaries, latitude, longitude)
    raster = province_to_authoritative_raster(province)
    response = raster_sampler.sample(latitude, longitude, raster)
    if response.status != sampled: persist response status and stop
    ember_id = verified_crosswalk[response.raw_class_id]
    if ember_id absent: persist unmapped_class and stop
    if class evidence config absent: persist unmapped_class and stop
    evidence = class_evidence[ember_id]
    fsi = zero_order_sugeno(evidence.HSE, evidence.HBE)
    persist raw id, EMBER id/name, HSE, HBE, FSI, model version
```

## 4. Status dan arti hasil

| Status | Kondisi |
|---|---|
| `classified` | Piksel bernilai kelas yang mempunyai crosswalk dan konfigurasi evidence. |
| `outside_raster` | Koordinat tidak berada dalam extent raster terpilih atau extent raster manapun. |
| `zero_pixel` | Nilai piksel 0; pada produk ini menunjukkan area di luar/absen klasifikasi. Tidak dipetakan menjadi kelas. |
| `no_data` | Nilai tidak numerik atau cocok dengan metadata NoData. Metadata raster saat ini null; status tetap ditangani untuk kompatibilitas/error data. |
| `unmapped_class` | Raw ID valid numerik tetapi tidak ada mapping terverifikasi atau evidence kelas tidak tersedia. |
| `raster_unavailable` | Raster authoritative kurang/tidak dapat dibuka, Python/rasterio tidak tersedia, proses sampler gagal, atau respons tidak valid. |
| `pending_review` | Kelas raster diperoleh tetapi evidence kelas tidak lengkap pada saat job dijalankan. |
| `failed` | Koordinat tidak valid atau job gagal setelah retry. Detail diagnostik disimpan pada `classification_error`. |

Status non-`classified` tidak menghapus atribut FIRMS. Job unik per hotspot, memakai queue `ember-classification`, maksimal tiga percobaan dengan backoff 30, 120, dan 300 detik. Rekaman yang sudah classified dan memiliki `land_cover_id` dilewati; perubahan crosswalk atau model tidak otomatis memperbarui hasil lama.

## 5. Metode HSE/HBE dan FSI

Klasifikasi piksel hanya memilih **kelas konteks**. Setelah kelas terpilih, service mengambil satu pasangan HSE/HBE kelas dari konfigurasi; nilai ini bukan statistik lokal di sekitar koordinat.

- **HSE (Hotspot Evidence):** skor baseline kelas dari kepadatan hotspot berbobot confidence, dengan data hotspot 2021–2023 dan luas kelas untuk normalisasi. Skor runtime kelas tersimpan dalam `config/ember.php`.
- **HBE (Historical Burn Evidence):** skor baseline kelas dari footprint dan frekuensi kebakaran historis. Rekonstruksi pra-2024 saat ini memakai raster frekuensi kumulatif 2000–2024 dan mask tahunan 2024; validasi terhadap raster tahunan independen masih diperlukan.
- **FSI:** sistem inferensi fuzzy Zero-Order Sugeno menerima HSE dan HBE (keduanya dibatasi 0–100). Untuk setiap input, empat membership set (`very_low`, `low`, `moderate`, `high`) dibentuk dari anchor Q1/Q2/Q3 konfigurasi. Fungsi yang digunakan adalah bahu kiri untuk very-low, segitiga untuk low/moderate, dan bahu kanan untuk high. Derajat aktivasi aturan adalah hasil kali membership HSE dan HBE. Rule base 4×4 memilih konsekuen; konstanta keluaran yang tersedia adalah 10, 30, 50, 70, 90.

Secara matematis, jika aturan aktif ke-i mempunyai firing strength `w_i = μ_HSE,i × μ_HBE,i` dan konstanta konsekuen `z_i`, maka `FSI = Σ(w_i × z_i) / Σ(w_i)`. Hasil dijepit ke 0–100, dibulatkan dua desimal, lalu diberi label Very Low (<20), Low (<40), Moderate (<60), High (<80), atau Very High. `context_flag` menandai Evidence Divergence bila `|HSE − HBE|` mencapai threshold konfigurasi (default 35); flag ini indikator perbedaan evidence, bukan penalti FSI.

Konstanta, rule base monotonik, anchor, serta agregasi indikator merupakan pilihan desain EMBER dan memerlukan validasi independen. FSI adalah indeks relatif eksperimental, bukan probabilitas kebakaran, prediksi kebakaran di titik, atau konfirmasi event oleh NASA. Confidence NASA tetap atribut observasi dan bukan input terpisah ke FSI.

## 6. Asumsi, batasan, dan validasi

1. **Acuan temporal:** raster yang dipakai adalah kelas tahun 2024 untuk semua titik, termasuk hotspot historis. Kelas pada tahun kejadian mungkin berbeda; analisis historis year-matched memerlukan raster tahunan terkait.
2. **Representasi titik:** satu piksel yang memuat koordinat dipakai. Posisi FIRMS dan resolusi raster dapat menyebabkan kelas berbeda pada titik berdekatan; tidak ada majority filter atau uncertainty buffer.
3. **Kecocokan administrasi dan raster adalah tahap berbeda:** job dapat mengisi nama administratif melalui boundary dataset terpisah. Province matcher dipakai untuk memilih raster, tetapi hasil administrasi kabupaten/desa tidak menentukan kelas tutupan lahan.
4. **Coverage nol:** raster punya NoData metadata null; nilai 0 diperlakukan sebagai tidak ada klasifikasi. Status ini tidak disamakan dengan error file.
5. **Extent yang tumpang tindih:** bila provinsi tidak terdeteksi, algoritma menerima raster pertama yang mencakup koordinat dalam urutan file. Dalam kondisi ambigu, hasil dapat bergantung pada urutan nama file; gunakan atribusi provinsi yang benar dan audit titik dekat batas.
6. **Crosswalk dan versi legenda:** mapping harus diverifikasi terhadap legenda Collection 4 dan data raster yang terpasang. Kesamaan angka ID antarproduk/tahun tidak cukup untuk menjamin definisi kelas setara.
7. **Validasi yang sudah dicatat:** sepuluh raster authoritative EPSG:4326, satu band UInt16 `classification_2024`, pixel sekitar 0.000269494585236 derajat, dan union nilai teramati 0 serta 13 ID yang dipetakan. Detail provenance dan sampel tersedia di [Laporan Validasi](MAPBIOMAS_VALIDATION_REPORT.md), definisi kolom di [Data Dictionary](MAPBIOMAS_DATA_DICTIONARY.md), dan pemeriksaan operasional di [Testing Guide](MAPBIOMAS_TESTING_GUIDE.md).

Untuk reproduksibilitas, simpan versi raster, crosswalk, konfigurasi HSE/HBE/Sugeno, dan `model_version` bersama ekspor analisis. Rekaman lama tidak otomatis dihitung ulang setelah salah satu input berubah.

## 7. Raster authoritative

| File | Wilayah |
|---|---|
| `ember_mapbiomas_collection4_2024_aoi_01.tif` | Bengkulu |
| `ember_mapbiomas_collection4_2024_aoi_02.tif` | Lampung |
| `ember_mapbiomas_collection4_2024_aoi_03.tif` | Kepulauan Bangka Belitung |
| `ember_mapbiomas_collection4_2024_aoi_04.tif` | Sumatera Barat |
| `ember_mapbiomas_collection4_2024_aoi_05.tif` | Jambi |
| `ember_mapbiomas_collection4_2024_aoi_06.tif` | Aceh |
| `ember_mapbiomas_collection4_2024_kepulauan_riau.tif` | Kepulauan Riau (merged) |
| `ember_mapbiomas_collection4_2024_aoi_08.tif` | Sumatera Selatan |
| `ember_mapbiomas_collection4_2024_aoi_09.tif` | Riau |
| `ember_mapbiomas_collection4_2024_aoi_10.tif` | Sumatera Utara |

Tile Kepri `_kepri_tile_*` dan `ember_kepri_2024.vrt` adalah sumber/backup, bukan raster authoritative yang dipakai matcher.

## 8. Referensi kode

- Sync FIRMS dan dispatch: `app/Services/NasaFirmsSyncService.php`
- Job klasifikasi: `app/Jobs/ClassifyHotspotLandCover.php`
- Province matcher: `app/Services/AdministrativeProvinceMatcher.php`
- Matcher raster/crosswalk: `app/Services/MapBiomasSpatialMatcher.php`
- Sampler rasterio: `storage/app/ember/scripts/raster_sample_server.py`
- HSE/HBE/FSI runtime: `app/Services/FireSusceptibilityService.php` dan `config/ember.php`
- Source raster dan skrip ekspor: `storage/app/ember/source/ember_mapbiomas_collection4/` dan `storage/app/ember/scripts/`
