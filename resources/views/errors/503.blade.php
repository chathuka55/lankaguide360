@extends('errors.layout')

@section('code', '503')
@section('title', 'We’re updating the site')

@section('message')
    <p>LankaGuide360 will be back in a minute. Your trips and bookings are safe.</p>
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Try again</a>
@endsection
