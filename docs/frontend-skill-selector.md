---
name: frontend-skill-selector
description: Pilih dan gabungkan skill desain UI dan animasi yang tersedia untuk tugas frontend, berdasarkan kebutuhan produk, framework, dan kode yang sudah ada.
---

# Pemilih skill desain dan animasi

Gunakan panduan ini ketika pengguna meminta AI memilih skill frontend yang paling sesuai. Tujuannya menghasilkan antarmuka yang jelas, konsisten, responsif, dan nyaman dipakai. Pilihan terbaik bergantung pada tugas; banyaknya skill atau efek bukan ukuran kualitas.

## Cara menggunakan panduan ini

1. Pahami hasil yang diminta, target halaman atau komponen, framework, sistem styling, library animasi, serta desain yang sudah ada. Periksa kode dan dependensi sebelum memilih implementasi.
2. Pilih skill utama untuk kebutuhan tersebut. Tambahkan skill pendukung hanya jika ada pekerjaan khusus yang membutuhkannya. Untuk pekerjaan backend tanpa UI, panduan ini tidak perlu diterapkan.
3. Baca `SKILL.md` asli dari setiap skill terpilih beserta referensi yang relevan sebelum menerapkannya. Tabel berikut adalah peta pemilihan, bukan pengganti instruksi skill asli.
4. Sampaikan pilihan dan alasannya dalam satu atau dua kalimat, lalu kerjakan tugasnya. Tidak perlu meminta pengguna memilih lagi jika konteksnya sudah cukup.
5. Verifikasi hasil sesuai perubahan: tampilan pada ukuran yang relevan, perilaku interaksi, keyboard/fokus, reduced motion, dan cleanup jika memakai animasi JavaScript. Jelaskan keterbatasan pemeriksaan yang tidak bisa dilakukan.

## Lokasi skill dan keterbatasan portabilitas

Skill pada komputer pengguna saat panduan dibuat tersimpan di:

```text
C:/Users/Faiz Ismail M/.agents/skills/<nama-skill>/SKILL.md
```

Utamakan lokasi skill yang diberikan lingkungan sesi saat ini. Jika tidak tersedia, cari di folder skills pengguna atau proyek. Jangan menganggap lokasi Windows di atas berlaku pada mesin lain.

Menyertakan panduan ini ke AI lain tidak otomatis memasang skill atau library npm. Jika skill asli tidak dapat diakses, jelaskan hal itu dan gunakan panduan ini sebatas peta pemilihan; jangan mengaku sudah membaca atau menjalankan skill tersebut. Jangan memasang paket atau mengubah konfigurasi hanya untuk melengkapi katalog ini tanpa kebutuhan dari tugas pengguna.

## Desain dan penyempurnaan UI

| Skill | Pilih ketika | Peran dan batas pemakaian |
| --- | --- | --- |
| `impeccable` | Membuat UI baru, redesign, menyusun UX, memperbaiki hierarki, layout, warna, tipografi, responsive, aksesibilitas, atau design system. | Arah desain dan evaluasi menyeluruh. Cocok untuk landing page, dashboard, aplikasi, dan dokumentasi. Ikuti mode serta setup dari skill aslinya. Permintaan polish tidak berarti izin redesign. |
| `make-interfaces-feel-better` | UI sudah ada tetapi detailnya terasa kurang rapi: radius, bayangan, alignment, ikon, angka, hover, focus, atau micro-interaction. | Ketelitian komponen dan finishing. Pertahankan sistem styling proyek; jangan menambahkan sistem styling baru untuk perbaikan kecil. |

Untuk dashboard operasional, prioritaskan keterbacaan, kecepatan menyelesaikan tugas, dan konsistensi. Untuk landing page atau portfolio, ekspresi visual dan motion dapat lebih menonjol jika sesuai brief. Pertahankan identitas yang ada ketika pengguna meminta perbaikan terbatas.

## Transitions.dev

