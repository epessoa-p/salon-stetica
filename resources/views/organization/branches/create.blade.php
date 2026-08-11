@extends('layouts.app')

@section('title', 'Nueva sucursal')

@section('page')
@include('organization.branches.form', ['action' => route('branches.store'), 'method' => 'POST', 'branch' => null])
@endsection