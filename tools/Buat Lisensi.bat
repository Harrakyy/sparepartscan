@echo off
rem Form pembuat lisensi Verifikasi Faktur (hanya di laptop Ashira yang punya secret.key).
rem Biarkan jendela "Buat Lisensi" terbuka selama memakai form; tutup bila selesai.
start "Buat Lisensi - jangan ditutup" php -S 127.0.0.1:8777 -t "%~dp0buat-lisensi"
timeout /t 2 >nul
start "" http://127.0.0.1:8777/
