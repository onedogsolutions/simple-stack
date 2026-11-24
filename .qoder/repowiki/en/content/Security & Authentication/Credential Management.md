# Credential Management

<cite>
**Referenced Files in This Document**
- [Encryption utilities](file://includes/core/simple-stack.php)
- [SPA secret redactor](file://includes/core/api/class-sstk-secret-redactor.php)
- [Provider REST controller](file://includes/core/api/class-sstk-rest-providers.php)
- [Provider API base](file://includes/core/providers/class-cloud-provider-api.php)
- [Setup wizard](file://includes/core/class-sstk-setup-wizard.php)
- [React API client](file://src/admin/lib/api.js)
</cite>

## Scope

Simple Stack stores provider credentials, SSH private keys and other sensitive settings in
WordPress options. The supported providers are Vultr API v2 and UpCloud API 1.3. Credentials are
encrypted before storage, decrypted only when a provider or SSH operation needs them, and never
included in provider-state responses.

The setup wizard recommends `SSTK_ENCRYPTION_KEY` and blocks its own progression until the
constant is defined. Runtime encryption also has a persistent fallback key for requests that do
not pass through the wizard.

## Encryption contract

`SSTK::encrypt()` and `SSTK::decrypt()` implement one format only:

- Prefix: `sstk-encrypt-v2:`.
- Cipher: AES-128-CBC with a fresh random IV for every encryption.
- Integrity: HMAC-SHA256 over the IV and ciphertext, compared with `hash_equals()`.
- Encoding: base64 of `IV || HMAC || ciphertext`.
- Key precedence: explicit method argument, then `SSTK_ENCRYPTION_KEY`, then the
  `sstk_encryption_key_v2` option. If no key exists, encryption generates and stores one.

Decryption rejects values without the v2 prefix, malformed payloads, invalid lengths and failed
HMAC checks by returning an empty string. There is no v1 decryption or automatic legacy migration.
Changing or removing the key makes existing v2 ciphertext unreadable; operators must re-enter the
affected credentials after a key change.

## Provider credential shapes

| Provider | Authentication | Stored options |
|---|---|---|
| Vultr v2 | Bearer API key | `sstk_vultr-v2_apikey` |
| UpCloud 1.3 | HTTP Basic user name and password | `sstk_upcloud_user_name`, `sstk_upcloud_user_password` |

SSH private keys are encrypted before they are written to provider settings. Empty credential
fields mean “leave the stored value unchanged”; they do not erase an existing secret.

The React client saves credentials with `POST /settings/providers` and tests them with
`POST /settings/providers/{slug}/test`. There is no `/settings/providers/save_provider` route.
Provider reads return presence booleans such as `has_credentials`, `has_api_key`,
`has_user_password` and `has_ssh_key`, plus feature flags and cached region/size counts. Raw
credential values are not returned.

## REST redaction boundary

`SSTK_REST_Controller_Base::respond()` applies `SSTK_Secret_Redactor::redact()` to SPA controller
payloads before constructing the `{ ok, data, meta }` response. The redactor:

- masks values whose keys look like passwords, secrets, tokens, private material,
  `Authorization`, API keys or other key-shaped fields;
- scrubs PEM private-key blocks and common credential assignments from strings;
- converts objects to arrays before recursion; and
- blanks stored defaults for password-type tab fields.

This guarantee is scoped to controllers extending `SSTK_REST_Controller_Base`. The WordPress App
REST controller family and the two public callbacks do not automatically pass through this
redactor. Operational log scrubbing is a separate concern; documentation must not claim that every
log or every REST response in the plugin is processed by this class.

## Handling rules

- Never place plaintext root passwords, private SSH keys, provider tokens or encryption keys in
  command output, debug logs, error logs or REST responses.
- Read secrets through `SSTK::decrypt()` at the point of use; do not add plaintext copies to
  post meta or transients.
- Use `sstk_get_early_option()` for settings consumed outside an active Meta Box settings screen.
- Treat a failed decryption as unavailable credentials, not as permission to continue with an empty
  secret.
- Back up `SSTK_ENCRYPTION_KEY` securely. The database fallback key is not a substitute for a
  reviewed backup and key-rotation procedure.
