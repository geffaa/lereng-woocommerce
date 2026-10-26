"""Lists every translatable string in the plugin and theme (gettext calls in PHP, hard-coded
labels in block templates, user-facing strings in JS marked with t('…')). Used by i18n-build.py
to report missing Indonesian translations."""
import re, json, pathlib, sys
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'wp-content'
SQ = r"'((?:[^'\\]|\\.)*)'"
CALL = re.compile(r"\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x|_ex|esc_html_x|esc_attr_x)\(\s*" + SQ)
PLURAL = re.compile(r"\b_n\(\s*" + SQ + r"\s*,\s*" + SQ)
def unq(s): return s.replace("\\'", "'").replace('\\\\', '\\')
def strings():
    out = {}
    for f in sorted([*ROOT.glob('plugins/lereng-commerce/src/**/*.php'), *ROOT.glob('plugins/lereng-commerce/templates/*.php'), *ROOT.glob('plugins/lereng-commerce/*.php'), *ROOT.glob('themes/lereng/**/*.php')]):
        t = f.read_text()
        for m in CALL.finditer(t): out.setdefault(unq(m.group(1)), str(f.relative_to(ROOT)))
        for m in PLURAL.finditer(t):
            out.setdefault(unq(m.group(1)), str(f.relative_to(ROOT))); out.setdefault(unq(m.group(2)), str(f.relative_to(ROOT)))
    for f in sorted([*ROOT.glob('themes/lereng/templates/*.html'), *ROOT.glob('themes/lereng/parts/*.html')]):
        t = f.read_text()
        for m in re.finditer(r'"(?:label|placeholder|buttonText|content|text)":"([^"]+)"', t): out.setdefault(m.group(1), str(f.relative_to(ROOT)))
        # visible text between tags (not inside comments)
        for m in re.finditer(r'>([^<>]*[A-Za-z][^<>]*)</(?:h\d|p|a|span|li|button|strong|summary|div)>', re.sub(r'<!--.*?-->', '', t, flags=re.S)):
            s = m.group(1).strip()
            if s and not s.startswith('{'): out.setdefault(s, str(f.relative_to(ROOT)))
    for f in sorted([*ROOT.glob('plugins/lereng-commerce/assets/js/*.js'), *ROOT.glob('themes/lereng/assets/js/*.js')]):
        for m in re.finditer(r"\bt\(\s*'((?:[^'\\]|\\.)*)'", f.read_text()): out.setdefault(unq(m.group(1)), str(f.relative_to(ROOT)))
    return out
if __name__ == '__main__':
    s = strings()
    json.dump(s, sys.stdout, ensure_ascii=False, indent=0)
