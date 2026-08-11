@extends('layouts.app')

@section('title', 'Editar personal')

@section('page')
@include('staff.personal.form', ['action' => route('personal.update', $personal), 'method' => 'PUT', 'personal' => $personal])
@endsection
