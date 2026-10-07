# Routing fixes

Existing `Route::get/post/put/delete/restful/group` calls remain supported. `Route::run()` now returns the handler result through all middleware layers. The application entry point continues to support handlers that echo their output; returned response rendering remains the responsibility of its caller.

- Matching compiles registered paths, quotes literal regex characters, and captures any number of nonempty parameters. Static routes take priority. Parameters are URL-decoded only after matching and passed positionally to closures and controller methods.
- Query strings are removed before slash normalization; leading/trailing slashes are normalized consistently, including the root path.
- Global, outer group, inner group and route middleware wrap the real handler in that order. Middleware must call and return `$next()` to continue; returning without calling it stops dispatch. CSRF middleware retains its validation and now returns the downstream response.
- Groups support `prefix` and `middleware`, including nesting and cleanup after exceptions. Middleware accepts an alias/class or a list; unknown aliases fail explicitly rather than silently skipping protection.
- Controller constructors receive no dummy array. Concrete class dependencies are recursively resolved; default arguments are respected, and unresolved scalar/interface dependencies and cycles fail explicitly. Request injection applies to a Request type in the first action parameter and preserves all path arguments. Actions must be public.
- Route metadata is updated/reset per dispatch. Missing paths return false with HTTP 404; unsupported methods return false with HTTP 405 and an Allow header. HEAD falls back to GET matching; suppression of the response body belongs to the HTTP server/response layer.

Tests: `php tests/routes.php` and `php tests/regression.php`. Tests use isolated handlers and temporary data; no business database is touched.
