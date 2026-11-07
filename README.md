# TerbangLebih — Full-Stack Ticketing Pesawat

Aplikasi pemesanan tiket pesawat (OTA) berbasis **PHP 8.3/8.4 + MySQL/MariaDB**.
Seluruh data (penerbangan, kota, promo, testimoni, FAQ, pengaturan) berasal dari
database; tampilan Tailwind dipertahankan dari versi statis.

---

## 1. Fitur

| Area | Fitur |
| --- | --- |
| Publik | Home dinamis, pencarian penerbangan (sekali jalan, pulang-pergi, multi-kota), filter & sorting, autocomplete kota, halaman promo, pusat bantuan (FAQ) |
| Autentikasi | Register, login, logout, forgot/reset password via token email, profil, ganti password, rate limit 5 percobaan/menit |
| Booking | Form penumpang dinamis (hingga 9), halaman review, simpan ke database, kode booking, batas waktu pembayaran, pembatalan |
| Pembayaran | Transfer Bank, Virtual Account, E-Wallet, Kartu Kredit; halaman instruksi, upload bukti, verifikasi otomatis (simulasi), invoice |
| Promo | Validasi kode di checkout: masa berlaku, minimal transaksi, kuota, diskon persen/nominal, pencatatan `promo_usage` |
| E-tiket | PDF via DomPDF (jika terpasang) atau fallback `SimplePDF` tanpa dependency |
| User | Pesanan Saya (list + detail), invoice, e-tiket, batalkan booking |
| Admin | Login terpisah, dashboard (total & grafik pendapatan 7 hari), CRUD Maskapai, Penerbangan, Promo, daftar/detail booking, ubah status |
| Notifikasi | Email HTML booking & perubahan status via PHPMailer; fallback ditulis ke `storage/mail/` |
| Keamanan | PDO prepared statement, `password_hash`/`password_verify`, CSRF di semua POST, escaping output, regenerasi session, validasi upload |

---

## 2. Kebutuhan

- **PHP 8.3 atau 8.4** dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `gd`/`dom` (untuk PDF)
- **MySQL 8.0+** atau **MariaDB 10.4+**
- Apache + `mod_rewrite` (opsional, hanya untuk clean URL) atau Nginx
- Composer (opsional, untuk DomPDF & PHPMailer)

> Proyek ini tidak membutuhkan framework. Seluruh file PHP dapat dijalankan
> langsung dan setiap halaman melakukan bootstrap sendiri.

---

## 3. Instalasi Cepat (XAMPP / Laragon)

### 3.1 Letakkan proyek

Salin folder proyek ke document root web server, misalnya:

```text
C:\xampp\htdocs\terbanglebih
```

### 3.2 Buat & impor database

Cara termudah: buka **phpMyAdmin → Import**, jalankan berurutan:

1. `database/schema.sql` — membuat database `terbanglebih` dan seluruh tabel
2. `database/seed.sql` — 25 kota, 100+ penerbangan, maskapai, promo, FAQ,
   testimoni, pengaturan, dan data demo

Atau lewat terminal:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p terbanglebih < database/seed.sql
```

> `schema.sql` membuat database jika belum ada. `seed.sql` memakai
> `USE terbanglebih;`, jadi pastikan database tersebut sudah dibuat.

### 3.3 Konfigurasi

Default sudah cocok untuk XAMPP/Laragon (`127.0.0.1:3306`, user `root`, password
kosong, database `terbanglebih`). Jika berbeda, set environment variable berikut
sebelum PHP dijalankan, atau sesuaikan nilai default di `config/config.php`.

| Variable | Default | Keterangan |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1` | Host MySQL |
| `DB_PORT` | `3306` | Port MySQL |
| `DB_NAME` | `terbanglebih` | Nama database |
| `DB_USER` | `root` | User database |
| `DB_PASS` | *(kosong)* | Password database |
| `APP_URL` | otomatis | URL publik lengkap, mis. `http://localhost/terbanglebih` |
| `APP_ENV` | `local` | `local` / `production` |
| `APP_DEBUG` | `1` | Set `0` di production |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu |
| `APP_PRETTY` | `0` | `1` untuk clean URL (`/pesawat`, `/promo`, `/bantuan`) |
| `MAIL_HOST` | *(kosong)* | SMTP host; kosong = email ditulis ke `storage/mail/` |
| `MAIL_PORT` | `587` | Port SMTP |
| `MAIL_USERNAME` | *(kosong)* | User SMTP |
| `MAIL_PASSWORD` | *(kosong)* | Password SMTP |
| `MAIL_ENCRYPTION` | `tls` | `tls`, `ssl`, atau kosong |
| `MAIL_FROM` | `no-reply@terbanglebih.test` | Alamat pengirim |
| `MAIL_FROM_NAME` | `TerbangLebih` | Nama pengirim |

