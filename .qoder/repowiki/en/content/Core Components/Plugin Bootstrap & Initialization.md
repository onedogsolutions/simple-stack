# Plugin Bootstrap & Initialization

<cite>
**Referenced Files in This Document**   
- [wpcd.php](file://wpcd.php)
- [wp-cloud-deploy.php](file://includes/core/wp-cloud-deploy.php)
- [class-wpcd-base.php](file://includes/core/class-wpcd-base.php)
- [functions-classes.php](file://includes/core/functions-classes.php)
- [class-simplestack-rest-api.php](file://includes/core/api/class-simplestack-rest-api.php)
- [class-wpcd-rest-api-controller-base.php](file://includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-base.php)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
This document explains SimpleStack’s plugin bootstrap and initialization system. It covers how the plugin loads dependencies, registers WordPress hooks, initializes custom post types, sets up the REST API, validates environment configuration, and exposes extension points for extending the lifecycle. The main orchestrator is `WP_CLOUD_DEPLOY`, which is instantiated during the plugin’s early boot sequence.

## Project Structure
SimpleStack follows a layered structure:
- Root plugin file defines constants, global hooks, activation/deactivation logic, and the bootstrap loader.
- Core classes live under `includes/core`.
- The main application orchestrator is `WP_CLOUD_DEPLOY`.
- The SimpleStack REST layer lives under `includes/core/api` and uses its own registrar.
- A shared base class provides common functionality to core components.

```mermaid
graph TB
Root["Root Plugin File<br/>wpcd.php"] --> Loader["Bootstrap Loader<br/>WPCD_Init::required_files()"]
Loader --> CoreFiles["Core Classes & Traits<br/>Post Types, Providers, Settings"]
Loader --> RestRegistrar["REST Registrar<br/>SimpleStack_REST_API"]
Loader --> MainOrchestrator["Main Orchestrator<br/>WP_CLOUD_DEPLOY"]
MainOrchestrator --> PostTypes["Custom Post Type Registry"]
MainOrchestrator --> AdminHooks["Admin Hooks & Menu"]
MainOrchestrator --> Roles["Roles & Capabilities"]
RestRegistrar --> Controllers["REST Controllers"]
```

**Diagram sources**
- [wpcd.php:267-414](file://wpcd.php#L267-L414)
- [wp-cloud-deploy.php:81-138](file://includes/core/wp-cloud-deploy.php#L81-L138)
- [class-simplestack-rest-api.php:51-99](file://includes/core/api/class-simplestack-rest-api.php#L51-L99)

**Section sources**
- [wpcd.php:1-130](file://wpcd.php#L1-L130)
- [wpcd.php:267-414](file://wpcd.php#L267-L414)

## Core Components
- **WPCD_Init**: Bootstraps constants, schedules cron intervals, registers activation/deactivation hooks, loads required files, and triggers the main orchestrator.
- **WP_CLOUD_DEPLOY**: Main application orchestrator that sets up post types, roles/capabilities, admin hooks, menu pages, asset loading, encryption utilities, provider registry, and logging.
- **SimpleStack_REST_API**: REST namespace registrar that loads controller files and registers routes on `rest_api_init`.
- **WPCD_Base**: Shared base class providing deferred action history and formatted custom links helpers used by core classes.

**Section sources**
- [wpcd.php:19-130](file://wpcd.php#L19-L130)
- [wp-cloud-deploy.php:15-138](file://includes/core/wp-cloud-deploy.php#L15-L138)
- [class-simplestack-rest-api.php:21-99](file://includes/core/api/class-simplestack-rest-api.php#L21-L99)
- [class-wpcd-base.php:22-105](file://includes/core/class-wpcd-base.php#L22-L105)

## Architecture Overview
The bootstrap flow is staged to ensure dependencies are loaded before higher-level components run:

1. **Constants and Global Setup**: Defined early in `WPCD_Init::__construct`.
2. **Required Files Loading**: Triggered at `init` with priority `-20` via `WPCD_Init::required_files`.
3. **REST Layer Registration**: Controller files are loaded; the REST namespace is registered later on `rest_api_init`.
4. **Main Orchestrator Instantiation**: `WP_CLOUD_DEPLOY` is constructed, setting up post types, roles, and hooks.
5. **Setup Wizard Trigger**: Conditionally included if setup has not been completed.

```mermaid
sequenceDiagram
participant WP as "WordPress"
participant Init as "WPCD_Init"
participant Loader as "required_files()"
participant REST as "SimpleStack_REST_API"
participant Main as "WP_CLOUD_DEPLOY"
WP->>Init : Construct (plugin load)
Init->>WP : add_action('init', required_files, -20)
WP->>Loader : init hook (-20)
Loader->>Loader : Load required plugins, traits, core classes
Loader->>REST : load_files()
Loader->>REST : simplestack_rest_api()
Loader->>Main : new WP_CLOUD_DEPLOY()
Main->>Main : setup_post_types()
Main->>Main : load_roles_capabilities()
Main->>Main : hooks()
WP->>REST : rest_api_init
REST->>REST : register_routes()
```

**Diagram sources**
- [wpcd.php:106-130](file://wpcd.php#L106-L130)
- [wpcd.php:267-414](file://wpcd.php#L267-L414)
- [class-simplestack-rest-api.php:51-99](file://includes/core/api/class-simplestack-rest-api.php#L51-L99)
- [wp-cloud-deploy.php:81-138](file://includes/core/wp-cloud-deploy.php#L81-L138)

## Detailed Component Analysis

### WPCD_Init: Bootstrap and Lifecycle Manager
Responsibilities:
- Define plugin constants and script versioning.
- Register cron schedules.
- Hook into activation/deactivation, admin notices, language loading, shutdown checks, and footer text.
- Load required third-party plugins and internal classes.
- Instantiate the main orchestrator.

Key behaviors:
- Constants are defined once and include both legacy lowercase and uppercase variants.
- Cron schedules include every minute, two minutes, and fifteen minutes.
- Activation runs component-specific activation routines and flushes rewrite rules.
- Deactivation runs corresponding cleanup routines.
- Required files are loaded in a strict order to satisfy dependencies.
- The REST API registrar is loaded after app classes so controllers can access the app engine and provider registry.

Extension points:
- Add new required files inside `required_files()` in the correct dependency order.
- Use existing conditional includes for optional features (e.g., email notifications, data sync).

**Section sources**
- [wpcd.php:24-130](file://wpcd.php#L24-L130)
- [wpcd.php:166-235](file://wpcd.php#L166-L235)
- [wpcd.php:267-414](file://wpcd.php#L267-L414)

### WP_CLOUD_DEPLOY: Main Application Orchestrator
Responsibilities:
- Provide a global instance reference (`$GLOBALS['WP_CLOUD_DEPLOY']`).
- Set up custom post types through lazy instantiation.
- Load roles and capabilities.
- Register admin hooks for menu highlighting, legacy CPT redirection, submenu ordering, error/notification logging, and script/style enqueues.
- Manage the SimpleStack React admin app container and Vite manifest-based assets.
- Provide encryption utilities and sensitive value redaction.
- Manage cloud provider discovery and virtual provider support.

Initialization steps:
- Constructor sets global reference, then calls post type setup, role loading, and hook registration.
- `hooks()` registers admin UI behavior and logging handlers.
- `setup_post_types()` lazily instantiates post type classes and stores them in `WPCD()->classes`.
- `load_roles_capabilities()` ensures the roles/capabilities class is available.

Provider management:
- `get_cloud_providers()` returns a filtered list of providers.
- `disable_digital_ocean_provider_by_default()` hides DigitalOcean unless explicitly enabled.
- `get_active_cloud_providers()` filters out providers without valid credentials.

Logging and security:
- `log_error()` writes to debug log and optionally to database, redacting sensitive values.
- Encryption/decryption methods support v1 and v2 algorithms with key fallbacks.

**Section sources**
- [wp-cloud-deploy.php:81-138](file://includes/core/wp-cloud-deploy.php#L81-L138)
- [wp-cloud-deploy.php:678-726](file://includes/core/wp-cloud-deploy.php#L678-L726)
- [wp-cloud-deploy.php:1460-1464](file://includes/core/wp-cloud-deploy.php#L1460-L1464)
- [wp-cloud-deploy.php:1194-1213](file://includes/core/wp-cloud-deploy.php#L1194-L1213)
- [wp-cloud-deploy.php:971-1141](file://includes/core/wp-cloud-deploy.php#L971-L1141)
- [wp-cloud-deploy.php:1238-1331](file://includes/core/wp-cloud-deploy.php#L1238-L1331)

### SimpleStack_REST_API: REST Namespace Registrar
Responsibilities:
- Load controller files during bootstrap without instantiating them prematurely.
- Register the `simple-stack/v1` namespace on `rest_api_init`.
- Instantiate each controller and call its `register_routes()` method.
- Expose controller instances for diagnostics and tests.

Controller list:
- Servers, Sites, Logs, Tabs, Providers, Security.

Base controller contract:
- Each controller extends a base class that centralizes route registration and permission handling.
- Permission callback requires admin access or delegates exception handling.

```mermaid
classDiagram
class SimpleStack_REST_API {
+controllers : array
+instances : array
+__construct()
+load_files() void
+register_routes() void
+get_controllers() array
+get_namespace() string
}
class SimpleStack_REST_Controller_Base {
+NAMESPACE : string
+register_routes() void
#register_route(route, method, action) bool
#register_get_route(route, action) bool
}
class SimpleStack_REST_Servers
class SimpleStack_REST_Sites
class SimpleStack_REST_Logs
class SimpleStack_REST_Tabs
class SimpleStack_REST_Providers
class SimpleStack_REST_Security
SimpleStack_REST_API --> SimpleStack_REST_Controller_Base : "instantiates"
SimpleStack_REST_Servers --|> SimpleStack_REST_Controller_Base
SimpleStack_REST_Sites --|> SimpleStack_REST_Controller_Base
SimpleStack_REST_Logs --|> SimpleStack_REST_Controller_Base
SimpleStack_REST_Tabs --|> SimpleStack_REST_Controller_Base
SimpleStack_REST_Providers --|> SimpleStack_REST_Controller_Base
SimpleStack_REST_Security --|> SimpleStack_REST_Controller_Base
```

**Diagram sources**
- [class-simplestack-rest-api.php:21-119](file://includes/core/api/class-simplestack-rest-api.php#L21-L119)
- [class-wpcd-rest-api-controller-base.php:63-104](file://includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-base.php#L63-L104)

**Section sources**
- [class-simplestack-rest-api.php:51-99](file://includes/core/api/class-simplestack-rest-api.php#L51-L99)
- [class-wpcd-rest-api-controller-base.php:63-104](file://includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-base.php#L63-L104)

### WPCD_Base: Shared Base Class
Responsibilities:
- Provide deferred action history tracking for server/app records.
- Format and render custom links stored in post meta.

Usage:
- Core classes extend this base to share these utilities.

Complexity notes:
- Deferred action history maintains a bounded list (last 15 entries), trimming older entries and adding timestamps as keys.

**Section sources**
- [class-wpcd-base.php:22-105](file://includes/core/class-wpcd-base.php#L22-L105)

### Environment Detection, Configuration Validation, and Error Handling
Environment checks performed during bootstrap:
- PHP version validation with admin notice guidance.
- Localhost detection warning.
- Encryption key presence and default placeholder detection.
- Permalink structure validation.
- Readability check for script execution folder.
- Critical cron activity monitoring with transient-backed throttling and email alerts.

Error handling patterns:
- Admin notices inform operators about misconfiguration.
- Shutdown hook sends emails when critical crons are absent.
- Logging utility redacts sensitive values and supports multiple output channels.

Configuration validation highlights:
- Early options and transients control notice frequency and email suppression.
- Options like `permalink_structure` and `WPCD_ENCRYPTION_KEY` are validated.

**Section sources**
- [wpcd.php:452-543](file://wpcd.php#L452-L543)
- [wpcd.php:550-623](file://wpcd.php#L550-L623)
- [wpcd.php:630-668](file://wpcd.php#L630-L668)
- [wp-cloud-deploy.php:1194-1213](file://includes/core/wp-cloud-deploy.php#L1194-L1213)

### Extending the Initialization Process
Recommended approaches:
- Add new required files in `WPCD_Init::required_files()` respecting dependency order.
- Register new REST controllers by adding their class name to `SimpleStack_REST_API::$controllers` and ensuring they extend the base controller.
- Extend provider lists via the `wpcd_get_cloud_providers` filter.
- Hook into admin menus using `admin_menu` and reorder submenus via `wpcd_main_menu_submenus_order`.
- Use `WPCD()->classes` to lazily instantiate additional services during `init`.

Examples:
- Adding a new REST endpoint:
  - Create a controller class extending `SimpleStack_REST_Controller_Base`.
  - Implement `register_routes()` to define endpoints.
  - Add the controller class name to the registrar’s controller list.
- Adding a new initialization step:
  - Use `add_action('init', 'your_init_function', -10)` to run early.
  - Ensure dependencies are loaded before your function executes.

**Section sources**
- [wpcd.php:267-414](file://wpcd.php#L267-L414)
- [class-simplestack-rest-api.php:30-37](file://includes/core/api/class-simplestack-rest-api.php#L30-L37)
- [wp-cloud-deploy.php:166-205](file://includes/core/wp-cloud-deploy.php#L166-L205)
- [functions-classes.php:29-50](file://includes/core/functions-classes.php#L29-L50)

## Dependency Analysis
The bootstrap depends on a strict ordering:
- Third-party plugins and Meta Box extensions must be loaded before core classes.
- Core traits and functions must be present before post type classes.
- Provider classes must be loaded before the REST layer accesses the provider registry.
- The main orchestrator must be instantiated after all foundational classes are available.

```mermaid
graph LR
ThirdParty["Third-Party Plugins<br/>Meta Box Extensions"] --> CoreTraits["Core Traits"]
CoreTraits --> CoreClasses["Core Classes<br/>Posts, Providers, Settings"]
CoreClasses --> RestControllers["REST Controllers"]
CoreClasses --> MainOrchestrator["WP_CLOUD_DEPLOY"]
RestControllers --> RESTNamespace["simple-stack/v1"]
```

**Diagram sources**
- [wpcd.php:267-393](file://wpcd.php#L267-L393)
- [class-simplestack-rest-api.php:63-75](file://includes/core/api/class-simplestack-rest-api.php#L63-L75)

**Section sources**
- [wpcd.php:267-393](file://wpcd.php#L267-L393)

## Performance Considerations
- Lazy instantiation: Post type classes and settings are created only when needed, reducing startup overhead.
- Conditional loading: Optional features (email notifications, data sync) are loaded based on configuration flags.
- Manifest caching: The Vite build manifest is read once per request and cached in memory.
- Asset scoping: Scripts/styles are enqueued only on relevant screens to avoid unnecessary payload.
- Transient throttling: Cron checks and notices use transients to limit repeated computations and I/O.

Best practices:
- Avoid heavy operations in constructors; defer to hooks.
- Use early options for lightweight configuration reads.
- Keep REST controller instantiation minimal until routes are requested.
- Cache expensive lookups within the request scope.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common issues and resolutions:
- Missing encryption key: Define `WPCD_ENCRYPTION_KEY` in `wp-config.php`; otherwise, a temporary key is generated and stored.
- Default permalink structure: Change permalinks to non-default to enable callbacks.
- Script readability: Ensure the scripts folder allows `.txt` files to be readable by web servers.
- Cron failures: Verify scheduled events and consider enabling better cron methods; suppress emails via early option if desired.
- Missing admin bundle: Rebuild assets with Node tools; the admin page shows an operator-facing message when the manifest is absent.

Diagnostic tools:
- Admin notices provide contextual warnings.
- Debug logging captures errors with sensitive value redaction.
- REST namespace availability can be verified via the help screen.

**Section sources**
- [wpcd.php:452-543](file://wpcd.php#L452-L543)
- [wp-cloud-deploy.php:324-336](file://includes/core/wp-cloud-deploy.php#L324-L336)
- [wp-cloud-deploy.php:1194-1213](file://includes/core/wp-cloud-deploy.php#L1194-L1213)

## Conclusion
SimpleStack’s bootstrap system is structured around a clear separation of concerns:
- `WPCD_Init` manages constants, hooks, and dependency loading.
- `WP_CLOUD_DEPLOY` orchestrates post types, roles, admin UI, and core services.
- `SimpleStack_REST_API` provides a secure, scoped REST interface for the React admin client.
- `WPCD_Base` offers shared utilities for core classes.

Extensibility is supported through filters, actions, and lazy instantiation patterns. Operators should follow the documented extension points to maintain compatibility and performance.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### Bootstrap Flow Summary
- Constants and globals are defined early.
- Required files are loaded at `init` with high priority.
- REST controllers are loaded but routed on `rest_api_init`.
- Main orchestrator sets up post types, roles, and hooks.
- Setup wizard is conditionally included.

```mermaid
flowchart TD
Start(["Plugin Load"]) --> Constants["Define Constants"]
Constants --> Hooks["Register Hooks"]
Hooks --> InitHook["init (-20)"]
InitHook --> LoadFiles["Load Required Files"]
LoadFiles --> RESTLoad["REST load_files()"]
RESTLoad --> RESTInstance["simplestack_rest_api()"]
RESTInstance --> MainConstruct["new WP_CLOUD_DEPLOY()"]
MainConstruct --> PostTypes["setup_post_types()"]
PostTypes --> Roles["load_roles_capabilities()"]
Roles --> AdminHooks["hooks()"]
AdminHooks --> End(["Ready"])
```

**Diagram sources**
- [wpcd.php:24-130](file://wpcd.php#L24-L130)
- [wpcd.php:267-414](file://wpcd.php#L267-L414)
- [wp-cloud-deploy.php:81-138](file://includes/core/wp-cloud-deploy.php#L81-L138)