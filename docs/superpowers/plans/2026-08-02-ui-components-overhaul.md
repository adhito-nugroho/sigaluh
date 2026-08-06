# UI & Component Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rombak total tampilan UI dan komponen aplikasi SI GALUH menggunakan tipografi Plus Jakarta Sans dan desain komponen modern (Glassmorphic cards, rounded-2xl, soft elevation, badge pastel) tanpa tampilan generic/AI slop.

**Architecture:** Pembaruan token Tailwind CSS pada CDN config header, pembaruan class utility di seluruh template PHP untuk merombak Card, Form Input, Button, Table, dan Badge.

**Tech Stack:** PHP Native (Procedural), Tailwind CSS (CDN), Lucide Icons, Google Fonts (Plus Jakarta Sans).

## Global Constraints

- Backend PHP Procedural & PDO tetap utuh tanpa mengubah fungsi / data logic.
- Menggunakan Plus Jakarta Sans (Google Fonts).
- Menghindari gradien norak / AI slop; menggunakan batas 1px yang bersih (`border-slate-200/80`), shadow halus, dan hierarki data yang jelas.

---

### Task 1: Update Global Typography & Tailwind Config (`includes/header.php`)

**Files:**
- Modify: `includes/header.php`

- [ ] **Step 1: Update Google Fonts to Plus Jakarta Sans & update Tailwind config**

Ganti font Inter menjadi Plus Jakarta Sans dan perbarui warna brand pada `includes/header.php`.

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                },
                colors: {
                    brand: {
                        primary: '#4338ca',   // indigo-700
                        secondary: '#312e81', // indigo-900
                        accent: '#8b5cf6',    // violet-500
                        dark: '#0f172a',      // slate-900
                    }
                }
            }
        }
    }
</script>
```

- [ ] **Step 2: Verify page renders without error**

Buka halaman aplikasi di browser dan pastikan font Plus Jakarta Sans berhasil dimuat.

---

### Task 2: Overhaul Sidebar & Layout Navigation (`includes/sidebar.php`)

**Files:**
- Modify: `includes/sidebar.php`

- [ ] **Step 1: Redesign sidebar items with rounded-xl and subtle active states**

Buka `includes/sidebar.php` dan perbarui kelas styling:
- Logo container: `h-16 flex items-center px-6 border-b border-slate-100`
- Navigation item active state: `bg-indigo-50/80 text-indigo-700 font-semibold rounded-xl`
- Hover state: `text-slate-600 hover:bg-slate-50 hover:text-slate-900 rounded-xl`
- User profile footer inside sidebar: `m-4 p-3 bg-slate-50 rounded-2xl border border-slate-100`

---

### Task 3: Overhaul Auth / Login Page (`pages/auth/login.php`)

**Files:**
- Modify: `pages/auth/login.php`

- [ ] **Step 1: Redesign login form card**

Buka `pages/auth/login.php` dan perbarui:
- Font link: Tambahkan link Plus Jakarta Sans di header.
- Card wrapper: `max-w-md bg-white/90 backdrop-blur-xl rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 p-8`
- Heading: `text-2xl font-extrabold text-slate-900 tracking-tight`
- Input field: `w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 text-sm transition-all outline-none`
- Button submit: `w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-md shadow-indigo-500/20 active:scale-[0.98] transition-all`

---

### Task 4: Overhaul Dashboard View (`pages/dashboard/index.php`)

**Files:**
- Modify: `pages/dashboard/index.php`

- [ ] **Step 1: Update dashboard summary cards, status badges, and table styling**

Buka `pages/dashboard/index.php`:
- Ganti status badge helper `get_status_badge()`:
  - `draft`: `bg-slate-100 text-slate-700 border border-slate-200 rounded-full px-3 py-1 text-xs font-semibold`
  - `submitted`: `bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full px-3 py-1 text-xs font-semibold`
  - `direview`: `bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-3 py-1 text-xs font-semibold`
- Summary Stat Cards: `bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all`
- Table wrapper: `bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm`
- Table headers: `bg-slate-50/80 px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider`
- Chart container: `bg-white rounded-2xl p-6 border border-slate-100 shadow-sm`

---

### Task 5: Overhaul Kegiatan Penyuluh Module (`pages/kegiatan/*`)

**Files:**
- Modify: `pages/kegiatan/index.php`
- Modify: `pages/kegiatan/form.php`
- Modify: `pages/kegiatan/detail.php`

- [ ] **Step 1: Update Index, Form, and Detail views for Kegiatan**

- Index: Update search/filter bar, button `Tambah Kegiatan` (`bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-xl shadow-sm shadow-indigo-500/20`), table layout `rounded-2xl border border-slate-200/80`.
- Form: Accordion & section cards with `rounded-2xl border border-slate-200/80 p-6`, inputs with `rounded-xl border-slate-200 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600`.
- Detail: Info card with `rounded-2xl border border-slate-200/80 p-6 bg-white`.

---

### Task 6: Overhaul Kelompok Tani Hutan (KTH) Module (`pages/kth/*`)

**Files:**
- Modify: `pages/kth/index.php`
- Modify: `pages/kth/form.php`
- Modify: `pages/kth/detail.php`

- [ ] **Step 1: Update KTH Index, Form, and Detail views**

Selaraskan styling komponen KTH dengan gaya `rounded-2xl`, form `rounded-xl`, dan tombol gradient/shadow indigo yang modern.

---

### Task 7: Overhaul Laporan, Users, Panduan & Profile Views

**Files:**
- Modify: `pages/laporan/index.php`
- Modify: `pages/users/index.php`
- Modify: `pages/users/form.php`
- Modify: `pages/panduan/index.php`
- Modify: `pages/profile/password.php`

- [ ] **Step 1: Update remaining module templates**

Terapkan Plus Jakarta Sans, rounded-2xl cards, rounded-xl inputs, dan badge pastel pada halaman Laporan, Users, Panduan, dan Ubah Password.
