@extends('layouts.app')
@section('title', 'Nuevo proveedor')
@section('page')
@include('purchasing.suppliers.form', ['action' => route('suppliers.store'), 'method' => 'POST'])
@endsection
