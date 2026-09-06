<?php

namespace Tests;

use App\Services\Installer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // RedirectIfNotInstalled mengarahkan SELURUH aplikasi ke wizard selama
        // penanda ini belum ada. Test yang menguji aplikasi terpasang butuh
        // penanda itu; InstallTest menghapusnya sendiri dan memulihkannya.
        $this->tandaiTerpasang();
    }

    protected function tandaiTerpasang(): void
    {
        $marker = app(Installer::class)->markerPath();

        if (! file_exists($marker)) {
            file_put_contents($marker, 'test');
        }
    }
}
