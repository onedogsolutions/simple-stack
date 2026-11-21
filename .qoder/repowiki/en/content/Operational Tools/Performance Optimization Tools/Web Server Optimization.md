# Web Server Optimization

<cite>
**Referenced Files in This Document**   
- [README.md](file://README.md)
- [01-prepare_server.txt](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt)
- [34-nginx_options.txt](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt)
- [55-ols_options.txt](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt)
- [04-manage_https.txt](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt)
- [39-php_workers.txt](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt)
- [manage_nginx_pagecache.txt](file://includes/core/apps/wordpress-app/scripts/v2/manage_nginx_pagecache.txt)
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
This document provides a comprehensive guide to web server performance optimization for high-traffic WordPress deployments using the repository’s provisioning and management scripts. It focuses on OpenLiteSpeed (primary) and legacy Nginx configuration paths, static asset optimization, browser caching, compression, HTTP/2, SSL/TLS, PHP worker tuning, and operational guidance for CDN integration and load balancing. The content is grounded in the repository’s shell scripts that configure web servers, PHP runtimes, and site-level behaviors.

## Project Structure
The repository provisions and manages WordPress sites primarily with OpenLiteSpeed and includes legacy Nginx support via scripts. Key areas relevant to web server optimization:
- Provisioning and base server hardening
- Web server options (OpenLiteSpeed and Nginx)
- HTTPS and HTTP/2 management
- PHP worker tuning
- Page cache script wrappers

```mermaid
graph TB
A["Provisioning Scripts<br/>01-prepare_server.txt"] --> B["Web Server Options<br/>55-ols_options.txt"]
A --> C["Legacy Nginx Options<br/>34-nginx_options.txt"]
A --> D["HTTPS & HTTP/2 Management<br/>04-manage_https.txt"]
A --> E["PHP Workers Tuning<br/>39-php_workers.txt"]
F["Page Cache Wrapper<br/>manage_nginx_pagecache.txt"] --> C
```

**Diagram sources**
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)
- [manage_nginx_pagecache.txt:1-9](file://includes/core/apps/wordpress-app/scripts/v2/manage_nginx_pagecache.txt#L1-L9)

**Section sources**
- [README.md:27-38](file://README.md#L27-L38)

## Core Components
- OpenLiteSpeed configuration and tuning via menu-driven scripts (compression, HTTP/2, headers, timeouts).
- Legacy Nginx configuration and tuning via menu-driven scripts (gzip, brotli, HTTP/2, browser cache includes, worker settings).
- HTTPS and HTTP/2 enablement/disabling across both web servers.
- PHP-FPM worker pool tuning for Nginx environments.
- Base server preparation including connection limits and worker directives.

Key responsibilities:
- Enabling and disabling compression (Gzip/Brotli).
- Managing HTTP/2 per domain.
- Setting security headers (HSTS, XSS protection, Content-Type options, Referrer-Policy, Permissions-Policy).
- Configuring browser caching strategies.
- Adjusting request body size and connection timeout at the server level.
- Tuning PHP workers (pm settings) for Nginx.

**Section sources**
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [34-nginx_options.txt:824-915](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L824-L915)
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)

## Architecture Overview
The optimization flow integrates provisioning, web server configuration, and runtime tuning. The scripts are invoked by the control plane to apply changes safely, validate configurations, and reload services.

```mermaid
sequenceDiagram
participant Admin as "Operator"
participant Script as "Management Script"
participant OLS as "OpenLiteSpeed"
participant Nginx as "Nginx"
participant PHP as "PHP-FPM / LSAPI"
Admin->>Script : Select action (e.g., enable HTTP/2, set timeouts)
Script->>OLS : Apply config changes (if OLS)
Script->>Nginx : Apply config changes (if Nginx)
Script->>Script : Validate config (openlitespeed -t or nginx -t)
alt Validation passes
Script->>OLS : Reload service
Script->>Nginx : Reload service
else Validation fails
Script-->>Admin : Report error and abort
end
Script->>PHP : Reload or restart if PHP settings changed
```

**Diagram sources**
- [55-ols_options.txt:56-69](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L56-L69)
- [34-nginx_options.txt:41-49](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L41-L49)
- [39-php_workers.txt:30-48](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L30-L48)

## Detailed Component Analysis

### OpenLiteSpeed Performance Tuning
- Compression: Global Gzip toggle and per-domain Brotli enablement.
- HTTP/2: Per-domain enable/disable functions.
- Browser caching: Managed via LSCache option through WP CLI.
- Security headers: HSTS, XSS Protection, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- Request/connection tuning: maxReqBodySize and connTimeout directives.

```mermaid
flowchart TD
Start(["Action Selected"]) --> CheckType{"Web Server Type"}
CheckType --> |OpenLiteSpeed| OLSActions["Apply OLS Settings"]
CheckType --> |Nginx| NginxActions["Apply Nginx Settings"]
OLSActions --> ValidateOLS["Validate OLS Config"]
NginxActions --> ValidateNginx["Validate Nginx Config"]
ValidateOLS --> ReloadOLS{"Validation OK?"}
ValidateNginx --> ReloadNginx{"Validation OK?"}
ReloadOLS --> |Yes| RestartOLS["Restart/Reload OLS"]
ReloadOLS --> |No| ErrorOLS["Report Error"]
ReloadNginx --> |Yes| RestartNginx["Restart/Reload Nginx"]
ReloadNginx --> |No| ErrorNginx["Report Error"]
```

**Diagram sources**
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [55-ols_options.txt:704-746](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L704-L746)
- [55-ols_options.txt:1094-1273](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L1094-L1273)

**Section sources**
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [55-ols_options.txt:704-746](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L704-L746)
- [55-ols_options.txt:1094-1273](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L1094-L1273)

### Nginx Performance Tuning
- Compression: Gzip inclusion and Brotli enablement per domain.
- HTTP/2: Enable/disable via regex-based edits to listen directives.
- Browser caching: Include-based strategy and type lists for graphics and scripts.
- Worker tuning: worker_processes and worker_connections directives.
- Security headers: HSTS, XSS Protection, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.

```mermaid
flowchart TD
Start(["Nginx Action"]) --> GzipCheck{"Enable Gzip?"}
GzipCheck --> |Yes| IncludeGzip["Include gzip conf"]
GzipCheck --> |No| SkipGzip["Skip"]
IncludeGzip --> BrotliCheck{"Enable Brotli?"}
SkipGzip --> BrotliCheck
BrotliCheck --> |Yes| EnableBrotli["Add brotli directives"]
BrotliCheck --> |No| SkipBrotli["Skip"]
EnableBrotli --> CacheCheck{"Enable Browser Cache?"}
SkipBrotli --> CacheCheck
CacheCheck --> |Yes| IncludeCache["Include browser cache conf"]
CacheCheck --> |No| SkipCache["Skip"]
IncludeCache --> WorkerCheck{"Tune Workers?"}
SkipCache --> WorkerCheck
WorkerCheck --> |Yes| SetWorkers["Set worker_processes/connections"]
WorkerCheck --> |No| SkipWorkers["Skip"]
SetWorkers --> HeadersCheck{"Security Headers?"}
SkipWorkers --> HeadersCheck
HeadersCheck --> |Yes| AddHeaders["Add HSTS/XSS/CTO/RP/PP"]
HeadersCheck --> |No| SkipHeaders["Skip"]
AddHeaders --> Validate["nginx -t"]
SkipHeaders --> Validate
Validate --> Reload{"Config OK?"}
Reload --> |Yes| ReloadNginx["systemctl reload nginx"]
Reload --> |No| ErrorNginx["Report Error"]
```

**Diagram sources**
- [34-nginx_options.txt:173-246](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L173-L246)
- [34-nginx_options.txt:824-915](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L824-L915)
- [34-nginx_options.txt:1210-1260](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1210-L1260)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)

**Section sources**
- [34-nginx_options.txt:173-246](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L173-L246)
- [34-nginx_options.txt:824-915](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L824-L915)
- [34-nginx_options.txt:1210-1260](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1210-L1260)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)

