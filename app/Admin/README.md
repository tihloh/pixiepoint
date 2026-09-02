# Admin feature architecture

PixiePoint admin code is organized by feature, not by file type.

Each feature owns its page-specific controller, models/services/repositories when needed, routes, and views. Code that conceptually belongs to a feature stays in that feature even when another part of PixiePoint uses it. External PHP callers should use the feature's public `Api` class when one exists instead of reaching into its internal repository/service classes.

Only genuinely cross-cutting infrastructure belongs in global `app/Services`, `app/Shared`, or `app/Api`.

A feature does not need to contain every possible layer. Add files only when the feature actually needs them.
