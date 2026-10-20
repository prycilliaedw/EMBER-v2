# EMBER — Dokumentasi Teknis Laravel dan Metodologi HSE–HBE

**Versi dokumentasi:** 1.0<br>
**Model runtime:** `EMBER-HSE-HBE-SUGENO-DRAFT-1.0.0`<br>
**Status:** prototipe eksperimental, belum divalidasi untuk penggunaan operasional<br>
**Wilayah:** 10 provinsi Sumatra
**Kelas:** seluruh 13 kelas terperinci MapBiomas Indonesia Landy aktif

---

## 1. Ringkasan eksekutif

EMBER adalah aplikasi Laravel untuk memetakan konteks tutupan lahan pada rekaman hotspot NASA FIRMS/MODIS serta menyajikan indeks relatif kerawanan kebakaran tingkat kelas. Setiap titik hotspot dikaitkan dengan satu dari 13 kelas tutupan lahan MapBiomas Landy. Model membandingkan dua input berbasis evidence:

1. **Hotspot Evidence (HSE):** ringkasan kepadatan hotspot berbobot confidence per kelas.
2. **Historical Burn Evidence (HBE):** ringkasan riwayat footprint area terbakar dan frekuensi kebakaran per kelas.

Kedua input diproses oleh draft Zero-Order Sugeno Fuzzy Inference System untuk menghasilkan Fire Susceptibility Index (FSI) pada skala relatif 0–100. FSI bukan probabilitas kebakaran, bukan konfirmasi kebakaran pada setiap hotspot, dan tidak membuktikan hubungan sebab-akibat. Tujuannya adalah membantu membandingkan konteks kelas dan evidence historis.

**Keputusan metodologis tetap:** seluruh 13 kelas terperinci aktif. Label seperti “non-fuel” atau “konteks” hanya dapat menjadi catatan interpretasi/QA; label tersebut tidak menyaring kelas keluar dari tabel maupun inferensi.

## 2. Pertanyaan penelitian dan batas interpretasi

EMBER mencoba menjawab:

- Hotspot berada pada kelas tutupan lahan apa?
- Seberapa kuat evidence hotspot untuk kelas tersebut?
- Seberapa besar footprint kelas yang pernah terbakar dan berapa frekuensi kebakaran historisnya?
- Bagaimana dua evidence itu dapat digabungkan menjadi indeks relatif untuk perbandingan kelas?

EMBER **tidak** menyatakan bahwa sebuah hotspot pasti akan berkembang menjadi api yang menyebar. Deteksi hotspot adalah observasi anomali termal; area terbakar adalah evidence perubahan permukaan yang dipetakan. Kedua produk mempunyai definisi, resolusi, kesalahan pengamatan, dan keterbatasan berbeda.

## 3. Cakupan kelas tutupan lahan

Semua kelas berikut tetap digunakan dalam pencarian evidence, analisis sensitivitas, dan model FIS.

| ID Landy | Nama kelas EMBER |
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

Kode kelompok yang menaungi beberapa kelas turunan tidak boleh dijumlahkan bersama kelas turunannya dalam agregasi yang sama karena akan menghitung luas ganda. Kode tidak teramati, jika ada dalam legenda produk, perlu dicatat sebagai QA dan tidak diperlakukan seolah-olah evidence kebakaran terukur.

## 4. Data sumber yang dikemas

Semua file sumber ada di `storage/app/ember/source/`. GeoTIFF disertakan sebagai arsip/data sumber; aplikasi tidak membaca dan memproses raster tersebut secara langsung setiap kali halaman web dibuka. Nilai evidence kelas yang digunakan pada runtime tersimpan di `config/ember.php`.

