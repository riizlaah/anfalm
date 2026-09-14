@extends('layouts.auth')

@section('title', 'Daftar')

@section('content')
    <h1>Daftar Akun</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label for="nama_lengkap">Nama Lengkap</label>
        <input id="nama_lengkap" type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required autofocus>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">

        <label for="password">Kata Sandi (min. 8 karakter)</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">Konfirmasi Kata Sandi</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        @if ($errors->any())
            <div class="err">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <button class="btn" type="submit">Daftar</button>
    </form>

    <p class="alt">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
@endsection