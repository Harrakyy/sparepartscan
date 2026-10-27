import { apiGet, apiPost } from '../api.js';
import { formatWib } from '../format.js';

const SEARCH_DEBOUNCE_MS = 300;
const $ = id => document.getElementById(id);

let timer = null;
let seq = 0;

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function showError(message) {
  $('status').textContent = message;
  $('status').hidden = false;
}

// Faktur yang masih punya retur belum selesai langsung membuka retur itu (server mengembalikannya).
async function startReturn(invoice, button) {
  button.disabled = true;
  try {
    const ret = await apiPost('/api/returns', { invoice_id: invoice.id });
    window.location.href = `/retur/${ret.id}`;
  } catch (err) {
    button.disabled = false;
    showError(err.message);
  }
}

function row(invoice, index) {
  const tr = el('tr');
  const number = el('td');
  number.append(el('span', 'code-chip', invoice.number));
  const customer = el('td', '', invoice.customer_name);
  customer.style.fontWeight = '600';
  customer.style.color = 'var(--fg)';
  const action = el('td', 'num');
  const button = el('button', invoice.open_return_id ? 'btn' : 'btn btn-primary',
    invoice.open_return_id ? 'Lanjutkan retur' : 'Buat retur');
  button.type = 'button';
  button.addEventListener('click', () => startReturn(invoice, button));
  action.append(button);
  tr.append(
    el('td', 'rownum', String(index + 1)),
    number,
    customer,
    el('td', 'muted', invoice.picked_at ? formatWib(invoice.picked_at) : '–'),
    action,
  );
  return tr;
}

async function load() {
  const mySeq = ++seq;
  const q = $('search').value.trim();
  try {
    const list = await apiGet(`/api/returns/invoices${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    if (mySeq !== seq) return;
    $('list').replaceChildren(...list.map(row));
    $('empty').hidden = list.length > 0;
    $('status').hidden = true;
  } catch (err) {
    if (mySeq === seq) showError(err.message);
  }
}

$('search').addEventListener('input', () => {
  clearTimeout(timer);
  timer = setTimeout(load, SEARCH_DEBOUNCE_MS);
});

load();
