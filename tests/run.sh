#!/bin/sh
# No WordPress needed: the hero slider is loaded against stubs and run over a
# real temporary uploads directory.
exec php "$(dirname "$0")/test-hero-sources.php"
