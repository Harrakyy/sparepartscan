<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // belum ada login di tahap ini
    }

    /** Data faktur dikirim sebagai JSON di field `payload` bersama file. */
    protected function prepareForValidation(): void
    {
        $payload = json_decode((string) $this->input('payload'), true);
        if (! is_array($payload)) {
            return;
        }
        $this->merge([
            'number' => is_string($payload['number'] ?? null) ? trim($payload['number']) : ($payload['number'] ?? null),
            'customer_name' => is_string($payload['customer_name'] ?? null) ? trim($payload['customer_name']) : null,
            'invoice_date' => $payload['invoice_date'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'items' => $payload['items'] ?? null,
            'replace' => (bool) ($payload['replace'] ?? false),
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'number' => ['required', 'string', 'regex:/^\d{1,32}$/'],
            'customer_name' => ['required', 'string', 'max:150'],
            'invoice_date' => ['nullable', 'string', 'max:60'],
            'source_type' => ['required', 'in:pdf,photo'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.code' => ['required', 'string', 'max:64'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'replace' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File faktur wajib diunggah',
            'file.mimes' => 'File harus PDF, JPG, atau PNG',
            'file.max' => 'Ukuran file maksimal 10 MB',
            'number.required' => 'Nomor faktur wajib diisi',
            'number.regex' => 'Nomor faktur hanya berisi angka',
            'customer_name.required' => 'Nama penerima wajib diisi',
            'customer_name.max' => 'Nama penerima maksimal 150 karakter',
            'items.required' => 'Faktur harus berisi minimal satu barang',
            'items.min' => 'Faktur harus berisi minimal satu barang',
            'items.*.code.required' => 'Kode part wajib diisi',
            'items.*.code.max' => 'Kode part maksimal 64 karakter',
            'items.*.name.required' => 'Nama barang wajib diisi',
            'items.*.qty.*' => 'Jumlah barang harus bilangan bulat 1–10.000',
        ];
    }
}
