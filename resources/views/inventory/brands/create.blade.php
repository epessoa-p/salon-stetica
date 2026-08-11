@extends('layouts.app')
@section('title', 'Nueva marca')
@section('page')
@include('inventory.brands.form', ['action' => route('brands.store'), 'method' => 'POST'])
@endsection
