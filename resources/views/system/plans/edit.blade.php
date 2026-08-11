@extends('layouts.app')

@section('title', 'Editar plan')

@section('page')
@include('system.plans.form', ['action' => route('plans.update', $plan), 'method' => 'PUT', 'plan' => $plan])
@endsection
