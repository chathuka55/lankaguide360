@extends('errors.layout')

@section('code', '419')
@section('title', 'This page expired')

@section('message')
    <p>For your security, forms expire after a while. Go back, refresh the page and try again.</p>
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous() }}">Go back</a>
@endsection
