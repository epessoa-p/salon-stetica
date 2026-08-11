@extends('layouts.app')

@section('title', 'Nueva Caja')

@section('page')
@include('finance.cajas.form', [
    'caja'   => null,
    'action' => route('cajas.store'),
    'method' => 'POST',
])
@endsection