| Skill | Pilih ketika | Peran dan batas pemakaian |
| --- | --- | --- |
| `transitions-dev` | Menambahkan animasi komponen seperti dropdown, modal, accordion, tabs, toast, checkbox, toggle, skeleton, tooltip, pergantian ikon, atau streaming text. | Resep transisi CSS yang dapat diadaptasi ke framework proyek. Baca referensi komponen yang cocok; jangan menyalin seluruh katalog. |
| `transitions-polish` | Animasi sudah ada tetapi timing, easing, jarak, scale, blur, stagger, atau perbedaan masuk/keluar belum konsisten. | Penyempurnaan motion dengan skala token Transitions.dev. Gunakan bersama referensi `transitions-dev` yang relevan. Tidak perlu mengganti seluruh sistem animasi. |
| `transitions-ui-generation` | Pengguna meminta efek AI sedang membangun UI, dotted wireframe, scan line, atau wireframe yang berubah menjadi komponen nyata. | Efek generasi UI khusus, dengan runtime tersendiri. Loading biasa umumnya cukup memakai skeleton dari `transitions-dev`. Efek visual tidak boleh mengarang status proses atau menampilkan penalaran internal AI. |

## GSAP

| Skill | Pilih ketika | Peran dan pasangan yang sesuai |
| --- | --- | --- |
| `gsap-core` | Membuat atau mengubah tween JavaScript, easing, stagger, animasi DOM/SVG, atau animasi responsif dengan GSAP. | Dasar implementasi GSAP. Baca bersama skill khusus sesuai kebutuhan. |
| `gsap-timeline` | Beberapa animasi harus berurutan, bertumpuk, sinkron, bisa diulang atau dikendalikan playback-nya. | Koreografi dengan timeline, label, dan position parameter; biasanya bersama `gsap-core`. |
| `gsap-scrolltrigger` | Animasi mengikuti scroll, parallax, scrub, atau bagian halaman yang dipin. | Kontrol berdasarkan posisi scroll; bersama `gsap-core`, serta `gsap-timeline` bila berupa rangkaian. |
| `gsap-react` | GSAP dipakai pada React atau Next.js. | Integrasi lifecycle, scope, refs, `useGSAP`, dan cleanup. Tambahkan pada skill GSAP untuk efeknya; React saja bukan alasan wajib memakai GSAP. |
| `gsap-frameworks` | GSAP dipakai pada Vue/Nuxt, Svelte/SvelteKit, atau framework selain React. | Setup lifecycle, scope selector, dan cleanup sesuai framework. |
| `gsap-plugins` | Membutuhkan Flip, Draggable, Inertia, SplitText, ScrambleText, SVG drawing/morph, MotionPath, ScrollTo, ScrollSmoother, atau plugin khusus lain. | Pilih plugin yang dibutuhkan dan ikuti registrasinya. ScrollTrigger memiliki skill tersendiri. |
| `gsap-performance` | Animasi GSAP patah-patah, terlalu banyak elemen bergerak, terlalu banyak pembacaan layout, atau pembaruan pointer/scroll berat. | Diagnosis dan optimasi berdasarkan masalah yang terlihat. Gunakan bila kompleksitas animasi menimbulkan risiko performa. |
| `gsap-utils` | Implementasi GSAP membutuhkan clamp, pemetaan rentang, normalisasi, snap, wrap, random, atau pengolahan kumpulan elemen. | Helper pendukung implementasi; biasanya bukan skill utama untuk desain halaman. |

## Memilih dan menggabungkan

