The suite is bootstrapped by `tests/phpunit.php`, which locates the WordPress test library (`wp-phpunit/wp-phpunit`), loads `wp-tests-config.php`, registers the plugin as an mu-plugin via a `muplugins_loaded` filter (so it stays out of `active_plugins`), and enables legacy `simple-stack/v1` routes by setting `wordpress_app_rest_api_enable` before requiring the WP test bootstrap.

Two parallel test locations exist:
- `tests/data/` holds test doubles (e.g. `SSTK_Cloud_Provider_Test` extending `CLOUD_PROVIDER_API`) that replace real cloud provider calls during tests.
- `tests/phpunit/rest-api/` contains one PHPUnit class per REST controller (`ControllerServersTest`, `ControllerSitesTest`, `ControllerTasksTest`, `ControllerBaseTest`), each extending `WP_UnitTestCase`, resolving its controller through `SSTK_WORDPRESS_APP()->get_rest_controller(...)`, and dispatching requests against `rest_get_server()`.

Dependency direction is one-way: tests depend on the live plugin code loaded via the mu-plugin bootstrap; they do not expose any public API themselves.