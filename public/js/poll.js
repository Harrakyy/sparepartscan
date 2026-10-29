// Laptop lain bisa mengubah data yang sama (scan, cetak, hapus). Halaman mengambil data terbaru
// berkala, hanya selama tab terlihat; begitu tab dibuka lagi, langsung diperbarui sekali.
export function everyVisible(ms, task, doc = document) {
  const tick = () => {
    if (doc.visibilityState === 'visible') task();
  };
  const timer = setInterval(tick, ms);
  doc.addEventListener('visibilitychange', tick);
  return () => {
    clearInterval(timer);
    doc.removeEventListener('visibilitychange', tick);
  };
}
