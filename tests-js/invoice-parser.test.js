import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { parseInvoice, groupTextItems } from '../public/js/invoice-parser.js';

const lines = name => fs.readFileSync(new URL(`./fixtures/${name}`, import.meta.url), 'utf8').split(/\r?\n/);

test('parses header and all nine lines of the clean invoice', () => {
  const r = parseInvoice(lines('faktur-00066365.txt'));
  assert.equal(r.number, '00066365');
  assert.equal(r.customer, 'AKBAR JAYA MOTOR HSF');
  assert.equal(r.date, 'Thursday, October 22, 2026');
  assert.equal(r.items.length, 9);
  assert.deepEqual(r.items[0], { code: 'S-SCS-002', name: 'SAKLAR SIGN FUTURA 91-18/ T 120 SS 91-18/ KATANA', qty: 2 });
  assert.equal(r.items[4].code, 'H-MTE-009/010');
  assert.equal(r.items[6].code, 'H-TCB-N13');
  assert.deepEqual(r.items[8], { code: 'S-EFP-EP-T11', name: 'FUEL PUMP ZEBRA S89 86-95 (23100-87515)', qty: 3 });
  assert.deepEqual(r.warnings, []);
});

test('joins a continuation line onto the previous item name', () => {
  const r = parseInvoice(lines('faktur-00066365.txt'));
  assert.equal(r.items[7].name, 'CLOCK SPRING GRAND NEW AVANZA TYPE G ( 2017 - on ) (84306-BZ200)');
  assert.equal(r.items[7].qty, 2);
});

test('total quantity of the sample invoice is 14', () => {
  const r = parseInvoice(lines('faktur-00066365.txt'));
  assert.equal(r.items.reduce((sum, i) => sum + i.qty, 0), 14);
});

test('OCR misreads keep the line and produce warnings', () => {
  const r = parseInvoice(lines('faktur-00066365-ocr.txt'));
  assert.equal(r.items.length, 9);
  assert.equal(r.items[0].code, 'S-SCS-OO2');
  assert.equal(r.items[1].qty, null);
  assert.ok(r.warnings.some(w => w.includes('S-SCS-OO2')), r.warnings.join(' | '));
  assert.ok(r.warnings.some(w => w.includes('H-KTE-001') && w.includes('Jumlah')), r.warnings.join(' | '));
});

test('real Tesseract output of the photo is read completely', () => {
  const r = parseInvoice(lines('faktur-00066365-tesseract.txt'));
  assert.equal(r.number, '00066365');
  assert.equal(r.customer, 'AKBAR JAYA MOTOR HSF');
  assert.equal(r.date, 'Thursday, October 22, 2026');
  assert.deepEqual(r.items.map(i => [i.code, i.qty]), [
    ['S-SCS-002', 2], ['H-KTE-001', 2], ['S-MCS-005', 1], ['H-MYJ-005', 1], ['H-MTE-009/010', 1],
    ['H-SYJ-002', 1], ['H-TCB-N13', 1], ['S-TKS-002', 2], ['S-EFP-EP-T11', 3],
  ]);
  assert.equal(r.items[7].name, 'CLOCK SPRING GRAND NEW AVANZA TYPE G (2017 - on ) (84306-BZ200)');
  assert.equal(r.items[8].name, 'FUEL PUMP ZEBRA S89 86-95 (23100-87515)');
  assert.deepEqual(r.warnings, []);
});

test('faktur 2: misread "Kepada" label (iPpada) still yields the customer', () => {
  const r = parseInvoice(lines('faktur-00051034-tesseract.txt'));
  assert.equal(r.number, '00051034');
  assert.equal(r.customer, 'ARYA MANDIRI MOTOR');
  assert.equal(r.date, 'Thursday, October 22, 2026');
  assert.equal(r.items.length, 9);
  assert.equal(r.items.reduce((s, i) => s + i.qty, 0), 14);
  assert.deepEqual(r.warnings, []);
});

