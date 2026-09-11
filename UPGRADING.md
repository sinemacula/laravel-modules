# Upgrading

Notes for moving an application between minor versions. Each section covers what changed in the package and what,
if anything, the application has to do about it. Versions before 1.2.0 are not covered.

Nothing here is required to make the package boot; the entries that need an action say so explicitly, and there are
only two of them.

## 1.3.x to 1.4.0

### Rebuild the event cache once

Listener discovery derives a class name by stripping the application base path off each listener's real path. The
package canonicalises that base path from 1.4.0, so a base path spelled any other way now resolves. Before that, a
non-canonical base path stripped nothing, the derived class name came out wrong, and the framework swallowed the
failure, which left module listeners silently unregistered.

The most common way to hit it was `APP_BASE_PATH` pointing at a symlinked release directory. The documented
`dirname(__DIR__)` form was never affected, because PHP resolves `__DIR__` itself.

If any deploy ran `optimize` or `event:cache` while that was happening, the cached event file it wrote has the module
listeners missing from it, and upgrading does not rewrite that file. Run it once after deploying:

```bash
php artisan optimize
```

`module:clear` alone is not enough, since it only removes the module manifest. Applications that never cached events,
or whose base path was already canonical, need nothing.

One side effect worth knowing: `base_path()` now returns the resolved path. An application that passed a
non-canonical base path and compared `base_path()` against a string of its own will see the resolved spelling.

### A module prefix naming nothing is now an error

`resource_path()` accepts a `{module}::{path}` prefix. Previously a prefix naming a module that did not exist, or one
with no `Resources/` directory, fell through to the application resources directory, so a typo returned a real path to
the wrong place. It now raises `ModuleException`:

```text
Unknown module [billling].
Module [billing] has no resources directory.
```

The unprefixed form is unchanged and still falls back, which is what an application with no default module relies on.
If any code passes a prefix built from a variable, check it names a module that exists.

### Factories resolve by module

Module models now resolve to a factory grouped by module alone, so `App\Billing\Models\Invoice` and
`App\Billing\Invoice` both resolve to `Database\Factories\Billing\InvoiceFactory`. The `Models` segment is not part of
the factory name.

A factory already sitting where Laravel's default would put it keeps being used, so existing factories are not
orphaned and no migration is needed. A `#[UseFactory]` attribute on the model still wins over both. Models outside a
module are untouched.

`make:factory` guesses a model name without knowing about modules, so pass one explicitly:

```bash
php artisan make:factory Billing/InvoiceFactory --model "App\Billing\Models\Invoice"
```

### A seam for tests that boot more than one application

`Modules::setBasePath()` now accepts null, which returns the resolver to its uninitialised state, discarding the base
path along with the memoised maps. `Modules::flush()` still keeps the base path, which is what the caching commands
resolve against after they flush, so neither changes behaviour for an application that boots once.

An application whose own test suite boots several modular applications in a process should pass null in teardown:
leaving a base path behind makes the next boot depend on the order the tests ran in.

### Generated modules changed shape

`module:make` emits stubs conforming to the current coding standards. Existing modules are untouched; only newly
generated ones differ.

## 1.2.x to 1.3.0

### View paths that do not exist are dropped

A configured view path naming a directory that does not exist is pruned rather than kept. An application whose default
module has a `Resources` directory but no `views` child inside it previously carried a path to a missing directory.

### Module state can be reset through the public API

`Modules::flush()` discards the memoised module map. It is the supported seam for an application whose own tests boot
more than one modular application in a process, replacing reflection into private statics. The base path is left as it
is: it is a typed static with no default, so it cannot be returned to its uninitialised state once set.

## 1.1.x to 1.2.0

### The module manifest changed format, and rebuilds itself

The cached manifest now records a signature of the modules directory it was built from, and is discarded once that
directory no longer matches. This is what stops `optimize` building route and event caches from a superseded module
set.

A manifest written by 1.1.x has the old shape and is ignored, so the first boot after upgrading falls back to
discovery and behaves correctly. Nothing has to be cleared by hand. The next `module:cache` or `optimize` writes the
new format.

### Module order is deterministic

Discovery previously returned modules in filesystem order, which differs between machines and filesystems. It is now
sorted, so route registration order is stable everywhere. An application with two modules registering overlapping route
patterns may see a different one win than it did on a particular machine, since Laravel binds the first match. Order
now matches everywhere rather than varying between a developer machine, CI and production.

### Module directories must be real directories

A module directory that does not resolve to a real directory under `<base>/modules`, such as a symlink pointing
elsewhere, now raises `ModuleException` at discovery. It previously half-worked: routes, views, translations and
schedules resolved, while console commands and event listeners silently did not, because the framework derives those
class names from each file's real path.

A clear error is better than half a module. If any module directory is a symlink, move the real directory under
`modules/`.

### The base path is canonicalised

`Modules::setBasePath()` resolves the path it is given, so a trailing slash or a symlinked spelling agrees with the
paths discovery returns. Memoised state is discarded when the resolved value actually changes, and setting the same
path again is a no-op.

### module:make scaffolds the full set of conventions

Generated modules now include the resource and schedule directories the resolver reads, so a new module can hold views,
translations and a schedule without a manual `mkdir`.

The default module is deliberately scaffolded without them. A `Resources/lang` directory inside it becomes
`lang_path()`, which would orphan the framework's own published translations, and a `Resources` directory inside it
becomes `resource_path()`. Create either only when that is what you want.
