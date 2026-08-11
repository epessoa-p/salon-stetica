@extends('layouts.app')

@section('title', 'Nuevo servicio')

@section('page')
@include('catalog.services.form', ['action' => route('services.store'), 'method' => 'POST'])
@endsection
