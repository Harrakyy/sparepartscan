import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { isSalesReport, parseSalesReport } from '../public/js/sales-report-parser.js';

// Fixture = teks asli PDF laporan Zahir "Penjualan - Rincian", dibaca pdf.js seperti di app.
const lines = name => fs.readFileSync(new URL(`./fixtures/${name}`, import.meta.url), 'utf8').split(/\r?\n/);
const bivhb = () => parseSalesReport(lines('penjualan-rincian-bivhb.txt'));
const hsf = () => parseSalesReport(lines('penjualan-rincian-hsf.txt'));
const invoice = (list, number) => list.find(i => i.number === number);
const codesAndQty = inv => inv.items.map(i => [i.code, i.qty]);

test('recognises the Penjualan - Rincian report but not a single invoice', () => {
  assert.ok(isSalesReport(lines('penjualan-rincian-bivhb.txt')));
  assert.ok(!isSalesReport(lines('faktur-00066365.txt')));
});

test('BIVHB report: five invoices with customer, date and items', () => {
  const list = bivhb();
  assert.deepEqual(list.map(i => i.number), ['00030918', '00030919', '00030924', '00030925', '00030926']);
  assert.deepEqual(list[0], {
    number: '00030918',
    customer: 'SUBUR MOTOR CIBINONG BIVHb',
    date: '27/10/2026',
    items: [{ code: 'HB-SHB-B07', name: 'GRAND VITARA 06-17 FR/RR ABS (43402-60M00)', qty: 1 }],
    warnings: [],
  });
  assert.deepEqual(codesAndQty(list[3]), [['I-HSBF-003/G', 1], ['I-HSBF-005/G', 1]]);
});

test('a part code wrapped onto the next line is joined', () => {
  assert.deepEqual(invoice(bivhb(), '00030926').items, [
    { code: 'TB-ACCU-55B24L/NS60L', name: 'ACCU SUPER MAINTENANCE FREE 55B24L/ NX-100 S6L 45Ah 12v', qty: 1 },
  ]);
});

test('HSF report: 105 invoices, each with items and no warnings', () => {
  const list = hsf();
  assert.equal(list.length, 105);
  assert.equal(list[0].number, '00066660');
  assert.equal(list.at(-1).number, '00066764');
  assert.ok(list.every(i => i.items.length > 0 && i.customer && i.date === '27/10/2026'));
  assert.deepEqual(list.flatMap(i => i.warnings), []);
});

test('an invoice continuing on the next page keeps all its items', () => {
  const inv = invoice(hsf(), '00066663');
  assert.equal(inv.customer, 'SUMBER REJEKI MOTOR_KM 27 CIBUBUR HSF');
  assert.deepEqual(codesAndQty(inv), [['S-TKS-002', 1], ['S-TKS-003', 1], ['S-TKS-007', 2]]);
  assert.equal(inv.items[1].name, 'CLOCK SPRING GRAND NEW AVANZA TYPE G ( 2015 - 2016 ) / GRAND NEW AVANZA TYPE VELOZ (2015 - ON) (84306-BZ160)');
});

test('customer names split over two lines lose the CUST code', () => {
  assert.equal(invoice(hsf(), '00066660').customer, 'YSR AUTOPART SJM CISALAK HSF (RAHMATUL)');
  assert.equal(invoice(hsf(), '00066665').customer, 'TERUS JAYA MOTOR KM.31_CISALAK HSF');
});

test('a discount wrapped as "17.5 / %" does not disturb quantities or names', () => {
  const inv = invoice(hsf(), '00066661');
  assert.deepEqual(codesAndQty(inv), [['F-NSM-008', 2], ['H-SRT-001', 1], ['H-SRT-002', 1], ['H-TRT-003', 2]]);
  assert.equal(inv.items[1].name, 'RACK STEERING APV P/STEERING (48500-61J01-000)');
});

test('bonus lines (100% discount) are kept and repeated codes are merged', () => {
  assert.deepEqual(codesAndQty(invoice(hsf(), '00066718')), [['P-SRD-LC40', 2], ['P-TRD-LC37', 3]]);
  assert.deepEqual(codesAndQty(invoice(hsf(), '00066717')), [['P-SRD-LC2', 2], ['P-TRD-LC1', 1], ['P-TRD-LC37', 1]]);
});

test('an invoice without readable items gets a warning', () => {
  const [inv] = parseSalesReport([
    'Penjualan - Rincian',
    'Tanggal : 27/10/2026 Pelanggan : Mata Uang : IDR 1.00',
    'TOKO A (CUST 1 )',
    'No. Reference : 00000001 Gudang : Head Quarter',
    'Keterangan Diterima Dipesan Harga Satuan Diskon Sub Total Pajak',
    'Salesman : X Biaya Pengiriman : 0.00 Total : 0.00',
  ]);
  assert.deepEqual(inv.warnings, ['Tidak ada barang yang terbaca']);
});
