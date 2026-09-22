{{--
    Deliberately a Blade view rather than an Inertia page.

    These URLs are submitted to Google and Microsoft for OAuth verification, and
    a reviewer (or a crawler) has to be able to read the text in the initial HTML
    response. An Inertia page renders nothing without the Vite bundle, so a
    broken or missing front-end build would show the reviewer a blank policy and
    fail verification. This has no JavaScript and no build step at all.
--}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>@yield('title') — {{ config('legal.product') }}</title>
        <meta name="description" content="@yield('description')" />
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
        <style>
            :root {
                --bg: #ffffff;
                --fg: #18181b;
                --muted: #52525b;
                --border: #e4e4e7;
                --accent: #2563eb;
                --card: #fafafa;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #0a0a0a;
                    --fg: #ededed;
                    --muted: #a1a1aa;
                    --border: #27272a;
                    --accent: #60a5fa;
                    --card: #141414;
                }
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: var(--bg);
                color: var(--fg);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                line-height: 1.65;
                -webkit-text-size-adjust: 100%;
            }
            .wrap { max-width: 44rem; margin: 0 auto; padding: 0 16px 80px; }
            header {
                border-bottom: 1px solid var(--border);
                margin-bottom: 40px;
            }
            .bar {
                max-width: 44rem;
                margin: 0 auto;
                padding: 20px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
            }
            .brand {
                font-weight: 700;
                font-size: 1.05rem;
                color: var(--fg);
                text-decoration: none;
            }
            nav a {
                color: var(--muted);
                text-decoration: none;
                font-size: 0.875rem;
                margin-left: 16px;
            }
            nav a:hover, nav a[aria-current="page"] { color: var(--fg); }
            h1 { font-size: 1.9rem; line-height: 1.25; margin: 0 0 8px; letter-spacing: -0.02em; }
            h2 {
                font-size: 1.15rem;
                margin: 40px 0 12px;
                letter-spacing: -0.01em;
                scroll-margin-top: 24px;
            }
            h3 { font-size: 1rem; margin: 24px 0 8px; }
            p, li { color: var(--fg); }
            .lede { color: var(--muted); margin: 0 0 4px; font-size: 0.9rem; }
            ul, ol { padding-left: 22px; }
            li { margin: 6px 0; }
            a { color: var(--accent); }
            code {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 4px;
                padding: 1px 5px;
                font-size: 0.85em;
            }
            .callout {
                background: var(--card);
                border: 1px solid var(--border);
                border-left: 3px solid var(--accent);
                border-radius: 6px;
                padding: 16px 20px;
                margin: 24px 0;
            }
            .callout p:first-child { margin-top: 0; }
            .callout p:last-child { margin-bottom: 0; }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 16px 0;
                font-size: 0.9rem;
                display: block;
                overflow-x: auto;
            }
            th, td {
                border: 1px solid var(--border);
                padding: 8px 10px;
                text-align: left;
                vertical-align: top;
            }
            th { background: var(--card); font-weight: 600; }
            footer {
                border-top: 1px solid var(--border);
                margin-top: 56px;
                padding-top: 24px;
                color: var(--muted);
                font-size: 0.85rem;
            }
            footer a { color: var(--muted); }
        </style>
    </head>
    <body>
        <header>
            <div class="bar">
                <a class="brand" href="/">{{ config('legal.product') }}</a>
                <nav>
                    <a href="{{ route('legal.privacy') }}"
                       @if(request()->routeIs('legal.privacy')) aria-current="page" @endif>Privacy</a>
                    <a href="{{ route('legal.terms') }}"
                       @if(request()->routeIs('legal.terms')) aria-current="page" @endif>Terms</a>
                </nav>
            </div>
        </header>

        <main class="wrap">
            <h1>@yield('title')</h1>
            <p class="lede">
                Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('j F Y') }}
                @if(config('legal.entity') !== config('legal.product'))
                    · Operated by {{ config('legal.entity') }}
                @endif
            </p>

            @yield('body')

            <footer>
                <p>
                    {{ config('legal.entity') }}@if(config('legal.address')) · {{ config('legal.address') }}@endif
                </p>
                <p>
                    Questions: <a href="mailto:{{ config('legal.privacy_email') }}">{{ config('legal.privacy_email') }}</a>
                    · <a href="{{ route('legal.privacy') }}">Privacy</a>
                    · <a href="{{ route('legal.terms') }}">Terms</a>
                    · <a href="/">Home</a>
                </p>
            </footer>
        </main>
    </body>
</html>
