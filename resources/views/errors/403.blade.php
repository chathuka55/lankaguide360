@extends('errors.layout')

@php
    $user = auth()->user();
    // A traveller account opened the back office (e.g. after registering during planning).
    $staffArea = $user && ! $user->isStaff() && request()->is('admin', 'admin/*');
@endphp

@section('code', '403')
@section('title', $staffArea ? 'This area is for LankaGuide360 staff' : 'You can’t open this page')

@section('message')
    @if ($staffArea)
        <p>You’re signed in as <strong>{{ $user->name }}</strong> ({{ $user->email }}), a traveller account. The admin panel needs an agent or admin account.</p>
        <p class="note">Your trips are safe in this account. Switch accounts to continue as staff.</p>
    @else
        <p>Your account doesn’t have permission for this page or action.</p>
        @if ($user)
            <p class="note">Signed in as {{ $user->name }}. If you think you should have access, ask an administrator.</p>
        @endif
    @endif
@endsection

@section('actions')
    @if ($staffArea)
        <form method="POST" action="{{ route('switch-account') }}">
            @csrf
            <input type="hidden" name="next" value="{{ request()->getRequestUri() }}">
            <button type="submit" class="btn btn-primary">Switch to a staff account</button>
        </form>
        <a class="btn btn-light" href="{{ route('my-trips') }}">My Trips</a>
    @elseif ($user?->isStaff())
        <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Admin dashboard</a>
    @elseif ($user)
        <a class="btn btn-primary" href="{{ route('my-trips') }}">My Trips</a>
    @endif
@endsection
