# CLAUDE.md — partisi-platform

Panduan untuk Claude Code saat bekerja di repository ini.

## Project

**Platform Manajemen Partisi Petugas Lapangan BPS** — sistem web multi-user untuk BPS Indonesia
yang mengelola pembagian wilayah kerja (SubSLS) kepada petugas lapangan (PPL) dan supervisor (PML)
untuk berbagai kegiatan survei (SUSENAS, SAKERNAS, SP, survei insidentil, dll).

Ini adalah rewrite dari tools Streamlit sekali-pakai (`C:\app\cencus_partitioner`) menjadi platform
persisten berbasis web yang bisa di-hosting di cPanel.

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Vue 3 + Inertia.js (SPA tanpa API terpisah) |
| Styling | Tailwind CSS 4 |
| Peta | Leaflet.js (planned) |
| Database | MySQL (production) / SQLite (local dev) |
| Build | Vite 7 |
| Algoritma Partisi | Python microservice (FastAPI) — *planned, belum dibuat* |

## How to Run

```bash
# Install dependencies (sekali)
composer install
npm install

# Setup database
cp .env.example .env
php artisan key:generate
php artisan migrate

# Dev server (jalankan semua sekaligus)
npm run dev
# atau
php artisan serve   # Laravel di :8000
# + npm run build   # Vite untuk assets

# Tinker (REPL)
php artisan tinker
```

## Domain Knowledge

### Istilah Penting

| Istilah | Penjelasan |
|---|---|
| **Kegiatan** | Satu pelaksanaan survei, e.g. "SUSENAS Maret 2025". Bisa berkala (triwulan/semester) atau insidentil. |
| **SubSLS** | *Satuan Lingkungan Setempat* — unit terkecil, satu polygon wilayah dengan satu nilai muatan. Primary key bisnis: `idsubsls` (kode BPS 16 digit). |
| **Muatan** | Beban kerja per SubSLS (jumlah keluarga, rumah tangga, dll). Nama kolom bisa beda per kegiatan. |
| **Wilkerstat** | Wilayah kerja statistik — subset SubSLS yang masuk dalam satu kegiatan. |
| **PPL** | Petugas Pencacah Lapangan — enumerator yang mengunjungi SubSLS. |
| **PML** | Pengawas Mitra Lapangan — supervisor yang mengawasi beberapa PPL. |
| **Sesi Partisi** | Satu upaya pembagian wilayah (draft/final). Satu kegiatan bisa punya banyak draft, satu final. |

### Hierarki Wilayah

```
Provinsi → Kabupaten → Kecamatan → Desa → SLS → SubSLS
```

SubSLS adalah unit atomik: setiap SubSLS harus masuk ke tepat satu PPL dalam satu sesi final.

### Kolom GeoJSON SubSLS

| Kolom | Keterangan |
|---|---|
| `idsubsls` | ID unik SubSLS (required) |
| `Perkiraan_Jumlah_Muatan` | Muatan default (nama kolom configurable) |
| `nmkec`, `nmdesa`, `nmsls` | Nama kecamatan, desa, SLS |
| `kdsubsls`, `kdprov`, `kdkab`, `kdkec`, `kddesa`, `kdsls` | Kode-kode administratif |

## Database Schema

```
users                   → akun login (role: admin | koordinator)
kegiatan                → survei (berkala/insidentil, tahun, gelombang nullable)
geojson_uploads         → file GeoJSON per kegiatan (level: desa | subsls)
subsls                  → master SubSLS dengan geometry JSON + centroid
kegiatan_wilayah        → wilkerstat: SubSLS mana yang masuk kegiatan + muatan
petugas                 → daftar PPL/PML (data saja, tidak login)
kegiatan_petugas        → PPL/PML aktif di suatu kegiatan (peran + group_id)
sesi_partisi            → sesi pembagian (draft → final, simpan CV + config)
partisi_detail          → hasil: subsls_id → ppl_id + pml_id
kegiatan_override       → force_connect / force_disconnect antar SubSLS
```

Relasi utama:
- `kegiatan` ←→ `subsls` via `kegiatan_wilayah`
- `kegiatan` ←→ `petugas` via `kegiatan_petugas`
- `sesi_partisi` → `partisi_detail` → `subsls` + `kegiatan_petugas`

## Arsitektur

```
Browser (Vue + Inertia)
    ↕ Inertia requests (bukan pure API)
Laravel (Controller → Model → MySQL)
    ↕ HTTP (planned)
Python Microservice FastAPI
    → algoritma partisi: graph, osmnx, k-means++
    → input: GeoJSON + config
    → output: {idsubsls: group_id} dict
```

Geometry disimpan sebagai `JSON` column di MySQL (tidak pakai PostGIS — kompatibel shared hosting cPanel).
Saat render peta: Laravel kirim GeoJSON via Inertia props → Vue render dengan Leaflet.js.

## Roadmap

### ✅ Selesai
- [x] Migrations 10 tabel + Eloquent models dengan relationships

### 🔲 Berikutnya (urutan prioritas)
- [ ] Auth: setup Laravel Breeze (Inertia/Vue) + tambah role middleware
- [ ] CRUD Kegiatan: list, create, show, edit, delete + change status
- [ ] Upload GeoJSON: parse + upsert `subsls` + isi `kegiatan_wilayah`
- [ ] Kelola Petugas: CRUD petugas + assign ke kegiatan sebagai PPL/PML
- [ ] Peta wilayah: Vue component dengan Leaflet, render SubSLS per kegiatan
- [ ] Partisi manual: tabel assignment SubSLS → PPL di browser
- [ ] Partisi auto: integrasi ke Python microservice (FastAPI)
- [ ] Export: Excel (hasil assignment) + PDF

## Workflow Preferences

- **Propose plan before implementing** — outline dulu sebelum sentuh file.
- **Bahasa Indonesia** untuk semua UI label, pesan error, dan komentar domain.
- **Kode** boleh pakai Bahasa Inggris (variable names, method names).
- **No test suite** — validasi dengan `php artisan tinker` atau jalankan langsung di browser.
- **cPanel-compatible**: hindari dependensi yang butuh root/daemon (tidak ada Supervisor, tidak ada Redis wajib). Queue driver default: `database`.

## Linting & Formatting

```bash
./vendor/bin/pint          # format PHP (Laravel Pint)
npm run build              # build assets + check Vite errors
```
