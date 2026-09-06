<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;

/**
 * Selama penanda pemasangan belum ada, seluruh aplikasi diarahkan ke wizard.
 * Tanpa ini pengguna baru mendarat di halaman login yang pasti error karena
 * tabel users belum dibuat.
 */
class RedirectIfNotInstalled
{
    public function __construct(private Installer $installer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $this->installer->isInstalled() && ! $request->routeIs('install.*')) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
