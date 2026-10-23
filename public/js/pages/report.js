import { apiGet } from '../api.js';
import { formatWib } from '../format.js';

const $ = id => document.getElementById(id);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function row(cells) {
  const tr = el('tr');
  for (const [text, className] of cells) tr.append(el('td', className, String(text)));
  return tr;
}

// Tanggal hari ini menurut WIB, format YYYY-MM-DD untuk input type="date".
function todayWib() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
}

function invoiceBlock(inv) {
  const box = el('div', 'report-invoice');
  const head = el('div', 'row');
  head.style.justifyContent = 'space-between';
  head.append(el('strong', 'mono', inv.number), el('span', '', inv.customer_name), el('span', 'muted', formatWib(inv.picked_at)));
  const table = el('table');
  const body = el('tbody');
  body.append(...inv.items.map(i => row([[i.part_code, 'mono'], [i.part_name, ''], [i.qty, 'num']])));
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
    $('totals').replaceChildren(...totals.map(t => row([[t.part_code, 'mono'], [t.part_name, ''], [t.qty, 'num']])));
    $('invoices').replaceChildren(...invoices.map(invoiceBlock));
    $('empty').hidden = totals.length > 0;
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
