# Panduan Pemasangan — Billing Biliar

Panduan ini untuk memasang aplikasi di **PC kasir baru** (Windows). Sebagian besar
konfigurasi dikerjakan lewat wizard di browser, jadi Anda tidak perlu menyunting
berkas apa pun secara manual.

Perkiraan waktu: **15–30 menit**, di luar pemasangan PHP dan MySQL.

---

## 1. Yang harus disiapkan lebih dulu

| Kebutuhan | Versi | Catatan |
|---|---|---|
| Windows | 10 / 11 | Aplikasi ini memang ditargetkan untuk Windows |
| PHP | 8.0.2 – 8.2 | Ekstensi wajib: `pdo_mysql`, `mbstring`, `openssl` |
| MySQL / MariaDB | 5.7+ / 10.3+ | Boleh dari XAMPP, Laragon, PhpWebStudy, atau instalasi sendiri |
| Composer | 2.x | https://getcomposer.org |
| Node.js + npm | 18+ | Hanya dipakai sekali untuk membangun tampilan |
| Printer struk | 80mm | Opsional, bisa diatur belakangan |

**Tentang versi PHP.** Aplikasi ini memakai Laravel 9 yang secara resmi mendukung
PHP 8.0 sampai 8.2. PHP 8.3 dan 8.4 **tetap bisa jalan** — mesin pengembangan
memakai 8.4.7 tanpa masalah fungsional — tetapi memunculkan pesan *deprecated* di
log. Kalau Anda memasang PHP dari nol, pilih **8.2** untuk paling aman.

Ekstensi `zip` dan `curl` tidak wajib, tapi tanpa keduanya fitur backup dan
Google Drive/Telegram tidak akan berfungsi. Wizard akan memberi tahu kalau kurang.

---

## 2. Siapkan database kosong

Buat satu database baru di MySQL. Isinya biar kosong — migrasi akan mengisi
tabelnya nanti.

```sql
CREATE DATABASE billiard_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Catat **nama database, username, dan password**-nya; nanti diminta wizard.

---

## 3. Ambil kode program

```powershell
git clone -b master-v2 https://github.com/ariexx/billing-biliard.git
cd billing-biliard
```

Kalau di PC itu tidak ada Git, salin saja seluruh folder aplikasi dari mesin lain.

---

## 4. Pasang dependensi

```powershell
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

`npm run build` **wajib dijalankan**. Hasil buildnya tidak ikut disimpan di
repositori, jadi tanpa langkah ini tampilan aplikasi akan berantakan tanpa CSS.

---

## 5. Jalankan aplikasi

Klik dua kali **`start.bat`**, atau dari terminal:

```powershell
.\start.bat
```

Skrip ini menyiapkan berkas konfigurasi awal dan kunci aplikasi secara otomatis,
lalu menampilkan:

```
==========================================================
  Aplikasi belum dipasang.
  Buka di browser:  http://localhost:8000/install
==========================================================
```

Biarkan jendela ini **tetap terbuka** selama aplikasi dipakai. Akan muncul juga
satu jendela kecil bernama *billiard-scheduler* — jangan ditutup, itu yang
menjalankan backup otomatis dan menutup sesi meja yang waktunya habis.

---

## 6. Isi wizard pemasangan

Buka **http://localhost:8000/install** di browser. Ada enam langkah:

### 1️⃣ Syarat Sistem
Pemeriksaan otomatis. Yang bertanda opsional boleh merah, yang wajib harus hijau
semua. Kalau ada yang merah, perbaiki dulu lalu muat ulang halaman.

### 2️⃣ Database
Isi host, port, nama database, username, dan password dari langkah 2. Tekan
**Tes Koneksi** sebelum lanjut — pesannya akan memberi tahu persis apa yang salah
kalau gagal.

### 3️⃣ Identitas Aplikasi
Nama usaha (muncul di struk dan judul aplikasi) dan alamat aplikasi.

### 4️⃣ Akun
Buat akun **admin** — ini pemilik/pengelola, satu-satunya yang bisa membuka panel
admin. Akun kasir boleh diisi sekarang atau ditambahkan belakangan dari panel.

