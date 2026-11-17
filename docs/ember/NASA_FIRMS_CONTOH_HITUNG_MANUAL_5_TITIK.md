# Contoh Hitung Manual: 5 Titik NASA FIRMS sampai FSI

> **Status contoh:** seluruh baris FIRMS dan koordinat di dokumen ini simulasi, bukan respons API aktual, hasil database, atau keluaran sampling raster aktual. Kelas raster di bawah diasumsikan sebagai keluaran contoh supaya cara hitung dapat ditelusuri. Nama administrasi tidak ditebak; titik nyata harus dicocokkan ke poligon administrasi yang dipasang pada sistem.

## 1. Alur end-to-end

```text
NASA_FIRMS_MAP_KEY -> permintaan area/csv -> validasi dan AOI
  -> pencocokan administrasi -> sampling raster MapBiomas
  -> raw class ID -> crosswalk EMBER -> HSE/HBE baseline kelas
  -> fuzzy membership -> aturan Sugeno -> FSI dan kategori
```

Key FIRMS disimpan di environment server (`NASA_FIRMS_MAP_KEY`), tidak ditulis di source code atau dibagikan. Bentuk endpoint aplikasi:

```text
{NASA_FIRMS_BASE_URL}/area/csv/{API_KEY}/{SOURCE}/{west,south,east,north}/{days}
```

Sumber default mencakup `VIIRS_NOAA20_NRT`, `VIIRS_NOAA21_NRT`, `VIIRS_SNPP_NRT`, dan `MODIS_NRT`. Aplikasi membaca CSV, memvalidasi koordinat serta tanggal akuisisi, lalu mengecek titik ke poligon AOI Sumatra. Bounding box hanya membatasi respons API; point-in-polygon AOI menentukan titik yang diterima. Baris yang lolos disimpan dengan atribut NASA dan dikirim ke queue klasifikasi.

## 2. Lima baris respons API simulasi

| Titik | Produk | Tanggal | Waktu UTC | Latitude | Longitude | Confidence | FRP |
|---|---|---|---:|---:|---:|---:|---:|
| P1 | VIIRS_SNPP_NRT | 2026-11-15 | 0612 | -0.50 | 101.20 | 18 | 4.2 |
| P2 | VIIRS_NOAA20_NRT | 2026-11-15 | 0630 | -1.20 | 102.10 | 42 | 7.8 |
| P3 | VIIRS_NOAA21_NRT | 2026-11-15 | 0706 | -2.10 | 103.40 | 76 | 11.5 |
| P4 | MODIS_NRT | 2026-11-15 | 0418 | -0.90 | 100.50 | 85 | 18.1 |
| P5 | VIIRS_SNPP_NRT | 2026-11-15 | 0712 | -3.00 | 102.70 | 96 | 25.6 |

Label confidence deskriptif dari implementasi: nilai `<30` = Low, `30–<80` = Nominal, `>=80` = High. Confidence tidak digunakan untuk menghitung HSE/HBE/FSI titik. Ia tetap atribut observasi FIRMS.

## 3. Pencocokan lokasi dan tutupan lahan

Untuk setiap titik, job melakukan pencocokan spasial ke administrasi, lalu sampler membaca piksel raster MapBiomas Collection 4. Raw class ID kemudian diterjemahkan oleh crosswalk terverifikasi. Hasil contoh berikut adalah **asumsi output**—koordinat simulasi tidak dijalankan terhadap raster atau geometri proyek.

| Titik | Hasil administrasi pada sistem nyata | Raw class (asumsi) | ID EMBER | Kelas EMBER |
|---|---|---:|---:|---|
| P1 | provinsi/kabupaten/kecamatan/desa dari matcher poligon | 5 | 5 | Mangrove |
| P2 | provinsi/kabupaten/kecamatan/desa dari matcher poligon | 3 | 3 | Formasi Hutan |
| P3 | provinsi/kabupaten/kecamatan/desa dari matcher poligon | 24 | 24 | Pemukiman |
| P4 | provinsi/kabupaten/kecamatan/desa dari matcher poligon | 35 | 35 | Sawit |
| P5 | provinsi/kabupaten/kecamatan/desa dari matcher poligon | 13 | 13 | Tumbuhan Non-Hutan Lainnya |

