import { apiGet, apiPatch, apiPost } from '../api.js';
import { formatWib } from '../format.js';

const ROLE_LABEL = { superadmin: 'Superadmin', admin: 'Admin' };
const MIN_PASSWORD = 8;
const $ = id => document.getElementById(id);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function showStatus(text, kind) {
  $('status').textContent = text;
  $('status').className = `msg msg-${kind}`;
  $('status').hidden = !text;
}

async function change(user, data, okMessage) {
  try {
    await apiPatch(`/api/users/${user.id}`, data);
    showStatus(okMessage, 'ok');
    load();
  } catch (err) {
    showStatus(err.message, 'bad');
  }
}

function actionButton(label, icon, onClick) {
  const button = el('button', 'btn btn-icon');
  button.type = 'button';
  const i = el('i', `bi ${icon}`);
  i.setAttribute('aria-hidden', 'true');
  button.append(i, ` ${label}`);
  button.addEventListener('click', onClick);
  return button;
}

function row(user) {
  const isMe = user.id === window.currentUserId;
  const tr = el('tr', user.is_active ? '' : 'row-muted');

  const role = el('td');
  const roleSelect = el('select');
  for (const [value, label] of Object.entries(ROLE_LABEL)) {
    const option = el('option', '', label);
    option.value = value;
    option.selected = value === user.role;
    roleSelect.append(option);
  }
  // Superadmin tidak bisa menurunkan dirinya sendiri (selalu tersisa superadmin).
  roleSelect.disabled = isMe;
  roleSelect.addEventListener('change', () => change(user, { role: roleSelect.value }, `Peran ${user.name} diubah`));
  role.append(roleSelect);

  const actions = el('td', 'num');
  actions.append(actionButton('Password baru', 'bi-key', () => {
    const password = window.prompt(`Password baru untuk ${user.name} (min. ${MIN_PASSWORD} karakter):`);
    if (password === null) return;
    change(user, { password }, `Password ${user.name} diganti. Sesi lamanya berakhir.`);
  }));
  if (!isMe) {
    actions.append(' ', user.is_active
      ? actionButton('Nonaktifkan', 'bi-person-slash', () => {
        if (window.confirm(`Nonaktifkan ${user.name}? Ia langsung keluar dan tidak bisa masuk lagi.`)) {
          change(user, { is_active: false }, `${user.name} dinonaktifkan`);
        }
      })
      : actionButton('Aktifkan', 'bi-person-check', () => change(user, { is_active: true }, `${user.name} diaktifkan`)));
  }

  const name = el('td', '', user.name + (isMe ? ' (Anda)' : ''));
  name.style.fontWeight = '600';
  tr.append(
    name,
    el('td', 'muted', user.email),
    role,
    el('td', '', user.is_active ? 'Aktif' : 'Nonaktif'),
    el('td', 'muted', user.last_login_at ? formatWib(user.last_login_at) : 'Belum pernah'),
    actions,
  );
  return tr;
}

async function load() {
  try {
    const users = await apiGet('/api/users');
    $('list').replaceChildren(...users.map(row));
  } catch (err) {
    showStatus(err.message, 'bad');
  }
}

$('add-form').addEventListener('submit', async e => {
  e.preventDefault();
  try {
    const user = await apiPost('/api/users', {
      name: $('name').value.trim(),
      email: $('email').value.trim(),
      role: $('role').value,
      password: $('password').value,
    });
    $('add-form').reset();
    showStatus(`${user.name} ditambahkan. Ia bisa masuk dengan email dan password tadi.`, 'ok');
    load();
  } catch (err) {
    showStatus(err.message, 'bad');
  }
});

load();