> Pakai password yang benar-benar Anda pilih sendiri. Akun admin bisa melihat
> seluruh laporan keuangan dan membatalkan tagihan.

### 5️⃣ Data Awal
- **Jumlah meja** — akan dibuat otomatis sebagai Meja 1, Meja 2, dan seterusnya
- **Metode pembayaran** — pisahkan dengan koma, contoh: `Tunai, QRIS`
- **Paket jam** — ini yang perlu perhatian:

| Tipe | Arti | Isi kolom harga dengan |
|---|---|---|
| **Reguler** | Blok jam, dibayar di muka | Harga sekali bayar untuk seluruh blok |
| **Main bebas** | Terbuka, dihitung saat selesai | **Tarif per MENIT** |

Contoh main bebas: isi `500` berarti Rp 500/menit, atau Rp 30.000 per jam. Salah
mengisi di sini langsung berarti salah tagih ke pelanggan.

### 6️⃣ Printer & Backup
Nama printer harus **sama persis** seperti di Windows › Settings › Printers &
scanners. Bagian Google Drive boleh dikosongkan dulu.

Tekan **Pasang Sekarang**. Setelah selesai Anda diarahkan ke halaman login.

---

## 7. Periksa hasilnya

Masuk dengan akun admin, lalu pastikan:

- [ ] Halaman `/home` menampilkan kartu meja sesuai jumlah yang diisi
- [ ] Setiap kartu meja punya pilihan durasi (kalau kosong, paket jamnya belum
      terhubung — cek di panel admin)
- [ ] Panel admin terbuka di `/billiard-admin`
- [ ] Coba buka satu meja, tambah minuman, lalu tandai lunas dan cetak struk
- [ ] Jendela *billiard-scheduler* masih berjalan

Login sebagai kasir dan pastikan `/billiard-admin` **ditolak** — kasir memang
tidak boleh masuk panel admin.

---

## 8. Pengaturan opsional

### Backup ke Google Drive

Backup selalu tersimpan di `storage/app/backups` walau Drive tidak diatur. Untuk
menyalakan unggahan ke Drive:

1. Buka https://console.cloud.google.com → buat project baru
2. **APIs & Services › Library** → cari *Google Drive API* → **Enable**
3. **OAuth consent screen** → External → isi nama aplikasi → tambahkan email Anda
   sebagai *Test user*
4. **Credentials › Create Credentials › OAuth client ID** → tipe **Desktop app**
5. Salin Client ID dan Client Secret ke `.env`, lalu jalankan:

```powershell
php artisan gdrive:authorize
```

Ikuti URL yang muncul, setujui aksesnya, tempel kode yang diberikan Google, lalu
salin `GOOGLE_DRIVE_REFRESH_TOKEN` hasilnya ke `.env`.

Uji: `php artisan backup:database`

### Rekap harian ke Telegram

1. Chat **@BotFather** → `/newbot` → salin token
2. Chat **@userinfobot** → salin angka ID Anda (untuk grup: tambahkan bot ke grup
   lalu ambil ID grupnya)
3. Isi di `.env`:

```
TELEGRAM_BOT_TOKEN=isi-token-dari-botfather
TELEGRAM_CHAT_ID=isi-id-chat
TELEGRAM_REPORT_HOUR=22
```

Rekap terkirim **otomatis** tiap hari setelah jam yang diisi. Kalau PC sedang
mati saat itu, rekapnya dikirim susulan begitu aplikasi hidup lagi.

Uji tanpa menunggu jadwal: tombol **Kirim rekap ke Telegram** di halaman Laporan,
atau `php artisan rekap:telegram --tanggal=2026-09-06`

Untuk ikut mengirim berkas backup ke chat yang sama, tambahkan
`TELEGRAM_SEND_BACKUP=true`. **Pertimbangkan dulu:** berkas itu berisi seluruh
data transaksi dan hash password pengguna, sedangkan chat Telegram biasa tidak
terenkripsi ujung-ke-ujung. Aman untuk chat pribadi ke diri sendiri; pikirkan
lagi kalau tujuannya grup.

