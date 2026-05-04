<x-mail::message>
# Welcome to {{ config('app.name') }}, {{ $ownerName }}!

Your clinic has been set up and is ready to use.

**Your login details:**

- **Login URL:** [{{ $loginUrl }}]({{ $loginUrl }})
- **Username:** `{{ $username }}`
- **Temporary PIN:** `{{ $temporaryPin }}`

<x-mail::panel>
⚠️ **This PIN is temporary and expires on {{ \Carbon\Carbon::parse($pinExpiresAt)->format('F j, Y \a\t g:i A') }} (24 hours from now).**

After logging in, go to your **Profile Settings** and set a permanent PIN or password. Your temporary PIN will stop working after it expires.
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Log In to Your Clinic
</x-mail::button>

If you did not expect this email, you can safely ignore it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
