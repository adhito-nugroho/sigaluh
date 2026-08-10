# Kelola TUSI (Tugas dan Fungsi) Level Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyiapkan halaman manajemen Master TUSI terpadu pada level Admin untuk mengelola data Seksi TUSI (`m_tusi`) dan rincian Uraian Tugas & Substansi Materi (`m_kegiatan_tusi`) beserta proteksi relasi data.

**Architecture:** Tampilan Single-Page (Two-Level View) berbasis Tab Navigasi Seksi TUSI dan Data Table Rincian Tugas. Penanganan request POST PHP murni menggunakan PDO prepared statements dan CSRF verification, dilengkapi fitur instant status toggle (aktif/non-aktif) dan modal dialog re-usable.

**Tech Stack:** PHP (PDO, Session CSRF), HTML5, Tailwind CSS, Lucide Icons, JavaScript (Vanilla ES6).

## Global Constraints

- PHP 8.x + MySQL PDO prepared statements
- CSRF validation via `verify_csrf_token()`
- Strict Role check via `has_role('admin')`
- Foreign Key checks to prevent orphaned data in `m_kegiatan_tusi` and `kegiatan`

---

### Task 1: Navigation & Routing Integration

**Files:**
- Modify: `includes/sidebar.php:70-92`
- Modify: `index.php:54-74`

**Interfaces:**
- Consumes: `BASE_URL`, `has_role()`, `get_active_class()`, `get_active_icon_class()`
- Produces: Sidebar link `page=master/tusi` and Breadcrumb routing for `master/tusi`

- [ ] **Step 1: Update `includes/sidebar.php` to add Master TUSI menu link**

Add the sidebar menu item under the Administrasi section for Admin users in [includes/sidebar.php](file:///d:/laragon/www/sigaluh2/includes/sidebar.php#L80-L90):

```php
                <?php if (has_role('admin')): ?>
                <a href="<?= BASE_URL ?>/index.php?page=master/tusi" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('master/tusi', $current_page) ?>">
                    <i data-lucide="layers" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('master/tusi', $current_page) ?>"></i>
                    <span>Master TUSI</span>
                </a>
                <?php endif; ?>
```

- [ ] **Step 2: Update `get_breadcrumb()` in `index.php`**

Add `'master/tusi' => ['Master Data', 'Kelola TUSI']` to the `$map` array in [index.php](file:///d:/laragon/www/sigaluh2/index.php#L54-L74).

- [ ] **Step 3: Verify routing and sidebar render**

Run browser check or check page navigation manually.

- [ ] **Step 4: Commit Navigation changes**

```bash
git add includes/sidebar.php index.php
git commit -m "feat(nav): add master tusi route and sidebar menu for admin"
```

---

### Task 2: Core Page Logic & Backend Handlers (`pages/master/tusi/index.php`)

**Files:**
- Create: `pages/master/tusi/index.php`

**Interfaces:**
- Consumes: `$pdo`, `$_SESSION`, `verify_csrf_token()`, `e()`, `has_role('admin')`
- Produces: Backend CRUD operations (`create_seksi`, `update_seksi`, `delete_seksi`, `create_kegiatan`, `update_kegiatan`, `delete_kegiatan`, `toggle_status`) and data fetching.

- [ ] **Step 1: Create `pages/master/tusi/index.php` with authorization and CRUD handlers**

Write the backend logic in `pages/master/tusi/index.php`:
1. Check `has_role('admin')`, redirect if false.
2. CSRF verification on POST request.
3. Process actions:
   - `create_seksi`: Insert `kode`, `nama` into `m_tusi`.
   - `update_seksi`: Update `kode`, `nama` in `m_tusi` by `id`.
   - `delete_seksi`: Check if `m_kegiatan_tusi` count > 0 for this `tusi_id`. If yes, set error message. If no, delete from `m_tusi`.
   - `create_kegiatan`: Insert `tusi_id`, `uraian_tugas`, `substansi_materi`, `aktif` into `m_kegiatan_tusi`.
   - `update_kegiatan`: Update `m_kegiatan_tusi` by `id`.
   - `delete_kegiatan`: Check if `kegiatan` count > 0 where `kegiatan_tusi_id = ?`. If yes, set error message. If no, delete from `m_kegiatan_tusi`.
   - `toggle_status`: Switch `aktif` between `1` and `0` for `m_kegiatan_tusi`.
4. Fetch all Seksi TUSI from `m_tusi` with rincian count.
5. Determine active Seksi ID (`$active_tusi_id`).
6. Fetch rincian `m_kegiatan_tusi` for active Seksi with optional search `q` and status filter `status`.

- [ ] **Step 2: Commit Backend Logic**

```bash
git add pages/master/tusi/index.php
git commit -m "feat(tusi): implement backend CRUD logic and security validation"
```

---

### Task 3: Interactive UI (Tabs, Table, Modals & JS Handlers) in `pages/master/tusi/index.php`

**Files:**
- Modify: `pages/master/tusi/index.php`

**Interfaces:**
- Consumes: Backend `$tusi_list`, `$kegiatan_tusi_list`, `$active_tusi_id`, `$csrf_token`
- Produces: Responsive UI with Tabbed Navigation, Search/Filter Toolbar, Data Table, Status Badges, Edit/Delete/Toggle Forms, and Modal Dialogs.

- [ ] **Step 1: Complete HTML Header, Tab Bar, Toolbar, and Data Table in `pages/master/tusi/index.php`**

Render:
- Header with title, description, and "+ Tambah Seksi TUSI" button.
- Horizontal Tab Bar showing each Seksi TUSI (`kode` - `nama` (`count`)), with active highlight and mini Edit/Delete actions for active Seksi.
- Toolbar with Search Input, Status Filter dropdown, and "+ Tambah Uraian Tugas" button.
- Responsive Data Table for `m_kegiatan_tusi` showing No, Uraian Tugas, Substansi Materi, Status Badge, and Action buttons (Toggle Status, Edit, Delete).

- [ ] **Step 2: Implement Modal Dialogs (Seksi TUSI & Uraian Tugas)**

Add Tailwind modal markup for:
- `modalSeksi`: Form to create/edit Seksi TUSI (`kode`, `nama`, hidden `action`, `id`).
- `modalKegiatan`: Form to create/edit Uraian Tugas (`tusi_id`, `uraian_tugas`, `substansi_materi`, `aktif`, hidden `action`, `id`).
- `modalDelete`: Confirmation modal with CSRF token and warning text.

- [ ] **Step 3: Add JavaScript Modal & Form helper functions**

Add Vanilla JS script:
- `openModalSeksiCreate()`, `openModalSeksiEdit(id, kode, nama)`, `closeModalSeksi()`
- `openModalKegiatanCreate()`, `openModalKegiatanEdit(id, tusiId, uraian, substansi, aktif)`, `closeModalKegiatan()`
- `openModalDelete(action, id, itemName)`
- `lucide.createIcons()` call on page load.

- [ ] **Step 4: Verify complete functionality and data flow**

Verify:
- Navigation from sidebar works.
- Switching tabs updates active Seksi TUSI.
- Adding, editing, and deleting Seksi TUSI works cleanly.
- Adding, editing, deleting, and toggling status for Uraian Tugas TUSI works cleanly.
- Foreign Key safety prevents invalid deletion when data is in use.

- [ ] **Step 5: Commit complete TUSI management feature**

```bash
git add pages/master/tusi/index.php
git commit -m "feat(tusi): finalize tabbed UI, modal forms, and client script"
```
