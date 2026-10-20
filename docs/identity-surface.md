# Identitas operasional

Mode: Operate. Ekstensi Ruang Tim untuk login/MFA/reset dan direktori baca saja. Identitas visual mengikuti DESIGN.md: Segoe UI, plum, latar terang, kontrol 44px, radius 7/14px dan navigasi atas tanpa sidebar. Tidak ada perubahan dunia visual atau comp baru.

Halaman memakai `components/identity-layout`, bukan layout auth statis. Form POST memakai CSRF, password autocomplete yang sesuai, label eksplisit, ringkasan error, status region dan focus-visible incumbent. Challenge menyediakan OTP dan alternatif recovery code. Setup menyediakan QR dan disclosure kunci manual TOTP dengan konteks penerbit/akun. Recovery code hanya tersedia di halaman keamanan setelah konfirmasi password. Navigasi mengikuti policy; detail direktori dibatasi query server, tidak menyembunyikan data hanya lewat CSS.

Ukuran yang harus dicapture: desktop1440×900 dan mobile390×844 untuk login, challenge/recovery, setup QR/manual, MFA terkonfirmasi/recovery, direktori berisi/empty dan detail. Browser inventory kosong; tidak ada screenshot valid. Verdict visual `recapture`, bukan ship. Detector source hanya mengembalikan advisory baseline lama pada CSS; tidak ada temuan primer baru. Source review manual enrollment telah ditangani; pengesahan visual/keyboard/kontras browser masih terbuka.
