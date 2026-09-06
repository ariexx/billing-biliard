<?php

namespace App\Http\Controllers;

use App\Services\Installer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstallController extends Controller
{
    public function __construct(private Installer $installer)
    {
    }

    public function show()
    {
        return view('install.index', [
            'requirements' => $this->installer->requirements(),
            'siap' => $this->installer->requirementsMet(),
            'tebakan' => [
                'app_url' => request()->getSchemeAndHttpHost(),
                'db_host' => env('DB_HOST', '127.0.0.1'),
                'db_port' => env('DB_PORT', '3306'),
                'db_database' => env('DB_DATABASE', 'billiard_billing'),
                'db_username' => env('DB_USERNAME', 'root'),
            ],
        ]);
    }

    public function testDatabase(Request $request)
    {
        $data = $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        return response()->json($this->installer->testDatabase($data));
    }

    public function install(Request $request)
    {
        abort_unless($this->installer->requirementsMet(), 422, 'Syarat sistem belum terpenuhi.');

        $data = $request->validate([
            'app_name' => 'required|string|max:60',
            'app_url' => 'required|url',

            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',

            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8|confirmed',

            'cashier_name' => 'nullable|string|max:255',
            'cashier_email' => 'nullable|email|different:admin_email',
            'cashier_password' => 'nullable|required_with:cashier_email|string|min:8',

            'table_count' => 'required|integer|min:1|max:100',
            'table_prefix' => 'nullable|string|max:20',
            'payment_methods' => 'required|string',

            'hours' => 'required|array|min:1',
            'hours.*.name' => 'required|string|max:60',
            'hours.*.type' => ['required', Rule::in(['regular', 'free time'])],
            'hours.*.hour' => 'required|integer|min:1',
            'hours.*.price' => 'required|integer|min:0',

            'printer_name' => 'nullable|string|max:100',
            'paper_size' => ['nullable', Rule::in(['58mm', '80mm'])],
            'backup_hours' => 'nullable|string|max:40',

            'gdrive_client_id' => 'nullable|string',
            'gdrive_client_secret' => 'nullable|string',
            'gdrive_refresh_token' => 'nullable|string',
            'gdrive_folder_id' => 'nullable|string',
        ]);

        $uji = $this->installer->testDatabase([
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'] ?? '',
        ]);

        if (! $uji['ok']) {
            return back()->withInput()->withErrors(['db_database' => $uji['pesan']]);
        }

        try {
            $this->installer->install($data);
        } catch (\Throwable $e) {
            \Log::error('Pemasangan gagal', ['error' => $e->getMessage()]);

            return back()->withInput()->withErrors([
                'db_database' => 'Pemasangan gagal: '.$e->getMessage(),
            ]);
        }

        return redirect()->route('login')->with('status', 'Pemasangan selesai. Silakan masuk dengan akun admin yang baru dibuat.');
    }
}