| File | Peran | Catatan |
|---|---|---|
| `EMBER_Hotspots_Enriched_Evidence_v2.csv` | Data hotspot ter-enrich | 33.209 rekaman; tahun 2021–2026 (2026 parsial); memiliki koordinat, tanggal, confidence, administrasi, dan ID kelas. |
| `EMBER_Final_Evidence_13Classes_All13.csv` | Tabel evidence 13 kelas | Menjadi sumber skor HSE/HBE dan metrik historis; kolom FSI dalam file ini adalah keluaran eksperimen sebelumnya, bukan nilai runtime otoritatif. |
| `EMBER_HBE04_Recomputed_13Classes_FINAL.csv` | Tabel HBE | Menyimpan evidence historis kandidat. |
| `EMBER_MapBiomas_Area_by_Province_and_Class_GAUL2025.csv` | Luas per provinsi dan kelas | Penyebut untuk indikator kelas; hasil ekspor terdahulu perlu dicocokkan dengan wilayah/legenda sebelum publikasi. |
| `EMBER_AOI_10Provinsi_Sumatra_GAUL2025_v2.geojson` | Batas analisis | 10 provinsi target GAUL 2025; geometri disertakan. |
| `burned_coverage_2024.tif` | Peta area terbakar tahunan 2024 | Produk raster MapBiomas Fire. |
| `fire_accumulated_2000_2024.tif` | Peta akumulasi area terbakar | Mencakup 2000–2024. |
| `fire_frequency_2000_2024.tif` | Peta frekuensi kebakaran | Mencakup 2000–2024. |
| `EMBER_MapBiomasFire_Historis_Kelas_10Provinsi_GAUL2025.xlsx` | Ringkasan raster | Hasil agregasi historis menurut kelas dan provinsi, untuk analisis offline. |
| `EMBER_MapBiomasFire_Historis_Kelas_10Provinsi_GAUL2025_Ringkasan_Kelas.csv` | Ringkasan kelas | Ringkasan area tahunan/kumulatif. |
| `EMBER_MapBiomasFire_Historis_Kelas_10Provinsi_GAUL2025_Ringkasan_Provinsi.csv` | Ringkasan provinsi | Ringkasan per provinsi. |

### 4.1 Pengecekan dataset hotspot

Pengecekan otomatis terhadap CSV yang dikemas menemukan 33.209 baris data dan 13 ID kelas yang diharapkan. Distribusi tahun pada data hotspot adalah:

| Tahun | Rekaman |
|---:|---:|
| 2021 | 4.066 |
| 2022 | 2.950 |
| 2023 | 11.894 |
| 2024 | 5.440 |
| 2025 | 5.252 |
| 2026 (parsial) | 3.607 |

Jumlah pada tabel menghasilkan total 33.209. Kolom yang diperlukan oleh importer saat pemeriksaan file ini tersedia dan nilai `CONFIDENCE` numerik pada semua rekaman. Penghitungan tersebut merupakan pemeriksaan struktur, bukan validasi kebenaran lokasi deteksi di lapangan.

## 5. Definisi dan formula evidence

### 5.1 Hotspot Evidence (HSE)

Untuk kelas tutupan lahan \(c\), confidence setiap rekaman hotspot digunakan sebagai bobot deskriptif. Implementasi sumber memakai normalisasi pada level kelas. Bentuk operasional dasarnya:

\[
W_c=\sum_{i\in c}\frac{C_i}{100}
\]

\[
D_c=\frac{W_c}{A_c}\times1000
\]

\[
x_c=\ln(1+D_c)
\]

\[
HSE_c=100\frac{x_c-\min(x)}{\max(x)-\min(x)}
\]

Keterangan:

- \(C_i\): confidence MODIS per rekaman, rentang nominal 0–100.
- \(A_c\): luas kelas dalam km².
- \(D_c\): confidence-weighted detections per 1.000 km².
- Normalisasi dilakukan pada 13 nilai kelas; bukan pada 33.209 baris yang mengulang skor kelas.

`C_i/100` adalah konvensi pembobotan EMBER, bukan probabilitas kebakaran. Confidence MODIS bukan input langsung Sugeno; ia digunakan pada perhitungan HSE.

Skor HSE runtime menggunakan rekaman 2021–2023 yang dikelompokkan menurut ID kelas pada file enrich yang disediakan. File enrich saat ini memakai kelas MapBiomas tahun 2024 untuk rekaman hotspot historis; oleh sebab itu interpretasinya adalah konteks kelas pada peta 2024, bukan selalu kelas yang ada pada tahun rekaman hotspot. Untuk analisis yang benar-benar year-matched, setiap hotspot perlu dipetakan ulang terhadap layer kelas pada tahun yang sama.

### 5.2 Historical Burn Evidence (HBE)

HBE menggabungkan dua indikator yang menggambarkan aspek berbeda:

1. Proporsi luas kelas pada footprint yang pernah terbakar sebelum 2024.
2. Rata-rata frekuensi kebakaran pada footprint yang pernah terbakar.

