@extends('layouts.app')
@section('title', 'Editar producto')
@section('page')
@include('inventory.products.form', ['action' => route('products.update', $product), 'method' => 'PUT'])
@endsection
