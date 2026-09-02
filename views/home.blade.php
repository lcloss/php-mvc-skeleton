@extends('layouts.app')
@section('content')
    <main style="max-width:640px;margin:80px auto;font-family:system-ui,sans-serif;text-align:center">
        <h1>{{ $app_name }}</h1>
        <p>A tua nova aplicação PHP está pronta.</p>
        <p><small>&copy; {{ $year }}</small></p>
    </main>
@endsection
