@extends('layouts.app')

@section('title', 'Nueva cita')

@section('page')
@include('scheduling.appointments.form', ['action' => route('appointments.store'), 'method' => 'POST'])
@endsection
