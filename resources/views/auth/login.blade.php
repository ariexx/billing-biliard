@extends('layouts.auth')

@section('content')
<div class="row justify-content-center">
    <div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">

        <div class="text-center mb-4">
            <div class="lambang mb-3"><i class="fa fa-circle"></i></div>
            <h1 class="h3 text-white mb-1">{{ config('app.name') }}</h1>
            <p class="text-white-50 mb-0">Masuk untuk mulai melayani</p>
        </div>

        <div class="card kartu-masuk">
            <div class="card-body p-4 p-sm-5">

                @if (session('status'))
                    <div class="alert alert-success py-2">{{ session('status') }}</div>
                @endif

                {{-- Satu pesan untuk email maupun password. Laravel memang hanya
                     menempelkan error pada field email, dan memberi tahu mana yang
                     salah justru membocorkan email mana yang terdaftar. --}}
                @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-4">
                        <i class="fa fa-exclamation-circle"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input id="email" type="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}"
                               required autocomplete="username" autofocus
                               placeholder="nama@contoh.com">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="lihat-password"
                                    tabindex="-1" aria-label="Tampilkan password">
                                <i class="fa fa-eye" id="ikon-mata"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
                    </div>

                    <button type="submit" class="btn btn-success w-100 btn-masuk" id="tombol-masuk">
                        <span id="teks-masuk"><i class="fa fa-sign-in"></i> Masuk</span>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-white-50 small mt-4 mb-0">
            Lupa password? Hubungi admin untuk mengatur ulang dari panel.
        </p>
    </div>
</div>

<script>
(function () {
    // Melihat password membantu di layar sentuh, tempat salah ketik sering terjadi.
    const tombol = document.getElementById('lihat-password');
    const input = document.getElementById('password');
    const ikon = document.getElementById('ikon-mata');

    tombol.addEventListener('click', function () {
        const tersembunyi = input.type === 'password';
        input.type = tersembunyi ? 'text' : 'password';
        ikon.className = tersembunyi ? 'fa fa-eye-slash' : 'fa fa-eye';
        tombol.setAttribute('aria-label', tersembunyi ? 'Sembunyikan password' : 'Tampilkan password');
    });

    // Cegah kirim ganda saat koneksi lambat.
    const form = document.querySelector('form');
    form.addEventListener('submit', function () {
        setTimeout(function () {
            const btn = document.getElementById('tombol-masuk');
            btn.disabled = true;
            document.getElementById('teks-masuk').innerHTML =
                '<span class="spinner-border spinner-border-sm"></span> Memproses...';
        }, 0);
    });
})();
</script>
@endsection
