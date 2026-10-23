import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
import { apiPost } from '../api.js';
import { parseInvoice, groupTextItems } from '../invoice-parser.js';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.mjs';

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const MIN_PDF_TEXT = 20; // PDF hasil scan tidak punya teks: dibaca lewat OCR
const PREVIEW_SCALE = 2;
const $ = id => document.getElementById(id);

let currentFile = null;
let sourceType = 'pdf';

function showStatus(text, kind) {
  const el = $('status');
  el.textContent = text;
  el.className = `msg msg-${kind}`;
  el.hidden = !text;
}

function setReading(label, fraction) {
  $('reading').hidden = false;
  $('reading-label').textContent = label;
  $('reading-bar').style.width = `${Math.round(fraction * 100)}%`;
}

async function ocr(image) {
  const worker = await window.Tesseract.createWorker('eng', 1, {
    workerPath: '/vendor/tesseract/worker.min.js',
    corePath: '/vendor/tesseract/core',
    langPath: '/vendor/tesseract/lang',
    gzip: true,
    logger: m => {
      if (m.status === 'recognizing text') setReading('Membaca tulisan di gambar…', m.progress);
    },
  });
  try {
    const { data } = await worker.recognize(image);
    return data.text.split('\n');
  } finally {
    await worker.terminate();
  }
}

async function readPdf(file) {
  const doc = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
  const page = await doc.getPage(1);
  const viewport = page.getViewport({ scale: PREVIEW_SCALE });
  const canvas = $('preview-canvas');
  canvas.width = viewport.width;
  canvas.height = viewport.height;
  await page.render({ canvas, viewport }).promise;
  canvas.hidden = false;

  const content = await page.getTextContent();
  const lines = groupTextItems(content.items.map(i => ({ str: i.str, x: i.transform[4], y: i.transform[5] })));
  if (lines.join('').length >= MIN_PDF_TEXT) return lines;
  sourceType = 'photo';
  return ocr(canvas);
}

async function readImage(file) {
  const img = $('preview-img');
  img.src = URL.createObjectURL(file);
  img.hidden = false;
  return ocr(file);
}

function rowHtmlInput(className, value, attrs) {
  const input = document.createElement('input');
  input.className = className;
  input.value = value ?? '';
  Object.assign(input, attrs);
  return input;
}

function addRow(item = { code: '', name: '', qty: 1 }, warnCode = false) {
  const tr = document.createElement('tr');
  const code = rowHtmlInput('mono item-code', item.code, { maxLength: 64, required: true });
  const name = rowHtmlInput('item-name', item.name, { maxLength: 255, required: true });
  const qty = rowHtmlInput('item-qty', item.qty ?? '', { inputMode: 'numeric', required: true });
  qty.style.textAlign = 'right';
  code.classList.toggle('warn', warnCode);
  qty.classList.toggle('warn', item.qty === null);
  for (const input of [code, name, qty]) input.addEventListener('input', () => { input.classList.remove('warn'); updateTotal(); });
  const remove = document.createElement('button');
  remove.type = 'button';
  remove.className = 'btn btn-icon';
  remove.textContent = '✕';
  remove.setAttribute('aria-label', 'Hapus baris');
  remove.addEventListener('click', () => { tr.remove(); updateTotal(); });
  for (const el of [code, name, qty, remove]) {
    const td = document.createElement('td');
    td.append(el);
    tr.append(td);
  }
  $('items').append(tr);
}

function collectItems() {
  return [...$('items').querySelectorAll('tr')].map(tr => ({
    code: tr.querySelector('.item-code').value.trim().toUpperCase(),
    name: tr.querySelector('.item-name').value.trim(),
    qty: Number(tr.querySelector('.item-qty').value),
  }));
}

function updateTotal() {
  const items = collectItems();
  const total = items.reduce((sum, i) => sum + (Number.isInteger(i.qty) ? i.qty : 0), 0);
  $('total').textContent = `${items.length} baris · ${total} barang`;
}

