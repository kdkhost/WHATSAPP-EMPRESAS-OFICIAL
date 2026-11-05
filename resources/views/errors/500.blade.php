@extends('errors.layout')

@section('code', '500')
@section('icon', 'server')
@section('title', 'Erro interno do servidor')

@section('message')
    Algo deu errado no processamento da solicitação.<br>
    Tente novamente em alguns instantes ou acione o suporte se o erro persistir.
@endsection
