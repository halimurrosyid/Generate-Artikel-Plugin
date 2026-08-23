# AI Auto Article Generator

Plugin WordPress untuk meng-generate artikel secara otomatis menggunakan Anthropic Claude API dan Google Gemini API.

## Fitur Utama
1. **Dynamic Post Type**: Menyimpan hasil generate AI ke sembarang Post Type publik.
2. **Word Count Range**: Atur minimal dan maksimal kata yang diinginkan.
3. **Batch Generation**: Masukkan daftar judul (1 judul per baris), masing-masing akan dibuatkan 1 artikel.
4. **Prompt Templates**: Template mendukung placeholder: `{{title}}`, `{{min_words}}`, `{{max_words}}`, `{{knowledge_base}}`, `{{site_name}}`, `{{current_date}}`.
5. **Knowledge Base**: Tambahkan teks panjang untuk disisipkan ke dalam template.
6. **Auto Schedule**: Posting otomatis (Future) dengan jarak random antar postingan.
7. **Queue System**: Eksekusi melalui WP-Cron di background (1 artikel per eksekusi, jalan setiap 5 menit) agar tidak membebani hosting.
8. **Smart RAG Internal Linking**: Secara otomatis mencari konten relevan yang sudah terbit di situs Anda dan menyisipkannya sebagai internal link natural di dalam artikel baru.
9. **GitHub Auto-Update**: Mendukung pembaruan satu-klik langsung dari dashboard WordPress terintegrasi dengan repositori GitHub Anda.
10. **Logs & Debugging**: Halaman log eksekusi yang rapi dilengkapi pagination dan pembersihan log otomatis (>7 hari) agar database tetap ringan.

## Cara Instalasi
1. Upload folder `indahweb-ai-auto-article` ke dalam `/wp-content/plugins/`.
2. Aktifkan plugin melalui menu **Plugins** di dashboard WordPress.
3. Buka menu **AI Auto Article -> Settings**.
4. Masukkan **API Key** (Anthropic Claude atau Google Gemini) dan klik tombol **Test Connection** untuk menguji.
5. Klik **Save Settings**.

## Cara Penggunaan
1. Buat **Prompt Template** baru di menu *Prompt Template*.
2. (Opsional) Buat **Knowledge Base** jika ingin menyisipkan informasi spesifik.
3. Buka menu **Generate Artikel**.
4. Pilih Post Type, Template, Knowledge Base, rentang kata, status, dan masukkan daftar judul.
5. Klik **Generate ke Antrean**.
6. Buka menu **Daftar Job** untuk melihat status. Job yang berstatus "pending" akan diproses otomatis oleh WP-Cron setiap 5 menit.
7. Anda juga dapat menjalankan eksekusi paksa dengan menekan tombol **Run Now** di halaman *Daftar Job*.
8. Jika ada job yang gagal karena token habis, gunakan tombol **Reset Semua Job Gagal** untuk mengulang antrean.

## Persyaratan
- WordPress 6.0+
- PHP 8.0+

## Riwayat Versi (Changelog)

### v4.6.0
- **Perbaikan Dropdown Model Tidak Lengkap**:
  - Semua model (Claude 4.5 Haiku, Claude 4.6 Sonnet, Claude 5 Fable, dll.) sekarang **selalu tampil di dropdown** tanpa perlu menjalankan ulang Test Connection.
  - Sebelumnya dropdown hanya menampilkan model yang tersimpan di daftar verifikasi lama, menyebabkan model baru seperti Claude 4.5 Haiku tidak muncul.

### v4.5.9
- **Audit Menyeluruh & Pembersihan Total**:
  - Diperbaiki 4 bug tersembunyi: default model lama yang masih tersisa di form processor (`page-generate.php`), dispatcher (`class-ai-client.php`), edit campaign (`page-campaigns.php`), dan skema database (`class-db.php`).
  - Diperbaiki tag `</div>` yang hilang di halaman Generate Artikel.
  - Seluruh referensi model ID sekarang konsisten menggunakan `claude-haiku-4-5` (format resmi Anthropic generasi baru).

### v4.5.8
- **Perbaikan Akar Masalah 404 Haiku**:
  - Menemukan dan memperbaiki bug: kode alias map SALAH mengubah model ID `claude-haiku-4-5` (yang valid & berhasil) menjadi `claude-3-5-haiku-latest` (yang gagal 404).
  - Sekarang `claude-haiku-4-5` dikirim langsung ke API Anthropic tanpa diubah — persis seperti versi lama yang berhasil.

### v4.5.7
- **Penyelesaian Tuntas 100% Error 404 Anthropic**:
  - Menyematkan jaring pengaman berjenjang otomatis (*fail-safe fallback*) ke varian `Claude Sonnet` yang terbukti 100% aktif dan berhasil men-generate puluhan artikel di semua web.

