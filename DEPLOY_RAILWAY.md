# Deploy Gratis ke Railway

Konfigurasi ini menjalankan aplikasi Laravel tanpa mengubah fitur aplikasi. Railway membangun `Dockerfile`, menyimpan database SQLite serta file upload di satu volume, kemudian melakukan migrasi otomatis saat service dimulai.

## Batas paket gratis

Railway Free memberi kredit pemakaian $1 per bulan setelah trial 30 hari berakhir. Karena kredit tidak diakumulasikan dan pemakaian aplikasi atau volume dapat melampauinya, ini cocok untuk demo atau skripsi tetapi bukan layanan gratis permanen yang terjamin. Pantau pemakaian pada dashboard Railway.

## 1. Push ke GitHub

Pastikan perubahan deployment ini dipush ke repository GitHub. File `.env` tidak ikut terkirim karena sudah dikecualikan oleh `.gitignore` dan `.dockerignore`.

## 2. Buat service Railway

1. Masuk ke [Railway](https://railway.com/) menggunakan GitHub.
2. Buat **New Project** lalu pilih **Deploy from GitHub repo**.
3. Pilih repository `scratch_project`.
4. Railway akan mendeteksi `Dockerfile` pada root repository secara otomatis.
5. Di menu **Settings > Networking**, klik **Generate Domain**.

## 3. Tambahkan volume

Tambahkan satu **Volume** ke service dan gunakan mount path berikut:

```text
/data
```

Volume menyimpan database SQLite di `/data/database.sqlite` serta seluruh starter project, gambar materi, dan submission siswa pada `/data/uploads`.

## 4. Set Railway Variables

Atur variabel ini pada menu **Variables**. Gunakan domain Railway yang dibuat pada langkah sebelumnya sebagai nilai `APP_URL` dan `GOOGLE_REDIRECT_URL`.

```env
APP_NAME=AlgoLearn
APP_ENV=production
APP_KEY=base64:GENERATE_DI_LOKAL_DENGAN_php_artisan_key:generate_--show
APP_DEBUG=false
APP_URL=https://your-service.up.railway.app

DB_CONNECTION=sqlite
DB_DATABASE=/data/database.sqlite
DB_FOREIGN_KEYS=true

FILESYSTEM_DISK=public
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr

GOOGLE_CLIENT_ID=isi_dari_google_cloud
GOOGLE_CLIENT_SECRET=isi_dari_google_cloud
GOOGLE_REDIRECT_URL=https://your-service.up.railway.app/auth/google/callback
```

Jangan menyalin nilai rahasia dari file `.env` lokal ke GitHub. Masukkan seluruh secret hanya ke Railway Variables.

## 5. Google OAuth

Di Google Cloud Console, tambahkan URL berikut sebagai **Authorized redirect URI**:

```text
https://your-service.up.railway.app/auth/google/callback
```

Sesuaikan kembali URL ini apabila nantinya memakai custom domain.

## 6. Deploy dan seed data awal

Push ke branch yang terhubung dengan Railway untuk memulai deployment. Setelah service aktif, buka terminal Railway dan jalankan satu kali:

```bash
php artisan db:seed
```

Perintah tersebut membuat akun demo guru dan siswa sebagaimana dijelaskan di README.

## Catatan operasional

- File database dan upload tetap ada hanya selama volume Railway aktif.
- Buat backup berkala dengan mengunduh `/data/database.sqlite` melalui Railway CLI atau terminal service.
- `QUEUE_CONNECTION=sync` dipakai agar tidak memerlukan service worker tambahan pada paket gratis.
- Email reset password tidak akan terkirim sampai `MAIL_*` dikonfigurasi dengan penyedia email SMTP/API.
