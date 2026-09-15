{{--
    Standalone shell for every error page.

    DELIBERATELY NOT layouts/app. The 413 case forces this: ValidatePostSize
    is global middleware and runs BEFORE the web group's StartSession, so
    when a 413 renders there is no session, no auth and no flashed input.
    The app layout does not crash without one — its auth chrome sits inside
    @guest/@else — but it takes the @guest branch and shows LOGIN and
    REGISTER. A tenant who has just lost their work would also be told they
    are signed out. They are not: the session cookie is untouched and their
    next request is authenticated normally. The page simply cannot verify
    that, so it must not assert it either way.

    The other codes could use the app layout, but a 500 rendered through a
    layout that itself queries or reads state is a second failure waiting to
    happen. One shell, no chrome, nothing to go wrong.

    $title, $heading and the slot are supplied by each error view.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - renters.rent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/spacelab/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <p class="text-muted mb-4">
                    <a href="{{ url('/') }}" class="text-decoration-none">renters.rent</a>
                </p>

                <h1 class="h3 mb-3">{{ $heading }}</h1>

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
