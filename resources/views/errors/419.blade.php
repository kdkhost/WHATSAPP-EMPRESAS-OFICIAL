@extends('errors.layout')
@section('code', '419')
@section('icon')⏱️@endsection
@section('title', 'Sessão Expirada')
@section('message')
Sua sessão expirou por inatividade ou o token CSRF é inválido.<br>
Recarregue a página e tente novamente.
@endsection
