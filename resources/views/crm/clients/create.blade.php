@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('page')
@include('crm.clients.form', ['action' => route('clients.store'), 'method' => 'POST'])
@endsection
