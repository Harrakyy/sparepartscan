import { apiGet } from '../api.js';
import { formatWib } from '../format.js';

const SEARCH_DEBOUNCE_MS = 300;
const STATUS_LABEL = { waiting: 'Menunggu', picking: 'Sedang di-scan', picked: 'Selesai', qc: 'Sedang QC', qc_done: 'Selesai QC' };
const $ = id => document.getElementById(id);

let status = 'open';
let timer = null;
let seq = 0;

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function card(invoice) {
  const a = el('a', 'session-card');
  a.href = `/gudang/sesi/${invoice.id}`;
  const left = el('div');
  left.append(el('div', 'session-card-number mono', invoice.number), el('div', 'muted', formatWib(invoice.created_at)));
  const mid = el('div', 'session-card-customer', invoice.customer_name);
  const right = el('div');
  right.style.textAlign = 'right';
  right.append(el('div', `badge badge-${invoice.status}`, STATUS_LABEL[invoice.status] ?? invoice.status),
    el('div', 'muted', `${invoice.picked_total}/${invoice.total_qty} barang`));
  a.append(left, mid, right);
  return a;
}

async function load() {
  const mySeq = ++seq;
  const q = $('search').value.trim();
  try {
    const list = await apiGet(`/api/invoices?status=${status}${q ? `&q=${encodeURIComponent(q)}` : ''}`);
    if (mySeq !== seq) return;
    $('list').replaceChildren(...list.map(card));
    $('empty').hidden = list.length > 0;
    $('status').hidden = true;
  } catch (err) {
    if (mySeq !== seq) return;
    $('status').textContent = err.message;
    $('status').hidden = false;
  }
}

document.querySelectorAll('.tab').forEach(tab => tab.addEventListener('click', () => {
  status = tab.dataset.status;
  document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', String(t === tab)));
  load();
}));
$('search').addEventListener('input', () => {
  clearTimeout(timer);
  timer = setTimeout(load, SEARCH_DEBOUNCE_MS);
});

load();
