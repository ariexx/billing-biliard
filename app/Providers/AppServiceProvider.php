<?php

namespace App\Providers;

use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        config(['app.locale' => 'en']);
        Carbon::setLocale('id');
        date_default_timezone_set('Asia/Jakarta');

        // Aplikasi ini memakai Bootstrap 5. Tanpa baris ini Laravel merender
        // pagination bergaya Tailwind, yang di sini tampil tanpa gaya sama sekali.
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        $this->registerGoogleDriveDisk();
    }

    /**
     * Driver disk 'gdrive' untuk backup database (lihat App\Console\Commands\BackupDatabase).
     */
    private function registerGoogleDriveDisk(): void
    {
        Storage::extend('gdrive', function ($app, array $config) {
            $client = new GoogleClient();
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $adapter = new GoogleDriveAdapter(
                new GoogleDrive($client),
                $config['folderId'] ?: '/',
            );

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
