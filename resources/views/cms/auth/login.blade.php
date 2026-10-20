@extends('layouts.admin')

@section('title', 'Login CMS EMBER')

@section('content')
    <div class="grid min-h-screen bg-white lg:grid-cols-[1.08fr_.92fr]">
        <section class="relative hidden overflow-hidden bg-slate-950 text-white lg:flex lg:flex-col lg:justify-between lg:p-14 xl:p-20">
            <div class="absolute inset-0 opacity-20" aria-hidden="true" style="background-image: linear-gradient(rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px); background-size: 48px 48px;"></div>
            <div class="absolute inset-y-0 right-0 w-1 bg-red-600" aria-hidden="true"></div>

            <div class="relative">
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-4">
                    <span class="flex size-12 items-center justify-center bg-red-600 text-xl font-black">E</span>
                    <span>
                        <span class="block text-lg font-bold tracking-wide">EMBER</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Content Management System</span>
                    </span>
                </a>
            </div>

            <div class="relative max-w-2xl">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-400">Portal pengelola</p>
                <h1 class="mt-5 text-5xl font-bold leading-[1.08] tracking-tight xl:text-6xl">Kelola informasi lokasi dalam satu ruang kerja.</h1>
                <p class="mt-6 max-w-xl text-base leading-8 text-slate-300">Perbarui profil EMBER, anggota tim, pustaka foto, dan detail titik lokasi yang ditampilkan pada peta publik.</p>

                <div class="mt-10 grid max-w-xl grid-cols-2 gap-px bg-slate-700">
                    <div class="bg-slate-900 p-5">
                        <p class="text-2xl font-bold text-white">4</p>
                        <p class="mt-1 text-xs uppercase tracking-wider text-slate-400">Modul pengelolaan</p>
                    </div>
                    <div class="bg-slate-900 p-5">
                        <p class="text-2xl font-bold text-white">24/7</p>
                        <p class="mt-1 text-xs uppercase tracking-wider text-slate-400">Akses pemantauan</p>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-slate-500">Early Monitoring for Burning Environment Response</p>
        </section>

        <section class="flex min-h-screen items-center bg-slate-50 px-5 py-12 sm:px-10 lg:px-14 xl:px-20">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-9 lg:hidden">
                    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center bg-red-600 text-lg font-black text-white">E</span>
                        <span>
                            <span class="block font-bold text-slate-950">EMBER CMS</span>
                            <span class="block text-[10px] uppercase tracking-widest text-slate-500">Content Management</span>
                        </span>
                    </a>
                </div>

                <div class="border-l-4 border-red-600 pl-5">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-600">Akses administrator</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Selamat datang kembali</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Masukkan akun administrator untuk melanjutkan ke CMS.</p>
                </div>

                <form method="POST" action="{{ route('cms.login.store') }}" class="mt-8 border border-slate-200 border-t-slate-950 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-bold text-slate-800">Email administrator</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nama@ember.id" class="mt-2 w-full border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:ring-4 focus:ring-red-100">
                        @error('email')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5">
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-sm font-bold text-slate-800">Password</label>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-500">
                                <input type="checkbox" class="border-slate-300 text-red-600 focus:ring-red-500" onchange="document.getElementById('password').type = this.checked ? 'text' : 'password'">
                                Tampilkan
                            </label>
                        </div>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password" class="mt-2 w-full border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:ring-4 focus:ring-red-100">
                        @error('password')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <label class="mt-5 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="border-slate-300 text-red-600 focus:ring-red-500">
                        Tetap masuk di perangkat ini
                    </label>

                    <button type="submit" class="mt-6 flex w-full items-center justify-center gap-2 border border-red-600 bg-red-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-red-600/20 transition hover:bg-red-500">
                        Masuk ke CMS
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-xs text-slate-500">
                    <p>Akses khusus pengelola EMBER.</p>
                    <a href="{{ route('user.dashboard') }}" class="font-semibold text-slate-700 transition hover:text-red-600">Kembali ke situs</a>
                </div>
            </div>
        </section>
    </div>
@endsection
