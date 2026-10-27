"""Deploys the store to a plain PHP + MySQL host over FTP (used for the InfinityFree demo).

  IF_URL=https://example.rf.gd IF_FTP_HOST=ftpupload.net IF_USER=if0_… IF_PASS=… \
  IF_DB_HOST=sql…  IF_DB_NAME=if0_…_lereng python3 scripts/deploy_host.py [build|upload]

Free hosts cap files at 10 MB and requests at 60 s, so the site goes up as zip parts under
9 MB plus the database in ~2.5 MB SQL parts (from seed/export-mysql.php), and a one-off,
token-named deploy script on the server unpacks and imports them one request at a time
(scripts/deploy_run.mjs drives it). Nothing with credentials is written into the repository.
"""
import ftplib, json, os, pathlib, secrets, sys, urllib.request, zipfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
SITE = ROOT / '.wp/site'
OUT = ROOT / '.wp/deploy'
env = {k: os.environ[k] for k in ['IF_URL', 'IF_FTP_HOST', 'IF_USER', 'IF_PASS', 'IF_DB_HOST', 'IF_DB_NAME']}
LIMIT = 9_000_000

def files():
    """(path on disk, path on the server) for everything the site needs."""
    for p in sorted(SITE.rglob('*')):
        rel = p.relative_to(SITE).as_posix()
        if p.is_dir() or rel == 'wp-config.php' or rel.startswith('wp-content/'):
            continue
        yield p, rel
    picks = [(SITE / 'wp-content/plugins/woocommerce', 'wp-content/plugins/woocommerce'),
             (SITE / 'wp-content/uploads', 'wp-content/uploads'),
             (SITE / 'wp-content/languages', 'wp-content/languages'),
             (ROOT / 'wp-content/plugins/lereng-commerce', 'wp-content/plugins/lereng-commerce'),
             (ROOT / 'wp-content/themes/lereng', 'wp-content/themes/lereng')]
    skip = ('/vendor/', '/tests/', '/seed/', '/node_modules/', '/.phpunit.cache/', '/themes/lereng/src/', '.DS_Store')
    for base, dest in picks:
        for p in sorted(base.rglob('*')):
            rel = f'{dest}/{p.relative_to(base).as_posix()}'
            if p.is_file() and not any(s in '/' + rel + ('/' if p.is_dir() else '') for s in skip) and not rel.endswith(('phpunit.xml', 'composer.json', 'composer.lock')):
                yield p, rel
    yield SITE / 'wp-content/index.php', 'wp-content/index.php'

def wp_config():
    salts = urllib.request.urlopen('https://api.wordpress.org/secret-key/1.1/salt/', timeout=30).read().decode()
    return f"""<?php
define('DB_NAME', {json.dumps(env['IF_DB_NAME'])});
define('DB_USER', {json.dumps(env['IF_USER'])});
define('DB_PASSWORD', {json.dumps(env['IF_PASS'])});
define('DB_HOST', {json.dumps(env['IF_DB_HOST'])});
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
{salts}
$table_prefix = 'wp_';
define('WP_HOME', {json.dumps(env['IF_URL'])});
define('WP_SITEURL', {json.dumps(env['IF_URL'])});
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);   // nobody installs plugins on the public demo
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_MEMORY_LIMIT', '256M');
define('DISABLE_WP_CRON', true);       // the host blocks loopback requests; LERENG_DEMO runs the queue on page views
define('LERENG_DEMO', true);
define('LERENG_DEMO_LOGIN', 'demo / demo');
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
"""

HTACCESS = """# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
"""

def build():
    OUT.mkdir(parents=True, exist_ok=True)
    for f in OUT.glob('*'):
        f.unlink()
    token = secrets.token_hex(12)
    admin_pw = secrets.token_urlsafe(14)
    extra = {'wp-config.php': wp_config(), '.htaccess': HTACCESS}
    part, z, n = 0, None, 0
    def new():
        nonlocal part, z
        if z: z.close()
        part += 1
        z = zipfile.ZipFile(OUT / f'site-{part:02d}.zip', 'w', zipfile.ZIP_DEFLATED, compresslevel=9)
    new()
    for name, text in extra.items():
        z.writestr(name, text)
    for p, rel in files():
        if z.fp.tell() > LIMIT - min(p.stat().st_size, 3_000_000):
            new()
        z.write(p, rel)
        n += 1
    z.close()
    sql = sorted((SITE / 'wp-content/lereng-export').glob('part-*.sql'))
    for s in sql:
        (OUT / s.name).write_bytes(s.read_bytes())
    deploy = (ROOT / 'scripts/deploy_server.php').read_text().replace('__ADMIN_PASSWORD__', admin_pw)
    (OUT / f'deploy-{token}.php').write_text(deploy)
    meta = {'token': token, 'zips': part, 'sql': len(sql), 'admin_password': admin_pw, 'files': n}
    (OUT / 'meta.json').write_text(json.dumps(meta))
    print(f"{n} files → {part} zips, {len(sql)} SQL parts; total {sum(f.stat().st_size for f in OUT.glob('*')) / 1e6:.1f} MB")

def upload():
    meta = json.loads((OUT / 'meta.json').read_text())
    f = ftplib.FTP(env['IF_FTP_HOST'], timeout=120)
    f.login(env['IF_USER'], env['IF_PASS'])
    f.cwd('htdocs')
    for name in [f"deploy-{meta['token']}.php"] + sorted(p.name for p in OUT.glob('*.zip')) + sorted(p.name for p in OUT.glob('*.sql')):
        if name in f.nlst():
            continue
        for attempt in range(3):
            try:
                with open(OUT / name, 'rb') as fh:
                    f.storbinary(f'STOR {name}.part', fh, blocksize=262144)
                f.rename(f'{name}.part', name)
                print('uploaded', name, flush=True)
                break
            except ftplib.all_errors as e:
                print('retry', name, e, flush=True)
                f = ftplib.FTP(env['IF_FTP_HOST'], timeout=120); f.login(env['IF_USER'], env['IF_PASS']); f.cwd('htdocs')
    for junk in ['index2.html', 'files for your website should be uploaded here!']:
        try: f.delete(junk)
        except ftplib.all_errors: pass
    f.quit()

{'build': build, 'upload': upload}[sys.argv[1] if len(sys.argv) > 1 else 'build']()
