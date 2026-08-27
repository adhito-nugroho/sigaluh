# Instalasi Modul Log Aktivitas

## Langkah Instalasi

### 1. Jalankan script SQL untuk membuat tabel

Buka phpMyAdmin atau MySQL client, pilih database `sigaluh2`, kemudian jalankan SQL berikut:

```sql
CREATE TABLE IF NOT EXISTS activity_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  action        VARCHAR(50) NOT NULL,
  module        VARCHAR(50) NOT NULL,
  description   TEXT,
  data_before   TEXT,
  data_after    TEXT,
  ip_address    VARCHAR(45),
  user_agent    TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_module (module),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Atau import file: `database/migrations/create_activity_logs_table.sql`

### 2. Include activity logger di file yang membutuhkan

Tambahkan di bagian atas file yang ingin dicatat aktivitasnya:

```php
require_once __DIR__ . '/../../includes/activity_logger.php';
```

### 3. Gunakan fungsi log_activity()

Contoh penggunaan:

```php
// Login
log_activity('login', 'auth', 'User login ke sistem');

// Logout
log_activity('logout', 'auth', 'User logout dari sistem');

// Create
log_activity('create', 'kegiatan', 'Menambah kegiatan baru', null, $data_kegiatan);

// Update
log_activity('update', 'users', 'Mengubah data user', $data_lama, $data_baru);

// Delete
log_activity('delete', 'kth', 'Menghapus data KTH', $data_kth, null);

// View/Export
log_activity('export', 'laporan', 'Ekspor laporan bulanan periode ' . $periode);
```

### 4. Akses halaman log

Menu "Log Aktivitas" telah ditambahkan di sidebar untuk user admin.
Akses melalui: `/index.php?page=logs`

## Fitur

- ✓ Filter berdasarkan pengguna, aksi, modul, tanggal
- ✓ Pencarian deskripsi dan nama pengguna
- ✓ Pagination (50 data per halaman)
- ✓ Lihat detail perubahan data (data_before & data_after)
- ✓ Mencatat IP address dan user agent
- ✓ Hanya dapat diakses oleh admin

## File yang Dibuat

1. `database/migrations/create_activity_logs_table.sql` - Schema database
2. `database/migrations/install_activity_logs.php` - Script instalasi otomatis
3. `includes/activity_logger.php` - Helper functions untuk logging
4. `pages/logs/index.php` - Halaman tampilan log
5. `api/logs/detail.php` - API untuk detail log
6. `includes/sidebar.php` - Menu log ditambahkan (sudah diupdate)

## Integrasi dengan Modul Lain

Untuk mencatat aktivitas di modul yang sudah ada, tambahkan pemanggilan `log_activity()` di:

- `pages/auth/login.php` - saat login berhasil
- `pages/auth/logout.php` - saat logout
- `pages/kegiatan/process.php` - saat CRUD kegiatan
- `pages/kth/process.php` - saat CRUD KTH
- `pages/users/process.php` - saat CRUD users
- `pages/master/tusi/index.php` - saat CRUD TUSI
- dll.