function fillForm(result) {
  $('number').value = result.number;
  $('customer').value = result.customer;
  $('date').value = result.date;
  $('number').classList.toggle('warn', !result.number);
  $('customer').classList.toggle('warn', !result.customer);
  $('items').replaceChildren();
  for (const item of result.items) {
    addRow(item, result.warnings.some(w => w.includes(`Kode ${item.code} `)));
  }
  const box = $('warnings');
  box.replaceChildren();
  if (result.warnings.length) {
    box.append('Periksa bagian yang ditandai kuning:');
    const ul = document.createElement('ul');
    for (const w of result.warnings) {
      const li = document.createElement('li');
      li.textContent = w;
      ul.append(li);
    }
    box.append(ul);
  }
  box.hidden = !result.warnings.length;
  updateTotal();
}

function resetPage() {
  currentFile = null;
  $('file').value = '';
  $('confirm').hidden = true;
  $('reading').hidden = true;
  $('preview-img').hidden = true;
  $('preview-canvas').hidden = true;
}

async function handleFile(file) {
  showStatus('');
  resetPage();
  if (!file) return;
  if (file.size > MAX_FILE_BYTES) return showStatus('Ukuran file maksimal 10 MB', 'bad');
  const isPdf = file.type === 'application/pdf';
  if (!isPdf && !['image/jpeg', 'image/png'].includes(file.type)) return showStatus('File harus PDF, JPG, atau PNG', 'bad');

  currentFile = file;
  sourceType = isPdf ? 'pdf' : 'photo';
  setReading('Membaca faktur…', 0.05);
  try {
    const lines = isPdf ? await readPdf(file) : await readImage(file);
    fillForm(parseInvoice(lines));
    $('reading').hidden = true;
    $('confirm').hidden = false;
  } catch (err) {
    console.error(err);
    $('reading').hidden = true;
    showStatus('Faktur tidak bisa dibaca. Coba file lain atau foto yang lebih jelas.', 'bad');
  }
}

function validate(payload) {
  if (!/^\d{1,32}$/.test(payload.number)) return 'Nomor faktur hanya berisi angka';
  if (!payload.customer_name) return 'Nama penerima wajib diisi';
  if (!payload.items.length) return 'Faktur harus berisi minimal satu barang';
  for (const item of payload.items) {
    if (!item.code || !item.name) return 'Kode part dan nama barang wajib diisi di setiap baris';
    if (!Number.isInteger(item.qty) || item.qty < 1 || item.qty > 10000) return `Jumlah untuk ${item.code || 'baris kosong'} harus 1–10.000`;
  }
  return null;
}

async function save(event) {
  event.preventDefault();
  const payload = {
    number: $('number').value.trim(),
    customer_name: $('customer').value.trim(),
    invoice_date: $('date').value.trim() || null,
    source_type: sourceType,
    items: collectItems(),
  };
  const error = validate(payload);
  if (error) return showStatus(error, 'bad');

  const form = new FormData();
  form.append('file', currentFile);
  form.append('payload', JSON.stringify(payload));
  $('save').disabled = true;
  try {
    const invoice = await apiPost('/api/invoices', form);
    resetPage();
    const el = $('status');
    el.className = 'msg msg-ok';
    el.replaceChildren(`Faktur ${invoice.number} · ${invoice.customer_name} tersimpan. `);
    const link = document.createElement('a');
    link.href = `/gudang/sesi/${invoice.id}`;
    link.textContent = 'Buka sesi scan';
    el.append(link);
    el.hidden = false;
  } catch (err) {
    showStatus(err.message, 'bad');
  } finally {
    $('save').disabled = false;
  }
}

$('file').addEventListener('change', e => handleFile(e.target.files[0]));
const dropzone = $('dropzone');
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag'));
dropzone.addEventListener('drop', e => {
  e.preventDefault();
  dropzone.classList.remove('drag');
  handleFile(e.dataTransfer.files[0]);
});
$('add-row').addEventListener('click', () => { addRow(); updateTotal(); });
$('reset').addEventListener('click', () => { resetPage(); showStatus(''); });
$('invoice-form').addEventListener('submit', save);
