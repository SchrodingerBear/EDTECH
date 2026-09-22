#!/usr/bin/env python3
"""Analyze view files for CSS organization issues.

Detects per HTML/PHP view file:
  - inline style="..." attributes
  - embedded <style> blocks
  - external stylesheet <link> tags (framework vs custom)
  - framework class usage (bootstrap / tailwind) vs unknown classes
"""
import os
import re
import sys
from collections import Counter

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

IGNORE_DIRS = {
    "old",
    "manager/vfm-admin",
    "manager",  # vfm-admin is third-party; keep whole dir excluded
    ".git",
    "node_modules",
    "vendor",
    "storage",
    ".cursor",
    "database",
    "includes",
    "tools",
}

VIEW_EXTS = {".php", ".html", ".htm", ".phtml"}

FRAMEWORK_CSS = re.compile(
    r"(bootstrap|tailwind|windicss|unocss|pico|bulma|foundation|semantic-ui|materialize)",
    re.IGNORECASE,
)

INLINE_STYLE = re.compile(r"\bstyle\s*=\s*(\"[^\"]*\"|'[^']*')", re.IGNORECASE)
STYLE_BLOCK = re.compile(r"<style[^>]*>(.*?)</style>", re.IGNORECASE | re.DOTALL)
LINK_CSS = re.compile(r"<link[^>]*rel\s*=\s*[\"']stylesheet[\"'][^>]*>", re.IGNORECASE)
HREF_EXTRACT = re.compile(r'href\s*=\s*("[^"]*"|\'[^\']*\')', re.IGNORECASE)
OPENING_TAG = re.compile(r"<\s*(\w[\w-]*)((?:\"[^\"]*\"|'[^']*'|[^>\"'])*)>", re.IGNORECASE)
CLASS_ATTR = re.compile(r'class\s*=\s*(["\'])(.*?)\1', re.IGNORECASE | re.DOTALL)