Contoh Apache (vhost atau `.htaccess` dengan `mod_php`):

```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_NAME terbanglebih
SetEnv DB_USER root
SetEnv DB_PASS ""
SetEnv APP_URL http://localhost/terbanglebih
SetEnv APP_PRETTY 1
```

Contoh PowerShell (hanya berlaku untuk proses terminal tersebut):

```powershell
$env:DB_PASS = "rahasia"
$env:APP_PRETTY = "1"
```

### 3.4 Permission folder

Pastikan web server dapat menulis ke:

```text
uploads/proofs/     # bukti pembayaran
storage/mail/       # email fallback
storage/logs/       # log aplikasi
```

Folder dibuat otomatis oleh `config/bootstrap.php`, tetapi pada Linux/Mac
mungkin perlu:

```bash
chmod -R 775 uploads storage
```

### 3.5 Jalankan

- **XAMPP/Laragon:** buka `http://localhost/terbanglebih/`
- **PHP built-in server:**

  ```bash
  php -S localhost:8000
  ```

  Lalu buka `http://localhost:8000/index.php`. Clean URL tidak aktif pada
  built-in server karena `.htaccess` tidak diproses.

---

## 4. Akun Demo

| Peran | Email | Password | URL Login |
| --- | --- | --- | --- |
| User | `demo@terbanglebih.com` | `Demo#12345` | `/auth/login.php` |
| Admin | `admin@terbanglebih.com` | `Admin#12345` | `/admin/index.php` |

User kedua: `siti@terbanglebih.com` dengan password yang sama (`Demo#12345`).
Kredensial hanya untuk pengembangan — ganti sebelum deploy produksi.

---

## 5. Alur Utama

1. **Cari penerbangan** di `index.php?page=pesawat` (atau `/pesawat` dengan
   clean URL). Pilih rute, tanggal, jumlah penumpang, lalu pilih penerbangan.
2. **Isi data penumpang** di `booking/passengers.php`. Data disimpan sementara di
   session (`booking_draft`).
3. **Review** di `booking/review.php`, masukkan kode promo bila ada.
4. **Buat booking** via `booking/create.php` (transaksi database + row lock
   promo). Kode booking dibuat otomatis, status `pending`.
5. **Pilih pembayaran** di `booking/payment.php` → instruksi di
   `booking/instruction.php`.
6. **Upload bukti** (`booking/upload-proof.php`) atau simulasi kartu kredit.
   Booking berubah menjadi `paid` dan email konfirmasi dikirim/dicatat.
7. **E-tiket PDF** di `booking/e-ticket.php`; invoice di `user/invoice.php`.
8. **Kelola** booking di `user/my-bookings.php` atau admin panel.

Status booking: `pending` → `paid` / `cancelled`, dan `expired` otomatis bila
melewati `expires_at` (default 120 menit).

---

## 6. Admin Panel

Buka `admin/index.php` dan login dengan akun admin (terpisah dari akun user).

- **Dashboard** — total booking, pendapatan, user, penerbangan; grafik
  pendapatan 7 hari; booking terbaru; rute terpopuler.
- **Maskapai** — tambah/edit/hapus; maskapai yang masih dipakai tidak bisa dihapus.
- **Penerbangan** — CRUD lengkap dengan filter, pencarian, aktif/nonaktif.
- **Booking** — daftar & detail, ubah status (`pending`, `paid`, `cancelled`,
  `expired`); perubahan status mengirim email.
- **Promo** — CRUD kode promo, filter status, aktif/nonaktif, dan proteksi hapus
  untuk promo yang sudah pernah dipakai.

---

## 7. Clean URL (Opsional)

`.htaccess` di root sudah menyediakan aturan rewrite. Aktifkan dengan:

```text
APP_PRETTY=1
```

Aturan yang tersedia:

| URL cantik | Target |
| --- | --- |
| `/` | `index.php?page=home` |
| `/home` | `index.php?page=home` |
| `/pesawat` | `index.php?page=pesawat` |
| `/promo` | `index.php?page=promo` |
| `/bantuan` | `index.php?page=bantuan` |

Jika proyek berada di subfolder, `mod_rewrite` tetap bekerja karena aturan
menggunakan path relatif. Pastikan `AllowOverride All` aktif.

