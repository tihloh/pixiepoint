# PixiePoint hosted-portal bootstrapper

Upload the contents of this directory to a folder on the MikroTik, for example `flash/mt_hotspot`, then point the HotSpot server profile to it:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
```

The router stores only a small bootstrap page. It checks whether the hosted portal is reachable, securely POSTs the client/router context to it, and displays a completely local error screen when the portal or upstream internet cannot be reached. When unavailable, it keeps checking indefinitely in the background and opens the hosted portal automatically as soon as service returns.

The bootstrapper is configured for `https://hs.portalx.win`. The hosted server must provide:

- `GET /hotspot/health` — returns HTTP 200 and exactly identifies readiness with JSON such as `{ "ready": true }`
- `POST /` — accepts the MikroTik context and renders the hosted portal
- `POST /hotspot/session` — renders current device and live session information
- `POST /hotspot/disconnected` — renders the final session summary

Allow the portal through the HotSpot walled garden:

```routeros
/ip hotspot walled-garden add dst-host=hs.portalx.win
/ip hotspot walled-garden add dst-host=www.gstatic.com
```

The health response must allow the MikroTik HotSpot origin through CORS. A simple development response is:

```http
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: *
Cache-Control: no-store

{"ready":true}
```

The bootstrapper stays on its local UI for DNS errors, network failures, CORS failures, non-2xx responses, invalid JSON, or any response where `ready` is not exactly `true`. It retries in the background without refreshing or navigating the local page. A separate Google connectivity endpoint distinguishes an available internet connection with a broken portal from an unavailable upstream connection. If that diagnostic domain is not in the walled garden, failures will be reported as upstream connectivity failures.

## Included pages

- `login.html` — connectivity check, local fallback UI, and POST bootstrapper
- `status.html` and `logout.html` — AJAX-gated POST bootstrappers for live and completed sessions
- `online-bootstrap.js` — shared background health loop; it never refreshes a page while checking
- `alogin.html`, `error.html`, `rlogin.html`, `flogin.html`, `redirect.html` — minimal RouterOS redirect helpers
- `api.json` — captive portal API response

The hosted application must eventually submit credentials back to the supplied `login_url`; the next implementation step is the hosted `/hotspot/start` endpoint and its RADIUS-backed login flow.