Untuk kelas \(c\):

\[
r_c=\frac{A_{\mathrm{ever-burned},c}}{A_{\mathrm{class},c}}
\]

\[
F_c=\operatorname{MinMax}_{13}\big(\ln(1+r_c)\big)\times100
\]

\[
M_c=\text{rata-rata frekuensi pada footprint terbakar kelas }c
\]

\[
R_c=\operatorname{MinMax}_{13}\big(\ln(1+M_c)\big)\times100
\]

\[
HBE_c=0.5F_c+0.5R_c
\]

**Satuan penting:** \(r_c\) harus berupa proporsi 0–1 ketika masuk ke \(\ln(1+r_c)\), bukan angka persen (misalnya 17,6). Normalisasi memakai semua 13 kelas. Bobot 50:50 adalah desain baseline eksploratif agar footprint dan pengulangan mendapat bobot awal yang sama; belum dibuktikan sebagai bobot optimal.

### 5.3 Status HBE pra-2024

Raster yang tersedia saat ini adalah frekuensi 2000–2024 dan mask tahunan 2024. HBE pra-2024 pernah direkonstruksi dengan penyelarasan grid dan memisahkan kontribusi 2024. Ini adalah pendekatan praktis untuk eksperimen, tetapi tidak menggantikan verifikasi memakai raster tahunan 2000–2023 yang berdiri sendiri. Raster tahunan perlu diproses dan dibandingkan sebelum angka HBE disebut final/tervalidasi.

### 5.4 Nilai kelas yang digunakan runtime

Angka berikut dipakai di `config/ember.php`; HSE/HBE per kelas dan FSI draft dihitung dengan service runtime. FSI dapat berbeda dari kolom draft dalam CSV evidence lama karena kolom FSI lama berasal dari eksperimen sebelumnya.

| ID | Kelas | HSE | HBE | FSI runtime (draft) | Label FSI |
|---:|---|---:|---:|---:|---|
| 3 | Formasi Hutan | 22.356 | 8.691 | 30.00 | Low |
| 5 | Mangrove | 0.000 | 1.975 | 14.54 | Very Low |
| 9 | Kebun Kayu | 78.568 | 59.421 | 90.00 | Very High |
| 13 | Tumbuhan Non-Hutan Lainnya | 82.601 | 88.477 | 90.00 | Very High |
| 21 | Pertanian Lainnya | 54.712 | 32.208 | 74.83 | High |
| 24 | Pemukiman | 54.834 | 18.229 | 62.03 | High |
| 25 | Non-Vegetasi Lainnya | 100.000 | 99.172 | 90.00 | Very High |
| 30 | Lubang Tambang | 65.941 | 21.043 | 81.88 | Very High |
| 31 | Tambak | 4.482 | 3.448 | 20.35 | Low |
| 33 | Sungai, Danau, Laut | 68.291 | 24.794 | 90.00 | Very High |
| 35 | Sawit | 36.773 | 39.725 | 68.65 | High |
| 40 | Sawah | 44.870 | 55.473 | 83.92 | Very High |
| 76 | Hutan Rawa Gambut | 17.389 | 5.986 | 28.62 | Low |

Tabel ini harus dibaca sebagai output draft dari rule base sekarang, bukan kebenaran ekologis yang sudah tervalidasi. Sebagai contoh, skor tinggi pada kelas sungai/perairan bukan bukti bahwa badan air adalah bahan bakar; itu dapat timbul karena pola HSE/HBE dan aturan Sugeno saat ini. Karena semua kelas wajib tetap aktif, tindakan yang tepat adalah mempertahankan kelas dan menafsirkan skor secara transparan, lalu meninjau rule base pada validasi berikutnya—bukan menghapus kelas secara otomatis.

## 6. Zero-Order Sugeno FIS

### 6.1 Input

- Input 1: HSE 0–100.
- Input 2: HBE 0–100.
- NASA confidence tidak menjadi input fuzzy terpisah.

### 6.2 Membership function

Anchor kuartil yang digunakan:

| Input | Q1 | Q2 | Q3 |
|---|---:|---:|---:|
| HSE | 22.356 | 54.712 | 68.291 |
| HBE | 8.691 | 24.794 | 55.473 |

