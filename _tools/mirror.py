"""Mirror alumoimpact.ca pages + assets into _source/ for reference."""
import os, re, sys, urllib.request, urllib.parse, json, ssl

BASE = "https://alumoimpact.ca"
OUT = os.path.join(os.path.dirname(__file__), "..", "_source")
PAGES = {
    "home": "/",
    "about-the-fund": "/about-the-fund/",
    "how-to-apply": "/how-to-apply/",
    "apply-now": "/apply-now/",
    "partner-schools": "/partner-schools/",
    "past-winners": "/past-winners/",
    "privacy-policy": "/privacy-policy/",
    "cookies-policy": "/cookies-policy/",
    "terms-conditions": "/terms-conditions/",
}
UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126 Safari/537.36"}
ctx = ssl.create_default_context()

def fetch(url, binary=False):
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=60, context=ctx) as r:
        data = r.read()
    return data if binary else data.decode("utf-8", errors="replace")

def local_path_for(url):
    """Map an asset URL to a local _source/assets path."""
    p = urllib.parse.urlparse(url)
    path = p.path.lstrip("/")
    return os.path.join(OUT, "assets", p.netloc, *path.split("/"))

def save(path, data, binary=False):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    mode = "wb" if binary else "w"
    kw = {} if binary else {"encoding": "utf-8"}
    with open(path, mode, **kw) as f:
        f.write(data)

asset_urls = set()

def collect_from_html(html):
    urls = set()
    # href/src of css, js, images
    for m in re.finditer(r'(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpe?g|gif|svg|webp|woff2?|ttf|otf|ico|avif)(?:\?[^"\']*)?)["\']', html, re.I):
        urls.add(m.group(1))
    # srcset
    for m in re.finditer(r'srcset=["\']([^"\']+)["\']', html, re.I):
        for part in m.group(1).split(","):
            u = part.strip().split(" ")[0]
            if u:
                urls.add(u)
    # inline style background urls
    for m in re.finditer(r'url\((["\']?)([^)"\']+)\1\)', html):
        urls.add(m.group(2))
    return urls

def collect_from_css(css, css_url):
    urls = set()
    for m in re.finditer(r'url\((["\']?)([^)"\']+)\1\)', css):
        u = m.group(2)
        if u.startswith("data:"):
            continue
        urls.add(urllib.parse.urljoin(css_url, u))
    for m in re.finditer(r'@import\s+["\']([^"\']+)["\']', css):
        urls.add(urllib.parse.urljoin(css_url, m.group(1)))
    return urls

def norm(url, base):
    url = url.strip()
    if url.startswith("data:") or url.startswith("#"):
        return None
    absu = urllib.parse.urljoin(base, url)
    p = urllib.parse.urlparse(absu)
    if p.scheme not in ("http", "https"):
        return None
    return absu

# 1. fetch pages
failed = []
for name, path in PAGES.items():
    url = BASE + path
    try:
        html = fetch(url)
    except Exception as e:
        failed.append((url, str(e)))
        continue
    save(os.path.join(OUT, "pages", name + ".html"), html)
    for u in collect_from_html(html):
        nu = norm(u, url)
        if nu:
            asset_urls.add(nu)
    print(f"page {name}: {len(html)//1024} KB", flush=True)

# 2. fetch css assets first, collect nested urls (fonts, images)
css_urls = {u for u in asset_urls if ".css" in urllib.parse.urlparse(u).path}
for cu in sorted(css_urls):
    try:
        css = fetch(cu)
    except Exception as e:
        failed.append((cu, str(e)))
        continue
    save(local_path_for(cu), css)
    for u in collect_from_css(css, cu):
        nu = norm(u, cu)
        if nu:
            asset_urls.add(nu)

# 3. fetch every remaining asset
done = set(css_urls)
for u in sorted(asset_urls - done):
    p = local_path_for(u)
    if os.path.exists(p):
        continue
    try:
        data = fetch(u, binary=True)
        save(p, data, binary=True)
    except Exception as e:
        failed.append((u, str(e)))

print(f"\ntotal assets attempted: {len(asset_urls)}")
print(f"failures: {len(failed)}")
for u, e in failed[:20]:
    print("  FAIL", u, "->", e)
save(os.path.join(OUT, "failed.json"), json.dumps(failed, indent=1))