- Ikuti library yang secara eksplisit dipilih pengguna. Untuk proyek yang sudah berjalan, utamakan pendekatan yang konsisten dengan dependensi dan pola setempat.
- Untuk transisi state komponen yang sederhana, pertimbangkan CSS dan `transitions-dev`. Untuk koreografi kompleks, kontrol playback, plugin, atau scroll terkoordinasi, pertimbangkan GSAP. Tentukan berdasarkan kebutuhan, bukan sekadar framework.
- `impeccable` dapat menentukan arah desain, sedangkan `make-interfaces-feel-better` menangani finishing detail. Tidak perlu menjalankan dua evaluasi menyeluruh untuk perubahan komponen kecil.
- `transitions-polish` berfokus pada konsistensi motion; `make-interfaces-feel-better` mencakup detail visual yang lebih luas. Pilih sesuai keluhan, atau gabungkan jika keduanya benar-benar dibutuhkan.
- CSS dan GSAP boleh ada pada halaman yang sama, tetapi hindari keduanya mengendalikan properti yang sama pada elemen yang sama secara bersamaan. Tetapkan pemilik animasi atau gunakan wrapper yang jelas.
- Bila saran antar-skill berbeda, prioritaskan instruksi pengguna, kebutuhan produk, batas proyek, dan pedoman yang paling spesifik untuk tugas. Jelaskan tradeoff yang berdampak pada hasil. Jangan memaksakan seluruh saran secara bersamaan.
- Terapkan reduced motion dan pertahankan keterpakaian tanpa efek dekoratif. Hormati budget performa dan jangan menambah animasi pada setiap interaksi hanya karena tersedia resepnya.

## Contoh pemilihan

| Permintaan | Pilihan awal | Alasan |
| --- | --- | --- |
| Buat dashboard inventaris yang rapi dan mudah dipakai. | `impeccable`; tambah `make-interfaces-feel-better` saat finishing bila diperlukan. | Kebutuhan utama adalah struktur informasi dan usability. |
| Dropdown React ini perlu animasi buka/tutup sederhana. | `transitions-dev`. | Resep CSS bisa mencukupi tanpa menambah library. |
| Landing page Next.js dengan hero berurutan dan section mengikuti scroll. | `impeccable`, `gsap-core`, `gsap-react`, `gsap-timeline`, `gsap-scrolltrigger`. | Ada kebutuhan desain, lifecycle React, koreografi, dan scroll yang berbeda. |
| Animasi modal terasa terlalu lambat dan tidak konsisten. | `transitions-polish` dan referensi modal `transitions-dev`. | Yang diperbaiki adalah motion yang sudah ada. |
| Card dan tombol terasa kurang presisi, tetapi layout sudah benar. | `make-interfaces-feel-better`. | Fokus pada detail, bukan perubahan arah desain. |
| Tampilkan efek AI membangun kartu dari wireframe. | `transitions-ui-generation`. | Ini efek generasi UI khusus. |
| Animasi GSAP pada Vue tetap berjalan setelah pindah halaman. | `gsap-frameworks`; tambah `gsap-core` sesuai implementasi. | Masalah utama lifecycle dan cleanup. |
| Parallax GSAP patah-patah saat scroll. | `gsap-performance`, `gsap-scrolltrigger`. | Diagnosis performa dan konfigurasi animasi scroll. |
| Judul berubah huruf demi huruf dengan GSAP di React. | `gsap-core`, `gsap-react`, `gsap-plugins`. | Membutuhkan integrasi React serta plugin teks yang sesuai. |

## Bentuk respons yang diharapkan

Awali dengan pilihan singkat, misalnya: “Saya memakai impeccable untuk struktur dashboard dan transitions-dev untuk modalnya.” Kemudian lakukan pekerjaan yang diminta. Di akhir, laporkan hasil, pemeriksaan yang dilakukan, serta keterbatasan penting. Hindari menjanjikan hasil paling bagus secara mutlak; tunjukkan kecocokan pilihan dengan kebutuhan pengguna.

## Contoh instruksi saat menyertakan file ini

> Gunakan SKILL.md terlampir sebagai panduan memilih skill. Periksa proyek saya, pilih skill utama dan pendukung yang paling sesuai, baca instruksi aslinya yang tersedia, lalu kerjakan: [jelaskan tugas]. Pertahankan framework dan identitas desain yang sudah ada kecuali saya meminta perubahan.
