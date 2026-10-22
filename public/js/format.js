const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

const wibParts = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Asia/Jakarta',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
});

export function formatRupiah(n) {
  return rupiah.format(n);
}

export function formatWib(iso) {
  const p = Object.fromEntries(wibParts.formatToParts(new Date(iso)).map(x => [x.type, x.value]));
  return `${p.day}/${p.month}/${p.year} ${p.hour}:${p.minute} WIB`;
}
