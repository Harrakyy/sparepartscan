// Seluruh layar berkedip merah ~1 detik: salah barang harus terlihat walau bunyi kalah oleh bising gudang.
// Halaman perlu elemen <div id="error-flash" class="error-flash">.
export function flashError() {
  const overlay = document.getElementById('error-flash');
  overlay.classList.remove('is-on');
  void overlay.offsetWidth; // mulai ulang animasi bila dua kesalahan berturut-turut
  overlay.classList.add('is-on');
}
