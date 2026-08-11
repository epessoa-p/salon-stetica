@extends('layouts.app')

@section('title', 'Editar cargo')

@section('page')
@include('staff.cargos.form', ['action' => route('cargos.update', $cargo), 'method' => 'PUT', 'cargo' => $cargo])
@endsection
