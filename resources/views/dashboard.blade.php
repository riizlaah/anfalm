@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Halo, {{ auth()->user()->nama_lengkap }}!</h1>

    @if (auth()->user()->isAdmin())
        <p>Anda masuk sebagai <strong>Admin</strong>. Kelola konten dari <a href="{{ route('admin.dashboard') }}">area admin</a>.</p>
    @else
        <p>Selamat datang di platform tryout TKA. Pengerjaan tryout akan tersedia di sini.</p>
    @endif
@endsection