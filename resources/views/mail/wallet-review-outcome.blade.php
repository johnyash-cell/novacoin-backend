<x-mail::message>
<img src="{{ asset('images/novacoin-icon.png') }}" alt="{{ config('app.name') }}" width="48" height="48" style="margin-bottom: 16px;">

# {{ $emailHeading }}

{{ $emailBody }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

