=== Reactll Connect ===
Contributors: reactortechnology
Tags: analytics, leads, forms, site health
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.5
License: MIT

Connects this site to its Reactll dashboard: visits, calls, WhatsApp and directions clicks, form leads and site health.

== Description ==

Reactll Connect is built and maintained by Reactor Technology for the sites it builds and looks after.

* Visits and where they came from — cookieless, no consent banner needed for it.
* Calls, emails, WhatsApp and directions clicks.
* Every form lead (Contact Form 7, Gravity Forms, Elementor Pro, WPForms, Fluent Forms), sent only after the form accepted it.
* An hourly health report: WordPress and PHP versions, plugins and the updates waiting.

Updates come from Reactll's own channel and are installed only when their signature checks out.

== Changelog ==

= 1.1.5 =
* First release delivered through the heartbeat self-update.

= 1.1.4 =
* Self-update installs the release directly and keeps the plugin active.

= 1.1.3 =
* Updates itself as soon as Reactll says a newer release exists — no waiting for WordPress's own check, which heavily cached sites rarely run.
* Reports the new version to Reactll right after updating.

= 1.1.2 =
* Clears page caches once whenever a new version first runs — whichever way it was updated.

= 1.1.1 =
* Clears page caches (WP Fastest Cache, W3 Total Cache, WP Rocket, LiteSpeed, WP Super Cache, Autoptimize, SiteGround, Hummingbird, Cache Enabler, Breeze, Comet) on connect, activation and update — cached pages never miss the script.
* The page shows this minute's numbers, not the last hourly check's.

= 1.1.0 =
* Its own Reactll menu and a redesigned page: connection status, this week's visits, form leads and calls.
* "Reactll — this week" on the WordPress dashboard.

= 1.0.0 =
* First release.
