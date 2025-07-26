# CHANGELOG.md

## 1.0.2

* 📦 NEW: Added caching for HPOS compatibility status with refresh option.
* 📦 NEW: Added detection for HPOS compatibility declarations in plugins.
* 📦 NEW: Added display of HPOS compatibility status at the top of results.
* 👌 IMPROVE: Updated CSV export to include compatibility status.
* 👌 IMPROVE: Changed plugin-update-checker to be a Composer dependency.
* 👌 IMPROVE: Added package.json with npm scripts for frontend asset building.
* 🔄 CHANGE: Refocused scanner on SQL queries referencing posts and postmeta tables.
* 👌 IMPROVE: Added detection for various SQL query patterns targeting wp_posts and wp_postmeta.
* 👌 IMPROVE: Enhanced false positive detection for non-order related queries.
* 👌 IMPROVE: Enhanced scanning with reduced false positives.
* 👌 IMPROVE: Added code snippets and line numbers to scan results.
* 👌 IMPROVE: Improved UI with expandable code sections.
* 🔄 CHANGE: Removed WPCom Check dependency and implementation.

## 1.0.1

* [📦 NEW: Added WPCom Check to restrict plugin usage on wordpress.org](https://github.com/dustinparker/hpos-compatibility-scanner/commit/b5ed5b31511416f7d0918b9c11517b24fdb40202)
* [📦 NEW: Added Spanish translation](https://github.com/dustinparker/hpos-compatibility-scanner/commit/747b3100307720505ae237a99526bdd31523672c)
* [📦 NEW: Added French translation](https://github.com/dustinparker/hpos-compatibility-scanner/commit/312b61fcdb3c9e365b83754c7da0bd280bdabf01)
* [👌 IMPROVE: Updated text strings for localization](https://github.com/dustinparker/hpos-compatibility-scanner/commit/e4cd69b2fbe5a0c94c7a6ea3bc2d52bbf2ae44f2)

## 1.0.0

- initial release