<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pemasangan &mdash; Billing Biliar</title>
    {{-- Sengaja tidak memakai layouts.app: saat wizard dibuka, aplikasi belum
         tentu punya database, sehingga navbar dan Livewire bisa gagal. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; }
        .wizard { max-width: 820px; }
        .langkah { display: none; }
        .langkah.aktif { display: block; }
        .nav-langkah { font-size: .85rem; }
        .nav-langkah .bulat {
            width: 26px; height: 26px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; background: #dee2e6; color: #495057;
        }
        .nav-langkah .selesai .bulat { background: #198754; color: #fff; }
        .nav-langkah .kini .bulat { background: #0d6efd; color: #fff; }
    </style>
</head>
<body>
<div class="container py-5 wizard">
    <h1 class="h3 mb-1">Pemasangan Billing Biliar</h1>
    <p class="text-muted">Isi sekali, aplikasi langsung siap dipakai kasir.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <b>Pemasangan belum bisa dilanjutkan:</b>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ol class="nav-langkah list-unstyled d-flex flex-wrap gap-3 mb-4" id="peta-langkah"></ol>

    <form method="POST" action="{{ route('install.run') }}" id="form-instal">
        @csrf

        {{-- 1. SYARAT SISTEM --}}
        <section class="langkah aktif" data-judul="Syarat Sistem">
            <div class="card">
                <div class="card-body">
                    <h5>1. Syarat Sistem</h5>
                    <table class="table table-sm align-middle mb-0">
                        @foreach ($requirements as $r)
                            <tr>
                                <td>
                                    {{ $r['nama'] }}
                                    @unless ($r['wajib']) <span class="badge bg-secondary">opsional</span> @endunless
                                </td>
                                <td class="text-muted">{{ $r['nilai'] }}</td>
                                <td class="text-end">
                                    @if ($r['terpenuhi'])
                                        <span class="text-success">&#10004;</span>
                                    @else
                                        <span class="{{ $r['wajib'] ? 'text-danger' : 'text-warning' }}">&#10008;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    @unless ($siap)
                        <div class="alert alert-danger mt-3 mb-0">
                            Ada syarat wajib yang belum terpenuhi. Perbaiki dulu, lalu muat ulang halaman ini.
                        </div>
                    @endunless
                </div>
            </div>
        </section>

        {{-- 2. DATABASE --}}
        <section class="langkah" data-judul="Database">
            <div class="card">
                <div class="card-body">
                    <h5>2. Database</h5>
                    <p class="text-muted small">Databasenya harus sudah dibuat lebih dulu di MySQL (kosong tidak apa-apa).</p>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Host</label>
                            <input class="form-control" name="db_host" id="db_host"
                                   value="{{ old('db_host', $tebakan['db_host']) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port</label>
                            <input class="form-control" name="db_port" id="db_port"
                                   value="{{ old('db_port', $tebakan['db_port']) }}" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Nama Database</label>
                            <input class="form-control" name="db_database" id="db_database"
                                   value="{{ old('db_database', $tebakan['db_database']) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input class="form-control" name="db_username" id="db_username"
                                   value="{{ old('db_username', $tebakan['db_username']) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-muted">(boleh kosong)</span></label>
                            <input class="form-control" type="password" name="db_password" id="db_password"
                                   value="{{ old('db_password') }}">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary mt-3" id="tombol-tes-db">Tes Koneksi</button>
                    <div id="hasil-tes-db" class="mt-2"></div>
                </div>
            </div>
        </section>

        {{-- 3. APLIKASI --}}
        <section class="langkah" data-judul="Aplikasi">
            <div class="card">
                <div class="card-body">
                    <h5>3. Identitas Aplikasi</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Usaha</label>
                            <input class="form-control" name="app_name"
                                   value="{{ old('app_name', 'Billing Biliar') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat Aplikasi</label>
                            <input class="form-control" name="app_url"
                                   value="{{ old('app_url', $tebakan['app_url']) }}" required>
                            <div class="form-text">Alamat yang dibuka kasir di browser.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. AKUN --}}
        <section class="langkah" data-judul="Akun">
            <div class="card">
                <div class="card-body">
                    <h5>4. Akun Admin</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama</label>
                            <input class="form-control" name="admin_name" value="{{ old('admin_name', 'Admin') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="admin_email" value="{{ old('admin_email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="admin_password" minlength="8" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ulangi Password</label>
                            <input class="form-control" type="password" name="admin_password_confirmation" minlength="8" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6>Akun Kasir <span class="text-muted fw-normal">(opsional)</span></h6>
                    <p class="text-muted small">Bisa juga ditambahkan nanti dari panel admin.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nama</label>
                            <input class="form-control" name="cashier_name" value="{{ old('cashier_name', 'Kasir') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="cashier_email" value="{{ old('cashier_email') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="cashier_password" minlength="8">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 5. DATA AWAL --}}
        <section class="langkah" data-judul="Data Awal">
            <div class="card">
                <div class="card-body">
                    <h5>5. Data Awal</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Meja</label>
                            <input class="form-control" type="number" name="table_count" min="1" max="100"
                                   value="{{ old('table_count', 6) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Awalan Nama Meja</label>
                            <input class="form-control" name="table_prefix" value="{{ old('table_prefix', 'Meja') }}">
                            <div class="form-text">Menghasilkan Meja 1, Meja 2, ...</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Metode Pembayaran</label>
                            <input class="form-control" name="payment_methods"
                                   value="{{ old('payment_methods', 'Tunai, QRIS') }}" required>
                            <div class="form-text">Pisahkan dengan koma.</div>
                        </div>
                    </div>

                    <h6>Paket Jam</h6>
                    <div class="alert alert-info small">
                        <b>Perhatikan satuan harga.</b> Paket <b>Reguler</b> dibayar di muka sekali untuk
                        seluruh blok. Paket <b>Main Bebas</b> ditagih <b>per menit</b> &mdash; isi tarif per
                        menit, bukan per jam. Contoh: 500 berarti Rp 500/menit atau Rp 30.000/jam.
                    </div>

                    <div id="daftar-jam"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="tambah-jam">+ Tambah Paket</button>
                </div>
            </div>
        </section>

        {{-- 6. PRINTER & BACKUP --}}
        <section class="langkah" data-judul="Printer &amp; Backup">
            <div class="card">
                <div class="card-body">
                    <h5>6. Printer &amp; Backup</h5>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Printer Windows</label>
                            <input class="form-control" name="printer_name"
                                   value="{{ old('printer_name', 'POS-80C') }}">
                            <div class="form-text">Sama persis seperti di Windows &rsaquo; Printers &amp; scanners.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ukuran Kertas</label>
                            <select class="form-select" name="paper_size">
                                <option value="80mm" @selected(old('paper_size', '80mm') === '80mm')>80mm</option>
                                <option value="58mm" @selected(old('paper_size') === '58mm')>58mm</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jam Backup Otomatis</label>
                            <input class="form-control" name="backup_hours" value="{{ old('backup_hours', '13,21') }}">
                            <div class="form-text">Pilih jam saat toko buka; PC mati di luar itu.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6>Google Drive <span class="text-muted fw-normal">(opsional, bisa diisi nanti)</span></h6>
                    <p class="text-muted small">
                        Kosongkan dulu kalau belum punya. Backup tetap tersimpan di PC, dan kredensial
                        bisa dibuat kapan saja lewat <code>php artisan gdrive:authorize</code>.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input class="form-control" name="gdrive_client_id" value="{{ old('gdrive_client_id') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret</label>
                            <input class="form-control" name="gdrive_client_secret" value="{{ old('gdrive_client_secret') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Refresh Token</label>
                            <input class="form-control" name="gdrive_refresh_token" value="{{ old('gdrive_refresh_token') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Folder ID</label>
                            <input class="form-control" name="gdrive_folder_id" value="{{ old('gdrive_folder_id') }}">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-outline-secondary" id="tombol-mundur">Kembali</button>
            <div>
                <button type="button" class="btn btn-primary" id="tombol-maju">Lanjut</button>
                <button type="submit" class="btn btn-success d-none" id="tombol-pasang" @disabled(! $siap)>
                    Pasang Sekarang
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const langkah = [...document.querySelectorAll('.langkah')];
    const peta = document.getElementById('peta-langkah');
    const btnMaju = document.getElementById('tombol-maju');
    const btnMundur = document.getElementById('tombol-mundur');
    const btnPasang = document.getElementById('tombol-pasang');
    const form = document.getElementById('form-instal');
    let kini = 0;

    langkah.forEach((s, i) => {
        const li = document.createElement('li');
        li.className = 'd-flex align-items-center gap-2';
        li.innerHTML = '<span class="bulat">' + (i + 1) + '</span><span>' + s.dataset.judul + '</span>';
        peta.appendChild(li);
    });

    function gambar() {
        langkah.forEach((s, i) => s.classList.toggle('aktif', i === kini));
        [...peta.children].forEach((li, i) => {
            li.classList.toggle('kini', i === kini);
            li.classList.toggle('selesai', i < kini);
        });
        btnMundur.classList.toggle('invisible', kini === 0);
        btnMaju.classList.toggle('d-none', kini === langkah.length - 1);
        btnPasang.classList.toggle('d-none', kini !== langkah.length - 1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Validasi bawaan browser dijalankan per langkah, supaya kesalahan ketahuan
    // di tempatnya, bukan baru saat tombol Pasang ditekan.
    btnMaju.addEventListener('click', function () {
        const wajib = [...langkah[kini].querySelectorAll('input[required], select[required]')];
        if (!wajib.every(el => el.reportValidity())) return;
        if (kini < langkah.length - 1) { kini++; gambar(); }
    });

    btnMundur.addEventListener('click', function () {
        if (kini > 0) { kini--; gambar(); }
    });

    // --- Tes koneksi database ---
    document.getElementById('tombol-tes-db').addEventListener('click', async function () {
        const kotak = document.getElementById('hasil-tes-db');
        kotak.innerHTML = '<span class="text-muted">Menguji koneksi...</span>';

        const body = new FormData();
        ['host', 'port', 'database', 'username', 'password'].forEach(k =>
            body.append(k, document.getElementById('db_' + k).value));

        try {
            const res = await fetch('{{ route('install.test-database') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body,
            });
            const data = await res.json();
            kotak.innerHTML = '<div class="alert alert-' + (data.ok ? 'success' : 'danger') + ' mb-0">'
                + data.pesan + '</div>';
        } catch (e) {
            kotak.innerHTML = '<div class="alert alert-danger mb-0">Gagal menghubungi server: ' + e.message + '</div>';
        }
    });

    // --- Baris paket jam ---
    const daftarJam = document.getElementById('daftar-jam');
    let indeksJam = 0;

    function tambahJam(nilai) {
        const i = indeksJam++;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-end mb-2';
        div.innerHTML = `
            <div class="col-md-3">
                <label class="form-label small">Nama Paket</label>
                <input class="form-control" name="hours[${i}][name]" value="${nilai.name}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tipe</label>
                <select class="form-select" name="hours[${i}][type]">
                    <option value="regular" ${nilai.type === 'regular' ? 'selected' : ''}>Reguler (blok jam)</option>
                    <option value="free time" ${nilai.type === 'free time' ? 'selected' : ''}>Main bebas (per menit)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Jam</label>
                <input class="form-control" type="number" min="1" name="hours[${i}][hour]" value="${nilai.hour}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Harga (Rp)</label>
                <input class="form-control" type="number" min="0" name="hours[${i}][price]" value="${nilai.price}" required>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger w-100 hapus-jam">&times;</button>
            </div>`;
        daftarJam.appendChild(div);
    }

    daftarJam.addEventListener('click', function (e) {
        if (e.target.closest('.hapus-jam') && daftarJam.children.length > 1) {
            e.target.closest('.row').remove();
        }
    });

    document.getElementById('tambah-jam').addEventListener('click', () =>
        tambahJam({ name: '', type: 'regular', hour: 1, price: 0 }));

    // Contoh yang masuk akal untuk rental biliar; tetap bisa diubah.
    [
        { name: '1 Jam', type: 'regular', hour: 1, price: 50000 },
        { name: '2 Jam', type: 'regular', hour: 2, price: 95000 },
        { name: 'Main Bebas', type: 'free time', hour: 1, price: 500 },
    ].forEach(tambahJam);

    // Cegah kirim ganda: pemasangan menjalankan migrasi, klik kedua bisa tumpang tindih.
    form.addEventListener('submit', function () {
        setTimeout(() => {
            btnPasang.disabled = true;
            btnPasang.textContent = 'Memasang, mohon tunggu...';
        }, 0);
    });

    gambar();
})();
</script>
</body>
</html>
