@extends('layouts.app')

@section('title', 'Nuevo personal')

@section('page')
@include('staff.personal.form', ['action' => route('personal.store'), 'method' => 'POST', 'personal' => null])
@endsection
