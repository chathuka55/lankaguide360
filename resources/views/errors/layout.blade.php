{{--
    Error pages. Self-contained on purpose: no Vite bundle, no database queries, so they still
    render when the build or the database is the problem (that is what caused past 500s).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · LankaGuide360</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            background: radial-gradient(circle at 15% 10%, #ddebe1 0, transparent 45%), radial-gradient(circle at 90% 90%, #fef3c7 0, transparent 40%), #faf8f4;
            font: 16px/1.6 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #334155; }
        main { width: 100%; max-width: 34rem; background: #fff; border-radius: 28px; padding: 40px 32px; text-align: center;
            box-shadow: 0 25px 50px -12px rgb(12 30 22 / .15); border: 1px solid rgb(12 30 22 / .06); }
        .logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font-weight: 800; font-size: 20px; letter-spacing: -.01em; }
        .logo img { width: 44px; height: 44px; }
        .logo .g { color: #0e8437; } .logo .b { color: #0b4f8f; }
        .code { margin: 28px 0 0; font: italic 64px/1 "Instrument Serif", Georgia, "Times New Roman", serif; color: #234f3a; }
        h1 { margin: 12px 0 0; font-size: 24px; line-height: 1.25; color: #0c1e16; }
        p { margin: 12px 0 0; }
        .note { font-size: 14px; color: #64748b; }
        .actions { margin-top: 28px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .btn { display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 999px; padding: 12px 22px; font: 600 14px/1 inherit;
            font-family: inherit; text-decoration: none; cursor: pointer; }
        .btn-primary { background: #1d3f2f; color: #fff; } .btn-primary:hover { background: #183427; }
        .btn-light { background: #f3efe7; color: #1d3f2f; } .btn-light:hover { background: #e8e1d3; }
        form { display: inline; margin: 0; }
    </style>
</head>
<body>
    <main>
        <a class="logo" href="{{ url('/') }}">
            <img src="{{ asset('images/brand/mark-128.png') }}" alt="" width="44" height="44">
            <span><span class="g">Lanka</span><span class="b">Guide360</span></span>
        </a>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        @yield('message')
        <div class="actions">
            @yield('actions')
            <a class="btn btn-light" href="{{ url('/') }}">Home page</a>
        </div>
    </main>
</body>
</html>
