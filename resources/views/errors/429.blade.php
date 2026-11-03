@extends('errors.layout')

@section('code', '429')
@section('title', 'Too many requests')

@section('message')
    <p>Please wait a few minutes and try again.</p>
@endsection
