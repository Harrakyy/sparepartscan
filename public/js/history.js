import { apiGet } from './api.js';
import { formatWib } from './format.js';

const LIMIT = 100;
const TYPE_LABEL = { in: 'Masuk', out: 'Keluar' };
const $ = id => document.getElementById(id);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function renderRow(m) {
  const li = el('li');
  const head = el('div', 'row');
  head.style.alignItems = 'center';
  head.append(
    el('span', `badge badge-${m.type}`, TYPE_LABEL[m.type]),
    el('span', 'mono', m.part_number),
    el('span', 'num', `${m.type === 'in' ? '+' : '−'}${m.qty}`),
  );
  head.lastChild.style.marginLeft = 'auto';
  li.append(head, el('div', 'muted', m.name));
  const meta = el('div', 'muted', `${formatWib(m.created_at)} · stok jadi ${m.stock_after}`);
  meta.style.fontSize = '14px';
  li.append(meta);
  if (m.note) li.append(el('div', '', `Catatan: ${m.note}`));
  return li;
}

async function load() {
  try {
    const rows = await apiGet(`/api/movements?limit=${LIMIT}`);
    $('list').replaceChildren(...rows.map(renderRow));
    $('empty').hidden = rows.length > 0;
  } catch (err) {
    $('status').textContent = err.message;
    $('status').hidden = false;
  }
}

load();
