# AI Auto Article Generator

Plugin WordPress untuk meng-generate artikel secara otomatis menggunakan Anthropic Claude API.

## Fitur Utama
1. **Dynamic Post Type**: Menyimpan hasil generate AI ke sembarang Post Type publik.
2. **Word Count Range**: Atur minimal dan maksimal kata yang diinginkan.
3. **Batch Generation**: Masukkan daftar judul (1 judul per baris), masing-masing akan dibuatkan 1 artikel.
4. **Prompt Templates**: Template mendukung placeholder: `{{title}}`, `{{min_words}}`, `{{max_words}}`, `{{knowledge_base}}`, `{{site_name}}`, `{{current_date}}`.
5. **Knowledge Base**: Tambahkan teks panjang untuk disisipkan ke dalam template.
6. **Auto Schedule**: Posting otomatis (Future) dengan jarak random antar postingan.
7. **Queue System**: Eksekusi melalui WP-Cron di background (1 artikel per eksekusi, jalan setiap 5 menit) agar tidak membebani hosting.

## Cara Instalasi
1. Upload folder `ai-auto-article-generator` ke dalam `/wp-content/plugins/`.
2. Aktifkan plugin melalui menu **Plugins** di dashboard WordPress.
3. Buka menu **AI Auto Article -> Settings**.
4. Masukkan **Anthropic API Key** (atau tambahkan `define( 'AI_ARTICLE_ANTHROPIC_API_KEY', 'your_key' );` di `wp-config.php`).
5. Uji koneksi dengan klik tombol **Test API Connection**.

## Cara Penggunaan
1. Buat **Prompt Template** baru di menu *Prompt Template*.
2. (Opsional) Buat **Knowledge Base** jika ingin menyisipkan informasi spesifik.
3. Buka menu **Generate Artikel**.
4. Pilih Post Type, Template, Knowledge Base, rentang kata, status, dan masukkan daftar judul.
5. Klik **Generate ke Antrean**.
6. Buka menu **Daftar Job** untuk melihat status. Job yang berstatus "pending" akan diproses otomatis oleh WP-Cron setiap 5 menit.
7. Anda juga dapat menjalankan eksekusi paksa dengan menekan tombol **Run Now** di halaman *Daftar Job*.

## Persyaratan
- WordPress 6.0+
- PHP 8.0+
