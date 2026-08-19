# Icon Mapping: Lucide → Material Symbols

Referensi: `nte-nganjuk/assets/app.css` (aplikasi MD3 referensi).
Dipindahkan seluruhnya dari `<i data-lucide="...">` menjadi `<span class="material-symbols-outlined">nama_ikon</span>`.

## Mapping Utama

| Lucide | Material Symbols |
|--------|------------------|
| menu | menu |
| edit / edit-3 | edit |
| arrow-left | arrow_back |
| arrow-right | arrow_forward |
| eye | visibility |
| trash-2 | delete |
| check-circle / check-circle-2 | check_circle |
| plus | add |
| filter | filter_alt |
| x | close |
| x-circle | cancel |
| inbox | inbox |
| info | info |
| file-text / file-clock | description |
| file-spreadsheet | table_chart |
| file-x-2 | description (kosong) |
| check-square | fact_check |
| camera | photo_camera |
| message-square | chat |
| search | search |
| search-x | search_off |
| calendar | calendar_today |
| calendar-check | event_available |
| list-checks | fact_check / list_alt |
| alert-triangle | warning |
| alert-circle | error |
| chevron-down | expand_more |
| chevron-left | chevron_left |
| chevron-right | chevron_right |
| sparkles | auto_awesome |
| verified (lucide check-circle-2 di laporan) | verified |
| schedule | schedule |
| database | database |
| upload-cloud | upload |
| save | save |
| send | send |
| help-circle | help |
| loader | progress_activity |
| image-off | image_not_supported |
| clipboard-list / clipboard-copy / copy | content_copy |
| timer | timer |
| clock-3 | schedule |
| user-plus | person_add |
| user-x | person_off |
| user-check | person_check |
| shield-check | shield |
| award | workspace_premium |
| tree-pine | forest |
| layers | layers |
| map-pin | map |
| arrow-down-to-line (download) | download |

## Aturan Umum

- Ikon yang dirender dari JavaScript (string HTML) juga wajib memakai Material Symbols; tidak ada lagi `lucide.createIcons()` di halaman aplikasi.
- Ukuran default pakai CSS `.material-symbols-outlined` (20px); kecil `style="font-size:16px"`, besar `font-size:36px`.
- Di dalam `.btn`, pakai `font-size:18px` agar sejajar teks tombol.

## Verifikasi Selesai (19 halaman lint OK)

- kegiatan: index, detail, form
- kth: index, detail, form
- laporan: index, aktivitas
- penyuluh: index, form
- master: aktivitas, tusi
- users: index, form
- settings: app, wilayah
- panduan: index
- profile: password
- dashboard: index

Di luar scope (tetap Lucide, self-load): `pages/landing.php`, `pages/auth/*`.