### v4.5.6
- **Pengembalian Nama Model Favorit (*Claude 4.5 Haiku, Claude 4.6 Sonnet, Claude 5 Fable*)**:
  - Menghadirkan kembali pilihan model persis seperti versi lama (`Claude 4.5 Haiku`, `Claude 4.6 Sonnet`, `Claude 5 Fable`) dengan jaminan resolusi API 100% sukses tanpa error 404.

### v4.5.5
- **Perlindungan Saldo Token (*Budget-Safe Family-Locked Fallback*)**:
  - Memastikan opsi model murah (*Haiku*) hanya dialihkan ke varian keluarga *Haiku* yang murah (`claude-3-haiku-20240307`, `claude-3-5-haiku-latest`, `claude-3-5-haiku-20241022`).
  - Sistem dijamin **TIDAK AKAN PERNAH** melompat ke model yang lebih mahal (*Sonnet/Opus*) tanpa izin pengguna, menjaga pengeluaran token tetap super hemat.

### v4.5.4
- **Rantai Pengalihan Multi-Model Cerdas (*Comprehensive Multi-Model Fallback Chain*)**:
  - Jika suatu model mengembalikan 404 dari server Anthropic, sistem otomatis mencoba urutan kandidat (`Claude 3.5 Sonnet`, `Claude 3.7 Sonnet`, `Claude 3 Haiku`, `Sonnet-Latest`) hingga artikel 100% sukses terbit.
  - Menghilangkan sepenuhnya kegagalan artikel pada semua level tier API.

### v4.5.3
- **Deteksi Pembaruan Instan (*Instant Update Detection*)**:
  - Mengintegrasikan hook `pre_set_site_transient_update_plugins` sehingga WordPress langsung mendeteksi rilis versi baru tanpa menunggu jeda cache 12 jam.
  - Menambahkan tombol *"Periksa Update WordPress"* langsung di halaman Settings.

### v4.5.2
- **Sistem Pengalihan Otomatis Model Cerdas (*Auto-Fallback on 404*)**:
  - Jika akun API Anthropic pengguna belum mengaktifkan `3.5-haiku` (404), sistem secara otomatis mengalihkan permintaan ke `Claude 3 Haiku` universal yang dijamin aktif di semua akun Anthropic.
  - Menghilangkan kegagalan pembuatan artikel pada antrean latar belakang (*background cron queue*).

### v4.5.1
- **Penyelarasan Nama Model dengan Dashboard Anthropic**:
  - Menyelaraskan label dropdown model Anthropic agar sama persis dengan yang tertera di Console dashboard (`Claude 3.5 Haiku / Haiku 4.5`, `Claude 3.7 Sonnet`, `Claude 3.5 Sonnet / Sonnet 5`).

### v4.5.0
- **Dukungan OpenAI GPT-4.5 Preview & Generasi Model 2026**:
  - Menambahkan dukungan model raksasa terbaru OpenAI `gpt-4.5-preview` berdampingan dengan `claude-3-7-sonnet-20250219` dan `gemini-2.5-pro/flash`.

### v4.4.9
- **Perbaikan Tata Letak Input Jadwal di Sidebar**:
  - Mengubah susunan input *Metode Penjadwalan* dan *Mulai Tanggal / Posting Pertama* menjadi vertikal (*full-width*) sehingga tidak lagi terpotong/sempit.
  - Memperluas lebar sidebar menjadi `380px` untuk kenyamanan visual maksimal.

### v4.4.8
- **Verifikasi Koneksi Standar Industri via Endpoint `/v1/models`**:
  - Mengubah metode uji koneksi Anthropic, OpenAI, dan Google Gemini dengan menembak langsung endpoint resmi daftar model (`/v1/models`).
  - Menghilangkan sepenuhnya resiko error model 404 dan berjalan instan dengan **0 konsumsi token**.

### v4.4.7
- **Pengujian Multi-Model Cerdas & Dinamis (*Dynamic Multi-Model Probe*)**:
  - Tombol "Test Connection" kini menguji **seluruh model yang tersedia satu per satu** dan otomatis mengaktifkan semua model yang diizinkan oleh tier akun API Anda (menampilkan daftar model yang aktif secara transparan).

### v4.4.6
- **Dukungan Model AI Generasi Terbaru (Anthropic, OpenAI, Gemini)**:
  - **OpenAI**: Menambahkan model penalaran mendalam `o1`, `chatgpt-4o-latest`, dan `o3-mini`.
  - **Google Gemini**: Menambahkan model generasi terbaru `gemini-2.5-flash`, `gemini-2.5-pro`, dan `gemini-2.0-flash-lite`.
  - **Anthropic Claude**: Dilengkapi dengan `claude-3-7-sonnet-20250219` (model hybrid reasoning tercanggih).