### HTTPS and HTTP/2 Management
- Enables/disables HTTPS via certbot integration for both Nginx and OpenLiteSpeed.
- Manages HTTP/2 toggles per domain for both web servers.
- Updates WordPress home/siteurl and flushes caches after enabling HTTPS.

```mermaid
sequenceDiagram
participant Operator as "Operator"
participant Script as "04-manage_https.txt"
participant Certbot as "Certbot"
participant WebServer as "Nginx/OpenLiteSpeed"
participant WP as "WordPress"
Operator->>Script : Choose action (enable/disable HTTPS or HTTP/2)
alt Enable HTTPS
Script->>Certbot : Issue certificate
Script->>WebServer : Configure SSL listener
Script->>WP : Update home/siteurl to https
Script->>WP : Flush caches
Script->>WebServer : Restart/reload
else Disable HTTPS
Script->>WebServer : Remove SSL listener
Script->>WP : Update home/siteurl to http
Script->>WP : Flush caches
else Toggle HTTP/2
Script->>WebServer : Modify listen directive for http2
Script->>WebServer : Restart/reload
end
```

**Diagram sources**
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [04-manage_https.txt:99-137](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L99-L137)
- [04-manage_https.txt:181-204](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L181-L204)

**Section sources**
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [04-manage_https.txt:99-137](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L99-L137)
- [04-manage_https.txt:181-204](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L181-L204)

