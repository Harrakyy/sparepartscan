import { apiGet } from '../api.js';
import { formatWib } from '../format.js';

const TABS = ['open', 'done', 'zahir_pending'];
const $ = id => document.getElementById(id);

let tab = 'open';

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function zahirCell(ret) {
  if (ret.status === 'open') return el('td', 'muted', '–');
  return ret.zahir_entered_at
    ? el('td', '', `✓ ${formatWib(ret.zahir_entered_at)}`)
    : el('td', '', 'Belum');
}

function row(ret, index) {
  const tr = el('tr', 'clickable');
  const open = () => { window.location.href = `/retur/${ret.id}`; };
  tr.addEventListener('click', open);
  tr.tabIndex = 0;
  tr.addEventListener('keydown', e => { if (e.key === 'Enter') open(); });
  const number = el('td');
  number.append(el('span', 'code-chip', ret.invoice_number));
  const customer = el('td', '', ret.customer_name);
  customer.style.fontWeight = '600';
  customer.style.color = 'var(--fg)';
  tr.append(
    el('td', 'rownum', String(index + 1)),
    el('td', 'muted', formatWib(ret.done_at ?? ret.created_at)),
    number,
    customer,
    el('td', 'num', String(ret.good_total)),
    el('td', 'num', String(ret.bad_total)),
    zahirCell(ret),
  );
  return tr;
}

async function load() {
  try {
    const lists = await Promise.all(TABS.map(t => apiGet(`/api/returns?tab=${t}`)));
    TABS.forEach((t, i) => { $(`count-${t}`).textContent = lists[i].length; });
    const list = lists[TABS.indexOf(tab)];
    $('list').replaceChildren(...list.map(row));
    $('empty').hidden = list.length > 0;
    $('status').hidden = true;
  } catch (err) {
    $('status').textContent = err.message;
    $('status').hidden = false;
  }
}

document.querySelectorAll('.tab').forEach(button => button.addEventListener('click', () => {
  tab = button.dataset.tab;
  document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', String(t === button)));
  load();
}));

load();
