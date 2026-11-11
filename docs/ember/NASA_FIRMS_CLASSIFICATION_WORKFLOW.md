# NASA FIRMS Classification Workflow

Dokumen end-to-end saat ini dipelihara di [EMBER: Metodologi dan Alur Otomatis End-to-End](EMBER_END_TO_END_METHODOLOGY.md). Dokumen itu menjelaskan pemanggilan FIRMS, jendela dua hari, jadwal scheduler, upsert/deduplikasi, pencocokan provinsi sampai desa, raster/crosswalk MapBiomas, queue, HSE/HBE/FSI, serta keseimbangan statistik.

## Ringkasan operasional

- Jadwal aplikasi menjalankan sync FIRMS per jam dengan jendela default dua hari (`NASA_FIRMS_OVERLAP_DAYS=2`). Dua hari adalah periode data yang diminta setiap eksekusi, bukan frekuensi scheduler.
- OS harus menjalankan `php artisan schedule:run` setiap menit. Lihat `php artisan schedule:list` untuk jadwal yang didefinisikan.
- Job klasifikasi memakai queue `ember-classification`. Scheduler menjalankan worker singkat setiap menit; untuk pemrosesan manual gunakan `php artisan queue:work --queue=ember-classification --tries=3 --timeout=45 --memory=512`.
- Titik baru memerlukan sumber raster/crosswalk dan executable Python. Pencocokan administrasi tingkat desa juga memerlukan `village.ndjson` dan script matcher.
- HSE/HBE merupakan baseline tetap tingkat kelas; FSI Sugeno dihitung dari keduanya. Nilai FSI bukan probabilitas kebakaran.

## Pemeriksaan aman

```powershell
php artisan schedule:list
php artisan ember:sync-firms --days=2 --dry-run --limit=1
```

Dry-run mengambil dan memeriksa respons tanpa menulis database atau mengantrekan klasifikasi. Untuk persyaratan file, troubleshooting queue, dan aturan rekonsiliasi statistik, ikuti dokumen end-to-end di atas.
