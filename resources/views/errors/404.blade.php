@extends('errors.layout')

@section('code', '404')
@section('icon', 'search')
@section('title', 'Página não encontrada')

@section('message')
    A página que você procura não existe, foi movida ou o endereço está incorreto.<br>
    Verifique o caminho informado e tente novamente.
@endsection
