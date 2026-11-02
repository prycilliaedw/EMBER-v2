# EMBER — Laravel HSE/HBE + Zero-Order Sugeno (DRAFT)

Aplikasi untuk melihat konteks tutupan lahan pada setiap hotspot NASA FIRMS/MODIS dan menyajikan indeks relatif kerawanan kebakaran kelas-level menggunakan dua input: Hotspot Evidence (HSE) dan Historical Burn Evidence (HBE).

**Status model:** eksperimental; FSI adalah indeks relatif, bukan probabilitas kebakaran atau konfirmasi bahwa hotspot pasti berkembang menjadi api. Semua 13 kelas detail MapBiomas Landy aktif.

## Mulai cepat (Windows / PowerShell)

1. Ekstrak ZIP ke folder proyek, lalu buka PowerShell di folder tersebut.
2. Pastikan PHP 8.3+ (dengan ekstensi Laravel/PHPUnit yang diperlukan), Composer, Node.js dan npm tersedia. Siapkan MySQL/MariaDB atau konfigurasi database lain yang didukung Laravel.
3. Buat dan edit `.env`:

   ```powershell
   Copy-Item .env.example .env
   notepad .env
   ```

4. Atur `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`, dan kredensial CMS. **Sebelum seed admin, ubah `CMS_ADMIN_PASSWORD` menjadi password lokal yang kuat. Jangan pakai default ini untuk server publik.**
5. Pasang dependency, siapkan kunci, database, admin, dan aset:

   ```powershell
   composer install
   npm install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed --class=AdminUserSeeder
   npm run build
   ```

6. Validasi CSV dengan dry-run terlebih dahulu. Dry-run menampilkan jumlah baris valid/tidak valid, kecocokan yang sudah ada, rekaman baru, duplikasi di sumber, dan distribusi kelas tanpa menulis ke database:

   ```powershell
   php artisan ember:import-hotspots "storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv" --dry-run
   ```

   Setelah hasil diperiksa dan backup database tersedia, jalankan impor tanpa opsi destruktif. Identitas FIRMS stabil dan impor berulang melewati rekaman yang sudah cocok:

   ```powershell
   php artisan ember:import-hotspots "storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv"
   ```

   Baris tidak valid membatalkan impor secara atomik. Nama kelas disimpan pada kolom `land_cover` sesuai skema `titik_lokasi` saat ini.
7. Jalankan aplikasi:

   ```powershell
   php artisan serve
   ```

   Buka URL yang dicetak Artisan. Halaman CMS tersedia melalui `/cms/login`. Nilai login ditentukan oleh `CMS_ADMIN_EMAIL` dan `CMS_ADMIN_PASSWORD` saat seeder dijalankan.

## Komponen model

- **HSE:** confidence-weighted hotspot density, agregasi kelas untuk periode referensi 2021–2023, min–max skala 0–100. MODIS confidence digunakan sebagai bobot pembentuk HSE, bukan input fuzzy tersendiri.
- **HBE:** baseline kandidat 50:50 dari skor proporsi footprint historis yang pernah terbakar dan skor rerata frekuensi pada footprint tersebut; HBE pra-2024 masih provisional karena direkonstruksi dari raster frekuensi 2000–2024 dan mask 2024.
- **FSI:** output Zero-Order Sugeno dari HSE dan HBE. Nilai membership, rule base dan konstanta output masih draft penelitian.
- **13 kelas aktif:** ID `3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76`.

## Data yang sudah disertakan

Sumber tersedia di `storage/app/ember/source/`, termasuk CSV hotspot 33.209 baris, tabel evidence 13 kelas, luas kelas per provinsi, batas GeoJSON GAUL 2025 untuk 10 provinsi, workbook ringkasan, serta tiga raster MapBiomas Fire (`burned_coverage_2024.tif`, `fire_accumulated_2000_2024.tif`, `fire_frequency_2000_2024.tif`). Laravel tidak memproses ulang raster setiap request; skor yang dipakai runtime berada di `config/ember.php`.

## Klasifikasi hotspot MapBiomas otomatis

Hotspot baru NASA FIRMS diklasifikasi terhadap raster Collection 4 2024 pada koordinat latitude/longitude sebelum atau langsung saat persisten database. Rute ini memakai server raster Python persistent dan crosswalk EMBER yang berwenang. Dokumentasi operasional:

- `docs/ember/MAPBIOMAS_PIPELINE_ARCHITECTURE.md`
- `docs/ember/MAPBIOMAS_DATA_DICTIONARY.md`
- `docs/ember/NASA_FIRMS_CLASSIFICATION_WORKFLOW.md`
- `docs/ember/MAPBIOMAS_VALIDATION_REPORT.md`
- `docs/ember/EMBER_OPERATIONS_RUNBOOK.md`
- `docs/ember/MAPBIOMAS_TESTING_GUIDE.md`

## Pemeriksaan & debugging

```powershell
php artisan migrate:status
php artisan ember:import-hotspots --help
php artisan test
npm run build
```

Paket pembuatan diuji dengan `php -l` pada file PHP yang tersedia, `node --check` pada JavaScript, smoke test service untuk 13 kelas dan FSI 0–100 serta monotonicity input grid. `php artisan test` belum dapat dijalankan di lingkungan paket ini karena ekstensi PHP `dom`, `mbstring`, dan `xmlwriter` tidak terpasang; jalankan kembali di lingkungan lokal. Build Vite baru juga harus dijalankan setelah `npm install` bersih.

## Dokumen lengkap

- `docs/EMBER_Dokumentasi_Teknis_dan_Metodologi_HSE_HBE.md` — dokumentasi teknis lengkap.
- `docs/EMBER_Dokumentasi_Laravel_dan_Metodologi_HSE_HBE.docx` — dokumen Word untuk dibaca/dibagikan.
- `docs/EMBER_Dokumentasi_Laravel_dan_Metodologi_HSE_HBE.pdf` — salinan PDF.
- `README_EMBER_FSI.md` — gambaran algoritma/model runtime.

## Catatan riset penting

HSE/HBE/FSI adalah metrik relatif tingkat kelas: hotspot pada kelas sama menerima nilai konteks yang sama pada versi model yang sama. Atribusi hotspot historis pada tabel ter-enrich saat ini menggunakan kelas referensi MapBiomas 2024. Untuk klaim temporal yang kuat, re-join hotspot 2021–2023 ke peta kelas tahun masing-masing. Validasi HBE pra-2024 perlu dibandingkan dengan raster annual 2000–2023 yang berdiri sendiri. Pastikan juga crosswalk legenda MapBiomas Landy dan MapBiomas Fire terdokumentasi.
