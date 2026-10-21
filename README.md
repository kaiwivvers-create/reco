# SuppliRank — Sistem Informasi Rekomendasi Supplier

Aplikasi web untuk **Analisis dan Perancangan Sistem Informasi Rekomendasi Supplier Berbasis Multi-Criteria Decision Making (MCDM) dan Explainable AI**.

Sistem membantu tim pengadaan membandingkan supplier berdasarkan beberapa kriteria berbobot (harga, kualitas, waktu pengiriman, keandalan, dan kriteria lain yang dapat ditambah sendiri), lalu menjelaskan **mengapa** supplier tertentu direkomendasikan.

Rekomendasi tidak mengasumsikan supplier termurah sebagai pilihan terbaik — seluruh kriteria dihitung sesuai bobot dan arahnya (benefit/cost).

---

## Fitur utama

| Halaman | Fungsi |
|---|---|
| **Dashboard** | Ringkasan supplier, kriteria, evaluasi, rekomendasi terakhir, kelengkapan data, dan aktivitas terbaru. |
| **Data Supplier** | CRUD supplier beserta nilai mentah setiap kriteria, status aktif, kontak, dan riwayat evaluasi. |
| **Kriteria & Bobot** | Tambah/ubah/hapus kriteria, tipe benefit/cost, satuan, bobot, serta penyimpanan bobot massal. |
| **Evaluasi & Ranking** | Pilih supplier, kriteria, bobot, dan metode **SAW** atau **TOPSIS**; hasil disimpan sebagai snapshot. |
| **Explainable AI** | Kontribusi per kriteria, alasan pemenang, perbandingan head-to-head, analisis kontrafaktual, sensitivitas bobot ±20%, dan narasi opsional berbantuan LLM. |
| **Laporan** | Laporan siap cetak dengan ekspor **PDF**, **Excel (.xlsx)**, dan **print** browser. |
| **Riwayat Aktivitas** | Audit trail perubahan data (sebelum/sesudah), revert edit, pulihkan data terhapus, dan hapus permanen. |
| **Hak Akses & Pengguna** | Matriks izin per role (lihat/kelola per halaman) dan manajemen akun pengguna. |
| **Pengaturan Aplikasi** | Nama aplikasi, tagline, logo, warna brand, metode default, catatan kaki laporan, dan API key integrasi AI (terenkripsi). |
| **Database** | Statistik tabel, backup JSON/SQL, ekspor CSV per tabel, dan impor JSON dengan mode ganti/gabung. |

## Metode perhitungan

### SAW (Simple Additive Weighting)

- Benefit: `r = x / max(x)`
- Cost: `r = min(x) / x`
- Skor: `Σ (w_normal × r) × 100`

### TOPSIS

- Normalisasi vektor: `r = x / √(Σx²)`
- Matriks terbobot: `v = w × r`
- Solusi ideal positif/negatif sesuai tipe kriteria
- Kedekatan relatif: `C = d⁻ / (d⁺ + d⁻)`, skor = `C × 100`

Bobot tidak harus berjumlah 100; sistem menormalisasi bobot secara proporsional.

## Explainable AI

Penjelasan dihitung deterministik dari matriks keputusan yang sama — bukan tebakan:

1. **Kontribusi kriteria** — `bobot × performa ternormalisasi` untuk setiap kriteria, ditampilkan sebagai poin dan porsi kontribusi.
2. **Kekuatan & catatan perbaikan** — kriteria dengan kontribusi terbesar dan potensi poin yang belum tergarap.
3. **Perbandingan head-to-head** — kriteria yang unggul/tertinggal dibanding peringkat kedua.
4. **Kontrafaktual** — perubahan satu kriteria minimal yang membuat runner-up menyamai pemenang (pencarian numerik).
5. **Sensitivitas bobot** — peringkat diuji ulang pada perubahan bobot ±20% untuk melihat stabilitas rekomendasi.
6. **Narasi AI (opsional)** — jika API key OpenAI-compatible diisi dan fitur diaktifkan, ringkasan eksekutif dapat dibuat melalui tombol di halaman evaluasi.

## Teknologi

- Laravel 12 (PHP 8.2+)
- MySQL / SQLite
- Tailwind CSS 4 + Vite 7
- JavaScript vanilla (tanpa framework frontend)
- PDF writer dan XLSX writer internal (tanpa paket tambahan)

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate

# atur koneksi database pada .env (default proyek: MySQL)
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Untuk database SQLite, ubah `.env`:

```env
DB_CONNECTION=sqlite
# DB_DATABASE=/path/absolut/database/database.sqlite
```

## Akun demo

| Role | Email | Password |
|---|---|---|
| Administrator | `kai@example.com` | `250510` |
| Analis Pengadaan | `chips@example.com` | `250510` |
| Viewer | `tweek@example.com` | `250510` |

Data demo berisi 3 supplier peralatan kantor, 4 kriteria (harga, kualitas, waktu pengiriman, keandalan), dan satu evaluasi SAW. **CV Mitra Kantor Sejahtera (Supplier B)** menang dengan skor 91,92 — bukan supplier dengan harga terendah — dan alasannya dijelaskan pada halaman evaluasi.

## Peran & hak akses

- **Administrator** — akses penuh, termasuk pengaturan, hak akses, dan database.
- **Analis Pengadaan** — mengelola supplier, kriteria, evaluasi, laporan, dan riwayat aktivitas.
- **Viewer** — hanya melihat dashboard, supplier, kriteria, evaluasi, dan laporan.

Matriks izin dapat diubah pada halaman **Hak Akses & Pengguna**. Dashboard selalu dapat dilihat oleh semua role.

## Keamanan & operasional

- API key AI disimpan terenkripsi menggunakan `Crypt` dan tidak pernah ditampilkan kembali setelah disimpan.
- Setiap perubahan data penting dicatat pada `activity_logs` beserta nilai sebelum/sesudah dan IP.
- Hapus supplier/kriteria/evaluasi bersifat *soft delete*; pemulihan dan hapus permanen dilakukan dari Riwayat Aktivitas.
- Backup disarankan sebelum melakukan impor mode **Ganti**, karena dapat menimpa data pengguna dan evaluasi.
- Login dibatasi 5 percobaan per menit per email/IP.

## Pengujian

```bash
php artisan test
```

Mencakup perhitungan SAW/TOPSIS, normalisasi bobot, arah benefit/cost, keluaran XAI (kontribusi, kontrafaktual, sensitivitas), kontrol akses role, alur CRUD + revert/restore/hapus permanen, pembuatan evaluasi, ekspor PDF/Excel, backup/impor, dan pembaruan pengaturan.

## Struktur penting

```
app/Services/McdmService.php        # Mesin SAW & TOPSIS
app/Services/XaiService.php         # Kontribusi, kontrafaktual, sensitivitas
app/Services/EvaluationService.php  # Snapshot evaluasi & penyimpanan hasil
app/Support/PdfReport.php           # Generator PDF laporan
app/Support/XlsxReport.php          # Generator Excel laporan
app/Support/Permissions.php         # Matriks modul & izin role
app/Http/Middleware/EnsurePermission.php
resources/views/                    # Seluruh halaman aplikasi
```