Untuk masing-masing input, konfigurasi draft membentuk empat fuzzy set: Very Low (left shoulder), Low (triangle 0–Q1–Q2), Moderate (triangle Q1–Q2–Q3), dan High (right shoulder Q2–Q3). Ini dipilih agar anchor berasal dari distribusi 13 skor kelas, bukan angka literatur yang dipinjam lintas studi. Karena hanya ada 13 unit kelas, estimasi kuartil perlu diperlakukan sebagai parameter eksploratif.

### 6.3 Rule base

| HSE \ HBE | Very Low | Low | Moderate | High |
|---|---|---|---|---|
| Very Low | Very Low | Low | Moderate | Moderate |
| Low | Low | Low | Moderate | High |
| Moderate | Moderate | Moderate | High | Very High |
| High | Moderate | High | Very High | Very High |

Konsekuen Sugeno orde nol:

| Label keluaran | Konstanta |
|---|---:|
| Very Low | 10 |
| Low | 30 |
| Moderate | 50 |
| High | 70 |
| Very High | 90 |

Kekuatan tembak aturan adalah:

\[
w_j=\mu_{HSE,j}\times\mu_{HBE,j}
\]

Keluaran FSI:

\[
FSI=\frac{\sum_j w_jz_j}{\sum_j w_j}
\]

FSI dibatasi pada 0–100. Klasifikasi tampilan saat ini: `<20` Very Low; `<40` Low; `<60` Moderate; `<80` High; selain itu Very High.

Rule base dan konstanta 10/30/50/70/90 merupakan keputusan desain EMBER yang eksplisit, bukan hasil yang dikutip dari jurnal. Rule base ini masih perlu diuji terhadap data independen dan dapat direvisi tanpa mengecualikan kelas.

## 7. Implementasi Laravel

### 7.1 Komponen utama

| Path | Fungsi |
|---|---|
| `app/Services/FireSusceptibilityService.php` | Fungsi lookup HSE/HBE, membership fuzzy, inferensi Sugeno, label FSI dan flag divergence. |
| `config/ember.php` | Versi model, semua 13 class scores, kuartil, rule base dan konstanta output. |
| `app/Console/Commands/ImportEmberHotspots.php` | Impor CSV hotspot ter-enrich ke tabel `titik_lokasi`, mengisi evidence dan FSI. |
| `database/migrations/2026_10_20_000001_add_hse_hbe_evidence_to_titik_lokasi.php` | Menambahkan kolom HSE, HBE, versi model dan indeks. |
| `resources/views/user/map.blade.php` | Panel detail hotspot menampilkan HSE, HBE, FSI, kelas, dan QA flag. |
| `resources/views/user/location-detail.blade.php` | Detail satu titik dengan HSE/HBE dan keterangan FSI relatif eksperimental. |
| `resources/views/user/methodology.blade.php` | Halaman metodologi bilingual. |
| `resources/views/cms/locations/show.blade.php` | Detail rekaman pada CMS. |
| `resources/js/app.js` | Normalisasi data peta dan rendering panel detail. |
| `public/build/manifest.json` | Manifest aset produksi yang menunjuk bundle JS. |
| `tests/Unit/FireSusceptibilityServiceTest.php` | Unit test yang disiapkan untuk service. |

### 7.2 Perubahan skema

Kolom baru:

- `hotspot_evidence` — decimal (6,3), nullable.
- `historical_burn_evidence` — decimal (6,3), nullable.
- `model_version` — string (64), nullable.

Kolom legacy `prior_lcs`, `empirical_evidence`, dan `hybrid_lcs` dipertahankan agar tidak mematahkan skema lama, tetapi bukan input model baru. Importer mengisinya dengan `NULL` dan menggunakan kolom HSE/HBE yang eksplisit.

### 7.3 Impor data

Importer menerima `LAND_COVER_ID` atau `MAPBIOMAS_ID`, lalu mewajibkan koordinat, tanggal, confidence, metadata satelit/instrumen, siang/malam, dan atribut administrasi yang tersedia pada CSV ter-enrich. Kelas yang tidak ada dalam konfigurasi 13 kelas menyebabkan proses berhenti dengan pesan error, bukan diberi skor tebakan.

