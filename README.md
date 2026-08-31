# PixiePoint Wi-Fi

PixiePoint is a Docker-hosted MikroTik captive portal and lightweight Wi-Fi management system. The repository contains:

- `mt_hotspot/` — minimal files uploaded to RouterOS. They diagnose connectivity, remain usable offline, and bootstrap clients into the hosted portal.
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

The PHP container runs this startup command:

```bash
php bin/migrate.php --auto && exec php-fpm
```

Create the MariaDB database and account using [deploy/mariadb-setup.sql](deploy/mariadb-setup.sql), after replacing its password. The same credentials must be used in `.env`.

The shared nginx container must:

1. Join `webnet`.
2. Reach PHP-FPM using `pixiepoint_php:9000`.
3. Mount this repository at `/var/www/html` so nginx can serve `public/assets` and resolve `try_files`.
4. Load the example virtual host from [deploy/nginx/hs.portalx.win.conf](deploy/nginx/hs.portalx.win.conf).

For example, add this to the shared nginx service:

```yaml
volumes:
  - /path/to/pixiepoint:/var/www/html:ro
networks:
  - webnet
```

After nginx and TLS are active, open `https://hs.portalx.win/setup` to create the first administrator. The management UI is at `/admin`.

## First configuration

1. Copy `.env.example` to `.env`.
2. Set the MariaDB password and a random `ACCOUNTING_KEY`.
3. Serve the application over HTTPS at `hs.portalx.win` through shared nginx.
4. Create the first administrator at `/setup`.
5. In **Routers**, register the exact value from `/system identity print`.
6. Set **Public hostname / VPN IP** to the hostname used in the MikroTik `link-login` URL. This prevents forged portal requests from stealing voucher credentials.
7. Create a voucher and synchronize the same username/password and limits with the RADIUS server.

## Router bootstrap files

Upload `mt_hotspot` to `flash/mt_hotspot`, then configure:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
/ip hotspot walled-garden add dst-host=hs.portalx.win
/ip hotspot walled-garden add dst-host=www.gstatic.com
```

The Google endpoint is used only as an independent connectivity probe. Without a second allowed domain, a browser cannot reliably distinguish a broken portal from a broken upstream connection.

## Authentication requirement

The portal accepts and manages vouchers, but MikroTik remains the network enforcement point. Configure RouterOS HotSpot to authenticate against RADIUS, and add each generated voucher to the RADIUS credential store. For the current form handoff, use a certificate-backed HTTPS HotSpot login endpoint with `https`/`http-pap`; do not send PAP credentials to a plain HTTP router URL.

```routeros
/radius add service=hotspot address=RADIUS_VPN_IP secret="UNIQUE_LONG_SECRET"
/ip hotspot profile set [find name="hsprof1"] use-radius=yes radius-accounting=yes radius-interim-update=1m login-by=https,http-pap,cookie
```

The management API accepts normalized accounting events at `POST /api/accounting` using `Authorization: Bearer ACCOUNTING_KEY`. Supported JSON fields are `session_id`, `status` (`start`, `update`, or `stop`), `username`, `client_ip`, `mac`, `router_identity`, `uptime`, `bytes_in`, `bytes_out`, and `terminate_cause`. Start events are linked to the portal’s pending authorization record; updates and stops update the same session.

## Diagnostic contract

`GET /hotspot/health` returns HTTP 200, CORS permission, and `{ "ready": true }` only when the hosted app and its database are available. The local bootstrapper navigates nowhere unless all three checks succeed.

## Legacy JuanFi login migration

The recommended RouterOS v7 on-login replacement is [routeros/LoginScript_PixiePoint.rsc](routeros/LoginScript_PixiePoint.rsc). It preserves JuanFi's `duration,amount,extension,vendo` comment format while moving sales, points, devices, and idempotency into PixiePoint. See [routeros/README.md](routeros/README.md) for provisioning and security guidance.

## Unmodified JuanFi ESP compatibility

PixiePoint can operate with the existing JuanFi ESP/NodeMCU firmware while a native device platform is developed. The MikroTik bootstrap loads the portal application and stylesheet from PixiePoint into one native local document. The hosted application then communicates with the ESP from the browser, preserving the current coin acceptor and voucher protocol without an iframe, nested scrolling, or a second portal layout stored on the router.

Configure the local device allowlist in `mt_hotspot/vendo-config.js`, upload that directory to the MikroTik, and use `/hotspot/compat` through the local bootstrap page. The hosted compatibility UI supports local health, JuanFi-delimited rates, coin polling, generated and existing vouchers, extension, conversion, charging discovery/top-up, cancellation, and local MikroTik CHAP/PAP handoff. E-load stays disabled until its compressed catalog and purchase transaction pass hardware validation. See [mt_hotspot/README.md](mt_hotspot/README.md) for installation details.
