@extends('layouts.app')
@section('title', 'Editar marca')
@section('page')
@include('inventory.brands.form', ['action' => route('brands.update', $brand), 'method' => 'PUT'])
@endsection
