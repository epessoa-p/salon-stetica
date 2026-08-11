@extends('layouts.app')

@section('title', 'Editar sucursal')

@section('page')
@include('organization.branches.form', ['action' => route('branches.update', $branch), 'method' => 'PUT', 'branch' => $branch])
@endsection