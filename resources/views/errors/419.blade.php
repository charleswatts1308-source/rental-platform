@component('errors._shell', ['title' => 'Your session expired', 'heading' => 'Your session expired before that was sent'])
    {{--
        #57. The MOST likely error a real tenant meets, because writing up
        a repair honestly takes time and a long form outlives a session.

        Says the work is gone. It is: the submission failed CSRF/session
        verification and nothing was stored. Offering "we saved your draft"
        here would be the #46 failure mode — a surface claiming something
        the system does not honour.
    --}}
    <p class="lead">Your form did not reach us, so nothing has been saved.</p>

    <p>This happens when a page has been open for a long time. It is not a
       fault, and nothing is wrong with your account.</p>

    <p>We cannot recover what you had typed — you will need to enter it
       again. If you were writing a long description, it is worth copying it
       somewhere before you sign back in.</p>

    <a href="{{ url('/login') }}" class="btn btn-primary mt-3">Sign in again</a>
    <a href="{{ url('/') }}" class="btn btn-link mt-3">Home</a>
@endcomponent
