#!/usr/bin/env python3
"""Build the "unsanitised" variant of the Portfolio Studio metadata class.

The variant is the current class with exactly three changes: the three text
sanitisers stop calling `sanitize_text_field()`. Everything else is byte for byte
identical, so the red proof isolates one cause: the sanitisation.

The generated file is used by `run-counter-proof.sh`: the security suite MUST fail
against it. A test that cannot fail proves nothing.

    python3 -I tests/build-unsanitised-variant.py [source] [destination]

Exit code 0 when the variant was written, 1 otherwise.
"""

import hashlib
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)

SRC = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    REPO, "portfolio-studio", "includes", "class-portfolio-studio-meta.php"
)
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(
    HERE, "build", "class-portfolio-studio-meta.php"
)

REPLACEMENTS = [
    # sanitize_client() and sanitize_role(): the text is stored as it was typed.
    (
        "\t\treturn sanitize_text_field( (string) $value );",
        "\t\treturn (string) $value; // BUGGY VARIANT: the sanitisation was removed.",
    ),
    # sanitize_technologies(): same, for every item of the list.
    (
        "\t\t\t$item = sanitize_text_field( (string) $item );",
        "\t\t\t$item = (string) $item; // BUGGY VARIANT: the sanitisation was removed.",
    ),
]


def main() -> int:
    if not os.path.isfile(SRC):
        print("build-unsanitised-variant: source introuvable: %s" % SRC)
        return 1

    with open(SRC, "r", encoding="utf-8") as handle:
        source = handle.read()

    out = source
    for old, new in REPLACEMENTS:
        if old not in out:
            print("build-unsanitised-variant: motif introuvable: %r" % old)
            return 1
        out = out.replace(old, new)

    if "sanitize_text_field" in out:
        print("build-unsanitised-variant: il reste un appel a sanitize_text_field()")
        return 1

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as handle:
        handle.write(out)

    print("variante non assainie ecrite : %s" % OUT)
    print("md5 source   : %s" % hashlib.md5(source.encode("utf-8")).hexdigest())
    print("md5 variante : %s" % hashlib.md5(out.encode("utf-8")).hexdigest())
    return 0


if __name__ == "__main__":
    sys.exit(main())