### PHP Workers Tuning (Nginx)
- Interactively adjusts PHP-FPM pm settings (pm, pm.max_children, pm.start_servers, pm.min_spare_servers, pm.max_spare_servers).
- Validates parameter relationships before applying changes.
- Restarts PHP-FPM after successful updates.

```mermaid
flowchart TD
Start(["Select Domain"]) --> GetVersion["Detect PHP Version"]
GetVersion --> AskPM["Prompt for pm settings"]
AskPM --> Validate["Validate constraints"]
Validate --> |Valid| Apply["Apply pm directives"]
Validate --> |Invalid| Error["Exit with error"]
Apply --> Restart["Restart PHP-FPM"]
Restart --> Done(["Done"])
```

**Diagram sources**
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)

**Section sources**
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)

### Static Asset Optimization and Browser Caching
- OpenLiteSpeed: Uses LSCache options via WP CLI to manage browser cache behavior.
- Nginx: Includes centralized browser cache configuration files and allows adding/removing file types for graphics and scripts.

```mermaid
flowchart TD
Start(["Static Assets"]) --> Strategy{"Web Server"}
Strategy --> |OpenLiteSpeed| LSCache["wp litespeed-option set cache-browser 1"]
Strategy --> |Nginx| IncludeCache["include browsercache.conf"]
IncludeCache --> Types["Manage file types (graphics/scripts)"]
LSCache --> Done(["Caching Enabled"])
Types --> Done
```

**Diagram sources**
- [55-ols_options.txt:298-333](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L298-L333)
- [34-nginx_options.txt:1210-1260](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1210-L1260)

**Section sources**
- [55-ols_options.txt:298-333](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L298-L333)
- [34-nginx_options.txt:1210-1260](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1210-L1260)

### Connection Handling and Timeouts
- OpenLiteSpeed: Sets maxReqBodySize and connTimeout at the server level.
- Nginx: Allows setting worker_processes and worker_connections; base provisioning also tunes worker_rlimit_nofile and worker_connections.

```mermaid
flowchart TD
Start(["Connection Tuning"]) --> OLSConn{"OpenLiteSpeed?"}
OLSConn --> |Yes| SetMaxBody["Set maxReqBodySize"]
SetMaxBody --> SetConnTimeout["Set connTimeout"]
OLSConn --> |No| NginxConn["Nginx"]
NginxConn --> SetWorkers["Set worker_processes/connections"]
SetWorkers --> BaseTuning["Base: worker_rlimit_nofile, worker_connections"]
SetConnTimeout --> Done(["Optimized"])
BaseTuning --> Done
```

**Diagram sources**
- [55-ols_options.txt:1094-1273](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L1094-L1273)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)

**Section sources**
- [55-ols_options.txt:1094-1273](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L1094-L1273)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)

### SSL/TLS Optimization
- HTTPS enablement uses certbot integration for both Nginx and OpenLiteSpeed.
- HSTS header can be enabled per domain for both web servers.
- After enabling HTTPS, WordPress URLs are updated and caches flushed.

```mermaid
sequenceDiagram
participant Admin as "Admin"
participant Script as "HTTPS Script"
participant Certbot as "Certbot"
participant WebServer as "Web Server"
participant WP as "WordPress"
Admin->>Script : Enable HTTPS
Script->>Certbot : Obtain certificate
Script->>WebServer : Configure SSL
Script->>WP : Update home/siteurl to https
Script->>WP : Flush caches
Script->>WebServer : Restart/reload
Admin->>Script : Enable HSTS
Script->>WebServer : Add Strict-Transport-Security header
```

