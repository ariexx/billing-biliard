<?php
namespace App\Traits;

use Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                // Cast ke string wajib. Str::uuid() mengembalikan objek Ramsey UUID,
                // sehingga pada request yang membuat model itu $model->uuid adalah
                // objek sementara nilai yang sama dibaca dari database berupa string.
                // Perbandingan === antara keduanya selalu false -- itu membuat cek
                // kepemilikan di OrderPolicy menolak pemiliknya sendiri.
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
