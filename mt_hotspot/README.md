# PixiePoint MikroTik HotSpot package

This directory is the MikroTik HotSpot package installed on the router.

PixiePoint keeps the customer portal, theme engine, `{{ }}` rendering, voucher validation, and access-method logic on the PixiePoint server. The MikroTik files only provide the native RouterOS HotSpot handoff and the standard HotSpot support pages.

## Install

1. Upload the contents of this directory to `flash/mt_hotspot` on the MikroTik router.
2. Configure the HotSpot profile:

```routeros
/ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
```

3. Allow the PixiePoint portal before authentication:

```routeros
/ip hotspot walled-garden add dst-host=hs.portalx.win
```

## Login flow

`login.html` follows the same native external-portal pattern as a standard MikroTik HotSpot integration:

```text
MikroTik login.html
        ↓
https://hs.portalx.win/hotspot/login
        ↓
PixiePoint resolves router/station/theme/features
        ↓
ThemeEngine renders the portal server-side
        ↓
Voucher is validated by PixiePoint
        ↓
PixiePoint submits username/password to $(link-login-only)
        ↓
MikroTik completes PAP or HTTP-CHAP authentication
```

The redirect forwards the native MikroTik variables required by PixiePoint, including router identity, server address, login URL, original destination, client IP/MAC, error state, and CHAP challenge values.

## Platform separation

The theme remains platform-neutral. MikroTik-specific values are translated into PixiePoint's portal context by the MikroTik adapter. Theme files do not need RouterOS `$(...)` variables.

The other files in this directory (`alogin.html`, `error.html`, `logout.html`, `status.html`, and related RouterOS files) remain part of the complete MikroTik HotSpot package and should be uploaded together.

## Notes

- Do not use the old hosted-embed/bootstrap flow from earlier versions of this package.
- `md5.js` remains in the package for compatibility with standard MikroTik HotSpot pages, although PixiePoint's hosted voucher flow handles its own authentication handoff.
- Keep `hs.portalx.win` reachable through the HotSpot walled garden for unauthenticated clients.
