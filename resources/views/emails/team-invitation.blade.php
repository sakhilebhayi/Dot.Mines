@component('mail::message')
{{ __('You have been invited to join the :team team!', ['team' => $invitation->team->name]) }}

@if (\App\Support\CentralSignIn::active())
{{ __('Sign in with your InfoDot account, or create one on the same page with this email address. You will arrive at your invitation and can accept it there:') }}

@component('mail::button', ['url' => \App\Support\CentralSignIn::loginThen($acceptUrl)])
{{ __('Sign in or create an InfoDot account') }}
@endcomponent

{{ __('If you are already signed in, you may accept this invitation directly:') }}

@elseif (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
{{ __('If you do not have an account, you may create one by clicking the button below. After creating an account, you may click the invitation acceptance button in this email to accept the team invitation:') }}

@component('mail::button', ['url' => route('register')])
{{ __('Create Account') }}
@endcomponent

{{ __('If you already have an account, you may accept this invitation by clicking the button below:') }}

@else
{{ __('You may accept this invitation by clicking the button below:') }}
@endif


@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept Invitation') }}
@endcomponent

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
@endcomponent
