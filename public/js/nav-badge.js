// Badge merah di menu Gudang: jumlah faktur yang perlu di-scan.
// Angka awal dari server; diperbarui berkala dan saat halaman lain memberi tahu ada perubahan.
import { apiGet } from './api.js';

const REFRESH_MS = 30000;
const badge = document.getElementById('nav-open-count');

function render(count) {
  badge.textContent = count > 99 ? '99+' : String(count);
  badge.hidden = count === 0;
  badge.setAttribute('aria-label', `${count} faktur perlu di-scan`);
}

async function refresh() {
  try {
    render((await apiGet('/api/invoices/summary')).open);
  } catch {
    // Biarkan angka terakhir; percobaan berikutnya berjalan sesuai jadwal.
  }
}

if (badge) {
  setInterval(refresh, REFRESH_MS);
  document.addEventListener('invoices-changed', refresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}
