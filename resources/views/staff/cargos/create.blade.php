@extends('layouts.app')

@section('title', 'Nuevo cargo')

@section('page')
@include('staff.cargos.form', ['action' => route('cargos.store'), 'method' => 'POST', 'cargo' => null])
@endsection