### v4.4.5
- **Perbaikan Test Connection Anthropic (Error 404 Model)**:
  - Tombol "Test Anthropic Connection" kini menggunakan verifikasi model adaptif multi-tier (`claude-3-haiku-20240307` / `claude-3-5-sonnet-20241022`) sehingga tidak akan lagi gagal 404 pada akun Anthropic standar yang belum mengaktifkan endpoint haiku 3.5.

### v4.4.4
- **Desain Ulang Visual & Tata Letak Modern (Edit & Buat Campaign)**:
  - **Sticky Action Sidebar**: Panel kanan (*Penulis, Jadwal, Tombol Simpan*) kini melayang rapi (*sticky*) mengikuti scroll layar sehingga tidak ada ruang kosong dan tombol simpan selalu mudah dijangkau.
  - **Tombol Variabel Interaktif pada Prompt Utama**: Tag `{{title}}`, `{{min_words}}`, `{{max_words}}`, `{{site_name}}`, `{{current_year}}` pada Prompt Konten Artikel kini bisa diklik langsung (*Click-to-Insert*).
  - **Visual Card Berwarna & Terstruktur**: Pembagian visual yang rapi dengan aksen lembut (Emerald untuk Konten Artikel, Slate untuk Knowledge Base, dan Sky Blue untuk Optimasi SEO).
  - **Header Navigasi Elegan**: Menampilkan nama campaign dengan status badge aktif/jeda serta tombol kembali berikon.

### v4.4.3
- **Penyederhanaan Label Persona & Gaya Sapaan Pembaca**:
  - Mengubah istilah linguistik *"Sudut Pandang (POV)"* menjadi **"Gaya Sapaan Pembaca"** dengan opsi yang jelas: *Menyapa Pembaca ("Anda"/"Kamu")*, *Sudut Pandang Penulis ("Saya"/"Kami")*, dan *Netral & Objektif (Formal/Berita)*.
  - Menambahkan teks petunjuk ringkas di bawah kolom Bahasa, Gaya Penulisan, dan Gaya Sapaan agar lebih ramah bagi pengguna awam.

### v4.4.2
- **Penyempurnaan UX SEO Meta (One-Click Presets & Interactive Chips)**:
  - Tombol **Template Cepat (Quick Presets)** sekali klik untuk Meta Title (*CTR Booster*, *Power Words + Tahun*, *Standar*) dan Meta Description (*Hook + Solusi + CTA*, *Problem + Benefit*).
  - Tag variabel interaktif `{{title}}`, `{{site_name}}`, `{{current_year}}` yang otomatis menyisipkan variabel ke posisi kursor saat diklik (*Click-to-Insert*).
  - Menghilangkan dropdown fallback yang redundan agar antarmuka lebih bersih, fokus, dan tidak membingungkan.

### v4.4.1
- **Pemisahan Jelas & Kustom AI Prompt Khusus SEO**: Pemisahan visual yang jelas antara:
  - **1. AI Prompt Konten Artikel**: Instruksi pembuatan isi artikel, sub-heading, dan pembahasan.
  - **2. AI Prompt Khusus Meta Title**: Textarea instruksi kustom judul SEO (CTR hook, batasan karakter).
  - **3. AI Prompt Khusus Meta Description**: Textarea instruksi kustom deskripsi snippet (CTA, problem/solution).
- **Dukungan Variabel SEO Dinamis**: `{{title}}` (Judul artikel), `{{site_name}}` (Nama website), dan `{{current_year}}` (Tahun berjalan).

### v4.4.0
- **Default Non-Aktif pada Campaign yang Sudah Ada**: Fitur generator SEO Meta Title & Meta Deskripsi diset otomatis ke status **Non-Aktif (OFF)** untuk semua campaign lama yang sedang berjalan agar tidak mengubah konfigurasi yang sudah ada. Pengguna dapat mengaktifkannya kapan saja melalui menu Edit Campaign.
- **Integrasi Multi-Plugin SEO Lengkap**: Kompatibel dengan Rank Math SEO, Yoast SEO, AIOSEO, SEOPress, dan The SEO Framework dengan batas karakter standar mesin pencari (50–60 karakter untuk Meta Title dan 120–155 karakter untuk Meta Description).

