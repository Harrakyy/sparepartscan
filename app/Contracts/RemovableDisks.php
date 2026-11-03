<?php

namespace App\Contracts;

/** Sumber daftar disk lepas-pasang (flashdisk). Di produksi: PowerShell; di tes: fake. */
interface RemovableDisks
{
    /** @return array<int, array{drive: string, label: string, free: int}> */
    public function list(): array;
}
