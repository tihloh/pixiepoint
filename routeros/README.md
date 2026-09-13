# PixiePoint RouterOS login integration

`LoginScript_PixiePoint.rsc` is the RouterOS v7 HotSpot on-login integration for PixiePoint.

It keeps router-side responsibility small:

- Enforces expiry for local HotSpot vouchers using a minimal-policy scheduler.
- Extends an existing expiry exactly once.
- Posts an authenticated, structured login/sale event to PixiePoint.
- Leaves a durable `pp-pending` retry marker when PixiePoint is unavailable.
- Never reverses an already successful customer login when the hosted server is down.
- Sends no third-party credentials and makes no unrelated API calls.
- Skips expiry scheduling for RADIUS users because RADIUS accounting is authoritative.

## Voucher metadata

Local HotSpot user comments use:

```text
duration,amount_pesos,is_extension
```

Example:

```text
1h,10,0
```

Before contacting the server, the script replaces that comment with a retry-safe record:

```text
pp-pending,event_key,duration,amount_pesos,is_extension
```

The record is cleared only after PixiePoint acknowledges the event. Repeated delivery is safe because the server enforces a unique event key.

## Installation

1. Register the router using its exact `/system identity` value.
2. Copy that router's API key from PixiePoint.
3. Replace `REPLACE_WITH_THIS_ROUTER_API_KEY` in the script.
4. Paste the script into the relevant HotSpot user profile's `on-login` configuration, or save it as a system script called by `on-login`.
5. Import a trusted CA chain into RouterOS so `check-certificate=yes` can validate `hs.portalx.win`.

Do not reuse an API key across routers or place global platform secrets in RouterOS scripts.