**Diagram sources**
- [04-manage_https.txt:99-137](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L99-L137)
- [34-nginx_options.txt:877-915](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L877-L915)
- [55-ols_options.txt:749-791](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L749-L791)

**Section sources**
- [04-manage_https.txt:99-137](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L99-L137)
- [34-nginx_options.txt:877-915](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L877-L915)
- [55-ols_options.txt:749-791](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L749-L791)

### CDN Integration Guidelines
- Use OpenLiteSpeed LSCache browser caching and server-side caching modules where applicable.
- For Nginx, leverage include-based browser cache configuration and consider offloading static assets to a CDN.
- Ensure cache-busting strategies for dynamic content and consistent cache headers.

[No sources needed since this section provides general guidance]

### Load Balancing Configurations
- Scale horizontally by provisioning multiple servers and distributing traffic via a load balancer.
- Tune PHP workers and connection limits per node to match expected concurrency.
- Monitor resource utilization and adjust worker_processes/connections accordingly.

[No sources needed since this section provides general guidance]

## Dependency Analysis
The scripts depend on common functions and system utilities to validate and reload services. They interact with:
- System services (systemctl) for restarting/reloading web servers and PHP.
- WP CLI for managing WordPress options and caches.
- Certbot for SSL certificate management.

```mermaid
graph TB
Common["Common Functions<br/>9999-common-functions.txt"] --> Prep["01-prepare_server.txt"]
Prep --> OLS["55-ols_options.txt"]
Prep --> Nginx["34-nginx_options.txt"]
Prep --> HTTPS["04-manage_https.txt"]
Prep --> PHPW["39-php_workers.txt"]
```

**Diagram sources**
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)

**Section sources**
- [01-prepare_server.txt:443-449](file://includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt#L443-L449)
- [55-ols_options.txt:163-230](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L163-L230)
- [34-nginx_options.txt:1457-1599](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L1457-L1599)
- [04-manage_https.txt:16-67](file://includes/core/apps/wordpress-app/scripts/v1/raw/04-manage_https.txt#L16-L67)
- [39-php_workers.txt:117-264](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L117-L264)

## Performance Considerations
- Prefer OpenLiteSpeed for integrated caching and performance features; use Nginx only when necessary due to legacy support.
- Enable compression (Gzip/Brotli) based on workload characteristics and client support.
- Use HTTP/2 for improved multiplexing and header compression where SSL is enabled.
- Configure browser caching for static assets and leverage CDN for global distribution.
- Tune PHP workers to match CPU cores and memory constraints; monitor process counts under load.
- Set appropriate request body sizes and connection timeouts to prevent bottlenecks.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- Configuration validation failures: Scripts test configs before reloading; check error messages and revert changes if needed.
- Service reload issues: Verify systemctl status and logs for web servers and PHP-FPM.
- WordPress cache inconsistencies: Flush caches after HTTPS changes and configuration updates.
- Permission errors: Ensure correct ownership for .htaccess and vhost directories.

**Section sources**
- [55-ols_options.txt:56-69](file://includes/core/apps/wordpress-app/scripts/v1/raw/55-ols_options.txt#L56-L69)
- [34-nginx_options.txt:41-49](file://includes/core/apps/wordpress-app/scripts/v1/raw/34-nginx_options.txt#L41-L49)
- [39-php_workers.txt:30-48](file://includes/core/apps/wordpress-app/scripts/v1/raw/39-php_workers.txt#L30-L48)

## Conclusion
The repository provides robust, script-driven mechanisms to optimize web server performance for WordPress deployments. By leveraging OpenLiteSpeed’s native capabilities and Nginx’s flexible configuration, operators can tune compression, HTTP/2, browser caching, SSL/TLS, and PHP workers effectively. Adhering to the provided workflows ensures safe, validated changes with minimal downtime.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices
- Page cache wrapper script for Nginx operations.

**Section sources**
- [manage_nginx_pagecache.txt:1-9](file://includes/core/apps/wordpress-app/scripts/v2/manage_nginx_pagecache.txt#L1-L9)