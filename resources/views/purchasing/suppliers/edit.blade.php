@extends('layouts.app')
@section('title', 'Editar proveedor')
@section('page')
@include('purchasing.suppliers.form', ['action' => route('suppliers.update', $supplier), 'method' => 'PUT'])
@endsection
