@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <h1>Masuk</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Kata Sandi</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        @if ($errors->any())
            <div class="err">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <button class="btn" type="submit">Masuk</button>
    </form>

    <p class="alt">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
@endsection