Penting: HSE/HBE diambil dari konfigurasi kelas. Akibatnya, seluruh titik dari kelas yang sama memperoleh HSE, HBE, dan FSI kelas yang sama pada versi model tertentu. Ini model class-context, bukan model FSI piksel-lokasi yang memakai lingkungan sekitar tiap koordinat.

## 8. Instalasi lokal (Windows / PowerShell)

### 8.1 Prasyarat

- PHP 8.3 atau versi yang sesuai `composer.json`.
- Composer.
- Node.js dan npm.
- Database yang didukung Laravel; SQLite paling mudah untuk uji lokal, MySQL dapat dipakai sesuai konfigurasi.
- Ekstensi PHP yang disyaratkan `composer.json` dan PHPUnit.

### 8.2 Langkah instalasi

1. Ekstrak ZIP proyek ke folder kerja, misalnya `D:\capstone\ember-neko-main`.
2. Buka PowerShell di folder proyek.
3. Buat `.env` dari `.env.example`:

```powershell
Copy-Item .env.example .env
```

4. Atur `DB_CONNECTION`, kredensial database, dan `APP_URL` di `.env`. Jangan membagikan `.env` atau kredensial database.
5. Instal dependency dan siapkan key:

```powershell
composer install
npm install
php artisan key:generate
```

6. Jalankan migrasi:

```powershell
php artisan migrate
php artisan migrate:status
```

7. Build aset front-end:

```powershell
npm run build
```

8. Impor hotspot yang disediakan. **Backup database dahulu.** Opsi `--truncate` akan mengosongkan tabel `titik_lokasi` sebelum impor:

```powershell
php artisan ember:import-hotspots "storage/app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv" --truncate
```

9. Jalankan server:

```powershell
php artisan serve
```

Buka URL lokal yang dicetak oleh Artisan. Login CMS berada di `/cms/login`; seeder membaca `CMS_ADMIN_EMAIL` dan `CMS_ADMIN_PASSWORD` dari `.env`. Ganti password contoh sebelum seed dan jangan gunakan kredensial contoh pada server publik. Jika memakai mode pengembangan aset, jalankan `npm run dev` pada terminal kedua.

### 8.3 Mengganti data / menjalankan impor ulang

1. Backup database.
2. Pastikan kolom pada CSV sesuai dokumentasi dan ID kelas termasuk salah satu dari 13 ID.
3. Jalankan impor. `--truncate` mengganti seluruh rekaman hotspot; tanpa flag ini data akan ditambahkan dan duplikasi tidak dideteksi otomatis.
4. Periksa jumlah baris, distribusi kelas, nilai HSE/HBE kosong, distribusi FSI, dan tanggal terawal/terakhir.
5. Pastikan `model_version` terisi `EMBER-HSE-HBE-SUGENO-DRAFT-1.0.0` pada setiap rekaman baru.

## 9. Quality assurance yang dilakukan saat penyiapan paket

### 9.1 Pemeriksaan yang lolos

- PHP lint terhadap service, importer, config, migration, controller dan template yang diubah.
- `node --check` untuk `resources/js/app.js` dan bundle JavaScript produksi yang disertakan.
- Smoke test langsung terhadap service dengan shim konfigurasi: semua 13 kelas tersedia; semua FSI finite pada 0–100; peningkatan HSE atau HBE pada input grid tidak menurunkan FSI untuk rule base yang sekarang.
- Validasi CSV sumber: 33.209 baris; ID kelas hanya berisi 13 kelas yang diharapkan; confidence numerik dan kolom wajib tersedia.

### 9.2 Pemeriksaan yang belum dapat dijalankan di lingkungan pembuat paket

- PHPUnit/feature test integrasi Laravel penuh.
- Migrasi database dan impor nyata ke database aplikasi.
- Rebuild Vite bersih. Lingkungan penyiapan kekurangan ekstensi PHP yang dibutuhkan oleh sebagian test dan optional native binding Vite/Rolldown. Bundle produksi yang disertakan diperiksa sintaksnya, tetapi setelah instalasi dependency di komputer pengguna sebaiknya jalankan `npm install` dan `npm run build`.

Karena batasan ini, hasil disebut pemeriksaan sintaks dan smoke test, bukan pengujian end-to-end penuh.

## 10. Keterbatasan dan risiko metodologis