Untuk **Nginx**, tambahkan (sesuaikan root):

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(config|database|storage)/ { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
```

---

## 8. Email & PDF (Opsional)

Tanpa Composer, aplikasi tetap berjalan:

- Email ditulis sebagai file HTML ke `storage/mail/`
- E-tiket dibuat dengan `includes/SimplePDF.php` (PDF minimal tanpa dependency)

Untuk email SMTP sungguhan dan PDF DomPDF:

```bash
composer require phpmailer/phpmailer dompdf/dompdf
```

Lalu isi `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_ENCRYPTION`, dan `MAIL_FROM`. File `vendor/autoload.php` dideteksi otomatis.

---

## 9. Struktur Folder

```text
terbanglebih/
├── index.php                  # Front controller halaman publik
├── .htaccess                  # Proteksi folder + clean URL opsional
├── .gitignore                 # Abaikan file runtime/lokal
├── README.md
├── admin/
│   ├── index.php              # Login admin
│   ├── dashboard.php
│   ├── airlines.php
│   ├── flights.php
│   ├── bookings.php
│   ├── promos.php
│   ├── logout.php
│   └── includes/
│       ├── header.php
│       └── footer.php
├── api/
│   ├── autocomplete.php
│   └── apply-promo.php
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── auth/
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   ├── forgot-password.php
│   └── reset-password.php
├── booking/
│   ├── passengers.php
│   ├── review.php
│   ├── create.php
│   ├── payment.php
│   ├── instruction.php
│   ├── upload-proof.php
│   └── e-ticket.php
├── config/
│   ├── config.php             # Konstanta & konfigurasi
│   ├── db.php                 # PDO singleton
│   └── bootstrap.php          # Session, header keamanan, autoload helper
├── database/
│   ├── schema.sql
│   └── seed.sql
├── includes/
│   ├── functions.php
│   ├── mailer.php
│   ├── header.php
│   ├── footer.php
│   ├── booking.php
│   ├── booking-itinerary.php
│   ├── booking-steps.php
│   └── SimplePDF.php
├── pages/
│   ├── home.php
│   ├── flights.php
│   ├── promo.php
│   └── help.php
├── user/
│   ├── my-bookings.php
│   ├── booking-detail.php
│   ├── cancel-booking.php
│   ├── invoice.php
│   ├── profile.php
│   └── change-password.php
├── uploads/
│   ├── .htaccess              # Cegah eksekusi PHP
│   └── proofs/
├── storage/
│   ├── mail/
│   └── logs/
└── vendor/                    # Opsional (Composer)
```

---

## 10. Keamanan

- Semua query memakai **PDO prepared statement** (emulasi prepare dimatikan).
- Password disimpan dengan `password_hash(PASSWORD_BCRYPT)`; verifikasi dengan
  `password_verify()`.
- Setiap form POST menyertakan **CSRF token** dan diverifikasi via
  `verify_csrf()`.
- Semua output di-escape dengan `htmlspecialchars()` melalui helper `e()`.
- `session_regenerate_id(true)` dipanggil setelah login berhasil.
- Login dibatasi **5 percobaan per 60 detik** per IP + email (tabel
  `login_attempts`); percobaan berlebih dikunci sementara.
- Input divalidasi & disanitasi (tipe, panjang, format tanggal, regex kode,
  validasi URL, whitelist metode/status).
- Harga tiket selalu dihitung ulang dari database — nilai dari client tidak
  dipercaya.
- Upload bukti dibatasi JPG/PNG/PDF, maksimal 2 MB, dan MIME dicek dengan
  `finfo`.
- Folder `config/`, `database/`, `storage/` diblokir dari akses web; folder
  `uploads/` tidak dapat mengeksekusi PHP.
- Cookie session `HttpOnly`, `SameSite=Lax`, dan `Secure` otomatis saat HTTPS.

Untuk produksi: set `APP_DEBUG=0`, gunakan user database dengan hak minimal,
pasang HTTPS, dan ganti semua akun demo.

---

## 11. Troubleshooting

| Masalah | Solusi |
| --- | --- |
| `SQLSTATE[HY000] [1045] Access denied` | Periksa `DB_USER` / `DB_PASS` |
| `Unknown database 'terbanglebih'` | Impor `database/schema.sql` lebih dulu |
| Halaman blank | Set `APP_DEBUG=1`, cek `storage/php-error.log` |
| Upload bukti gagal | Pastikan `uploads/proofs/` writable dan `file_uploads=On` |
| Email tidak terkirim | Isi konfigurasi `MAIL_*`; tanpa itu cek `storage/mail/` |
| Clean URL 404 | Aktifkan `mod_rewrite`, `AllowOverride All`, dan `APP_PRETTY=1` |
| E-tiket bukan PDF rapi | Jalankan `composer require dompdf/dompdf` |

---

## 12. Lisensi

Proyek contoh untuk pembelajaran. Aset gambar menggunakan URL Unsplash dan
Font Awesome/Tailwind via CDN.
