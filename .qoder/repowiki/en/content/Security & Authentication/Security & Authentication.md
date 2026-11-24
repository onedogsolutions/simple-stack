# Security & Authentication

<cite>
**Referenced Files in This Document**
- [class-sstk-rest-api.php](file://includes/core/api/class-sstk-rest-api.php)
- [class-sstk-rest-controller-base.php](file://includes/core/api/class-sstk-rest-controller-base.php)
- [class-sstk-secret-redactor.php](file://includes/core/api/class-sstk-secret-redactor.php)
- [class-sstk-rest-security.php](file://includes/core/api/class-sstk-rest-security.php)
- [class-sstk-rest-api-controller-base.php](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [class-sstk-app.php](file://includes/core/apps/class-sstk-app.php)
- [git-control-site.php](file://includes/core/apps/wordpress-app/tabs/git-control-site.php)
- [api.js](file://src/admin/lib/api.js)
- [functions.php](file://includes/core/functions.php)
- [class-sstk-roles-capabilities.php](file://includes/core/class-sstk-roles-capabilities.php)
- [class-sstk-posts-team.php](file://includes/core/class-sstk-posts-team.php)
- [class-sstk-posts-permission-type.php](file://includes/core/class-sstk-posts-permission-type.php)
- [functions-kses.php](file://includes/core/functions-kses.php)
- [class-cloud-provider-api.php](file://includes/core/providers/class-cloud-provider-api.php)
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
This document explains the security model for Simple Stack, focusing on authentication, authorization, credential handling, secret redaction, input validation, output escaping, secure remote operations, and audit practices. It is intended for administrators, integrators, and developers who need to understand how access is controlled, how sensitive data is protected, and how to operate Simple Stack securely.

The React admin surface is registered under `simple-stack/v1` through `SSTK_REST_API` and `SSTK_REST_Controller_Base`. Routes in that controller family require an authenticated WordPress user with `manage_options`, use shared argument validation and response formatting, and pass response payloads through `SSTK_Secret_Redactor`. The WordPress App controller family also uses `simple-stack/v1`, but authorizes through `sstk_is_admin()` and keeps its existing response and error-handling conventions.

Two WordPress App callbacks are intentionally public under `simple-stack/v1`: the provisioning command callback validates a pending-command transient, and the GitHub webhook validates an HMAC-SHA1 signature with `hash_equals()`. They do not inherit the admin controller's response envelope or redaction contract. The React client sends the WordPress REST nonce in `X-WP-Nonce`; existing AJAX handlers use WordPress nonce checks. Team-based permissions provide resource-level controls in the native screens and models, while role-based capabilities define platform-wide access boundaries.

## Project Structure
Security-related functionality is concentrated under the core API layer and WordPress permission subsystem:

- Simple Stack REST API entry point registers the admin SPA controllers and loads shared utilities.
- `SSTK_REST_Controller_Base` enforces `manage_options` for its controller family, validates parameters, and wraps its responses with secret redaction.
- The WordPress App REST base controller uses `sstk_is_admin()` and its existing exception-to-`WP_Error` handling under the same namespace.
- The provisioning command callback and GitHub webhook are registered separately under `simple-stack/v1` and authenticate with a pending-command transient and HMAC-SHA1 respectively.
- The React admin client sends `X-WP-Nonce`; existing AJAX handlers verify WordPress nonces.
- Secret redactor masks known credential keys and scrubs embedded secrets from free text.
- Security posture endpoint performs read-only SSH probes against sites without mutating state.
- Role and capability definitions create custom roles and capabilities.
- Team and permission type models implement team membership, per-user server/app permissions, and permission catalog management.
- KSES helpers restrict allowed HTML attributes for safe UI rendering.
- Cloud provider base class centralizes credential fingerprinting and cache key generation.

```mermaid
graph TB
Client["React Admin Client<br/>X-WP-Nonce"] --> API["SSTK_REST_API"]
API --> Base["SSTK_REST_Controller_Base<br/>manage_options"]
Base --> Redactor["SSTK_Secret_Redactor"]
Base --> Security["SSTK_REST_Security"]
Security --> SSH["Remote Site Probe"]
Client --> WPApp["WordPress App Controllers<br/>simple-stack/v1"]
WPApp --> WPAppBase["SSTK_REST_API_Controller_Base<br/>sstk_is_admin()"]
Callbacks["Provisioning Command / GitHub Webhook<br/>simple-stack/v1"] --> CallbackAuth["Pending-Command Transient / HMAC-SHA1"]
Roles["SSTK_ROLES_CAPABILITIES"] --> WP["WordPress Roles & Capabilities"]
Teams["SSTK_POSTS_TEAM"] --> DB["Permission Assignments Table"]
PermTypes["SSTK_POSTS_PERMISSION_TYPE"] --> DB
Providers["CLOUD_PROVIDER_API"] --> Secrets["Provider Credentials"]
```

**Diagram sources**
- [class-sstk-rest-api.php:21-37](file://includes/core/api/class-sstk-rest-api.php#L21-L37)
- [class-sstk-rest-controller-base.php:22-60](file://includes/core/api/class-sstk-rest-controller-base.php#L22-L60)
- [class-sstk-secret-redactor.php:24-37](file://includes/core/api/class-sstk-secret-redactor.php#L24-L37)
- [class-sstk-rest-security.php:19-38](file://includes/core/api/class-sstk-rest-security.php#L19-L38)
- [class-sstk-rest-api-controller-base.php](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [class-sstk-app.php](file://includes/core/apps/class-sstk-app.php)
- [git-control-site.php](file://includes/core/apps/wordpress-app/tabs/git-control-site.php)
- [api.js](file://src/admin/lib/api.js)
- [class-sstk-roles-capabilities.php:15-34](file://includes/core/class-sstk-roles-capabilities.php#L15-L34)
- [class-sstk-posts-team.php:15-35](file://includes/core/class-sstk-posts-team.php#L15-L35)
- [class-sstk-posts-permission-type.php:15-35](file://includes/core/class-sstk-posts-permission-type.php#L15-L35)
- [class-cloud-provider-api.php:15-77](file://includes/core/providers/class-cloud-provider-api.php#L15-L77)

**Section sources**
- [class-sstk-rest-api.php:12-37](file://includes/core/api/class-sstk-rest-api.php#L12-L37)
- [class-sstk-rest-controller-base.php:12-60](file://includes/core/api/class-sstk-rest-controller-base.php#L12-L60)
- [class-sstk-secret-redactor.php:12-37](file://includes/core/api/class-sstk-secret-redactor.php#L12-L37)
- [class-sstk-rest-security.php:12-38](file://includes/core/api/class-sstk-rest-security.php#L12-L38)
- [class-sstk-roles-capabilities.php:12-34](file://includes/core/class-sstk-roles-capabilities.php#L12-L34)
- [class-sstk-posts-team.php:12-35](file://includes/core/class-sstk-posts-team.php#L12-L35)
- [class-sstk-posts-permission-type.php:12-35](file://includes/core/class-sstk-posts-permission-type.php#L12-L35)
- [class-cloud-provider-api.php:12-77](file://includes/core/providers/class-cloud-provider-api.php#L12-L77)

## Core Components
- Simple Stack REST API: Registers the `simple-stack/v1` namespace and loads controllers including servers, sites, logs, tabs, providers, and security.
- Base Controller: Enforces `manage_options` for routes registered through `SSTK_REST_Controller_Base`, defines typed argument helpers, normalizes params, dispatches existing tab actions safely, and wraps its responses with secret redaction.
- WordPress App Base Controller: Uses `sstk_is_admin()` for the WordPress App controller family under `simple-stack/v1`.
- Public WordPress App Callbacks: Validate a pending-command transient or HMAC-SHA1 signature before side effects under `simple-stack/v1`.
- Secret Redactor: Recursively masks array values whose keys match credential patterns and scrubs PEM private keys and credential assignments from free text.
- Security Posture Endpoint: Performs read-only SSH commands to list plugins, check configuration flags, detect PHP version, and inspect vhost handlers.
- Roles & Capabilities: Creates custom roles and capabilities such as provisioning, managing servers/apps/logs/settings/groups/teams, and a catch-all manage-all capability.
- Team Permissions: Stores per-user, per-object permissions in a dedicated table and supports team manager controls and exclusion logic.
- Permission Types: Defines the permission catalog, groups, categories, and default entries for servers and apps.
- KSES Helpers: Provide allowed attribute sets for safe rendering of select elements and related UI components.
- Cloud Provider API: Centralizes credential fingerprinting, cache key construction, feature flags, and credential availability checks.

**Section sources**
- [class-sstk-rest-api.php:12-37](file://includes/core/api/class-sstk-rest-api.php#L12-L37)
- [class-sstk-rest-controller-base.php:22-60](file://includes/core/api/class-sstk-rest-controller-base.php#L22-L60)
- [class-sstk-secret-redactor.php:24-37](file://includes/core/api/class-sstk-secret-redactor.php#L24-L37)
- [class-sstk-rest-security.php:19-38](file://includes/core/api/class-sstk-rest-security.php#L19-L38)
- [class-sstk-roles-capabilities.php:73-151](file://includes/core/class-sstk-roles-capabilities.php#L73-L151)
- [class-sstk-posts-team.php:312-347](file://includes/core/class-sstk-posts-team.php#L312-L347)
- [class-sstk-posts-permission-type.php:355-760](file://includes/core/class-sstk-posts-permission-type.php#L355-L760)
- [functions-kses.php:18-52](file://includes/core/functions-kses.php#L18-L52)
- [class-cloud-provider-api.php:169-207](file://includes/core/providers/class-cloud-provider-api.php#L169-L207)

## Architecture Overview
The Simple Stack security architecture combines WordPress-native authentication with two current controller families under `simple-stack/v1`, independently authenticated WordPress App callbacks under `simple-stack/v1`, typed input validation on the admin SPA controllers, centralized redaction on that controller family's responses, and team-scoped native permission models.

```mermaid
sequenceDiagram
participant Admin as "Admin User"
participant WP as "WordPress Auth"
participant API as "SSTK_REST_API"
participant Base as "Base Controller"
participant Sec as "Security Endpoint"
participant SSH as "SSH Transport"
participant Site as "Remote Site"
Admin->>WP : Authenticate (admin session)
Admin->>API : POST /sites/{id}/security-status
API->>Base : Route to handler
Base->>Base : permissions_check()
Base->>Sec : security_status(request)
Sec->>Sec : resolve_ssh(server_id)
Sec->>SSH : exec(read-only commands)
SSH-->>Sec : stdout packets
Sec->>Sec : parse_probe_output()
Sec->>Base : respond(data)
Base->>Base : SSTK_Secret_Redactor : : redact(data)
Base-->>Admin : {ok, data, meta}
```

**Diagram sources**
- [class-sstk-rest-api.php:51-99](file://includes/core/api/class-sstk-rest-api.php#L51-L99)
- [class-sstk-rest-controller-base.php:53-60](file://includes/core/api/class-sstk-rest-controller-base.php#L53-L60)
- [class-sstk-rest-security.php:49-100](file://includes/core/api/class-sstk-rest-security.php#L49-L100)
- [class-sstk-secret-redactor.php:64-95](file://includes/core/api/class-sstk-secret-redactor.php#L64-L95)

## Detailed Component Analysis

### Authentication and Authorization Model
- The React admin client relies on WordPress authentication and sends the REST nonce in `X-WP-Nonce`. Existing AJAX handlers verify WordPress nonces.
- Routes registered through `SSTK_REST_Controller_Base` require `current_user_can( 'manage_options' )`.
- WordPress App controllers under `simple-stack/v1` use `sstk_is_admin()`, which accepts a super admin or a user with `manage_options`.
- `simple-stack/v1/command/{id}/{name}/{status}/{cmdid}/` is public but validates a pending-command transient before dispatching command hooks.
- `simple-stack/v1/handle-git-webhook/{randomid}/{id}/` is public but validates the payload with HMAC-SHA1 and `hash_equals()` before processing it.
- Custom roles and capabilities extend WordPress roles to grant granular permissions for provisioning, managing servers/apps/logs/settings/groups/teams, and a global manage-all capability.
- Team-based permissions allow assigning specific server and app permissions to individual users within teams, with support for team managers and explicit exclusion of permissions.

```mermaid
flowchart TD
Start(["Request"]) --> RouteFamily{"Controller family?"}
RouteFamily --> |Admin SPA| CheckCap["SSTK_REST_Controller_Base: manage_options"]
RouteFamily --> |WordPress App| CheckAdmin["WordPress App base: sstk_is_admin()"]
RouteFamily --> |Public callback| CallbackAuth["Validate transient or HMAC"]
CheckCap --> |Allowed| RouteHandler["Controller handler"]
CheckCap --> |Denied| Deny["Return error"]
CheckAdmin --> |Allowed| RouteHandler
CheckAdmin --> |Denied| Deny
CallbackAuth --> |Valid| RouteHandler
CallbackAuth --> |Invalid| Deny
RouteHandler --> TeamCheck{"Resource-specific team permission required?"}
TeamCheck --> |Yes| TeamPerms["Check team assignment table"]
TeamPerms --> Decision{"Permission granted?"}
Decision --> |Yes| Proceed["Execute action"]
Decision --> |No| Deny
TeamCheck --> |No| Proceed
```

**Diagram sources**
- [class-sstk-rest-controller-base.php:53-60](file://includes/core/api/class-sstk-rest-controller-base.php#L53-L60)
- [class-sstk-roles-capabilities.php:73-151](file://includes/core/class-sstk-roles-capabilities.php#L73-L151)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)

**Section sources**
- [class-sstk-rest-controller-base.php:53-60](file://includes/core/api/class-sstk-rest-controller-base.php#L53-L60)
- [class-sstk-roles-capabilities.php:73-151](file://includes/core/class-sstk-roles-capabilities.php#L73-L151)
- [class-sstk-posts-team.php:312-347](file://includes/core/class-sstk-posts-team.php#L312-L347)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)

### Credential Management and Provider Integration
- Provider classes expose template methods for API keys and secret keys.
- A credential fingerprint method chooses whichever value is set, preventing identical empty strings from masking distinct accounts during connection tests.
- Cache keys incorporate hashed secret material to isolate cached data per account.
- Credential availability checks verify presence of SSH public/private keys and provider API keys before allowing operations.

```mermaid
classDiagram
class CLOUD_PROVIDER_API {
+get_api_key() string
+get_secret_key() string
+get_credential_fingerprint() string
+has_credentials() bool
+credentials_available() bool
+get_cache_key(method) string
}
```

**Diagram sources**
- [class-cloud-provider-api.php:169-207](file://includes/core/providers/class-cloud-provider-api.php#L169-L207)
- [class-cloud-provider-api.php:501-531](file://includes/core/providers/class-cloud-provider-api.php#L501-L531)
- [class-cloud-provider-api.php:669-704](file://includes/core/providers/class-cloud-provider-api.php#L669-L704)

**Section sources**
- [class-cloud-provider-api.php:169-207](file://includes/core/providers/class-cloud-provider-api.php#L169-L207)
- [class-cloud-provider-api.php:501-531](file://includes/core/providers/class-cloud-provider-api.php#L501-L531)
- [class-cloud-provider-api.php:669-704](file://includes/core/providers/class-cloud-provider-api.php#L669-L704)

### Secret Redaction System
- Responses returned through `SSTK_REST_Controller_Base::respond()` pass through the secret redactor. Public WordPress App callbacks return their own response shapes and are not covered by that contract.
- Array keys matching patterns like password, secret, token, private, authorization, `_key`, and api key variants are masked entirely.
- Free-text scrubbing removes PEM private key blocks and collapses credential-looking shell assignments and authorization headers to safe placeholders.
- The redactor includes recursion depth protection to avoid pathological structures.

```mermaid
flowchart TD
Input["Response Data"] --> Walk["Recursive walk"]
Walk --> IsArray{"Is array?"}
IsArray --> |Yes| KeyCheck{"Key matches secret pattern?"}
KeyCheck --> |Yes| Mask["Replace value with mask"]
KeyCheck --> |No| Recurse["Recurse into child"]
IsArray --> |No| IsString{"Is string?"}
IsString --> |Yes| Scrub["Scrub PEM blocks and credential lines"]
IsString --> |No| Keep["Keep scalar unchanged"]
Mask --> Output["Redacted Response"]
Recurse --> Output
Scrub --> Output
Keep --> Output
```

**Diagram sources**
- [class-sstk-secret-redactor.php:64-95](file://includes/core/api/class-sstk-secret-redactor.php#L64-L95)
- [class-sstk-secret-redactor.php:115-126](file://includes/core/api/class-sstk-secret-redactor.php#L115-L126)
- [class-sstk-rest-controller-base.php:136-149](file://includes/core/api/class-sstk-rest-controller-base.php#L136-L149)

**Section sources**
- [class-sstk-secret-redactor.php:24-37](file://includes/core/api/class-sstk-secret-redactor.php#L24-L37)
- [class-sstk-secret-redactor.php:64-95](file://includes/core/api/class-sstk-secret-redactor.php#L64-L95)
- [class-sstk-secret-redactor.php:115-126](file://includes/core/api/class-sstk-secret-redactor.php#L115-L126)
- [class-sstk-rest-controller-base.php:136-149](file://includes/core/api/class-sstk-rest-controller-base.php#L136-L149)

### Input Sanitization and Output Escaping
- Parameter validation uses typed helpers: integers, strings, enums, free-form text, objects, and normalized params maps.
- Strings use sanitization callbacks; free-form text preserves characters but still rejects non-string inputs.
- Existing tab actions receive sanitized and isolated superglobals snapshots to prevent request leakage.
- KSES helpers constrain allowed HTML attributes for safe rendering of select elements and related UI components.

```mermaid
flowchart TD
Raw["Raw Request"] --> Validate["Validate & sanitize args"]
Validate --> Params["Normalize params map"]
Params --> Dispatch["Dispatch tab action"]
Dispatch --> Existing["Existing $_POST/$_FILES snapshot"]
Existing --> Restore["Restore superglobals"]
Restore --> Render["Render output"]
Render --> Escape["wp_kses where needed"]
```

**Diagram sources**
- [class-sstk-rest-controller-base.php:192-242](file://includes/core/api/class-sstk-rest-controller-base.php#L192-L242)
- [class-sstk-rest-controller-base.php:255-341](file://includes/core/api/class-sstk-rest-controller-base.php#L255-L341)
- [class-sstk-rest-controller-base.php:362-406](file://includes/core/api/class-sstk-rest-controller-base.php#L362-L406)
- [functions-kses.php:18-52](file://includes/core/functions-kses.php#L18-L52)

**Section sources**
- [class-sstk-rest-controller-base.php:192-242](file://includes/core/api/class-sstk-rest-controller-base.php#L192-L242)
- [class-sstk-rest-controller-base.php:255-341](file://includes/core/api/class-sstk-rest-controller-base.php#L255-L341)
- [class-sstk-rest-controller-base.php:362-406](file://includes/core/api/class-sstk-rest-controller-base.php#L362-L406)
- [functions-kses.php:18-52](file://includes/core/functions-kses.php#L18-L52)

### Secure Remote Operations and Read-Only Probes
- The security posture endpoint constructs read-only commands using escaped arguments and executes them via SSH.
- Command output is split into packets and parsed into safe fields: plugin list, file edit flag, PHP version, and vhost handler detection.
- No write operations are performed; the endpoint reports installed plugins and configuration posture without mutating the site.

```mermaid
sequenceDiagram
participant Handler as "Security Handler"
participant Builder as "Command Builder"
participant Shell as "Shell Execution"
participant Parser as "Output Parser"
Handler->>Builder : build_command(domain)
Builder-->>Handler : escaped command string
Handler->>Shell : exec(ssh, command)
Shell-->>Handler : raw stdout
Handler->>Parser : parse_probe_output(stdout)
Parser-->>Handler : normalized posture data
```

**Diagram sources**
- [class-sstk-rest-security.php:162-191](file://includes/core/api/class-sstk-rest-security.php#L162-L191)
- [class-sstk-rest-security.php:117-150](file://includes/core/api/class-sstk-rest-security.php#L117-L150)

**Section sources**
- [class-sstk-rest-security.php:49-100](file://includes/core/api/class-sstk-rest-security.php#L49-L100)
- [class-sstk-rest-security.php:117-150](file://includes/core/api/class-sstk-rest-security.php#L117-L150)
- [class-sstk-rest-security.php:162-191](file://includes/core/api/class-sstk-rest-security.php#L162-L191)

### Team-Based Permissions and User Isolation
- Team records are private posts and restricted by capability checks.
- Permission rules associate users with server and app permissions, supporting team managers and exclusion updates.
- The permission assignment table stores grants and denials per user, team, and permission type.
- Query filters limit team listings to authorized users based on team manager status and capabilities.

```mermaid
erDiagram
TEAM ||--o{ PERMISSION_ASSIGNMENT : "grants/denies"
USER ||--o{ PERMISSION_ASSIGNMENT : "assigned_to"
PERMISSION_TYPE ||--o{ PERMISSION_ASSIGNMENT : "type_id"
TEAM {
int id PK
string title
string status
}
USER {
int id PK
string display_name
}
PERMISSION_TYPE {
int id PK
string name
int object_type
string category
int group
}
PERMISSION_ASSIGNMENT {
int team_id FK
int user_id FK
int permission_type_id FK
tinyint granted
}
```

**Diagram sources**
- [class-sstk-posts-team.php:138-186](file://includes/core/class-sstk-posts-team.php#L138-L186)
- [class-sstk-posts-team.php:312-347](file://includes/core/class-sstk-posts-team.php#L312-L347)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)
- [class-sstk-posts-permission-type.php:331-348](file://includes/core/class-sstk-posts-permission-type.php#L331-L348)

**Section sources**
- [class-sstk-posts-team.php:138-186](file://includes/core/class-sstk-posts-team.php#L138-L186)
- [class-sstk-posts-team.php:312-347](file://includes/core/class-sstk-posts-team.php#L312-L347)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)
- [class-sstk-posts-permission-type.php:331-348](file://includes/core/class-sstk-posts-permission-type.php#L331-L348)

## Dependency Analysis
- Simple Stack REST API depends on the base controller and secret redactor.
- Security endpoint depends on SSH transport and WordPress app helpers to resolve domain and server metadata.
- Roles and capabilities hook into WordPress activation and multisite initialization.
- Team and permission type models depend on WordPress post types, metaboxes, and a custom database table.
- Cloud provider API provides credential abstractions consumed by provider-specific implementations.

```mermaid
graph LR
API["SSTK_REST_API"] --> Base["Base Controller"]
Base --> Redactor["Secret Redactor"]
Base --> Security["Security Endpoint"]
Security --> App["WordPress App Helpers"]
Roles["Roles & Capabilities"] --> WP["WordPress Roles"]
Teams["Team Model"] --> DB["Custom Table"]
PermTypes["Permission Type Model"] --> DB
Providers["Cloud Provider API"] --> Secrets["Credentials"]
```

**Diagram sources**
- [class-sstk-rest-api.php:51-99](file://includes/core/api/class-sstk-rest-api.php#L51-L99)
- [class-sstk-rest-controller-base.php:136-149](file://includes/core/api/class-sstk-rest-controller-base.php#L136-L149)
- [class-sstk-rest-security.php:77-100](file://includes/core/api/class-sstk-rest-security.php#L77-L100)
- [class-sstk-roles-capabilities.php:54-66](file://includes/core/class-sstk-roles-capabilities.php#L54-L66)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)
- [class-sstk-posts-permission-type.php:331-348](file://includes/core/class-sstk-posts-permission-type.php#L331-L348)
- [class-cloud-provider-api.php:169-207](file://includes/core/providers/class-cloud-provider-api.php#L169-L207)

**Section sources**
- [class-sstk-rest-api.php:51-99](file://includes/core/api/class-sstk-rest-api.php#L51-L99)
- [class-sstk-rest-controller-base.php:136-149](file://includes/core/api/class-sstk-rest-controller-base.php#L136-L149)
- [class-sstk-rest-security.php:77-100](file://includes/core/api/class-sstk-rest-security.php#L77-L100)
- [class-sstk-roles-capabilities.php:54-66](file://includes/core/class-sstk-roles-capabilities.php#L54-L66)
- [class-sstk-posts-team.php:443-517](file://includes/core/class-sstk-posts-team.php#L443-L517)
- [class-sstk-posts-permission-type.php:331-348](file://includes/core/class-sstk-posts-permission-type.php#L331-L348)
- [class-cloud-provider-api.php:169-207](file://includes/core/providers/class-cloud-provider-api.php#L169-L207)

## Performance Considerations
- Parameter validation avoids expensive parsing by using typed helpers and early rejection of invalid types.
- The security posture endpoint batches multiple read-only commands into one SSH round trip to reduce latency.
- Secret redaction walks nested arrays and strings; keep response payloads minimal to reduce processing time.
- Cloud provider caching isolates per-account transients and limits cache scope to whitelisted methods.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- If admin SPA routes under `simple-stack/v1` return unauthorized errors, verify the user has the WordPress `manage_options` capability and that requests include a valid REST nonce.
- If WordPress App controller routes return authorization errors, verify `sstk_is_admin()` succeeds for the current user.
- If a public command or webhook callback rejects a request, verify the pending-command transient or HMAC signature rather than adding an administrator permission requirement.
- If team listings show no items, confirm the current user is either a team manager or has the `sstk_manage_teams` capability.
- If secret redaction hides expected values, check whether array keys match credential patterns or if free text contains PEM blocks or credential-like assignments.
- If security posture reports cannot connect, ensure the server’s provider instance is active and SSH key details are available.
- If provider operations fail due to missing credentials, verify API key or secret key configuration and SSH key pair presence.

**Section sources**
- [class-sstk-rest-controller-base.php:53-60](file://includes/core/api/class-sstk-rest-controller-base.php#L53-L60)
- [class-sstk-posts-team.php:528-551](file://includes/core/class-sstk-posts-team.php#L528-L551)
- [class-sstk-secret-redactor.php:64-95](file://includes/core/api/class-sstk-secret-redactor.php#L64-L95)
- [class-sstk-rest-security.php:77-100](file://includes/core/api/class-sstk-rest-security.php#L77-L100)
- [class-cloud-provider-api.php:669-704](file://includes/core/providers/class-cloud-provider-api.php#L669-L704)

## Conclusion
Simple Stack’s security model uses WordPress authentication and capabilities for the admin SPA controller family, `sstk_is_admin()` for the WordPress App controller family, and transient or HMAC verification for the two public callbacks. Typed validation and centralized redaction protect the admin SPA response boundary; provider credential abstractions, account-isolated caches, and read-only remote probes reduce exposure elsewhere. Administrators should configure roles and capabilities carefully, assign team permissions thoughtfully, and treat redaction as a defense-in-depth boundary rather than a substitute for keeping secrets out of payloads and logs.