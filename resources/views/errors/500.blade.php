@component('errors._shell', ['title' => 'Something went wrong', 'heading' => 'Something went wrong at our end'])
    {{--
        #57. The one page where the temptation to reassure is strongest and
        most dangerous. This view does NOT know whether the failed request
        saved anything, so it must not say either way.

        It also does not promise that anyone has been told. Whether this was
        logged and whether anyone reads that log are different questions,
        and only the first is true by construction.
    --}}
    <p class="lead">This was a fault on our side, not something you did.</p>

    <p>We cannot tell from here whether the thing you were doing completed.
       If you were sending something, check your case before trying again,
       so you do not send it twice.</p>

    <a href="{{ url('/cases') }}" class="btn btn-primary mt-3">Your cases</a>
    <a href="{{ url('/') }}" class="btn btn-link mt-3">Home</a>
@endcomponent
