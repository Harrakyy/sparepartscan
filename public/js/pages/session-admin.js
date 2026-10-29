import { apiDelete, apiPost } from '../api.js';

// Aksi khusus superadmin di halaman sesi. Tombolnya hanya dirender untuk superadmin,
// server juga menolak admin (403), jadi modul ini cukup diam bila tombolnya tidak ada.
const $ = id => document.getElementById(id);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

/**
 * Edit jumlah keluar setelah retur: hanya mengurangi (0 sampai jumlah faktur), alasan wajib.
 * @param {{ invoiceId: string, getInvoice: () => object|null, onSaved: (invoice: object) => void }} o
 * @returns {() => void} dipanggil setiap render: tombol muncul setelah scan pertama selesai
 */
export function setupAdjust({ invoiceId, getInvoice, onSaved }) {
  if (!$('adjust')) return () => {};
  const panel = $('adjust-panel');

  function open() {
    const invoice = getInvoice();
    $('adjust-items').replaceChildren(...invoice.items.map(item => {
      const input = el('input', 'num');
      input.type = 'number';
      input.min = '0';
      input.max = String(item.qty);
      input.step = '1';
      input.value = String(item.out_qty ?? item.qty);
      input.dataset.itemId = String(item.id);
      input.setAttribute('aria-label', `Jumlah keluar ${item.part_code}`);
      const qtyCell = el('td', 'num');
      qtyCell.append(input);
      const tr = el('tr');
      tr.append(el('td', 'code mono', item.part_code), el('td', '', item.part_name), el('td', 'num', String(item.qty)), qtyCell);
      return tr;
    }));
    $('adjust-note').value = '';
    $('adjust-error').hidden = true;
    panel.hidden = false;
    panel.scrollIntoView({ block: 'nearest' });
    $('adjust-items').querySelector('input')?.focus();
  }

  async function save() {
    const items = [...$('adjust-items').querySelectorAll('input')].map(input => ({
      invoice_item_id: Number(input.dataset.itemId),
      qty: Number(input.value),
    }));
    $('adjust-save').disabled = true;
    try {
      const fresh = await apiPost(`/api/invoices/${invoiceId}/adjust`, { note: $('adjust-note').value.trim(), items });
      panel.hidden = true;
      onSaved(fresh);
    } catch (err) {
      $('adjust-error').textContent = err.message;
      $('adjust-error').hidden = false;
    } finally {
      $('adjust-save').disabled = false;
    }
  }

  $('adjust').addEventListener('click', open);
  $('adjust-cancel').addEventListener('click', () => { panel.hidden = true; });
  $('adjust-save').addEventListener('click', save);

  return () => {
    const invoice = getInvoice();
    $('adjust').hidden = !invoice || invoice.picked_at === null;
  };
}

/**
 * Hapus faktur: harus mengetik ulang nomor faktur supaya tidak terhapus tak sengaja.
 * @param {{ invoiceId: string, getInvoice: () => object|null }} o
 */
export function setupDelete({ invoiceId, getInvoice }) {
  if (!$('delete')) return;
  const dialog = $('delete-dialog');

  $('delete').addEventListener('click', () => {
    const invoice = getInvoice();
    if (!invoice) return;
    const scanned = invoice.items.reduce((sum, i) => sum + i.picked_qty, 0);
    $('delete-summary').textContent = `Sudah di-scan ${scanned} barang, dicetak ${invoice.print_logs.length} kali.`;
    $('delete-confirm').value = '';
    $('delete-submit').disabled = true;
    $('delete-error').hidden = true;
    dialog.showModal();
    $('delete-confirm').focus();
  });

  $('delete-confirm').addEventListener('input', () => {
    $('delete-submit').disabled = $('delete-confirm').value.trim() !== getInvoice()?.number;
  });
  $('delete-cancel').addEventListener('click', () => dialog.close());

  $('delete-submit').addEventListener('click', async () => {
    $('delete-submit').disabled = true;
    try {
      await apiDelete(`/api/invoices/${invoiceId}`, { confirm_number: $('delete-confirm').value.trim() });
      window.location.href = '/gudang';
    } catch (err) {
      $('delete-error').textContent = err.message;
      $('delete-error').hidden = false;
      $('delete-submit').disabled = false;
    }
  });
}