BOOTSTRAP_RE = re.compile(
    r"""^(container(-fluid)?|row|col(-[a-z\d-]*)?|g-|gx-|gy-|gap-|
        m[tebsxy]?-|mt-|mb-|ms-|me-|px-|py-|pt-|pb-|ps-|pe-|p-|
        btn(-[a-z-]*)?|card(-[a-z-]*)?|nav(-[a-z@]+)?|navbar(-[a-z@-]*)?|
        modal(-[a-z-]*)?|dropdown(-[a-z-]*)?|accordion(-[a-z-]*)?|alert(-[a-z-]*)?|
        badge(-[a-z-]*)?|table(-[a-z@]*)?|form-(control|label|select|check|range|text)(-|$)|input-group(-text)?|
        list-group(-item)?|breadcrumb(-item)?|pagination(-[a-z-]*)?|progress(-bar)?|spinner-(border|grow)|
        carousel(-[a-z-]*)?|collapse(ing)?|toast(-[a-z-]*)?|tooltip|popover|offcanvas(-[a-z-]*)?|placeholder|
        d-|w-|h-|vw-|vh-|min-vw|min-vh|text-|bg-|border-|rounded(-[a-z]*)?|shadow(-[a-z]*)?|flex(-[a-z@-]*)?|
        align-(start|end|center|baseline|stretch|items-start|items-end|items-center|items-baseline|items-stretch|
        content-start|content-end|content-center|content-between|content-around|content-stretch|self-
        auto|start|end|center|baseline|stretch)|justify-content-(start|end|center|between|around|evenly)|
        float-(start|end|none)|clearfix|overflow-(auto|hidden|visible|scroll)|position-(static|relative|absolute|
        fixed|sticky)|top-(0|50|100)|bottom-(0|50|100)|start-(0|50|100)|end-(0|50|100)|translate-middle|
        chain|stretched-link|object-fit-(contain|cover|fill|scale-down|none)|ratio|vr|opacity-\d|visible|invisible|
        user-select-|pe-none|pointer-event-(none|auto)|fst-|fw-|lh-|link-(primary|secondary|success|danger|warning|
        info|light|dark|body|muted)|sticky-top|fixed-top|fixed-bottom|visually-hidden|d-print-|btn-close|vstack|hstack|
        tab-content|tab-pane|fade|show|active|disabled|aria-|role-|shadow-lg|shadow-sm|rounded-pill)""",
    re.IGNORECASE | re.VERBOSE,
)
TAILWIND_RE = re.compile(
    r"""^(flex|grid|block|inline|inline-block|hidden|absolute|relative|fixed|sticky|static|
        items-(start|end|center|baseline|stretch)|justify-(start|end|center|between|around|evenly)|
        content-(start|end|center|between|around|evenly|stretch)|self-(auto|start|end|center|stretch)|
        w-(auto|full|screen|min|max|\d+.*)|h-(auto|full|screen|min|max|\d+.*)|
        p[trblxy]?-(0|1|2|3|4|5|6|8|10|12|16|20|24|px|auto)|m[trblxy]?-(0|1|2|3|4|5|6|8|10|12|16|20|24|px|auto)|
        gap[-xy]?-\d+|text-(xs|sm|base|lg|xl|\d+xl|[a-z-]+)|bg-(white|black|gray-\d+|slate-\d+|[a-z-]+)|
        border(-[trblxy]?)?(-2|-4|-8|-0|-[a-z-]+)?|rounded(-[trblxy]{1,2})?(-(sm|md|lg|xl|full|none))?|
        shadow(-(sm|md|lg|xl|2xl|inner|none))?|overflow-(auto|hidden|visible|scroll|x-auto|y-auto)|
        z-(0|10|20|30|40|50|auto)|object-(cover|contain|fill|none|scale-down)|opacity-(0|25|50|75|100|5|10|20|90)|
        transition|duration-\d+|ease-(in|out|in-out)|animate-(spin|ping|pulse|bounce|none)|cursor-(pointer|default|not-allowed|grab)|
        select-(none|text|all|auto)|whitespace-(nowrap|pre|normal)|break-(words|all)|leading-\d|tracking-\w+|
        font-(thin|light|normal|medium|semibold|bold|extrabold|black)|underline|line-through|italic|uppercase|lowercase|capitalize|
        grid-cols|col-span|row-span|aspect-(square|video|auto)|max-w-|min-w-|max-h-|min-h-|
        space-|divide-|placeholder-|group|peer|sr-only|not-sr-only|backdrop-|blur|brightness|contrast|grayscale|invert|saturate|sepia|filter|transform)""",
    re.IGNORECASE | re.VERBOSE,
)


def scan(path):
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as f:
            content = f.read()
    except OSError:
        return None

    # Strip PHP blocks so their `?>` / `->` don't corrupt HTML attribute parsing
    content = re.sub(r"<\?(?:php)?\s.*?\?>", "", content, flags=re.DOTALL)
    content = re.sub(r"<\?(?:php)?\s.*", "", content, flags=re.DOTALL)

    inline = INLINE_STYLE.findall(content)
    style_blocks = STYLE_BLOCK.findall(content)
    links = LINK_CSS.findall(content)

    ext_css = []
    for link in links:
        m = HREF_EXTRACT.search(link)
        if m:
            href = m.group(1).strip("\"'")
            if not href.lower().startswith(("javascript:", "data:")):
                ext_css.append(href)

    has_bootstrap = False
    has_tailwind = False
    framework_count = 0

    link_lines = []
    for href in ext_css:
        is_fw = bool(FRAMEWORK_CSS.search(href))
        is_cdn = href.lower().startswith(("http://", "https://", "//"))
        if is_fw:
            has_bootstrap = has_bootstrap or "bootstrap" in href.lower()
            has_tailwind = has_tailwind or "tailwind" in href.lower()
            framework_count += 1
            link_lines.append((href, "framework"))
        elif is_cdn:
            link_lines.append((href, "library"))
        else:
            link_lines.append((href, "custom"))

    # scan classes across whole file
    class_counter = Counter()
    for _, cls in CLASS_ATTR.findall(content):
        for c in cls.split():
            class_counter[c.strip()] += 1

    def occ(match):
        return sum(n for c, n in class_counter.items() if match(c))

    boot_classes = occ(lambda c: BOOTSTRAP_RE.match(c))
    tw_classes = occ(lambda c: TAILWIND_RE.match(c) and not BOOTSTRAP_RE.match(c))
    custom_classes = occ(
        lambda c: not BOOTSTRAP_RE.match(c) and not TAILWIND_RE.match(c)
    )
    total_classes = sum(class_counter.values())

    return {
        "path": os.path.relpath(path, ROOT),
        "inline_count": len(inline),
        "inline_bytes": sum(len(s) for s in inline),
        "style_blocks": len(style_blocks),
        "style_block_bytes": sum(len(b) for b in style_blocks),
        "external_css": ext_css,
        "ex_framework": link_lines,
        "classes": total_classes,
        "bootstrap_classes": boot_classes,
        "tailwind_classes": tw_classes,
        "custom_classes": total_classes - boot_classes - tw_classes,
    }


