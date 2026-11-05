@extends('errors.layout')

@section('code', '403')
@section('icon', 'lock')
@section('title', 'Acesso negado')

@section('message')
    Você não tem permissão para acessar este recurso.<br>
    Contate o administrador se acredita que isso é um erro.
@endsection
