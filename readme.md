# Silsilahku
Adalah aplikasi pencatatan Silsilah Keluarga berbasis Web yang mengambil banyak inpirasi dari [github/nafiesl/silsilah](https://github.com/nafiesl/silsilah). Dibangun dengan Laravel 12, Livewire Volt, Tailwind CSS, dan Flux UI.

## 🌟 Fitur Utama

### Manajemen Pengguna
- **Pendataan Profil**: Kelola profil pengguna dengan dukungan pengurutan data kronologis berdasarkan urutan pilihan.
- **Dynamic Tag System**: Sistem manajemen tag dinamis untuk mengkategorikan pengguna, terinspirasi dari antarmuka GitHub.
- **Edit Interaktif**: Popup modal untuk mengedit data pengguna secara cepat tanpa perlu berpindah halaman, dilengkapi sanitasi input.

### Manajemen Hubungan
- **Hubungan Keluarga**: Kelola hubungan keluarga seperti ayah, ibu, suami, istri, orang tua, dan anak.
- **Edit Interaktif**: Popup modal untuk mengedit hubungan keluarga secara cepat tanpa perlu berpindah halaman, dilengkapi sanitasi input.

### Manajemen Pernikahan
- **Pernikahan**: Kelola pernikahan.
- **Edit Interaktif**: Popup modal untuk mengedit pernikahan secara cepat tanpa perlu berpindah halaman, dilengkapi sanitasi input.

### Manajemen Foto
- **Foto Profil**: Kelola foto profil pengguna.

### Manajemen Data
- **Pencarian**: Cari data berdasarkan nama.
- **Penyaringan**: Filter data berdasarkan jenis kelamin.
- **Penyortiran**: Urutkan data berdasarkan nama.

### Tampilan Visual
- **Tampilan Pohon**: Tampilkan silsilah keluarga dalam bentuk pohon.
- **Tampilan Daftar**: Tampilkan silsilah keluarga dalam bentuk daftar.

---

## 💻 Cara Instalasi

Aplikasi ini menggunakan ekosistem Laravel modern. Ikuti langkah-langkah di bawah ini untuk menjalankan aplikasi di komputer lokal Anda:

### Persyaratan Sistem
- **PHP** >= 8.2
- **Composer**
- **Node.js** & **NPM**
- Database (MySQL / SQLite / PostgreSQL)

### Langkah-langkah Instalasi

1. **Clone Repository / Siapkan Folder Aplikasi**
   Buka terminal, dan masuk ke direktori web server Anda (misal: `d:\www\`).

2. **Jalankan Setup Otomatis**
   Aplikasi ini sudah dilengkapi dengan script setup bawaan dari Composer. Buka terminal di dalam folder `silsilahku` dan jalankan:
   ```bash
   composer setup
   ```
   > **Catatan**: Perintah `setup` ini secara otomatis akan menjalankan:
   > - `composer install`
   > - Copy `.env.example` menjadi `.env`
   > - `php artisan key:generate`
   > - `php artisan migrate --force`
   > - `npm install`
   > - `npm run build`

3. **Konfigurasi Environment (.env)**
   Buka file `.env` dan atur konfigurasi database sesuai pengaturan di server lokal Anda.

4. **Jalankan Development Server**
   Untuk menjalankan server PHP, Vite (asset bundling) secara bersamaan, jalankan perintah:
   ```bash
   composer dev
   ```
   Aplikasi sekarang dapat diakses melalui browser pada `http://localhost:8000`.

### Instalasi Manual (Opsional)
Jika Anda tidak ingin menggunakan `composer setup`, Anda dapat menginstal secara berurutan:
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

## Lisensi
Aplikasi ini merupakan perangkat lunak bebas. Anda dapat menggunakan, menyalin, memodifikasi, dan mendistribusikan ulang kode ini sesuai dengan lisensi MIT.

---

## Ko-Fi

Buy me a coffee (or tea, or snacks) :) [Ko-Fi](https://ko-fi.com/amuadib)