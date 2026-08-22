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
