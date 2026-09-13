# PixiePoint MikroTik hotspot bootstrap

This directory contains the small local bootstrap installed on MikroTik. The customer portal, styling, and application logic are hosted by PixiePoint and rendered into the local HotSpot document. There is no iframe and no second nested portal viewport.

## Install

1. Upload the files in this directory to `flash/mt_hotspot`.
2. Configure RouterOS:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
/ip hotspot walled-garden add dst-host=hs.portalx.win
```

## Login flow

`login.html` collects the RouterOS HotSpot variables and places them in the local page URL. It then loads:

```text
https://hs.portalx.win/assets/hotspot-embed.js
```

The embed script requests `/hotspot/compat`, receives the server-rendered portal theme, and writes it into the current local document with `document.open()`, `document.write()`, and `document.close()`.

The rendered portal then uses `hotspot-login.js` for voucher login. When RouterOS provides CHAP values, the password response is calculated in the local document. Otherwise the configured PAP form is submitted to the RouterOS login URL.

## Status flow

`status.html` passes the current RouterOS session values to the same hosted portal endpoint. The rendered connected-state page uses `session-portal.js` to display remaining time, traffic counters, and logout actions.

## Responsibilities

The MikroTik bootstrap contains only the RouterOS-facing variables and hosted-portal loader. Portal themes, voucher UI, member/QR/trial/points UI, device information, and session presentation remain hosted in PixiePoint.

Keep `hs.portalx.win` in the HotSpot walled garden so unauthenticated clients can load the portal assets.
