@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('page')
@include('catalog.categories.form', ['action' => route('service-categories.store'), 'method' => 'POST'])
@endsection
