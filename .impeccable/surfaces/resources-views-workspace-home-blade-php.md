---
version: 1
slug: "resources-views-workspace-home-blade-php"
primary_target: "resources/views/workspace/home.blade.php"
related_targets: ["resources/views/components/identity-layout.blade.php", "resources/css/workspace.css", "resources/views/workspace/employees.blade.php", "resources/views/organization/index.blade.php"]
---

# Beranda operasional — Ruang Tim

Mode: Operate. Extension dari dunia Ruang Tim yang sudah dipilih pengguna: plum, ground lavender tipis, Segoe UI, panel putih beradius 14 px, navigasi horizontal tanpa sidebar. Bukan redesign identitas baru.

Masalah: /app memakai shell formulir sempit, sementara prototype memakai layout produk yang lebih lengkap. Screenshot pengguna pada 23 Oktober memperlihatkan styling termuat tetapi layout operasional belum memadai. Klaim integrasi backend tidak menjadi bukti penyelesaian desain.

Struktur: header horizontal dengan lokasi aktif, pencarian/notifikasi dan menu akun; konteks perusahaan/tanggal; sapaan dan tindakan tambah; ringkasan data berizin; peninjauan dan direktori di kiri, panduan organisasi dan tautan kerja di kanan. Ponsel memakai satu kolom dan navigasi atas yang dapat digeser. Tak ada statistik kehadiran, grafik atau agenda rekaan untuk mengisi database kosong.

Data: jumlah karyawan dan lima catatan terbaru mengikuti EmployeeVisibility. Master organisasi hanya bagi company HR Admin. Pengajuan memakai WorkflowListing berizin yang dibatasi; jumlah adalah catatan ditampilkan, bukan total antrean global. Platform Admin tidak memperoleh metrik/direktori HR. Teks tidak mengklaim sesi telah melewati MFA ketika testing lokal melewatinya.

Shell dibagi seluruh halaman operasional; direktori karyawan dan organisasi memakai tabel semantik dengan create/edit/filter asli. Formulir domain lain tetap memakai adapter operasional, kini dalam shell dan treatment form bersama; tidak diklaim seluruh modul memiliki desain khusus atau UAT menyeluruh.

Verifikasi visual: fixture HTML dari Blade dan CSS build aktual dirender di Chrome headless terpisah pada 1440/360 px untuk beranda dan 1100/390 px untuk tabel/form serta kondisi kosong. Capture ditinjau langsung: layout tampil, header responsif, tidak ada overflow halaman pada skenario yang diuji. Capture lokal di .cache/operation-browser; tidak masuk Git dan bukan bukti data produksi. Tidak ada raster shipping baru.
