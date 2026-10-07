# Security and correctness fixes

- Session IDs are validated, generated with `random_bytes`, and retained during the first request. Invalid or unknown cookies receive a new ID. Session files are confined to the session directory; objects are not instantiated by deserialization. Cookies use HttpOnly, SameSite=Lax and Secure on HTTPS.
- The model stores structured conditions. All built-in database drivers bind values and validate identifiers and limits. Conditional deletion uses the same representation as selection and update. Multiple selected fields use `implode`.
- CSRF protection is enabled globally. Safe requests generate a stable token. POST, PUT, DELETE and other unsafe requests require a nonempty matching token in `csrf_token` or `X-CSRF-Token`; failures return HTTP 403. Forms should include `csrf_token()` in a hidden field. The legacy `csrf()` helper uses the same token store.

## Compatibility

- Raw WHERE strings are deliberately rejected. Use `$model->where('id', $id)` or `[['column' => 'id', 'value' => $id, 'boolean' => 'AND']]`; the legacy `['column' => ..., 'value' => ...]` form is also supported.
- Explicit raw SQL remains available through `query($sql, $params)` and `exec($sql, $params)`. Use placeholders for all external values.
- Update and delete require conditions. Order arguments map validated column names to ASC or DESC; identifiers accept simple names, without raw expressions.
- SQLite now uses PDO SQLite. SQL Server requires PDO SQLSRV. MySQL requires mysqli/mysqlnd. Session MySQL storage requires a unique `key` column for upserts; Redis requires the Redis extension.
- Existing Session IDs with 32 hexadecimal characters remain readable. Serialized objects are no longer reconstructed. Scoped session values now work consistently.
- PHP 7.3 or later is required for the cookie options API. Custom database drivers must implement the updated Database interface, including optional parameter arrays and delete.

## Validation

Run `php tests/regression.php`. Tests use an in-memory SQLite database and a temporary Session directory, never application tables or Session files. Live MySQL, PostgreSQL, SQL Server and Redis services are not required by these tests.
