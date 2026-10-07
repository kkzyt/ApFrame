# Controller and template fixes

- Controller instance caches are keyed by the concrete class. Normal route dispatch still constructs controllers per invocation.
- `view()` returns HTML without emitting it. View data is extracted with EXTR_SKIP in a static rendering scope, so it cannot override compiler paths or access compiler state. View names use dot-separated simple components and must resolve within the view directory.
- Template caches compare source and compiled timestamps. Fresh caches are reused, repeat rendering uses require rather than require_once, and compilation is published atomically. Exceptions clean up the rendering output buffer.
- The web entry point sends returned text or stringable responses, preserves legacy echo handlers, suppresses HEAD bodies and ignores boolean/null sentinels. Arrays must be explicitly JSON/XML encoded; content type headers are the application's responsibility.
- `redirect($url, $status = 302)` stops action execution by throwing a dedicated Redirect exception. The web entry point discards buffered output and sends the Location header/status. Direct callers of `Init::exec()` must handle that exception themselves. Do not mix echo and return for the same content, or catch Redirect inside an action and continue.
- Demo `test::index` returns the escaped path name, and `test::all` returns demo text instead of debug exits/password output. Empty CRUD examples remain placeholders; no business operations were invented.

Run `php tests/controllers.php`, `php tests/routes.php`, and `php tests/regression.php`. Controller tests use a temporary view/cache directory and fake-app subprocesses to validate the real entry file without touching business data.
