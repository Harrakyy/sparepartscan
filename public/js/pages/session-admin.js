import { apiDelete } from '../api.js';

// Aksi khusus superadmin di halaman sesi. Tombolnya hanya dirender untuk superadmin,
// server juga menolak admin (403), jadi modul ini cukup diam bila tombolnya tidak ada.
const $ = id => document.getElementById(id);

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
