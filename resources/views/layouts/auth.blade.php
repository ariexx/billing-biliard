<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk &mdash; {{ config('app.name') }}</title>

    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700" rel="stylesheet">
    {{-- Sumber yang sama dengan layouts/app supaya ikonnya konsisten. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
          integrity="sha512-MV7K8+y+gLIBoVD59lQIYicR65iaqukzvf/nwasF0nqhPay5w/9lJmVM2hMDcnK1OnMGCdVK+iQrJ7lzPJQd1w=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        /* Halaman login berdiri sendiri, tidak memakai navbar aplikasi: saat
           membuka halaman ini pengguna belum punya apa pun untuk dinavigasi. */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: Nunito, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #0f172a;
            background-image:
                radial-gradient(at 20% 20%, rgba(16, 185, 129, .22) 0px, transparent 55%),
                radial-gradient(at 80% 80%, rgba(59, 130, 246, .18) 0px, transparent 55%);
        }
        .kartu-masuk {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .35);
        }
        .lambang {
            width: 56px; height: 56px;
            border-radius: 1rem;
            display: inline-flex; align-items: center; justify-content: center;
            background: #10b981; color: #fff;
            font-size: 1.6rem;
        }
        /* Sasaran sentuh besar: PC kasir kadang layar sentuh. */
        .kartu-masuk .form-control { padding: .7rem .9rem; }
        .kartu-masuk .btn-masuk { padding: .7rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container py-5">
        @yield('content')
    </div>
</body>
</html>
