# Form CoreHR operasional

Mode: Operate. Memperluas layout identitas Ruang Tim. Master organisasi memakai daftar/form native; karyawan memakai tambah awal, pengajuan identitas/penempatan, daftar/perbandingan/keputusan. Segoe UI, plum, radius7/14px, kontrol44px, focus incumbent dan navigasi atas tanpa sidebar tetap dipakai.

`core-field` menggabungkan label, input/select/textarea, hint, aria-invalid, aria-describedby dan pesan field. Ringkasan error menautkan field. Nilai lama dipulihkan saat validasi gagal; versi stale memakai halaman409 dengan panduan buka ulang. Pilihan keputusan dipertahankan. POST memakai CSRF dan server authorization.

Capture wajib desktop1440×900/mobile390×844: daftar/form master normal-invalid-arsip ditolak; tambah karyawan/empty/invalid; pengajuan identitas/penempatan; daftar/detail pending/approved/rejected; keputusan tidak diizinkan;409;riwayat. Browser tidak tersedia, screenshot tidak ada. Verdict `recapture`. Tiga temuan source telah dinilai resolved. Detector sekali sebelum batch perbaikan hanya menemukan34advisory baseline CSS lama, tanpa temuan primer. Batch memakai warna/komponen incumbent; tidak ada sistem visual baru.
