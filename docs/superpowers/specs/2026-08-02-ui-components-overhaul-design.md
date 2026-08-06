# Design Spec: UI & Component Overhaul (SI GALUH)

## Overview
Rombak total tampilan UI dan komponen sistem informasi **SI GALUH** dengan menggunakan tipografi modern **Plus Jakarta Sans** dan gaya komponen **Glassmorphism & Soft Elevation** untuk menciptakan kesan aplikasi SaaS enterprise modern, bersih, dan berkelas.

## Visual & Design System Guidelines

### 1. Tipografi (Typography)
- **Font Family**: `Plus Jakarta Sans` (Google Fonts: weights 400, 500, 600, 700, 800).
- **Konfigurasi Tailwind**: `fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }`.

### 2. Skema Warna (Color Tokens - Executive Indigo)
- `brand-primary`: `#4338ca` (Indigo-700)
- `brand-secondary`: `#312e81` (Indigo-900 / Navy)
- `brand-accent`: `#8b5cf6` (Violet-500)
- `brand-gradient`: `from-indigo-600 to-indigo-800`

### 3. Komponen Desain System (Component System)
- **Cards & Panels**:
  - Border radius: `rounded-2xl`
  - Background & Glassmorphism: `bg-white border border-slate-100/80 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all duration-200`
- **Buttons (Tombol)**:
  - **Primary**: `bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-5 py-2.5 shadow-sm shadow-indigo-500/20 active:scale-[0.98] transition-all`
  - **Secondary/Outline**: `bg-white hover:bg-slate-50 text-slate-700 font-medium rounded-xl border border-slate-200 px-5 py-2.5 shadow-sm active:scale-[0.98] transition-all`
  - **Danger**: `bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl px-4 py-2 shadow-sm active:scale-[0.98] transition-all`
- **Form Inputs & Selects**:
  - `w-full rounded-xl border border-slate-200 px-4 py-2.5 text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 transition-all outline-none text-sm`
- **Tables**:
  - Container: `rounded-2xl border border-slate-200/80 overflow-hidden bg-white`
  - Header: `bg-slate-50/80 text-slate-500 uppercase tracking-wider text-xs font-bold py-3.5 px-6`
  - Rows: `hover:bg-indigo-50/30 transition-colors divide-y divide-slate-100 text-sm`
- **Status Badges (Pills)**:
  - `px-3 py-1 text-xs font-semibold rounded-full inline-flex items-center gap-1.5`
  - Draft: `bg-slate-100 text-slate-600 border border-slate-200`
  - Submitted: `bg-indigo-50 text-indigo-700 border border-indigo-200`
  - Direview: `bg-emerald-50 text-emerald-700 border border-emerald-200`
- **Sidebar & Topbar**:
  - Sidebar logo & menu items: `rounded-xl`, subtle active indicator with indigo background glow.
  - User profile section in sidebar: `bg-slate-50/80 rounded-xl p-3 border border-slate-100`.

## Target Files Affected
- `includes/header.php` (Font Google + Tailwind Config)
- `includes/sidebar.php` (Sidebar items & rounded corners)
- `includes/footer.php`
- `pages/auth/login.php`
- `pages/dashboard/index.php`
- `pages/kegiatan/index.php`, `form.php`, `detail.php`
- `pages/kth/index.php`, `form.php`, `detail.php`
- `pages/laporan/index.php`
- `pages/users/index.php`, `form.php`
- `pages/panduan/index.php`
- `pages/profile/password.php`