1. **HBE pra-2024 rekonstruksi sementara.** Raster tahunan 2000–2023 tetap dibutuhkan untuk mengonfirmasi hasil tanpa bergantung pada pengurangan raster frekuensi dan mask 2024.
2. **Atribusi kelas historis.** CSV enrichment sekarang memakai klasifikasi 2024 untuk hotspot historis; itu dapat berbeda dari tutupan lahan pada tahun kejadian. Re-join ke peta tahun kejadian diperlukan untuk klaim historis yang lebih kuat.
3. **Versi legenda.** Peta Landy/MapBiomas Land Cover dan MapBiomas Fire harus disamakan atau crosswalk terdokumentasi. Kesamaan ID saja tidak membuktikan kesetaraan definisi kelas.
4. **Validasi terbatas.** Korelasi kelas dengan 13 observasi agregat bukan bukti prediksi spasial; hasil dipengaruhi ukuran kelas, ketidakpastian pemetaan, dan keterkaitan sumber data.
5. **Seluruh kelas dipertahankan.** Ini keputusan proyek. Kelas perairan, permukiman, tambak, tambang dan non-vegetasi tetap aktif, tetapi skor yang muncul harus ditafsirkan hati-hati. Tipe kelas adalah QA/interpretasi dan tidak menjadi filter eksklusi.
6. **FSI bukan probabilitas.** Nilai 80 tidak berarti peluang kebakaran 80%. FSI adalah skor relatif yang bergantung pada normalisasi, membership function dan rule base.
7. **HSE/HBE level kelas.** Semua titik di kelas yang sama berbagi skor; nilai ini tidak membedakan kepadatan lokal per desa atau konteks spasial tetangga.
8. **Sensor dan temporal overlap.** Hotspot dan fire products adalah pengamatan satelit yang dapat saling berkaitan; validasi ideal memerlukan waktu uji terpisah dan/atau sumber pembanding tambahan.

## 11. Pekerjaan riset lanjutan yang disarankan

1. Unduh dan proses raster area terbakar tahunan 2000–2023; cocokkan grid, AOI, legenda dan unit area.
2. Atribusikan ulang hotspot 2021–2023 pada peta Landy tahun kejadian jika pertanyaan riset menuntut kelas temporal.
3. Bandingkan burn footprint dengan produk area terbakar pembanding, misalnya NASA MCD64A1, dengan mencatat perbedaan resolusi dan kesalahan produk.
4. Lakukan analisis sensitivitas HBE dan komposit pada seluruh 13 kelas; simpan skenario, parameter, metrik dan peringkat.
5. Tinjau kembali membership/rule base dengan validasi yang terpisah; jangan memilih bobot hanya karena paling berkorelasi dengan tahun uji yang sama.
6. Catat setiap pembaruan skor beserta versi model pada database dan dokumentasi.

## 12. Referensi data dan halaman produk

- NASA FIRMS: https://firms.modaps.eosdis.nasa.gov/
- MapBiomas Indonesia Landy / koleksi: https://landy.mapbiomas.id/id/collectionmap
- MapBiomas Indonesia Fire downloads: https://fire.mapbiomas.id/id/downloads
- NASA MODIS MCD64A1 burned area: https://developers.google.com/earth-engine/datasets/catalog/MODIS_061_MCD64A1
- Google Earth Engine Export.table.toDrive: https://developers.google.com/earth-engine/apidocs/export-table-todrive

---

## Lampiran A — Perintah pemeriksaan setelah instalasi

```powershell
php artisan migrate:status
php artisan ember:import-hotspots --help
php artisan test
npm run build
```

Perintah `php artisan test` dan `npm run build` wajib dijalankan lagi di lingkungan lokal dengan dependency terpasang penuh. Jika gagal, simpan pesan error lengkap sebelum mengubah kode.

## Lampiran B — Keputusan desain yang eksplisit

- Tidak memakai Prior LCS sebagai input model baru.
- HSE dan HBE terpisah sebagai dua input Sugeno.
- NASA confidence hanya digunakan pada tahap pembentukan HSE, bukan input fuzzy langsung.
- Semua 13 kelas Landy tetap aktif tanpa pengecualian.
- Nilai HSE/HBE saat ini level kelas dan versioned.
- FSI adalah indeks relatif eksperimental, bukan probabilitas.
- Kode Laravel belum diklaim tervalidasi end-to-end sampai migration, import, UI dan build dijalankan di komputer pengguna.
