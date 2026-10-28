<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Banyak faktur dari satu PDF laporan "Penjualan - Rincian"; data dikirim sebagai JSON di `payload`. */
class StoreInvoiceBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // belum ada login di tahap ini
    }

    protected function prepareForValidation(): void
    {
        $payload = json_decode((string) $this->input('payload'), true);
        $invoices = is_array($payload['invoices'] ?? null) ? $payload['invoices'] : null;
        $this->merge([
            'invoices' => $invoices === null ? null : array_map(fn ($i) => is_array($i) ? array_merge($i, [
                'number' => is_string($i['number'] ?? null) ? trim($i['number']) : ($i['number'] ?? null),
                'customer_name' => is_string($i['customer_name'] ?? null) ? trim($i['customer_name']) : null,
            ]) : $i, $invoices),
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'invoices' => ['required', 'array', 'min:1', 'max:200'],
            'invoices.*.number' => ['required', 'string', 'regex:/^\d{1,32}$/'],
            'invoices.*.customer_name' => ['required', 'string', 'max:150'],
            'invoices.*.invoice_date' => ['nullable', 'string', 'max:60'],
            'invoices.*.items' => ['required', 'array', 'min:1', 'max:200'],
            'invoices.*.items.*.code' => ['required', 'string', 'max:64'],
            'invoices.*.items.*.name' => ['required', 'string', 'max:255'],
            'invoices.*.items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File laporan wajib diunggah',
            'file.mimes' => 'Laporan harus berupa PDF',
            'file.max' => 'Ukuran file maksimal 10 MB',
            'file.uploaded' => 'File gagal terkirim, kemungkinan terlalu besar untuk server.',
            'invoices.required' => 'Tidak ada faktur yang dipilih',
            'invoices.min' => 'Tidak ada faktur yang dipilih',
            'invoices.max' => 'Maksimal 200 faktur sekali simpan',
            'invoices.*.number.*' => 'Nomor faktur hanya berisi angka',
            'invoices.*.customer_name.required' => 'Nama penerima wajib diisi',
            'invoices.*.customer_name.max' => 'Nama penerima maksimal 150 karakter',
            'invoices.*.items.required' => 'Setiap faktur harus berisi minimal satu barang',
            'invoices.*.items.min' => 'Setiap faktur harus berisi minimal satu barang',
            'invoices.*.items.*.code.*' => 'Kode part wajib diisi (maksimal 64 karakter)',
            'invoices.*.items.*.name.*' => 'Nama barang wajib diisi (maksimal 255 karakter)',
            'invoices.*.items.*.qty.*' => 'Jumlah barang harus bilangan bulat 1–10.000',
        ];
    }
}
