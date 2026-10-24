import { apiGet } from '../api.js';
import { formatWib } from '../format.js';

const $ = id => document.getElementById(id);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function codeCell(code) {
  const td = el('td');
  td.append(el('span', 'code-chip', code));
  return td;
}

function itemRow(index, code, name, qty) {
  const tr = el('tr');
  tr.append(el('td', 'rownum', String(index + 1)), codeCell(code), el('td', '', name), el('td', 'num', String(qty)));
  return tr;
}

// Tanggal hari ini menurut WIB, format YYYY-MM-DD untuk input type="date".
function todayWib() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
}

function invoiceBlock(inv) {
  const box = el('div', 'report-invoice');
  const head = el('div', 'report-invoice-head');
  const customer = el('strong', '', inv.customer_name);
  head.append(el('span', 'code-chip', inv.number), customer, el('span', 'muted', formatWib(inv.picked_at)));
  const table = el('table', 'grid');
  const body = el('tbody');
  body.append(...inv.items.map((i, n) => itemRow(n, i.part_code, i.part_name, i.qty)));
  table.append(body);
  box.append(head, table);
  return box;
}

async function load(event) {
  event?.preventDefault();
  const params = new URLSearchParams({ from: $('from').value, to: $('to').value });
  if ($('q').value.trim()) params.set('q', $('q').value.trim());
  try {
    const { invoices, totals } = await apiGet(`/api/reports/outgoing?${params}`);
    $('totals').replaceChildren(...totals.map((t, n) => itemRow(n, t.part_code, t.part_name, t.qty)));
    $('invoices').replaceChildren(...invoices.map(invoiceBlock));
    $('empty').hidden = totals.length > 0;
    $('stat-invoices').textContent = invoices.length;
    $('stat-qty').textContent = totals.reduce((sum, t) => sum + t.qty, 0);
    $('status').hidden = true;
  } catch (err) {
    $('status').textContent = err.message;
    $('status').hidden = false;
  }
}

$('from').value = todayWib();
$('to').value = todayWib();
$('filter').addEventListener('submit', load);
load();
