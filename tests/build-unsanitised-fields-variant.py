#!/usr/bin/env python3
"""Build the "weakened" variant of the Portfolio Studio editor panel.

The variant is the current panel with exactly two changes: the FIRST cleaning of
the client and of the role is removed — `sanitize_text_field()` is no longer
called before the value reaches the sanitiser of the meta key. Everything else
is byte for byte identical, so the red proof isolates one cause: the double
cleaning of the write path.

Why this variant exists
-----------------------
The security suite asserts that the write path of the panel has the shape
`Portfolio_Studio_Meta::sanitize_client( sanitize_text_field( wp_unslash( ... ) ) )`.
A test that cannot fail proves nothing: this variant removes the cleaning, and
the suite MUST fail against it.

The values that reach the database do NOT change (the sanitiser of the meta key
still cleans them): what the suite catches here is the SHAPE of the write path,
which is exactly what the WordPress.org static analyser looks at.

    python3 -I tests/build-unsanitised-fields-variant.py [source] [destination]

Exit code 0 when the variant was written, 1 otherwise.
"""

import hashlib
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)

SRC = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    REPO, "portfolio-studio", "includes", "class-portfolio-studio-fields.php"
)
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(
    HERE, "build", "class-portfolio-studio-fields.php"
)

REPLACEMENTS = [
    # The client: the WordPress sanitiser is removed from the write path.
    (
        "Portfolio_Studio_Meta::sanitize_client( sanitize_text_field( "
        "wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_CLIENT ] ) ) )",
        "Portfolio_Studio_Meta::sanitize_client( "
        "wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_CLIENT ] ) ) "
        "// BUGGY VARIANT: the first cleaning was removed.",
    ),
    # The role: same.
    (
        "Portfolio_Studio_Meta::sanitize_role( sanitize_text_field( "
        "wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_ROLE ] ) ) )",
        "Portfolio_Studio_Meta::sanitize_role( "
        "wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_ROLE ] ) ) "
        "// BUGGY VARIANT: the first cleaning was removed.",
    ),
]


def main() -> int:
    if not os.path.isfile(SRC):
        print("build-unsanitised-fields-variant: source introuvable: %s" % SRC)
        return 1

    with open(SRC, "r", encoding="utf-8") as handle:
        source = handle.read()

    out = source
    for old, new in REPLACEMENTS:
        if old not in out:
            print("build-unsanitised-fields-variant: motif introuvable: %r" % old)
            return 1
        out = out.replace(old, new)

    # The nonce is still cleaned: only the write path of the details was weakened.
    if out.count("sanitize_text_field( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_CLIENT ] ) )"):
        print("build-unsanitised-fields-variant: le client est encore assaini")
        return 1
    if out.count("sanitize_text_field( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_ROLE ] ) )"):
        print("build-unsanitised-fields-variant: le role est encore assaini")
        return 1

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as handle:
        handle.write(out)

    print("variante affaiblie ecrite : %s" % OUT)
    print("md5 source   : %s" % hashlib.md5(source.encode("utf-8")).hexdigest())
    print("md5 variante : %s" % hashlib.md5(out.encode("utf-8")).hexdigest())
    return 0


if __name__ == "__main__":
    sys.exit(main())
