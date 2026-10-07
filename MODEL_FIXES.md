# Model fixes

- Query conditions, limits and field projections reset after every terminal operation, including exceptions. `find` preserves list-of-rows output and limits to one record. `getAll` intentionally ignores filters, as before.
- Pending write fields use an initialized array. Property and ArrayAccess methods share storage; missing values return null, explicit null overrides loaded values, and isset follows PHP null semantics. Writes require named keys.
- Create/update validate nonempty data and clear pending writes afterwards, including on failure. Update/delete require explicit conditions. Failed writes must be retried by setting their values and conditions again.
- Single-result reads hydrate attributes. Multiple results and missing results clear the current record. Reads do not clear unsaved write fields. Updates/delete clear loaded record data; a fresh find is required for later relations.
- `hasOne(Related::class, 'foreign_key', 'optional_local_key')` returns one row or null; `hasMany(...)` returns a row list. Both instantiate the related model and use the current loaded/set local key, defaulting to primary_key. Missing local keys fail explicitly. Existing association methods were broken, so this return contract is now defined. New inserts with generated IDs must be reloaded or assigned an ID before querying associations.
- `get`, `getAll`, and `find` continue returning arrays. They do not implicitly return Collection objects or perform writes from array access.

Tests: `php tests/models.php` uses in-memory SQLite and treats runtime warnings as failures. Run the existing regression, routes and controllers suites too. No live application database is modified.
