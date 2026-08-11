@extends('layouts.app')

@section('title', 'Editar cita')

@section('page')
@include('scheduling.appointments.form', ['action' => route('appointments.update', $appointment), 'method' => 'PUT'])
@endsection
