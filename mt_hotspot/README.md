# PixiePoint MikroTik HotSpot package

Upload this directory's contents to the router's configured HotSpot HTML directory
(for example flash/mt_hotspot). Deploy the matching PixiePoint application changes
as well; the updated login.html is required to preserve binary CHAP challenges.

## Architecture and login

The browser stays on the MikroTik portal. login.html, status.html, and logout.html
fetch the selected rendered theme from https://hs.portalx.win and display it locally.
That host supplies HTML and assets; it is not the login or reconnect destination.
The theme engine remains responsible for presentation. MikroTikAdapter supplies
router context and native authentication independently of the selected theme.

- MikroTik user login posts the entered credentials directly to link-login-only,
  using MD5(chap-id + password + chap-challenge) when CHAP is present, or PAP otherwise.
- Voucher Connect uses the same native submission, with the matched station's
  password mode: blank password or the voucher itself. Optional device-history
  recording never blocks authentication.
- Free trial uses MikroTik's T-MAC login URL only when the router reports trial=yes
  and the PixiePoint trial feature is enabled. Trial limits are enforced by RouterOS.
- Login destinations follow link-orig; no hosted portal destination is substituted.
- Reconnect after logout loads the router login page to obtain a fresh CHAP challenge.

CHAP bytes are transported as ASCII octal escapes, then rendered as JavaScript
hexadecimal escapes. This avoids UTF-8 expansion and loss of null/whitespace bytes.

## Router setup

    /ip hotspot profile set [find name="hsprof1"] html-directory="flash/mt_hotspot"
    /ip hotspot walled-garden add dst-host=hs.portalx.win
    /ip hotspot walled-garden add dst-host=cdn.jsdelivr.net

Allow these HTTPS resources before authentication, including any additional assets
used by a custom theme. Enable trial on the RouterOS HotSpot profile if offering
free trial. Set each station's password mode to match its router voucher accounts.
All login forms come from PixiePoint. The router pages contain only the resource
loader, loading status, and retry control; there is no local authentication fallback.

## Validation

Run node tests/mikrotik-login.cjs from the repository with PHP and Node available.
The suite verifies all bundled theme bootstraps, CHAP/PAP account and voucher login,
trial destination encoding, binary challenge transport, fresh request isolation,
and status/logout context. A real router test is still required for deployment,
walled-garden access, account validity, and RouterOS trial eligibility.


## Portal transport diagnostics

The HTTPS endpoint must serve the portal directly with Access-Control-Allow-Origin:
* (or the allowed router origin). Do not redirect it to HTTP. Redirects are rejected
by the loader to prevent forwarding router context to an insecure destination.
A successful top-level website visit does not prove that the browser can fetch it
cross-origin from the router page.

On 2026-09-16, the public HTTPS login endpoint returned a 301 redirect to HTTP
without a CORS header; the HTTP endpoint returned 200 with CORS enabled.
Correct the HTTPS downgrade rule at the deployed proxy before testing this loader.

A loading failure reports the request/response/render stage in the browser console.
HTTP errors show their status. Network/CORS/redirect failures ask to check browser
network errors rather than claiming that the PixiePoint service is unavailable.
