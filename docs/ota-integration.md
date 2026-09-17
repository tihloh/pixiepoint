# OTA integration

## Ownership

Vendo Gateway owns release discovery, validated target/hardware matching, version
comparison, firmware policy persistence, and OTA command creation. PixiePoint
authorizes operator actions, invokes those package APIs, and renders their results.
The private vendogate-firmware repository builds firmware; the public
vendogate-firmware-releases repository supplies the manifest and binary artifacts.

## Behavior

Opening Manage Vendo checks the release catalog using the device's stored identity.
The gateway shares a five-minute release cache across page loads. Check now forces
a fresh catalog request and updates the page immediately. Neither operation contacts
the ESP. Update stays disabled unless a newer compatible image has a valid checksum,
size, and URL.

Update queues an exact firmware artifact for the ESP to collect when it next polls.
The ESP waits until idle, validates the image, installs it, and acknowledges before
rebooting. Offline/busy devices have a 24-hour command window.

ESP automatic checks and automatic installation are separate settings. Automatic
installation defaults off. The device check interval is configurable from 1 to 24
hours; automatic installation requires automatic checks. PHP page-load/manual
release discovery is independent of those device settings.

## Stable dependency

PixiePoint requires vendo-gateway ^1.3 and composer.lock pins v1.3.1. Install
dependencies with composer install.

Gateway review: https://github.com/tihloh/vendo-gateway/pull/5
Firmware review: https://github.com/tihloh/vendogate-firmware/pull/2

Gateway v1.3.1 and firmware v1.2.3 are published. Deploy the gateway update
before directing devices to install firmware v1.2.3.

## Validation

- Gateway PHPUnit: 23 tests, 70 assertions.
- Live public release metadata resolved v1.2.3 and its verified ESP32 image.
- PlatformIO builds: esp32 and esp8266 successful.
- Frontend: node tests/vendo-firmware-ui.cjs exercises available/current/unknown
  manual-check responses without ESP contact or a page reload.
- PHP syntax checked for the PixiePoint controller and view.
- A physical ESP OTA run still needs to be validated after the new firmware release.

The Windows PHP CLI used for package tests is 8.2. The full PixiePoint application
requires PHP 8.3+ and was not booted under that local CLI.
