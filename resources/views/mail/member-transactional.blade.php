<x-mail::message>
# {{ $emailHeading }}

{{ $emailBody }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
