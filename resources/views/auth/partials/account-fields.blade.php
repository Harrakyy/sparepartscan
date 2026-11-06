@if ($errors->any())
    <p class="msg msg-bad" role="alert">{{ $errors->first() }}</p>
@endif
<div class="field">
    <label for="name">Nama</label>
    <input id="name" name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required autofocus>
</div>
<div class="field">
    <label for="username">Username</label>
    <input id="username" name="username" value="{{ old('username') }}" maxlength="30" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="mis. budi" required>
    <p class="muted">3–30 karakter: huruf kecil, angka, titik, minus, garis bawah.</p>
</div>
<div class="field">
    <label for="password">Password (min. 8)</label>
    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
</div>
<div class="field">
    <label for="password_confirmation">Ulangi password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
</div>
