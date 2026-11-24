/**
 * Web App penerima arsip backup SI GALUH.
 *
 * Cara pasang (sekali saja, di akun Google pemilik Drive tujuan):
 * 1. Buka https://script.google.com > New project.
 * 2. Hapus isi Code.gs, tempel seluruh isi file ini, simpan.
 * 3. Deploy > New deployment > pilih "Web app".
 *    - Execute as        : Me
 *    - Who has access    : Anyone
 * 4. Salin URL yang berakhiran /exec, tempel ke
 *    Pengaturan > Backup Data > URL Web App Apps Script di aplikasi.
 *
 * Setelah itu tombol "Kirim ke Drive" pada daftar arsip akan
 * mengunggah zip ke folder Drive bernama FOLDER_NAME.
 */

var FOLDER_NAME = 'Backup SI GALUH';

function doPost(e) {
  try {
    var body = JSON.parse(e.postData.contents);
    var folderName = body.folder || FOLDER_NAME;
    var keep = Number(body.keep || 14);

    var folder = getOrCreateFolder(folderName);
    var blob = Utilities.newBlob(Utilities.base64Decode(body.content), 'application/zip', body.name);
    var file = folder.createFile(blob);

    pruneFolder(folder, keep);

    return jsonOut({ ok: true, name: file.getName(), id: file.getId(), url: file.getUrl() });
  } catch (err) {
    return jsonOut({ ok: false, error: String(err) });
  }
}

function getOrCreateFolder(name) {
  var folders = DriveApp.getFoldersByName(name);
  return folders.hasNext() ? folders.next() : DriveApp.createFolder(name);
}

function pruneFolder(folder, keep) {
  if (!(keep > 0)) {
    return;
  }
  var files = [];
  var it = folder.getFiles();
  while (it.hasNext()) {
    var f = it.next();
    if (/^sigaluh2_backup_\d{8}_\d{6}\.zip$/.test(f.getName())) {
      files.push(f);
    }
  }
  files.sort(function (a, b) {
    return a.getName() < b.getName() ? -1 : 1;
  });
  while (files.length > keep) {
    files.shift().setTrashed(true);
  }
}

function jsonOut(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
