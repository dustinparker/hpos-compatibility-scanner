# Guidance for agents and developers

This plugin scans other plugins for HPOS compatibility problems. The test suite runs against a local WordPress site started with wp-env.

## Installing dependencies

Run `composer install` in the repository root and `composer install --working-dir=tools/infection` for Infection. The `vendor/roots/wordpress-no-content` folder is the test copy of WordPress, so do not edit it. A class added under `src/` needs `composer dump-autoload` before the suite or the site can load it, because the Jetpack classmap is generated and is not rebuilt by editing files.

## Starting and stopping the site

Run `npm run env:start` to start the site and `npm run env:stop` to stop it. The site is on port 7200 and the tests site is on port 7201. The login values are in the wp-env documentation: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/

The fixture plugins are mapped into the site on port 7200 only, under `env.development.mappings` in `.wp-env.json`. Keep every mapping there. A root-level mapping or one under `env.tests` also mounts into the tests site, and Docker then creates root-owned placeholder files for the single-file fixtures that make the next reconfiguring `wp-env start` fail with `EACCES`. A test checks this.

`npm run env:destroy` and `wp-env clean` delete the containers and the database. Both are the owner's steps, so do not run them.

## Running the suite

Run `php7.4 vendor/bin/phpunit` and `php8.2 vendor/bin/phpunit`. `composer test` runs the suite on the default PHP version. The site must be running, because the tests use its database.

The database connection comes from five variables, each with a fallback that matches the wp-env tests site:

- `WP_TESTS_DB_NAME`, default `hpos_scanner_tests`
- `WP_TESTS_DB_USER`, default is the wp-env default
- `WP_TESTS_DB_PASSWORD`, default is the wp-env default
- `WP_TESTS_DB_HOST`, default `127.0.0.1`
- `WP_TESTS_DB_PORT`, default `13507`

A second checkout that runs at the same time must set `WP_TESTS_DB_NAME` to its own name, because the suite drops and recreates its tables. The temporary plugins folder in `/tmp` already carries a hash of the checkout path, so checkouts do not share it.

## Expected responses

The AJAX tests compare each response with a stored JSON file. There are two sets: `tests/expected/php-81-plus` and `tests/expected/php-pre-81`. The suite picks one by PHP version.

Regenerate a set by running the suite with `HPOS_SCANNER_UPDATE_EXPECTED=1` on the matching PHP version:

- `HPOS_SCANNER_UPDATE_EXPECTED=1 php8.2 vendor/bin/phpunit` for `php-81-plus`
- `HPOS_SCANNER_UPDATE_EXPECTED=1 php7.4 vendor/bin/phpunit` for `php-pre-81`

Read the regenerated diff in review. A changed expected file means the scanner output changed.

## Infection

Run Infection on the changed lines with:

`php8.2 tools/infection/vendor/bin/infection --threads=4 --git-diff-lines --git-diff-base=<base commit> --with-uncovered --show-mutations=max --initial-tests-php-options=-dpcov.directory=src`

Run the full pass over the scanner and compatibility classes with:

`php8.2 tools/infection/vendor/bin/infection --threads=4 --filter=src/scanner/class-scanner.php,src/scanner/class-directory-scanner.php,src/compatibility/class-compatibility.php --with-uncovered --show-mutations=max --initial-tests-php-options=-dpcov.directory=src`

Kill each surviving mutant with a test, or list it in `infection.json5` with the reason it cannot be killed.

The entries in `infection.json5` are keyed by line number. When a change moves lines in `Scanner`, `Directory_Scanner` or `Compatibility`, check every entry for the moved method, because an entry that no longer matches its line silently ignores whatever mutant now sits on that number.

The five permission tests skip themselves when `file_exists()` reports a probe folder with mode 0000 as missing, which is what Infection's stream wrapper does. After upgrading Infection, run one full pass and check that those tests still skip inside mutant processes.

## Switching the WooCommerce version

The site uses the latest WooCommerce by default. To pin a version, create `.wp-env.override.json` with a full `plugins` array:

```json
{
	"plugins": [
		"https://downloads.wordpress.org/plugin/woocommerce.<version>.zip",
		"."
	]
}
```

Then run `wp-env start --update`. The file is git-ignored. Delete it to return to the latest version.

## Checking the scanner in the browser

1. Open http://localhost:7200/wp-admin/ and log in.
2. On the Plugins screen, confirm this plugin and WooCommerce are active and the fixture plugins are inactive.
3. Open HPOS Scanner.
4. Select a fixture such as `Fixture: search terms` and run the scan.
5. Compare the findings with `tests/expected/php-81-plus/scan-search-terms.json`.

## Standards

Run `composer wpcs`, `composer phpstan` and `composer validate`. There is no `phpcs.xml`, because the ruleset is on the command line in `composer.json`. The fixture plugins are excluded from the standards check, since they hold the patterns the scanner has to find.
