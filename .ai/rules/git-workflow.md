# Git Branching Workflow untuk Fitur Baru

Setiap kali membuat fitur baru atau eksperimen kode, agen **wajib** menggunakan alur Git Branching ini:

## 1. Fase Development (Lokal)
1. Selalu buat cabang baru dari `main`:
   `git checkout -b <nama-fitur>`
2. Lakukan coding, perubahan database, dll di cabang ini.
3. Commit dan push ke cabang tersebut:
   `git add .`
   `git commit -m "Draft fitur <nama-fitur>"`
   `git push origin <nama-fitur>`

## 2. Fase Testing (Server Production)
1. Fetch data dari Github: `git fetch`
2. Pindah ke cabang fitur: `git checkout <nama-fitur>`
3. Lakukan pengujian di server (migrasi, dll).

## 3. Penyelesaian (Server & Lokal)
- **Jika Error:** 
  Di server, kembalikan ke `main`: 
  `git checkout main` -> `php artisan optimize:clear`
- **Jika Sukses:** 
  Gabungkan ke `main` di lokal:
  `git checkout main` -> `git merge <nama-fitur>` -> `git push origin main`
  Lalu tarik di server:
  `git checkout main` -> `git pull`