def main():
    scope = sys.argv[1] if len(sys.argv) > 1 else ""
    results = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [
            d
            for d in dirnames
            if d not in IGNORE_DIRS
            and not any(d == ig.split("/")[-1] for ig in IGNORE_DIRS)
        ]
        for fn in filenames:
            if os.path.splitext(fn)[1].lower() in VIEW_EXTS:
                full = os.path.join(dirpath, fn)
                rel = os.path.relpath(full, ROOT).replace(os.sep, "/")
                if scope and scope not in rel:
                    continue
                r = scan(full)
                if r:
                    results.append(r)

    results.sort(key=lambda r: r["inline_bytes"] + r["style_block_bytes"], reverse=True)

    print("=" * 90)
    print("CSS ORGANIZATION REPORT  (inline / embedded / external / framework classes)")
    print("=" * 90)

    print("\n--- 1) FILES WITH INLINE style=\"\" (totally not externalized) ---")
    inline_files = [r for r in results if r["inline_count"]]
    if inline_files:
        print(f"{'FILE':<60} {'#':>4} {'bytes':>7}")
        print("-" * 90)
        for r in inline_files:
            print(f"{r['path']:<60} {r['inline_count']:>4} {r['inline_bytes']:>7}")
    else:
        print("  (none)")

    print("\n--- 2) FILES WITH EMBEDDED <style> BLOCKS (not externalized) ---")
    emb_files = [r for r in results if r["style_blocks"]]
    if emb_files:
        print(f"{'FILE':<60} {'#':>4} {'bytes':>7}")
        print("-" * 90)
        for r in emb_files:
            print(f"{r['path']:<60} {r['style_blocks']:>4} {r['style_block_bytes']:>7}")
    else:
        print("  (none)")

    print("\n--- 3) FILES LINKING CUSTOM CSS (non-framework .css files) ---")
    for r in results:
        custom = [h for h, kind in r["ex_framework"] if kind in ("custom", "library")]
        if custom:
            print(f"\n{r['path']}")
            for h in custom:
                kind = next((k for hh, k in r["ex_framework"] if hh == h), "?")
                tag = "LOCAL-CUSTOM" if kind == "custom" else "3RD-PARTY"
                print(f"    [{tag}] {h}")

    print("\n--- 4) FRAMEWORK ADOPTION (bootstrap / tailwind / custom classes) ---")
    print(f"{'FILE':<60} {'total':>7} {'boot':>6} {'tw':>5} {'custom':>8}")
    print("-" * 90)
    for r in results:
        if r["classes"]:
            print(
                f"{r['path']:<60} {r['classes']:>7} {r['bootstrap_classes']:>6} "
                f"{r['tailwind_classes']:>5} {r['custom_classes']:>8}"
            )

    print("\n--- SUMMARY ---")
    total = len(results)
    n_inline = len(inline_files)
    n_embed = len(emb_files)
    n_custom_link = sum(1 for r in results if any(k == "custom" for _, k in r["ex_framework"]))
    n_clean = sum(1 for r in results if not r["inline_count"] and not r["style_blocks"])
    print(f"view files scanned       : {total}")
    print(f"with inline style attr   : {n_inline}")
    print(f"with embedded <style>    : {n_embed}")
    print(f"with BOTH inline+<style> : {sum(1 for r in results if r['inline_count'] and r['style_blocks'])}")
    print(f"no inline/embedded css   : {n_clean}")
    print(f"linking LOCAL custom css : {n_custom_link}")


if __name__ == "__main__":
    main()