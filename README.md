# Reactll Connect for WordPress

Connects a WordPress site to its [Reactll](https://reactll.com) dashboard: visits, calls, WhatsApp and
directions clicks, form leads and site health. Built and maintained by **Reactor Technology**.

## Install

1. Download the latest zip from Reactll (Sites → the site → Install), or build it with `bin/release.sh`.
2. WordPress → Plugins → Add New → Upload Plugin.
3. Settings → Reactll Connect → paste the connection token → **Connect**.

## What it does

- Loads the Reactll Connect script (served by Reactll, cookieless). Admins and editors are not counted.
- Sends each successfully sent form to Reactll: Contact Form 7, Gravity Forms, Elementor Pro, WPForms,
  Fluent Forms. Failed sends wait in a queue and are retried hourly; Reactll never doubles a lead.
- Hourly heartbeat: WordPress, PHP, theme, active plugins, pending updates, form plugins found.
- Updates itself from Reactll's channel. Each release is signed (Ed25519); the plugin refuses a package
  whose signature does not match the key built into it.

## Release

```
bin/release.sh            # build dist/reactll-connect-X.Y.Z.zip + .sig + latest.json
bin/release.sh --publish  # and upload them to Reactll
```

The signing key lives outside the repo (`~/.reactll-connect/signing.key`) and never goes into it.

## Content-Security-Policy

If the site sends a CSP header, allow Reactll Connect in it, or the browser blocks the script and its beacons:

```
script-src  … https://connect.reactll.com
connect-src … https://connect.reactll.com
```

## License

MIT © Reactor Technology
