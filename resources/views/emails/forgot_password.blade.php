@extends('admin::emails.base')

@section('content')
    <p>Hi {{ $email }},</p>
    <p>Forgot your password?</p>
    <p>We received a request to reset the password for your account.</p>
    <br>
    <p>To reset your password, click on the link below, the link will be valid for 24 hours.</p>
    <p><a href="{{ $url }}" target="_blank">{{ $url }}</a></p>
@endsection