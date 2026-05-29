<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Cadence — offline</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
        <style>
            html, body { height: 100%; }
            body {
                margin: 0;
                display: grid;
                place-items: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #0a0a0a;
                color: #ededed;
                text-align: center;
                padding: 24px;
            }
            .logo {
                display: inline-grid;
                place-items: center;
                width: 48px;
                height: 48px;
                border-radius: 8px;
                background: #fff;
                color: #0a0a0a;
                font-weight: 700;
                margin-bottom: 16px;
            }
            h1 { font-size: 20px; margin: 0 0 8px; }
            p { color: #a3a3a3; max-width: 28rem; margin: 0 0 16px; }
            button {
                background: #fff;
                color: #0a0a0a;
                border: 0;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 500;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <div>
            <div class="logo">C</div>
            <h1>You're offline</h1>
            <p>Cadence will reconnect automatically when you're back online.</p>
            <button onclick="location.reload()">Try again</button>
        </div>
    </body>
</html>
