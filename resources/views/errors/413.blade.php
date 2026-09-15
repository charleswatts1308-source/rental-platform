@component('errors._shell', ['title' => 'Too large to send', 'heading' => 'That was too large to send'])
    {{--
        #57. Design agreed 22 Aug, built 13 Sep.

        NO HANDLER CODE. PostTooLargeException is an HttpException with
        status 413, so Laravel renders this view automatically and
        bootstrap/app.php stays empty.

        There is no session here. ValidatePostSize is global middleware and
        runs before the web group's StartSession, so a redirect-back-with-
        error is not merely worse, it is unavailable.

        What is deliberately ABSENT, and must stay absent: any claim a draft
        was saved, any half-populated form, any statement about sign-in
        status, any suggestion the work can be retrieved. The standing rule
        from #46, #49 and #53 applies here more than anywhere — an error
        page must not claim something the system cannot honour. It says the
        work is gone, once, plainly, and then gives the way forward.
    --}}
    <p class="lead">Your form did not reach us, so nothing has been saved.</p>

    <p>This is almost always caused by photos that are too large.</p>

    <p>We cannot recover what you had typed — you will need to enter the case
       again. We are sorry.</p>

    {{-- Rendered, never hardcoded (#58). This page runs under the web SAPI,
         so ini_get() reports the limits that actually applied to the request
         that just failed. A hardcoded figure here would drift the moment the
         hosting panel changed, which has already happened once. --}}
    <p>Each photo must be {{ \App\Support\PhotoLimits::perFileLabel() }} or
       smaller, and everything sent at once must come to less than
       {{ \App\Support\PhotoLimits::totalLabel() }} in total.</p>

    {{-- The page IS the landing; no redirect is possible. This link loads
         authenticated in the ordinary way, because the session cookie
         survived even though this render could not see it. --}}
    <a href="{{ url('/cases') }}" class="btn btn-primary mt-3">Back to your cases</a>
@endcomponent