Matcher menggunakan batas provinsi dan `village.ndjson`; titik di batas atau poligon yang tidak unik dapat menghasilkan nilai administrasi kosong. ID 3, 5, 13, 24, dan 35 dipetakan ke ID EMBER yang sama pada crosswalk fallback saat ini. Raster/crosswalk aktual tetap otoritatif. Jika raster hilang, piksel NoData, koordinat di luar raster, atau kelas tidak terpetakan, status klasifikasi harus menyatakan kegagalan/ketidaktersediaan; jangan menetapkan kelas secara manual.

## 4. HSE dan HBE kelas

HSE (Hotspot Evidence) dan HBE (Historical Burn Evidence) runtime adalah baseline tingkat kelas dari `config/ember.php`. Nilai ini tidak dihitung dari lima deteksi simulasi. Titik dengan kelas yang sama akan menerima baseline yang sama pada versi model yang sama.

| Titik | Kelas | HSE | HBE |
|---|---|---:|---:|
| P1 | Mangrove (5) | 0.000 | 1.975 |
| P2 | Formasi Hutan (3) | 22.356 | 8.691 |
| P3 | Pemukiman (24) | 54.834 | 18.229 |
| P4 | Sawit (35) | 36.773 | 39.725 |
| P5 | Tumbuhan Non-Hutan Lainnya (13) | 82.601 | 88.477 |

Anchor membership yang dikonfigurasi:

| Input | Q1 | Q2 | Q3 |
|---|---:|---:|---:|
| HSE | 22.356 | 54.712 | 68.291 |
| HBE | 8.691 | 24.794 | 55.473 |

Empat set fuzzy untuk masing-masing input: Very Low, Low, Moderate, High. Rumus yang relevan:

- `0 < x < Q1`: `Very Low = (Q1-x)/Q1`, `Low = x/Q1`.
- `Q1 < x < Q2`: `Low = (Q2-x)/(Q2-Q1)`, `Moderate = (x-Q1)/(Q2-Q1)`.
- `Q2 < x < Q3`: `Moderate = (Q3-x)/(Q3-Q2)`, `High = (x-Q2)/(Q3-Q2)`.
- Tepat Q1: Low = 1; tepat Q2: Moderate = 1; tepat/di atas Q3: High = 1.
- Membership lain yang tidak aktif bernilai 0.

Konsekuen Zero-Order Sugeno: Very Low = 10, Low = 30, Moderate = 50, High = 70, Very High = 90. Basis aturan konfigurasi:

| HSE \ HBE | Very Low | Low | Moderate | High |
|---|---|---|---|---|
| Very Low | Very Low | Low | Moderate | Moderate |
| Low | Low | Low | Moderate | High |
| Moderate | Moderate | Moderate | High | Very High |
| High | Moderate | High | Very High | Very High |

Setiap aturan aktif diberi bobot `w = μ(HSE) × μ(HBE)`. Rumus akhirnya:

```text
FSI = Σ(w × nilai konsekuen) / Σ(w)
```

Nilai input dibatasi pada 0–100; FSI dibulatkan dua angka desimal. Label runtime: `<20` Very Low, `<40` Low, `<60` Moderate, `<80` High, dan selebihnya Very High.

## 5. Hitung manual tiap titik

### P1 — Mangrove (5): hasil Very Low

HSE 0.000 tepat di batas bawah, maka `μHSE(Very Low)=1`. HBE 1.975 berada di bawah Q1 HBE:

```text
μHBE(Very Low) = (8.691 - 1.975) / 8.691 = 0.7727
μHBE(Low)      = 1.975 / 8.691         = 0.2273
```

| Aturan aktif | Bobot | Konsekuen |
|---|---:|---:|
| Very Low / Very Low | `1 × 0.7727 = 0.7727` | 10 |
| Very Low / Low | `1 × 0.2273 = 0.2273` | 30 |

```text
FSI = (0.7727×10 + 0.2273×30) / (0.7727+0.2273)
    = 14.55 -> Very Low
```

### P2 — Formasi Hutan (3): hasil Low

HSE = Q1 HSE dan HBE = Q1 HBE; membership Low masing-masing bernilai 1. Aturan Low/Low menghasilkan Low = 30:

```text
FSI = (1×30) / 1 = 30.00 -> Low
```

### P3 — Pemukiman (24): hasil High

HSE 54.834 berada antara Q2 dan Q3; HBE 18.229 antara Q1 dan Q2:

```text
μHSE(Moderate) = (68.291 - 54.834)/(68.291 - 54.712) = 0.9910
μHSE(High)     = (54.834 - 54.712)/(68.291 - 54.712) = 0.0090
μHBE(Low)      = (24.794 - 18.229)/(24.794 - 8.691) = 0.4077
μHBE(Moderate) = (18.229 - 8.691)/(24.794 - 8.691) = 0.5923
```

