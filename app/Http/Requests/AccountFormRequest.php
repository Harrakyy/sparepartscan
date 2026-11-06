<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Form akun dari halaman web (akun pertama & daftar petugas): nama, username, password + ulangi. */
class AccountFormRequest extends FormRequest
{
    private const MIN_PASSWORD = 8;

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => User::normalizeUsername($this->input('username'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', User::USERNAME_RULE, 'unique:users,username'],
            'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD, 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi',
            'name.max' => 'Nama maksimal 100 karakter',
            'username.required' => 'Username wajib diisi',
            'username.regex' => User::USERNAME_MESSAGE,
            'username.unique' => 'Username sudah dipakai',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal '.self::MIN_PASSWORD.' karakter',
            'password.confirmed' => 'Password tidak sama',
        ];
    }
}
