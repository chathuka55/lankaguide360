@extends('errors.layout')

@section('code', '404')
@section('title', 'We couldn’t find that page')

@section('message')
    <p>The link may be old, or the place or hotel isn’t published yet.</p>
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/destinations') }}">Browse destinations</a>
@endsection
