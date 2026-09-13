# PixiePoint Wi-Fi

PixiePoint is a Docker-hosted MikroTik captive portal and lightweight Wi-Fi management system. The repository contains:

- `mt_hotspot/` — minimal files uploaded to RouterOS that bootstrap clients into the hosted portal.
- `public/` — hosted PHP portal, JSON health endpoint, and administration UI.
- `src/` — application/database bootstrap.
- `docker/php/` — PHP-FPM image used by the shared Docker stack.
- `deploy/` — shared nginx and MariaDB setup examples.

## Docker deployment

The project expects an existing external Docker network named `webnet`, a MariaDB container reachable on that network, and a shared nginx container.

```bash
cp .env.example .env
docker network create webnet # only if the shared network does not exist
docker compose up -d --build
```

The PHP container runs:

```bash
php bin/migrate.php --auto && exec php-fpm
```

Create the MariaDB database and account using `deploy/mariadb-setup.sql`, then use the same credentials in `.env`.

The shared nginx container must:

1. Join `webnet`.
2. Reach PHP-FPM using `pixiepoint_php:9000`.
3. Mount this repository at `/var/www/html` so nginx can serve `public/assets` and resolve `try_files`.
4. Load `deploy/nginx/hs.portalx.win.conf`.

After nginx and TLS are active, open `https://hs.portalx.win/setup` to create the first administrator.

## First configuration

1. Copy `.env.example` to `.env`.
2. Set the MariaDB password and a random `ACCOUNTING_KEY`.
3. Serve the application over HTTPS at `hs.portalx.win`.
4. Create the first administrator at `/setup`.
5. Register the MikroTik router using its exact `/system identity` value.
6. Set **Public hostname / VPN IP** to the hostname used by the MikroTik HotSpot login URL.
7. Create vouchers and configure the corresponding RouterOS/RADIUS credentials.

## Router bootstrap files

Upload `mt_hotspot` to `flash/mt_hotspot`, then configure:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
/ip hotspot walled-garden add dst-host=hs.portalx.win
```

`login.html` and `status.html` keep the browser on the MikroTik local document while `hotspot-embed.js` fetches the rendered portal from PixiePoint and writes it into that document.

## Portal flow

The current portal is intentionally independent of coin-slot hardware.

```text
MikroTik login.html
  -> hotspot-embed.js
  -> GET /hotspot/compat
  -> hosted theme rendered by PixiePoint
  -> hotspot-login.js
  -> MikroTik CHAP/PAP voucher login
```

The portal supports the PixiePoint voucher UI and optional member, QR, trial, points, device and session features. Hardware-specific integrations can be added later as separate modules without coupling them to the portal core.

## Authentication

MikroTik remains the network enforcement point. The hosted portal hands credentials back to the router using the HotSpot login URL supplied by RouterOS. CHAP is computed inside the local portal document when challenge values are available.

For RADIUS deployments:

```routeros
/radius add service=hotspot address=RADIUS_VPN_IP secret="UNIQUE_LONG_SECRET"
/ip hotspot profile set [find name="hsprof1"] use-radius=yes radius-accounting=yes radius-interim-update=1m login-by=https,http-pap,cookie
```

The management API accepts normalized accounting events at `POST /api/accounting` using `Authorization: Bearer ACCOUNTING_KEY`. Supported fields include `session_id`, `status`, `username`, `client_ip`, `mac`, `router_identity`, `uptime`, `bytes_in`, `bytes_out`, and `terminate_cause`.

## Health endpoint

`GET /hotspot/health` returns HTTP 200 with `{ "ready": true }` when the hosted application is available.

## RouterOS login integration

`routeros/LoginScript_PixiePoint.rsc` can report RouterOS login/sale events to PixiePoint and manage local voucher expiry. See `routeros/README.md` for its metadata format and installation notes.
