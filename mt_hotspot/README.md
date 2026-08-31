# PixiePoint local JuanFi bridge

This directory is the small bootstrap installed on MikroTik. The complete customer interface, styling, and JuanFi workflow are downloaded from `https://hs.portalx.win` and rendered natively into the local document. There is no iframe and therefore no nested viewport or double scrollbar.

The document retains the MikroTik origin so the downloaded application can communicate with an unchanged ESP such as `http://10.0.0.2`. A top-level HTTPS page could not reliably make that HTTP private-network request. The bootstrap also computes MikroTik HTTP-CHAP locally, so the hosted site never needs direct router access.

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
- Only after a valid health response does it download the hosted stylesheet and portal application.
- If PixiePoint is reachable but the ESP is not, the hosted UI remains responsive and reports the local vendo failure separately.
- Coin checks use background AJAX and update the visible transaction without page reloads.

All customer-facing behavior stays in the hosted `juanfi-compat.js` application. The MikroTik files contain no rates, transaction rules, product catalog, account UI, sales logic, or operator interface. `vendo-config.js` contains only the locally trusted ESP addresses and feature switches, while `md5.js` performs the required local CHAP calculation.

The hosted compatibility layer currently implements coin/voucher login, legacy rate parsing, voucher extension and conversion, charging-station discovery and charging top-up. It can detect the JuanFi e-load service, but purchasing remains hidden by default and must not be enabled until the compressed product catalog and real transaction flow pass a physical-device test.

## Security notes

- Treat `vendo-config.js` as router configuration and restrict MikroTik administrative access.
- Do not put Telegram tokens, database passwords, API keys, or operator credentials in hotspot files or ESP-visible requests.
- Rotate any secrets embedded in the former JuanFi login script before deployment.
- Keep the ESP on the hotspot LAN and block management access from untrusted upstream networks.