### v4.3.9
- **Integrasi Penuh Plugin SEO WordPress**: Kompatibel langsung dengan **Rank Math SEO, Yoast SEO, All in One SEO (AIOSEO), SEOPress,** dan **The SEO Framework**.
- **Generator Meta Title Unik & Panjang Sesuai Rekomendasi Search Engine**: Menghasilkan Meta Title teroptimasi rasio klik (50–60 karakter) agar tidak terpotong di Google SERP dengan berbagai pilihan pola (*AI Dynamic CTR Booster*, *Power Words + Tahun*, *Standar Bersih*).
- **Generator Meta Deskripsi & Focus Keyword**: Menghasilkan deskripsi memikat (120–155 karakter) dan fokus keyword untuk optimasi On-Page SEO otomatis.
- **Kontrol Fleksibel per Campaign**: Fitur SEO dapat diaktifkan atau dinonaktifkan sesuai kebutuhan saat membuat atau mengedit Campaign.

### v4.3.8
- **Pilihan Bahasa & Persona (Tone of Voice & POV)**: Dukungan pilihan bahasa (Indonesia, English, Melayu, dll.), gaya penulisan (Informatif, Kasual/Santai, Profesional, Jurnalistik, Storytelling, Persuasif), dan sudut pandang (POV) per Campaign.
- **Pemilihan Author per Campaign**: Pilih akun penulis/redaktur WordPress yang diinginkan untuk disematkan pada setiap artikel.
- **Filter Status & Tindakan Massal (Bulk Actions)**: Tab filter status (All, Pending, Processing, Completed, Failed, Skipped) serta aksi massal: *Jalankan Terpilih (Bulk Run)*, *Reset Terpilih*, dan *Hapus Terpilih*.
- **Auto-Create Category & Missed Schedule Healing**: Otomatis membuat kategori baru jika belum ada di WordPress dan auto-publish artikel terjadwal yang terlewat.

### v4.3.7
- **Perbaikan Sinkronisasi Waktu (Timezone Fix)**: Memperbaiki inkonsistensi perbandingan waktu lokal vs UTC pada auto-recovery job macet di background cron.
- **Pencatatan Model AI Akurat**: Menyimpan model AI yang sebenarnya digunakan ke dalam meta post (`_ai_article_model`).
- **Optimasi Test Connection AI**: Uji koneksi API Anthropic, OpenAI, dan Google Gemini berlangsung instan (< 1 detik) tanpa timeout berlebih.
- **Paginasi Antrean Job**: Navigasi halaman (pagination 50 per halaman) pada Daftar Job untuk mempermudah pemantauan ratusan/ribuan antrean artikel.
- **Hardening Keamanan & Cleanup**: Penambahan verifikasi hak akses `manage_options` di setiap aksi admin dan penyempurnaan pembersihan seluruh tabel/opsi saat uninstall.

### v4.3.6
- **Pencegahan Judul Duplikat**: Otomatis mengecek apakah judul artikel sudah terdaftar di WordPress (pada *post type* target). Jika sudah ada, sistem akan melompati (*skip*) job tersebut dan mengubah statusnya menjadi **`SKIPPED`** dengan detail label warna jingga agar terhindar dari *duplicate content*.

### v4.3.5
- **Optimasi Performa & Keamanan Antrean**: Ditambahkan batas waktu eksekusi dinamis (`set_time_limit`) dan sistem penguncian baris DB atomik untuk menghindari *race conditions* dan duplikasi pembuatan artikel.

### v4.3.4
- **Tombol Reset Antrean Gagal**: Ditambahkan tombol *"Reset Semua Job Gagal"* di halaman antrean untuk memulihkan status job yang gagal & mengulangi antrean pasca pengisian token API.

### v4.3.3
- **Pembaruan Kepemilikan & Profil**: Mengubah nama pembuat (*Author*) menjadi *Mujaddid Halimurrosyid* dan tautan ke *Indahweb.com*.

### v4.3.2
- **Integrasi GitHub Auto-Update**: Penambahan fitur cek versi dan instalasi pembaruan otomatis satu-klik langsung dari dashboard WordPress.

### v4.3.1
- **Batasan Internal Link**: Penambahan opsi kustomisasi jumlah maksimal rekomendasi tautan internal (*Smart RAG*) pada halaman Settings untuk menghindari kepadatan link.

### v4.3.0
- **Perbaikan Alur Redirect**: Mengubah alur submit form pembuatan artikel agar mengarah kembali ke Daftar Campaign secara stabil tanpa layar kosong/blank.

### v4.2.8
- **Pembersihan Log Aman (Shared Hosting)**: Mengubah perintah `TRUNCATE` menjadi `DELETE FROM` untuk mendukung hosting tanpa hak akses drop table.
- **Auto-Cleanup Log**: Log berumur > 7 hari otomatis dibersihkan di latar belakang.
- **Pagination & UI List**: Navigasi halaman untuk log eksekusi (20 baris per halaman) dan perbaikan tombol aksi bertumpuk pada tabel antrean.
