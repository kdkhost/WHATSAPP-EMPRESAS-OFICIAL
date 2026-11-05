@extends('errors.layout')
@section('code', '429')
@section('icon')&#9889;@endsection
@section('title', 'Muitas requisições')
@section('message')
Você realizou muitas requisições em um curto período de tempo.<br>
Aguarde alguns instantes antes de tentar novamente.
@endsection
@section('note')
Esse bloqueio é temporário e protege o sistema contra abuso, automação excessiva e tentativas repetidas em sequência.
@endsection
