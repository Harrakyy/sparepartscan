import { apiGet } from '../api.js';
import { formatWib } from '../format.js';
import { everyVisible } from '../poll.js';

const SEARCH_DEBOUNCE_MS = 300;
const POLL_MS = 5000;
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

function row(invoice, index) {
  const tr = el('tr', 'clickable');
  const open = () => { window.location.href = `/gudang/sesi/${invoice.id}`; };
  tr.addEventListener('click', open);
  tr.tabIndex = 0;
  tr.addEventListener('keydown', e => { if (e.key === 'Enter') open(); });
  const number = el('td');
  number.append(el('span', 'code-chip', invoice.number));
  const customer = el('td', '', invoice.customer_name);
  customer.style.fontWeight = '600';
  customer.style.color = 'var(--fg)';
  const badge = el('td');
  badge.append(el('span', `badge badge-${invoice.status}`, STATUS_LABEL[invoice.status] ?? invoice.status));
  tr.append(
    el('td', 'rownum', String(index + 1)),
    number,
    customer,
    el('td', 'muted', formatWib(invoice.created_at)),
    badge,
    el('td', 'num', `${invoice.picked_total}/${invoice.total_qty}`),
  );
  return tr;
}

const listUrl = (which, q) => `/api/invoices?status=${which}${q ? `&q=${encodeURIComponent(q)}` : ''}`;

async function load() {
  const mySeq = ++seq;
  const q = $('search').value.trim();
  try {
    const [open, done] = await Promise.all([apiGet(listUrl('open', q)), apiGet(listUrl('done', q))]);
    if (mySeq !== seq) return;
    const list = status === 'open' ? open : done;
    $('count-open').textContent = open.length;
    $('count-done').textContent = done.length;
    $('list').replaceChildren(...list.map(row));
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

// Faktur baru, scan, dan hapus dari laptop lain muncul tanpa refresh.
everyVisible(POLL_MS, load);
load();
