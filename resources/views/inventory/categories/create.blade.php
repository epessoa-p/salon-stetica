@extends('layouts.app')
@section('title', 'Nueva categoría')
@section('page')
@include('inventory.categories.form', ['action' => route('product-categories.store'), 'method' => 'POST'])
@endsection
