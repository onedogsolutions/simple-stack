# Provider Abstraction Layer

<cite>
**Referenced Files in This Document**
- [class-cloud-provider-api.php](file://includes/core/providers/class-cloud-provider-api.php)
- [class-upcloud.php](file://includes/core/providers/class-upcloud.php)
- [class-vultr-v2.php](file://includes/core/providers/class-vultr-v2.php)
- [class-sstk-posts-cloud-provider.php](file://includes/core/class-sstk-posts-cloud-provider.php)
- [simple-stack.php](file://includes/core/simple-stack.php)
- [class-sstk-settings.php](file://includes/core/class-sstk-settings.php)
- [class-sstk-cloud-provider-test.php](file://tests/data/class-sstk-cloud-provider-test.php)
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

## Introduction
This document explains Simple Stack’s cloud provider abstraction layer. It covers the base provider interface, common instance lifecycle methods, authentication patterns, error handling, provider registration and discovery, and how to implement custom providers. It also documents secure credential handling, rate-limiting considerations, testing strategies, and the benefits of abstraction for supporting multiple cloud services and migrating between them.

The abstraction centers on a shared base class that defines the provider contract, concrete implementations for UpCloud and Vultr, and WordPress hooks that register virtual providers and expose provider settings.

## Project Structure
The provider system lives under `includes/core/providers` and is integrated with WordPress post types and settings:

- Base provider contract and shared utilities: `class-cloud-provider-api.php`
- Provider implementations:
  - UpCloud implementation: `class-upcloud.php`
  - Vultr v2 implementation: `class-vultr-v2.php`
- Virtual provider registration and UI: `class-sstk-posts-cloud-provider.php`
- Global provider registry and filters: `simple-stack.php`
- Settings screen integration: `class-sstk-settings.php`
- Test double for providers: `class-sstk-cloud-provider-test.php`

```mermaid
graph TB
Base["CLOUD_PROVIDER_API<br/>Base contract"] --> Upcloud["CLOUD_PROVIDER_API_Upcloud"]
Base --> Vultr["CLOUD_PROVIDER_API_VultrV2"]
WP_Posts["SSTK_POSTS_CLOUD_PROVIDER<br/>Virtual provider CPT"] --> Registry["sstk_get_cloud_providers filter"]
Registry --> Providers["Provider instances"]
```

**Diagram sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-upcloud.php:33-128](file://includes/core/providers/class-upcloud.php#L33-L128)
- [class-vultr-v2.php:30-127](file://includes/core/providers/class-vultr-v2.php#L30-L127)
- [class-sstk-posts-cloud-provider.php:588-616](file://includes/core/class-sstk-posts-cloud-provider.php#L588-L616)
- [simple-stack.php:1330-1364](file://includes/core/simple-stack.php#L1330-L1364)

**Section sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-sstk-posts-cloud-provider.php:588-616](file://includes/core/class-sstk-posts-cloud-provider.php#L588-L616)
- [simple-stack.php:1330-1364](file://includes/core/simple-stack.php#L1330-L1364)

## Core Components
- Base provider class: Defines the provider contract, feature flags, credential helpers, cache key management, state normalization, and shared HTTP request behavior.
- Concrete providers: Implement provider-specific API calls, authentication, and response mapping while adhering to the shared contract.
- Virtual provider registration: Adds user-defined provider aliases (for example, separate Vultr or UpCloud accounts) backed by real provider classes.
- Provider registry: Exposes all available providers via a filter so other plugins or white-label builds can extend or modify the list.

Key responsibilities:
- Instance lifecycle: create, details, status, reboot/off/on, delete, resize.
- Enumeration: regions, sizes, SSH keys.
- Optional features: snapshots, backup toggles, dynamic tags, SSH key creation, connection tests.
- Credentials: API keys or username/password pairs, encrypted at rest.
- Error handling: consistent `WP_Error` responses and normalized states.
- Caching: transients for read-only data like regions and sizes.

**Section sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-cloud-provider-api.php:423-450](file://includes/core/providers/class-cloud-provider-api.php#L423-L450)
- [class-cloud-provider-api.php:481-585](file://includes/core/providers/class-cloud-provider-api.php#L481-L585)
- [class-cloud-provider-api.php:645-704](file://includes/core/providers/class-cloud-provider-api.php#L645-L704)

## Architecture Overview
The abstraction follows a clear inheritance model:

- `CLOUD_PROVIDER_API` provides:
  - Provider identity (name, slug, base provider).
  - Feature flags (snapshots, resize, SSH key creation, test connection, etc.).
  - Credential helpers (`get_api_key`, `get_secret_key`, `uses_api_key`, `has_credentials`, `credentials_available`).
  - Cache key generation and transient storage for enumerations.
  - State normalization helpers.
- Each provider extends the base class and implements its own `call()` method, which maps internal method names to provider endpoints and normalizes responses.

```mermaid
classDiagram
class CLOUD_PROVIDER_API {
+set_provider()
+set_provider_slug()
+set_feature_flag()
+get_feature_flag()
+get_api_key()
+get_secret_key()
+uses_api_key()
+has_credentials()
+credentials_available()
+get_cache_key()
+store_cache()
+get_cached_data()
+clear_cache()
+get_server_state_text()
+call(method, attributes)
}
class CLOUD_PROVIDER_API_Upcloud {
+call(method, attributes)
+uses_api_key()
+has_credentials()
+credentials_available()
}
class CLOUD_PROVIDER_API_VultrV2 {
+call(method, attributes)
+get_secret_key()
}
CLOUD_PROVIDER_API <|-- CLOUD_PROVIDER_API_Upcloud
CLOUD_PROVIDER_API <|-- CLOUD_PROVIDER_API_VultrV2
```

**Diagram sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-upcloud.php:33-128](file://includes/core/providers/class-upcloud.php#L33-L128)
- [class-vultr-v2.php:30-127](file://includes/core/providers/class-vultr-v2.php#L30-L127)

## Detailed Component Analysis

### Base Provider Contract: CLOUD_PROVIDER_API
Responsibilities:
- Identity and configuration: provider name/slug, dashboard link, region prefix, help link.
- Feature flags: snapshot support, backups, resize, SSH key creation, test connection, dynamic tags, custom images.
- Credentials:
  - `get_api_key()` and `get_secret_key()` are template methods; descendants provide actual values.
  - `uses_api_key()` indicates whether the provider uses an API key or another mechanism.
  - `has_credentials()` checks configured secrets.
  - `credentials_available()` checks broader prerequisites such as SSH keys.
- Caching:
  - `get_cache_methods()` lists safe-to-cache methods.
  - `get_maybe_cache_methods()` lists all potentially cached methods.
  - `get_cache_key()` generates unique keys including provider slug, site context, and secret hash.
  - `store_cache()` and `get_cached_data()` manage transients.
- State normalization: `get_server_state_text()` maps internal states to human-readable labels.

Common methods exposed to callers:
- `call(method, attributes)` — abstracted entry point implemented by each provider.
- `get_region_description()` and `get_size_description()` — optional overrides for display text.

Error handling:
- Returns `WP_Error` for unsupported operations or missing inputs.
- Normalizes provider-specific errors into consistent structures.

```mermaid
flowchart TD
Start(["Call provider.call"]) --> CheckCache["Check if method is cacheable"]
CheckCache --> |Yes| Cached{"Cached data exists?"}
Cached --> |Yes| ReturnCache["Return cached data"]
Cached --> |No| AuthCheck["Check credentials"]
AuthCheck --> |Missing| ReturnFalse["Return false"]
AuthCheck --> |Present| Dispatch["Dispatch to provider-specific handler"]
Dispatch --> ValidateInputs["Validate required inputs"]
ValidateInputs --> |Invalid| ReturnError["Return WP_Error"]
ValidateInputs --> MakeRequest["Make provider API request"]
MakeRequest --> HandleResponse["Normalize response"]
HandleResponse --> StoreCache["Store cache if applicable"]
StoreCache --> ReturnResult["Return result"]
```

**Diagram sources**
- [class-cloud-provider-api.php:481-585](file://includes/core/providers/class-cloud-provider-api.php#L481-L585)
- [class-cloud-provider-api.php:645-704](file://includes/core/providers/class-cloud-provider-api.php#L645-L704)

**Section sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-cloud-provider-api.php:423-450](file://includes/core/providers/class-cloud-provider-api.php#L423-L450)
- [class-cloud-provider-api.php:481-585](file://includes/core/providers/class-cloud-provider-api.php#L481-L585)
- [class-cloud-provider-api.php:645-704](file://includes/core/providers/class-cloud-provider-api.php#L645-L704)

### UpCloud Provider
Highlights:
- Authenticates with HTTP Basic using account username and password rather than an API key.
- Overrides `uses_api_key()`, `has_credentials()`, and `credentials_available()` to reflect username/password and inline SSH key installation.
- Implements `call()` with explicit handlers for regions, sizes, keys, create, details, status, power operations, delete, resize, ssh_create, and test_connection.
- Snapshots are not supported through this control panel; related methods return `WP_Error`.
- Power operations are asynchronous without operation IDs; completion is inferred from server state with a timeout backstop.
- Storage templates must be cloned to create disks; create resolves a template UUID before issuing the server creation request.

```mermaid
flowchart TD
Start(["UpCloud call('create')"]) --> ValidateRegion["Validate zone"]
ValidateRegion --> ResolvePlan["Resolve plan from size mapping"]
ResolvePlan --> ResolveTemplate["Resolve storage template UUID"]
ResolveTemplate --> ValidateSSHKey["Validate public SSH key"]
ValidateSSHKey --> BuildPayload["Build server payload"]
BuildPayload --> ExecutePOST["POST /server"]
ExecutePOST --> ValidateUUID{"Server UUID returned?"}
ValidateUUID --> |No| ReturnError["Return WP_Error"]
ValidateUUID --> |Yes| ReturnCreate["Return provider_instance_id, name, created, actions, run_cmd"]
```

**Diagram sources**
- [class-upcloud.php:491-602](file://includes/core/providers/class-upcloud.php#L491-L602)

**Section sources**
- [class-upcloud.php:33-128](file://includes/core/providers/class-upcloud.php#L33-L128)
- [class-upcloud.php:310-372](file://includes/core/providers/class-upcloud.php#L310-L372)
- [class-upcloud.php:491-602](file://includes/core/providers/class-upcloud.php#L491-L602)
- [class-upcloud.php:697-776](file://includes/core/providers/class-upcloud.php#L697-L776)

### Vultr v2 Provider
Highlights:
- Uses bearer token authentication against the Vultr API v2.
- Implements comprehensive `call()` handlers for regions, sizes, keys, create, details, status, power operations, delete, resize, snapshots (create/delete/list/pending status), ssh_create, and test_connection.
- Pagination helper fetches all pages safely with bounded page counts.
- Power operations return no operation ID; completion is inferred from instance state with a timeout backstop.
- User data must be base64 encoded before sending to the API.

```mermaid
sequenceDiagram
participant Caller as "Caller"
participant Vultr as "Vultr Provider"
participant API as "Vultr REST API"
Caller->>Vultr : call("snapshot", {id})
Vultr->>API : POST /instances/{id}/snapshot
API-->>Vultr : snapshot object with id
Vultr-->>Caller : {id, snapshot-id, snapshot-id-type, provider_status, status}
Caller->>Vultr : call("delete_snapshot", {snapshot_id})
Vultr->>API : DELETE /snapshots/{snapshot_id}
API-->>Vultr : 204 No Content
Vultr-->>Caller : {status : success}
```

**Diagram sources**
- [class-vultr-v2.php:650-696](file://includes/core/providers/class-vultr-v2.php#L650-L696)

**Section sources**
- [class-vultr-v2.php:30-127](file://includes/core/providers/class-vultr-v2.php#L30-L127)
- [class-vultr-v2.php:183-250](file://includes/core/providers/class-vultr-v2.php#L183-L250)
- [class-vultr-v2.php:369-453](file://includes/core/providers/class-vultr-v2.php#L369-L453)
- [class-vultr-v2.php:500-588](file://includes/core/providers/class-vultr-v2.php#L500-L588)

### Virtual Provider Registration and Discovery
Virtual providers allow multiple accounts per provider type by defining a custom post type record that points to a base provider class. The registration process:

- Registers the `sstk_cloud_provider` custom post type and associated meta boxes.
- Adds filters to include virtual providers in the global provider list via `sstk_get_cloud_providers`.
- Prevents deletion of virtual providers that are still referenced by servers.

```mermaid
flowchart TD
Admin["Admin creates Virtual Provider"] --> SavePost["Save sstk_cloud_provider post"]
SavePost --> SetSlug["Set provider slug from title"]
SetSlug --> AddFilter["Add to sstk_get_cloud_providers"]
AddFilter --> ListProviders["Global provider list includes virtual provider"]
ListProviders --> UseInUI["UI shows virtual provider options"]
```

**Diagram sources**
- [class-sstk-posts-cloud-provider.php:505-555](file://includes/core/class-sstk-posts-cloud-provider.php#L505-L555)
- [class-sstk-posts-cloud-provider.php:588-616](file://includes/core/class-sstk-posts-cloud-provider.php#L588-L616)

**Section sources**
- [class-sstk-posts-cloud-provider.php:505-555](file://includes/core/class-sstk-posts-cloud-provider.php#L505-L555)
- [class-sstk-posts-cloud-provider.php:588-616](file://includes/core/class-sstk-posts-cloud-provider.php#L588-L616)
- [class-sstk-posts-cloud-provider.php:649-749](file://includes/core/class-sstk-posts-cloud-provider.php#L649-L749)

### Provider Registry and Filters
The global provider registry exposes a filter `sstk_get_cloud_providers` that allows other plugins or white-label builds to add, remove, or modify providers. The base provider class also registers a filter for provider dashboard links and supports feature flag configuration.

Key integration points:
- `sstk_get_cloud_providers` filter used to assemble the final provider list.
- `sstk_provider_types` filter used to define supported provider types in the virtual provider UI.
- Per-provider dashboard link filter: `sstk_cloud_provider_settings_api_key_label_desc_{provider}`.

**Section sources**
- [simple-stack.php:1330-1364](file://includes/core/simple-stack.php#L1330-L1364)
- [class-sstk-posts-cloud-provider.php:560-577](file://includes/core/class-sstk-posts-cloud-provider.php#L560-L577)
- [class-cloud-provider-api.php:377-389](file://includes/core/providers/class-cloud-provider-api.php#L377-L389)

### Authentication Patterns
Two primary patterns are implemented:

- API key/bearer token:
  - Vultr uses a bearer token stored in an encrypted option and passed via the `Authorization: Bearer` header.
  - `get_api_key()` returns the decrypted token; `get_secret_key()` aliases it for cache key hashing.
- Username/password (HTTP Basic):
  - UpCloud stores username and password in encrypted options and sends them via HTTP Basic authentication.
  - `uses_api_key()` returns false; `has_credentials()` checks both fields; `get_secret_key()` returns the password for cache key hashing.

Credential availability checks:
- `credentials_available()` ensures not only secrets are present but also required SSH keys for provisioning.

Security notes:
- Secrets are decrypted at runtime and never logged.
- UpCloud explicitly avoids logging headers, bodies, or full URLs containing credentials.

**Section sources**
- [class-vultr-v2.php:120-127](file://includes/core/providers/class-vultr-v2.php#L120-L127)
- [class-upcloud.php:117-128](file://includes/core/providers/class-upcloud.php#L117-L128)
- [class-upcloud.php:172-231](file://includes/core/providers/class-upcloud.php#L172-L231)
- [class-cloud-provider-api.php:173-207](file://includes/core/providers/class-cloud-provider-api.php#L173-L207)
- [class-cloud-provider-api.php:645-704](file://includes/core/providers/class-cloud-provider-api.php#L645-L704)

### Error Handling Strategies
Consistent error handling across providers:
- Missing inputs return `WP_Error` with descriptive messages.
- Unsupported operations return `WP_Error` with a “not-supported” code.
- HTTP errors are detected and converted to `WP_Error`, often including provider-specific error objects when available.
- Asynchronous operations track state via transients and timeouts to avoid wedging servers in pending states.

Examples:
- UpCloud infers power operation completion from server state with a timeout.
- Vultr infers power operation completion from instance state with a timeout.

**Section sources**
- [class-upcloud.php:697-776](file://includes/core/providers/class-upcloud.php#L697-L776)
- [class-vultr-v2.php:500-588](file://includes/core/providers/class-vultr-v2.php#L500-L588)

### Common Instance Management Methods
All providers implement a unified `call()` interface for instance lifecycle and enumeration:

- Create:
  - Validates region/zone, plan/size mapping, OS selection, and SSH key material.
  - Builds provider-specific payloads and returns normalized results including provider instance id, name, created timestamp, actions, and optional run commands.
- Details:
  - Returns OS, IP addresses, name, and normalized status.
- Status:
  - Reports operation completion or in-progress state, often inferring completion for asynchronous APIs.
- Power operations:
  - Reboot, off, on map to provider-specific endpoints and return in-progress status with action identifiers where applicable.
- Delete:
  - Destroys the instance and returns a done status.
- Resize:
  - Changes plan/size; may require manual restart depending on provider constraints.
- Enumeration:
  - Regions, sizes, and SSH keys are fetched and cached.

**Section sources**
- [class-upcloud.php:310-372](file://includes/core/providers/class-upcloud.php#L310-L372)
- [class-vultr-v2.php:183-250](file://includes/core/providers/class-vultr-v2.php#L183-L250)

### Testing Provider Implementations
A minimal test double is provided to avoid calling real providers during tests:

- `SSTK_Cloud_Provider_Test` extends `CLOUD_PROVIDER_API` and overrides `call()` to return a fixed success response.
- This enables unit tests to validate orchestration logic without network dependencies.

Best practices:
- Extend `CLOUD_PROVIDER_API` and override `call()` to simulate provider responses.
- Assert that callers handle `WP_Error` and normalized state strings correctly.
- Mock credential retrieval and SSH key availability to cover validation paths.

**Section sources**
- [class-sstk-cloud-provider-test.php:17-23](file://tests/data/class-sstk-cloud-provider-test.php#L17-L23)

## Dependency Analysis
Provider classes depend on:
- WordPress functions for options, transients, AJAX, and HTTP requests.
- SSTK core utilities for decryption, option access, and logging.
- Filters and actions for extending provider behavior and settings.

Coupling and cohesion:
- High cohesion within each provider implementation around its specific API.
- Low coupling to caller code via the shared `call()` contract.
- Shared caching and credential logic reduces duplication in the base class.

Potential circular dependencies:
- None observed among provider classes; dependencies flow from concrete providers to the base class and WordPress core.

External integrations:
- Cloud provider REST APIs (UpCloud 1.3 and Vultr v2).
- WordPress admin UI and settings screens.

```mermaid
graph LR
Callers["Application Code"] --> Base["CLOUD_PROVIDER_API"]
Base --> Up["UpCloud Provider"]
Base --> Vul["Vultr Provider"]
Base --> WP["WordPress Core"]
Base --> SSTK["SSTK Utilities"]
```

**Diagram sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)
- [class-upcloud.php:310-372](file://includes/core/providers/class-upcloud.php#L310-L372)
- [class-vultr-v2.php:183-250](file://includes/core/providers/class-vultr-v2.php#L183-L250)

**Section sources**
- [class-cloud-provider-api.php:15-167](file://includes/core/providers/class-cloud-provider-api.php#L15-L167)

## Performance Considerations
- Caching:
  - Read-only methods like regions, sizes, and keys are cached via transients.
  - Cache keys incorporate provider slug, site context, and secret hash to avoid collisions across accounts.
  - Cache lifetimes are configurable via options.
- Network efficiency:
  - Vultr paginates list endpoints with bounded page counts to prevent unbounded loops.
- Asynchronous operations:
  - Power operations and resizes use transients and timeouts to avoid indefinite pending states.

Recommendations:
- Prefer cached enumeration methods where possible.
- Avoid logging sensitive request details; providers already guard against this.
- Tune cache lifetimes based on provider update frequency and operational needs.

**Section sources**
- [class-cloud-provider-api.php:481-585](file://includes/core/providers/class-cloud-provider-api.php#L481-L585)
- [class-vultr-v2.php:43-53](file://includes/core/providers/class-vultr-v2.php#L43-L53)

## Troubleshooting Guide
Common issues and resolutions:
- Missing credentials:
  - Ensure API key or username/password is configured and decrypted correctly.
  - Verify SSH key pair is present for provisioning.
- Unsupported operations:
  - Some providers do not support snapshots or certain power operations; check feature flags and provider documentation.
- Asynchronous operations stuck:
  - Check transient backstops and provider dashboards for completion signals.
- Rate limiting:
  - Implement exponential backoff and jitter for retries when providers return throttling errors.
  - Respect provider quotas and consider batching enumeration requests.

Settings screen integration:
- Provider-specific fields are added via filters; ensure they are rendered and saved correctly.
- Private SSH keys are encrypted at rest and decrypted only when needed.

**Section sources**
- [class-sstk-settings.php:1394-1424](file://includes/core/class-sstk-settings.php#L1394-L1424)
- [class-upcloud.php:172-231](file://includes/core/providers/class-upcloud.php#L172-L231)
- [class-vultr-v2.php:18-29](file://includes/core/providers/class-vultr-v2.php#L18-L29)

## Conclusion
Simple Stack’s provider abstraction layer provides a robust, extensible foundation for managing cloud instances across multiple providers. The base class defines a clear contract for instance lifecycle, enumeration, authentication, and error handling, while concrete implementations encapsulate provider-specific details. Virtual provider registration and the global provider registry enable flexible configuration and migration between providers. By leveraging shared caching, credential management, and consistent error handling, the system simplifies multi-cloud operations and supports secure, maintainable integrations.