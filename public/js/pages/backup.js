import { apiGet, apiPost } from '../api.js';
import { formatWib } from '../format.js';
import { el } from '../dom.js';

const JENIS_LABEL = {
  perjam: 'Per jam',
  harian: 'Harian',
  manual: 'Manual',
  'sebelum-pulihkan': 'Sebelum pulihkan',
};
const $ = id => document.getElementById(id);

function showStatus(text, kind) {
  $('status').textContent = text;
  $('status').className = `msg msg-${kind}`;
  $('status').hidden = !text;
}

function formatUkuran(bytes) {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}

async function pulihkan(item) {
  const jawab = window.prompt(
    `Memulihkan akan MENGGANTI semua data sekarang dengan isi backup "${formatWib(item.waktu)}". `
      + 'Backup pengaman dibuat otomatis dulu. Ketik PULIHKAN untuk melanjutkan:',
  );
  if (jawab === null) return;
  try {
    await apiPost('/api/backups/restore', { file: item.nama, confirm: jawab });
    showStatus('Database dipulihkan. Backup pengaman sebelum pemulihan juga tersimpan.', 'ok');
    load();
  } catch (err) {
    showStatus(err.message, 'bad');
  }
}

function row(item) {
  const tr = el('tr');
  const aksi = el('td', 'num');
  const btn = el('button', 'btn btn-icon');
  btn.type = 'button';
  const i = el('i', 'bi bi-arrow-counterclockwise');
  i.setAttribute('aria-hidden', 'true');
  btn.append(i, ' Pulihkan');
  btn.addEventListener('click', () => pulihkan(item));
  aksi.append(btn);
  tr.append(
    el('td', '', formatWib(item.waktu)),
    el('td', 'muted', JENIS_LABEL[item.jenis] ?? item.jenis),
    el('td', 'num', String(item.jumlah_faktur)),
    el('td', 'num', formatUkuran(item.ukuran)),
    aksi,
  );
  return tr;
}

async function load() {
  try {
    const items = await apiGet('/api/backups');
    $('list').replaceChildren(...items.map(row));
    if (items.length === 0) {
      const tr = el('tr');
      tr.append(el('td', 'muted', 'Belum ada backup.'));
      tr.firstChild.colSpan = 5;
      $('list').replaceChildren(tr);
    }
  } catch (err) {
    showStatus(err.message, 'bad');
  }
}

async function loadDisks() {
  $('disk').replaceChildren(el('option', '', 'Memuat…'));
  try {
    const disks = await apiGet('/api/backups/disks');
    if (disks.length === 0) {
      const opt = el('option', '', 'Tidak ada flashdisk terdeteksi');
      opt.value = '';
      $('disk').replaceChildren(opt);
      return;
    }
    $('disk').replaceChildren(...disks.map(d => {
      const opt = el('option', '', `${d.drive} ${d.label || 'Flashdisk'} — ${formatUkuran(d.free)} kosong`);
      opt.value = d.drive;
      return opt;
    }));
  } catch (err) {
    showStatus(err.message, 'bad');
  }
}

function showLastDisk(iso) {
  $('last-disk').textContent = iso ? formatWib(iso) : 'Belum pernah';
}

$('muat-disk').addEventListener('click', loadDisks);

$('ke-disk').addEventListener('click', async () => {
  const drive = $('disk').value;
  if (!drive) {
    showStatus('Pilih flashdisk dulu.', 'bad');
    return;
  }
  try {
    const res = await apiPost('/api/backups/to-disk', { drive });
    showStatus(`${res.copied.length} file disalin ke ${drive}.`, 'ok');
    showLastDisk(res.at);
  } catch (err) {
    showStatus(err.message, 'bad');
  }
});

showLastDisk($('last-disk').dataset.iso || null);
load();
loadDisks();
