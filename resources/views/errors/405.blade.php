@extends('errors.layout')
@section('code', '405')
@section('icon')<i class="bi bi-slash-circle"></i>@endsection
@section('title', 'Metodo nao permitido')
@section('message', 'O metodo HTTP utilizado nao e suportado para esta rota. Verifique a URL e tente novamente.')
