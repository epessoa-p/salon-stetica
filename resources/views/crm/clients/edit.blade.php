@extends('layouts.app')

@section('title', 'Editar cliente')

@section('page')
@include('crm.clients.form', ['action' => route('clients.update', $client), 'method' => 'PUT'])
@endsection
