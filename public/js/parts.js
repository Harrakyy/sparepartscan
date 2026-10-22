import { apiGet, apiPut } from './api.js';
import { parseWholeNumber } from './format.js';

const SEARCH_DEBOUNCE_MS = 300;
const $ = id => document.getElementById(id);

let editing = null;
let searchTimer = null;
let searchSeq = 0;

function showStatus(text, kind) {
  const el = $('status');
  el.textContent = text;
  el.className = kind === 'ok' ? 'msg-ok' : 'msg-error';
  el.hidden = !text;
}

function renderList(parts) {
  const list = $('list');
  list.replaceChildren(...parts.map(part => {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.type = 'button';
    const info = document.createElement('div');
    info.className = 'grow';
    const pn = document.createElement('div');
    pn.className = 'mono';
    pn.style.fontWeight = '700';
    pn.textContent = part.part_number;
    const desc = document.createElement('div');
    desc.className = 'muted';
    desc.textContent = part.description;
    info.append(pn, desc);
    const stock = document.createElement('div');
    stock.className = 'num';
    stock.textContent = `Stok ${part.stock}`;
    btn.append(info, stock);
    btn.addEventListener('click', () => openEdit(part));
    li.append(btn);
    return li;
  }));
  $('empty').hidden = parts.length > 0;
}

async function load() {
  const seq = ++searchSeq;
  const q = $('search').value.trim();
  try {
    const parts = await apiGet(`/api/parts${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    if (seq === searchSeq) renderList(parts);
  } catch (err) {
    if (seq === searchSeq) showStatus(err.message);
  }
}

function openEdit(part) {
  editing = part;
  showStatus('');
  $('edit').hidden = false;
  $('e-barcode').textContent = part.barcode;
  $('e-stock').textContent = part.stock;
  $('e-part-number').value = part.part_number;
  $('e-description').value = part.description;
  $('e-price-retail').value = part.price_retail;
  $('e-price-member').value = part.price_member;
  $('e-price-wholesale').value = part.price_wholesale;
  $('edit').scrollIntoView({ block: 'start' });
  $('e-part-number').focus();
}

function closeEdit() {
  editing = null;
  $('edit').hidden = true;
}

async function save(event) {
  event.preventDefault();
  if (!editing) return;
  const button = $('e-submit');
  button.disabled = true;
  try {
    await apiPut(`/api/parts/${editing.id}`, {
      part_number: $('e-part-number').value,
      description: $('e-description').value,
      price_retail: parseWholeNumber($('e-price-retail').value),
      price_member: parseWholeNumber($('e-price-member').value),
      price_wholesale: parseWholeNumber($('e-price-wholesale').value),
    });
    closeEdit();
    showStatus('Tersimpan', 'ok');
    await load();
  } catch (err) {
    showStatus(err.message);
  } finally {
    button.disabled = false;
  }
}

$('search').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(load, SEARCH_DEBOUNCE_MS);
});
$('edit-form').addEventListener('submit', save);
$('e-cancel').addEventListener('click', closeEdit);

load();
