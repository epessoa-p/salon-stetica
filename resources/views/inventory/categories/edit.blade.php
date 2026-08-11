@extends('layouts.app')
@section('title', 'Editar categoría')
@section('page')
@include('inventory.categories.form', ['action' => route('product-categories.update', $category), 'method' => 'PUT'])
@endsection
