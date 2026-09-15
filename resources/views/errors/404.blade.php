@component('errors._shell', ['title' => 'Page not found', 'heading' => 'We could not find that page'])
    {{-- #57. Says what happened and offers a route back. Makes no guess
         about what the reader was looking for, and no claim about their
         cases, which this page has not looked at. --}}
    <p class="lead">The address you followed does not exist, or it has moved.</p>

    <p>Nothing has gone wrong with your account or your cases.</p>

    <a href="{{ url('/cases') }}" class="btn btn-primary mt-3">Your cases</a>
    <a href="{{ url('/') }}" class="btn btn-link mt-3">Home</a>
@endcomponent
