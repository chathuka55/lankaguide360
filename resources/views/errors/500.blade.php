@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong on our side')

@section('message')
    <p>The problem has been logged and we’ll look at it. Please try again in a moment.</p>
    <p class="note">If it keeps happening, contact us and tell us what you were doing.</p>
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Try again</a>
@endsection