test('faktur 3: separator read as a dash is not part of the customer name', () => {
  const r = parseInvoice(lines('faktur-00051189-tesseract.txt'));
  assert.equal(r.number, '00051189');
  assert.equal(r.customer, 'MEGA JAYA MOTOR');
  assert.equal(r.date, 'Thursday, October 22, 2026');
  assert.deepEqual(r.items.slice(0, 2).map(i => [i.code, i.qty]), [['S-SCS-015', 2], ['S-SCS-005', 2]]);
  assert.equal(r.items.length, 9);
  assert.deepEqual(r.warnings, []);
});

test('customer label with other separators and trailing "Up." label is cleaned', () => {
  for (const line of ['Kepada = TOKO A Up. : B', 'Kepada © TOKO A Up. © B', 'Kepada ; TOKO A', 'Kepada TOKO A']) {
    assert.equal(parseInvoice([line]).customer, 'TOKO A', line);
  }
});

const header = ['No Faktur : 123', 'Kepada : TOKO A', 'Nama Barang / Pesanan Jumlah Satuan Harga Satuan Disc. Sub Total'];

test('quantity is taken from the end of the line, not from numbers in the name', () => {
  const r = parseInvoice([...header, 'K-TIE-100 KABEL TIES 100 PCS 1 Pak 25,000.00 20.00 20,000.00']);
  assert.deepEqual(r.items, [{ code: 'K-TIE-100', name: 'KABEL TIES 100 PCS', qty: 1 }]);
});

test('an item name containing RIBU or RATUS does not end the item list', () => {
  const r = parseInvoice([
    ...header,
    'S-RBU-001 LAMPU SERIBU RATUS WATT 1 Pcs 10,000.00 0.00 10,000.00',
    'S-SCS-002 SAKLAR SIGN 2 Pcs 280,000.00 20.00 448,000.00',
    'Dua Juta Enam Ratus Enam Puluh Satu Ribu',
  ]);
  assert.deepEqual(r.items.map(i => i.code), ['S-RBU-001', 'S-SCS-002']);
});

test('an unrecognized line that looks like an item becomes a warning, not part of a name', () => {
  const r = parseInvoice([
    ...header,
    'S-SCS-002 SAKLAR SIGN 2 Pcs 280,000.00 20.00 448,000.00',
    '$-MCS-005 SAKLAR SIGN MITS.CANTER 1 Pcs 349,000.00 20.00 279,200.00',
  ]);
  assert.equal(r.items.length, 1);
  assert.equal(r.items[0].name, 'SAKLAR SIGN');
  assert.ok(r.warnings.some(w => w.startsWith('Baris tidak dikenali') && w.includes('$-MCS-005')), r.warnings.join(' | '));
});

test('missing invoice number and customer produce warnings', () => {
  const r = parseInvoice(['Nama Barang / Pesanan', 'S-SCS-002 SAKLAR 2 Pcs 1.00 0 1.00']);
  assert.equal(r.number, '');
  assert.equal(r.customer, '');
  assert.ok(r.warnings.includes('Nomor faktur tidak terbaca'));
  assert.ok(r.warnings.includes('Nama penerima tidak terbaca'));
  assert.equal(r.items.length, 1);
});

test('without the column header, code lines are still found', () => {
  const r = parseInvoice(['No Faktur : 123', 'Kepada : TOKO A', 'H-SYJ-002 YOKE JOINT SUZUKI ST100 1 Pcs 165,000.00 20.00 132,000.00']);
  assert.deepEqual(r.items, [{ code: 'H-SYJ-002', name: 'YOKE JOINT SUZUKI ST100', qty: 1 }]);
});

test('groupTextItems groups by row from top to bottom and sorts by x', () => {
  const rows = groupTextItems([
    { str: 'SAKLAR', x: 120, y: 100 },
    { str: 'S-SCS-002', x: 20, y: 101 },
    { str: '2', x: 400, y: 99 },
    { str: 'No Faktur : 00066365', x: 20, y: 120 },
    { str: ' ', x: 300, y: 120 },
  ]);
  assert.deepEqual(rows, ['No Faktur : 00066365', 'S-SCS-002 SAKLAR 2']);
});
