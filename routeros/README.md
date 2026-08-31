# PixiePoint RouterOS login integration

`LoginScript_PixiePoint.rsc` replaces the legacy JuanFi centralized login script on RouterOS v7.

It keeps the router-side responsibility deliberately small:

- Enforces expiry for legacy local HotSpot vouchers using a minimal-policy scheduler.
- Extends an existing expiry exactly once.
- Posts an authenticated, structured login/sale event to PixiePoint.
- Leaves a durable `pp-pending` retry marker when PixiePoint is unavailable.
- Never reverses an already successful customer login when the hosted server is down.
- Sends no Telegram credentials and makes no third-party API calls.
- Skips expiry scheduling for RADIUS users because RADIUS accounting is authoritative.

## Voucher metadata

For JuanFi compatibility, local HotSpot user comments use:

```text
duration,amount_pesos,is_extension,vendo_name
```

Example:

```text
1h,10,0,Main Vendo
```

Before contacting the server, the script replaces that comment with a `pp-pending` record. The vendo name is Base64-encoded in this internal record so commas and non-ASCII names cannot corrupt retries. It clears the record only after PixiePoint acknowledges the event. Repeated delivery is safe because the server enforces a unique event key.

## Installation

1. Register the router using its exact `/system identity` value.
2. Copy that router's API key from PixiePoint.
3. Replace `REPLACE_WITH_THIS_ROUTER_API_KEY` in the script.
4. Paste the script into the relevant HotSpot user profile's `on-login` configuration, or save it as a system script called by `on-login`.
5. Import a trusted CA chain into RouterOS so `check-certificate=yes` can validate `hs.portalx.win`.

Do not reuse an API key across routers. Do not place Telegram bot tokens or global platform secrets in RouterOS scripts.