| Kombinasi aturan | Bobot (`μHSE × μHBE`) | Konsekuen |
|---|---:|---:|
| Moderate / Low | 0.4040 | 50 |
| Moderate / Moderate | 0.5870 | 70 |
| High / Low | 0.0037 | 70 |
| High / Moderate | 0.0053 | 90 |

```text
FSI = (0.4040×50 + 0.5870×70 + 0.0037×70 + 0.0053×90) / 1.0000
    ≈ 62.03 -> High
```

### P4 — Sawit (35): hasil High

HSE 36.773 berada antara Q1 dan Q2; HBE 39.725 antara Q2 dan Q3:

```text
μHSE(Low)      = (54.712 - 36.773)/(54.712 - 22.356) = 0.5544
μHSE(Moderate) = (36.773 - 22.356)/(54.712 - 22.356) = 0.4456
μHBE(Moderate) = (55.473 - 39.725)/(55.473 - 24.794) = 0.5133
μHBE(High)     = (39.725 - 24.794)/(55.473 - 24.794) = 0.4867
```

| Kombinasi aturan | Bobot (`μHSE × μHBE`) | Konsekuen |
|---|---:|---:|
| Low / Moderate | 0.2846 | 50 |
| Low / High | 0.2698 | 70 |
| Moderate / Moderate | 0.2288 | 70 |
| Moderate / High | 0.2168 | 90 |

```text
FSI = (0.2846×50 + 0.2698×70 + 0.2288×70 + 0.2168×90) / 1.0000
    ≈ 68.65 -> High
```

### P5 — Tumbuhan Non-Hutan Lainnya (13): hasil Very High

HSE 82.601 dan HBE 88.477 masing-masing lebih tinggi dari Q3. Jadi `μHSE(High)=1` dan `μHBE(High)=1`. Aturan High/High menghasilkan Very High = 90:

```text
FSI = (1×90) / 1 = 90.00 -> Very High
```

## 6. Rekap

| Titik | Confidence NASA | Kelas tutupan lahan | HSE | HBE | FSI | Kategori |
|---|---|---|---:|---:|---:|---|
| P1 | 18 (Low) | Mangrove | 0.000 | 1.975 | 14.55 | Very Low |
| P2 | 42 (Nominal) | Formasi Hutan | 22.356 | 8.691 | 30.00 | Low |
| P3 | 76 (Nominal) | Pemukiman | 54.834 | 18.229 | 62.03 | High |
| P4 | 85 (High) | Sawit | 36.773 | 39.725 | 68.65 | High |
| P5 | 96 (High) | Tumbuhan Non-Hutan Lainnya | 82.601 | 88.477 | 90.00 | Very High |

Confidence NASA di tabel hanya label atribut; tidak mengubah FSI. Dengan baseline kelas yang saat ini dikonfigurasi, contoh ini memperlihatkan empat kategori berbeda, dengan dua titik pada High. Jangan mengarang pasangan HSE/HBE untuk mendapatkan label Moderate: nilai nyata harus bersumber dari konfigurasi kelas yang disetujui.

## 7. Batas interpretasi dan reproduksi

- HSE/HBE/FSI di EMBER saat ini baseline eksperimental tingkat kelas; titik pada kelas yang sama berbagi nilainya.
- HSE menggunakan bukti hotspot 2021–2023 berbobot confidence dan konteks luas kelas. HBE historis pra-2024 direkonstruksi dari raster frekuensi dan mask terbakar 2024.
- FSI bukan probabilitas kebakaran, bukan confidence NASA, dan bukan prediksi bahwa satu titik pasti terbakar.
- Kode endpoint/validasi: `app/Services/NasaFirmsSyncService.php`; job klasifikasi: `app/Jobs/ClassifyHotspotLandCover.php`; raster/crosswalk: `app/Services/MapBiomasSpatialMatcher.php`; rumus: `app/Services/FireSusceptibilityService.php`; nilai/aturan: `config/ember.php`.
- Untuk contoh nyata yang dapat direproduksi, simpan respons FIRMS asli, produk dan waktu akuisisi, koordinat, hasil administrasi, raster/tahun yang disampel, raw class ID, baris crosswalk, versi model, serta timestamp klasifikasi. Jangan simpan API key dalam laporan.
