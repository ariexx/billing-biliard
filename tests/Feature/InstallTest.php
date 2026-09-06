<?php

namespace Tests\Feature;

use App\Models\Hour;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    private Installer $installer;

    private ?string $markerBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installer = app(Installer::class);

        // Penanda dilepas supaya wizard hidup, lalu dikembalikan di tearDown --
        // kalau tidak, aplikasi asli di mesin ini ikut dianggap belum terpasang.
        $marker = $this->installer->markerPath();

        if (file_exists($marker)) {
            $this->markerBackup = file_get_contents($marker);
            unlink($marker);
        }
    }

    protected function tearDown(): void
    {
        if ($this->markerBackup !== null) {
            file_put_contents($this->installer->markerPath(), $this->markerBackup);
        }

        parent::tearDown();
    }

    private function dataValid(array $ganti = []): array
    {
        $db = config('database.connections.'.config('database.default'));

        return array_merge([
            'app_name' => 'Biliar Uji',
            'app_url' => 'http://localhost:8000',

            'db_host' => $db['host'] ?? '127.0.0.1',
            'db_port' => $db['port'] ?? '3306',
            'db_database' => $db['database'] ?? ':memory:',
            'db_username' => $db['username'] ?? 'root',
            'db_password' => $db['password'] ?? '',

            'admin_name' => 'Bos',
            'admin_email' => 'bos@contoh.test',
            'admin_password' => 'rahasia123',
            'admin_password_confirmation' => 'rahasia123',

            'table_count' => 3,
            'table_prefix' => 'Meja',
            'payment_methods' => 'Tunai, QRIS',

            'hours' => [
                ['name' => '1 Jam', 'type' => 'regular', 'hour' => 1, 'price' => 50000],
                ['name' => 'Main Bebas', 'type' => 'free time', 'hour' => 1, 'price' => 500],
            ],

            'printer_name' => 'POS-80C',
            'paper_size' => '80mm',
        ], $ganti);
    }

    /** @test */
    public function aplikasi_yang_belum_dipasang_mengarahkan_semua_halaman_ke_wizard(): void
    {
        $this->get('/home')->assertRedirect(route('install.show'));
        $this->get('/login')->assertRedirect(route('install.show'));
    }

    /** @test */
    public function wizard_menampilkan_hasil_pemeriksaan_syarat_sistem(): void
    {
        $this->get(route('install.show'))
            ->assertSuccessful()
            ->assertSee('Syarat Sistem')
            ->assertSee('Ekstensi pdo_mysql')
            ->assertSee('Paket Jam');
    }

    /** @test */
    public function wizard_hilang_total_setelah_aplikasi_terpasang(): void
    {
        $this->tandaiTerpasang();

        $this->get(route('install.show'))->assertNotFound();
        $this->post(route('install.run'), $this->dataValid())->assertNotFound();
    }

    /** @test */
    public function tes_koneksi_melaporkan_kredensial_yang_salah_dengan_bahasa_manusia(): void
    {
        $hasil = $this->installer->testDatabase([
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'database_yang_tidak_ada_'.uniqid(),
            'username' => 'user_ngawur',
            'password' => 'salah',
        ]);

        $this->assertFalse($hasil['ok']);
        $this->assertIsString($hasil['pesan']);
        // Bukan lemparan PDOException mentah ke layar.
        $this->assertStringNotContainsString('SQLSTATE', $hasil['pesan']);
    }

    /** @test */
    public function validasi_menolak_data_yang_tidak_lengkap(): void
    {
        $this->post(route('install.run'), [])
            ->assertSessionHasErrors(['app_name', 'db_database', 'admin_email', 'table_count', 'hours']);
    }

    /** @test */
    public function password_admin_harus_dikonfirmasi_dan_minimal_delapan_karakter(): void
    {
        $this->post(route('install.run'), $this->dataValid([
            'admin_password' => 'pendek',
            'admin_password_confirmation' => 'beda',
        ]))->assertSessionHasErrors('admin_password');
    }

    /** @test */
    public function tipe_paket_jam_hanya_boleh_regular_atau_free_time(): void
    {
        $this->post(route('install.run'), $this->dataValid([
            'hours' => [['name' => 'Ngawur', 'type' => 'mingguan', 'hour' => 1, 'price' => 1000]],
        ]))->assertSessionHasErrors('hours.0.type');
    }

    /** @test */
    public function pemasangan_membuat_admin_kasir_meja_paket_jam_dan_metode_bayar(): void
    {
        // Menjalankan langkah data saja: penulisan .env dan migrasi diuji terpisah
        // supaya test ini tidak menimpa .env mesin pengembang.
        $data = $this->dataValid([
            'cashier_name' => 'Kasir Satu',
            'cashier_email' => 'kasir1@contoh.test',
            'cashier_password' => 'rahasia123',
        ]);

        $this->jalankanLangkahData($data);

        $admin = User::where('email', 'bos@contoh.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));

        $kasir = User::where('email', 'kasir1@contoh.test')->first();
        $this->assertSame('cashier', $kasir->role);

        $this->assertSame(3, Product::where('type', 'billiard')->count());
        $this->assertSame('Meja 1', Product::where('product_code', 'MEJA-1')->value('name'));

        $this->assertSame(2, Hour::count());
        $this->assertSame(500, (int) Hour::where('type', 'free time')->value('price'));

        $this->assertEqualsCanonicalizing(['Tunai', 'QRIS'], Payment::pluck('name')->all());
    }

    /** @test */
    public function setiap_meja_terhubung_ke_semua_paket_jam(): void
    {
        // Tanpa relasi ini dropdown durasi di kasir kosong dan meja tidak bisa dipakai.
        $this->jalankanLangkahData($this->dataValid());

        foreach (Product::where('type', 'billiard')->get() as $meja) {
            $this->assertSame(2, $meja->hours()->count(), "Meja {$meja->name} tidak punya paket jam");
        }
    }

    /** @test */
    public function pemasangan_ulang_tidak_menggandakan_data(): void
    {
        $this->jalankanLangkahData($this->dataValid());
        $this->jalankanLangkahData($this->dataValid());

        $this->assertSame(3, Product::where('type', 'billiard')->count());
        $this->assertSame(2, Hour::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(1, User::where('email', 'bos@contoh.test')->count());
    }

    /** @test */
    public function kasir_bisa_langsung_membuka_meja_setelah_pemasangan(): void
    {
        // Pembuktian sebenarnya: hasil wizard harus cukup untuk satu transaksi.
        $this->jalankanLangkahData($this->dataValid());

        $kasir = User::factory()->create(['role' => 'cashier']);
        $meja = Product::where('product_code', 'MEJA-1')->first();
        $jam = Hour::where('type', 'regular')->first();

        \Livewire\Livewire::actingAs($kasir)
            ->test(\App\Http\Livewire\Product::class)
            ->set('selectedHours', [$meja->uuid => $jam->uuid])
            ->call('saveOrder', $meja->uuid);

        $this->assertSame(1, \App\Models\Order::count());
        $this->assertSame(1, \App\Models\ActiveOrder::where('is_active', true)->count());
    }

    /** @test */
    public function penulisan_env_mengisi_nilai_baru_dan_mempertahankan_key_lain(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, implode("
", [
            'APP_NAME=Lama',
            'APP_KEY=base64:KUNCILAMA=',
            'DB_DATABASE=lama',
            'MAIL_MAILER=smtp',
            'KEY_ASING=jangan-hilang',
        ]));

        (new Installer())->useEnvPath($tmp)->writeEnv($this->dataValid([
            'app_name' => 'Biliar Baru',
            'db_database' => 'db_baru',
            'printer_name' => 'POS-58',
        ]));

        $isi = file_get_contents($tmp);

        $this->assertStringContainsString('APP_NAME="Biliar Baru"', $isi);
        $this->assertStringContainsString('DB_DATABASE=db_baru', $isi);
        $this->assertStringContainsString('PRINTER=POS-58', $isi);
        // Key di luar wizard tidak boleh hilang.
        $this->assertStringContainsString('KEY_ASING=jangan-hilang', $isi);
        $this->assertStringContainsString('MAIL_MAILER=smtp', $isi);
        // APP_KEY lama HARUS dipertahankan; menggantinya membuat session lama
        // tidak bisa didekripsi.
        $this->assertStringContainsString('APP_KEY=base64:KUNCILAMA=', $isi);
        // Tidak ada duplikat key.
        $this->assertSame(1, substr_count($isi, 'DB_DATABASE='));

        @unlink($tmp);
        foreach (glob($tmp.'.backup-*') as $b) { @unlink($b); }
    }

    /** @test */
    public function penulisan_env_membuat_app_key_kalau_belum_ada(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, "APP_KEY=
DB_DATABASE=x
");

        (new Installer())->useEnvPath($tmp)->writeEnv($this->dataValid());

        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.+$/m', file_get_contents($tmp));

        @unlink($tmp);
        foreach (glob($tmp.'.backup-*') as $b) { @unlink($b); }
    }

    /** @test */
    public function nilai_env_berspasi_dikutip_supaya_terbaca_utuh(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, "APP_NAME=x
APP_KEY=base64:AA=
");

        (new Installer())->useEnvPath($tmp)->writeEnv($this->dataValid([
            'app_name' => 'Black Dragon Pool',
        ]));

        $this->assertStringContainsString('APP_NAME="Black Dragon Pool"', file_get_contents($tmp));

        @unlink($tmp);
        foreach (glob($tmp.'.backup-*') as $b) { @unlink($b); }
    }

    /**
     * Memanggil bagian pembuatan data dari Installer tanpa menyentuh .env,
     * migrasi, maupun penanda pemasangan.
     */
    private function jalankanLangkahData(array $data): void
    {
        $installer = new Installer();
        $ref = new \ReflectionClass($installer);

        foreach (['createAdmin', 'createMasterData'] as $method) {
            $m = $ref->getMethod($method);
            $m->setAccessible(true);
            $m->invoke($installer, $data);
        }
    }
}
