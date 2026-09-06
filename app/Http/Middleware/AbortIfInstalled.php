<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;

/**
 * Wizard menulis .env dan membuat akun admin, jadi ia harus mati total begitu
 * pemasangan selesai. Hapus storage/installed kalau perlu memasang ulang.
 */
class AbortIfInstalled
{
    public function __construct(private Installer $installer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        abort_if($this->installer->isInstalled(), 404);

        return $next($request);
    }
}
