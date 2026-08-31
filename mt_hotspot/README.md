# PixiePoint local JuanFi bridge

This directory is the small trusted component installed on MikroTik. The customer interface remains hosted at `https://hs.portalx.win`; the local page embeds it and relays only approved requests to the unchanged JuanFi ESP/NodeMCU on the customer LAN.

This is necessary because the hosted server cannot route to addresses such as `10.0.0.2`, and an HTTPS page cannot reliably call an HTTP device directly. The browser can reach both, so the local MikroTik origin acts as the bridge. It also computes MikroTik HTTP-CHAP locally, so the hosted site never needs direct router access.

## Install

1. Edit `vendo-config.js`. Set the hosted origin and list every allowed local vendo. This file is the local security allowlist.
2. Upload all files in this directory to `flash/mt_hotspot`.
3. Configure RouterOS:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
/ip hotspot walled-garden add dst-host=hs.portalx.win
```

Use `passwordMode: "blank"` for standard JuanFi voucher users whose password is empty, or `passwordMode: "voucher"` when username and password are the same.

## Runtime behavior

- `login.html` never navigates while checking availability.
- It polls `/hotspot/health` asynchronously and shows a persistent local error screen if DNS, internet, TLS, CORS, the PHP app, or its database is unavailable.
- Only after a valid health response does it embed `/hotspot/compat`.
- If PixiePoint is reachable but the ESP is not, the hosted UI remains responsive and reports the local vendo failure separately.
- Coin checks use background AJAX and update the visible transaction without page reloads.

The bridge permits only the known JuanFi routes declared in `bridge.js`. All customer-facing behavior stays in the hosted `/hotspot/compat` application. The bridge contains no rates, transaction rules, product catalog, account UI, sales logic, or operator interface.

The hosted compatibility layer currently implements coin/voucher login, legacy rate parsing, voucher extension and conversion, charging-station discovery and charging top-up. It can detect the JuanFi e-load service, but purchasing remains hidden by default and must not be enabled until the compressed product catalog and real transaction flow pass a physical-device test.

## Security notes

- Treat `vendo-config.js` as router configuration and restrict MikroTik administrative access.
- Do not put Telegram tokens, database passwords, API keys, or operator credentials in hotspot files or ESP-visible requests.
- Rotate any secrets embedded in the former JuanFi login script before deployment.
- Keep the ESP on the hotspot LAN and block management access from untrusted upstream networks.