### Jalan otomatis saat komputer menyala

Tekan `Win + R` → ketik `shell:startup` → Enter, lalu letakkan *shortcut*
`start.bat` di folder yang terbuka.

---

## 9. Penggunaan sehari-hari

| | |
|---|---|
| Menyalakan | Klik dua kali `start.bat` |
| Kasir | http://localhost:8000 |
| Panel admin | http://localhost:8000/billiard-admin |
| Mematikan | Tutup kedua jendela terminal |

Jendela *billiard-scheduler* harus tetap hidup. Kalau ditutup, backup otomatis,
rekap Telegram, dan penutupan sesi meja yang kedaluwarsa ikut berhenti.

---

## 10. Memperbarui aplikasi yang sudah terpasang

Selalu tiga langkah ini, berurutan:

```powershell
php artisan backup:database
php artisan db:integrity-check
php artisan migrate
```

`db:integrity-check` memeriksa data ganda yang bisa membuat migrasi gagal.
Kalau ia melaporkan masalah, jalankan `php artisan db:integrity-check --fix`
lebih dulu.

Kalau ada perubahan tampilan, jalankan juga `npm run build`.

---

## 11. Memasang ulang dari awal

Hapus penanda pemasangan, lalu buka `/install` lagi:

```powershell
del storage\installed
```

Berkas `.env` lama otomatis dicadangkan, dan `APP_KEY` yang sudah ada **tidak
akan diganti** — menggantinya membuat semua sesi login lama tidak bisa dibaca.

---

## 12. Masalah yang sering muncul

**Semua halaman melempar balik ke `/install`**
Aplikasi belum selesai dipasang. Selesaikan wizardnya.

**`/install` menampilkan 404**
Aplikasi sudah terpasang, dan wizard sengaja dimatikan karena ia bisa menulis
konfigurasi dan membuat akun admin. Lihat bagian 11 kalau memang ingin memasang
ulang.

**"Database tidak ditemukan"**
Databasenya belum dibuat. Lihat bagian 2.

**"Server MySQL tidak merespon"**
MySQL belum jalan. Nyalakan dari XAMPP/Laragon/Services.

**"Username atau password database salah"**
Periksa kembali kredensialnya. Pada instalasi MySQL bawaan, user `root` sering
punya password kosong — coba dikosongkan.

**Tampilan berantakan, tanpa warna**
`npm run build` belum dijalankan. Lihat bagian 4.

**Struk tidak keluar dari printer**
Nama printer di `.env` (`PRINTER=`) harus sama persis dengan nama di Windows,
termasuk spasi dan huruf besar-kecil.

**Backup tidak jalan otomatis**
Pastikan jendela *billiard-scheduler* masih terbuka. Periksa jadwalnya dengan
`php artisan schedule:list`.

> `mysqldump` **tidak dibutuhkan**. Aplikasi ini membuat dump lewat PHP langsung,
> jadi tidak masalah kalau `mysqldump` tidak ada di PATH.

---

## 13. Catatan keamanan

**Jangan jalankan `php artisan migrate --seed` di instalasi produksi.** Seeder itu
membuat akun `admin@laravel.com` dan `kasir@laravel.com` dengan password
`password` — untuk pengembangan saja. Wizard sudah membuatkan akun dengan
password pilihan Anda sendiri.

Beberapa hal lain yang perlu diketahui pengelola:

- Kasir **tidak bisa** membatalkan baris waktu meja; hanya admin. Pembatalan apa
  pun wajib beralasan dan tercatat di log aktivitas panel admin.
- Kasir hanya melihat dan mengubah order yang dibuatnya sendiri.
- Order yang sudah ditandai lunas tidak bisa diubah lagi.
- Satu hal yang tidak bisa dicegah aplikasi: kasir yang **tidak pernah menandai
  order lunas** tetap bisa mengantongi uangnya. Cocokkan uang fisik dengan
  laporan harian secara rutin.

---

Untuk hal-hal teknis di balik layar — arsitektur, aturan penagihan, dan utang
skema database — lihat `CLAUDE.md`.
