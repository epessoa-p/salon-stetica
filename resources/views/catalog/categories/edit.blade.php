@extends('layouts.app')

@section('title', 'Editar categoría')

@section('page')
@include('catalog.categories.form', ['action' => route('service-categories.update', $category), 'method' => 'PUT'])
@endsection
