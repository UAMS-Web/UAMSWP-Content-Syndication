# WordPress integration tests

This suite loads the real plugin on `muplugins_loaded`, before WordPress fires
`plugins_loaded` and `init`. It uses its own Composer autoloader and PHPUnit 9.6,
not the root Pest/PHPUnit setup. Install the committed lock:

```sh
composer install --working-dir=tests/Integration
```

## Fixture and safety

The deployment's WordPress version is **unknown**. WordPress **6.8.3** is an
explicit test fixture selected to match the repository's 6.8 stubs, not a claim
about a deployed site. `wp-phpunit/wp-phpunit` has a `~6.8.0` constraint and is
locked to **6.8.3**. The isolated setup locks PHPUnit **9.6.38** and PHPUnit
Polyfills **4.0.0**; its development PHP pin is **8.4.27**.

Use a clean, disposable WordPress copy with no additional must-use plugins.
The bootstrap defaults to the surrounding WordPress installation, five levels
above `tests/Integration` (`dirname(__DIR__, 5)`), for a checkout installed at
`wp-content/plugins/<plugin>`. Otherwise set `WP_TESTS_ABSPATH` on Linux,
macOS, or Windows. On Windows, use a native absolute path or a forward-slash
path recognized by PHP. The tests require PHP's `mysqli` extension and a
MySQL-compatible server. Coverage additionally needs PCOV or Xdebug.

The installer **drops and rebuilds test tables on every process start**. This
harness refuses every `WP_TESTS_DB_NAME` except **`syndication_tests`**, and also
refuses any `wp-config.php` in the core directory or its parent, without trying
to parse its database expression. Use an unconfigured core copy, never a working
site's database or credentials. The defaults are intentionally disposable:

| Variable | Default |
| --- | --- |
| `WP_TESTS_ABSPATH` | Surrounding WordPress installation, five levels above `tests/Integration` |
| `WP_TESTS_DB_NAME` | `syndication_tests` |
| `WP_TESTS_DB_USER` | `syndication_tests` |
| `WP_TESTS_DB_PASSWORD` | `syndication_disposable` |
| `WP_TESTS_DB_HOST` | `127.0.0.1:3307` |

The database account needs privileges on `syndication_tests.*` only. These are
public fixture credentials, not production secrets. Do not expose this server
through a portal or bind it to a public interface.

## Linux orb setup

The following was verified on Linux in an orb. The database commands are not
macOS or Windows service-installation instructions. Those platforms can use an
existing local MySQL-compatible service and override `WP_TESTS_DB_HOST`.

Run initialization only for a new disposable datadir, then use the supervised
orb service to start or restart it. No background shell daemon is needed.

```sh
curl --fail --location https://wordpress.org/wordpress-6.8.3.tar.gz \
  -o /tmp/synd-wordpress-6.8.3.tar.gz
mkdir -p /tmp/synd-wordpress
tar -xzf /tmp/synd-wordpress-6.8.3.tar.gz \
  --strip-components=1 -C /tmp/synd-wordpress

mkdir -p /tmp/synd-mariadb
/usr/bin/mariadb-install-db --datadir=/tmp/synd-mariadb \
  --auth-root-authentication-method=normal --skip-test-db
amp orb service start syndication-mariadb \
  --command '/usr/sbin/mariadbd --datadir=/tmp/synd-mariadb --socket=/tmp/synd-mariadb.sock --pid-file=/tmp/synd-mariadb.pid --bind-address=127.0.0.1 --port=3307 --skip-log-bin' \
  --port 3307

mysql --socket=/tmp/synd-mariadb.sock -u root <<'SQL'
CREATE DATABASE syndication_tests;
CREATE USER 'syndication_tests'@'127.0.0.1'
  IDENTIFIED BY 'syndication_disposable';
GRANT ALL PRIVILEGES ON syndication_tests.*
  TO 'syndication_tests'@'127.0.0.1';
SQL
```

For an initialized service, use `amp orb service restart syndication-mariadb`.
Stop it with `amp orb service stop syndication-mariadb` when no longer needed.

## Run serially

Run from the repository root, with **one integration process at a time** against
this database. Single-site and multisite runs rebuild the same database and
must never overlap.

```sh
WP_TESTS_ABSPATH=/tmp/synd-wordpress \
  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml
WP_TESTS_ABSPATH=/tmp/synd-wordpress WP_MULTISITE=1 \
  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml
```

The two network-only tests explicitly skip in single-site mode. They must run
without skips in multisite mode. Add `--coverage-text` to measure this layer.
`phpunit.xml` explicitly excludes retired `includes/_unused` implementations
from coverage because the plugin does not load them. No deployed-site traffic,
real remote service, persistent object-cache backend, or Shortcake plugin is
tested. HTTP fixtures use WordPress's real `pre_http_request` boundary; local
requests use the real REST server, database and object cache.

## Pinned behavior and known defects

- The registered shortcode and frontend stylesheet hook are exercised through
  WordPress dispatch. Removed-shortcode and removed-invalidation-hook controls
  prove these checks distinguish a missing registration.
- Anonymous local REST collections exclude drafts, resolve authors, cache empty
  and populated results, and retry failed routes. Saving posts or pages changes
  the local cache generation. Custom post types currently do not invalidate it.
- Remote data is cached by processed attributes; changing `cache_bust` misses
  that cache. Local saves do not invalidate remote results. Transport failures
  and HTTP 404s retry. HTTP 500 with an invalid body and malformed successful
  JSON currently become cached empty arrays.
- Local `headlines` currently reads native REST arrays as objects and raises
  property-access warnings. The test converts the first warning into a narrowly
  asserted exception and restores the error handler; it does not suppress suite
  warnings or repair the plugin.
- Local REST dispatch currently replaces the caller's global `$post` with a
  collection result instead of restoring the caller page. A distinct caller-page
  fixture pins this leak and restores test state afterward.
- Local requests currently need pretty permalinks because the plugin hard-codes
  `/wp-json/`. With plain permalinks, native REST can retrieve the post but the
  shortcode returns empty data. Tests default to pretty permalinks and include
  an explicit plain-permalink characterization.
- The legacy `filter[category_name]` parameter currently does not filter native
  6.8.3 REST results in this clean fixture. No legacy REST compatibility plugin
  is installed.
- A current subsite is routed locally but the returned `site_id` is currently
  hard-coded to `1`. A sibling is discovered using real network tables and is
  fetched over HTTP, not by running its REST collection in a switched context.
  The plugin contains no explicit site switch; core's `get_home_url($site)`
  switches temporarily and restores it. The network test observes paired switch
  events and verifies the original blog, switch stack, user and global post.

These tests characterize unchanged source. They intentionally report rather
than fix the observed defects. None establishes the full-coverage ticket's
mutation-clean closeout bar on its own.
