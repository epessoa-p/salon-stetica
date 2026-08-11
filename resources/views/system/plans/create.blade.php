@extends('layouts.app')

@section('title', 'Nuevo plan')

@section('page')
@include('system.plans.form', ['action' => route('plans.store'), 'method' => 'POST', 'plan' => null])
@endsection
