# Monitorix Statistics Visualization

## Status: retired

Monitorix is not part of the Simple Stack 1.0.0 stack. The inherited Monitorix integration was removed during the OpenLiteSpeed-only cutover, and there is no compatibility layer or replacement provisioning path.

The current WordPress app bootstrap does not load a Monitorix server-tab controller. There is also no active Monitorix wrapper or raw provisioning script under `includes/core/apps/wordpress-app/scripts/v2/`.

## Operational impact

- No Monitorix tab or action is registered in the WordPress admin.
- Simple Stack does not install, configure, upgrade, secure, or remove Monitorix.
- No Monitorix credentials, domains, SSL state, or component metadata are managed.
- Existing historical documentation that describes an Nginx proxy workflow is obsolete.

Operators who need historical system statistics should use the supported Monit integration or the retained Netdata script bridge, or manage a separate monitoring service outside Simple Stack.

## Related implementation

- [WordPress app server-tab bootstrap](../../Core%20Components/Plugin%20Bootstrap%20%26%20Initialization.md)
- [Monit service monitoring](Monit%20Service%20Monitoring.md)
- [Netdata real-time monitoring](Netdata%20Real-time%20Monitoring.md)
- [Simple Stack operator documentation](../../../../../../docs/SIMPLESTACK.md)
