#!/usr/bin/env python3
"""
MOOP System Monitor collector — runs as ROOT once a minute (systemd timer), installed by
scripts/install_monitor.sh to /usr/local/sbin/moop-monitor. Plan: notes/SYSTEM_MONITOR_PLAN.md.

Each run:
  1. samples CPU, RAM, swap, load, disk, and php-fpm's status page (workers busy/idle, queue,
     "max children reached" — the earliest sign the site is saturated);
  2. reads what nginx logged since the last run: requests by class, status codes, 429s, bytes,
     user-agent kinds, top addresses; and the error log's "limiting requests" lines;
  3. keeps the nginx rate-limit config in step with MOOP (exempt address ranges and the heavy
     group of scripts): MOOP publishes
     what it wants to {cache}/nginx_wanted.json, this re-validates every value, regenerates the
     conf, and applies it only if `nginx -t` passes (else restores) — so nobody has to remember
     to re-run install_nginx_limits.sh after changing the ranges;
  4. publishes {cache}/monitor_now.json, monitor_24h.json (per minute) and monitor_7d.json (per
     hour), which the admin page reads.

Security — root working beside a directory the web server can write:
  * Root never executes MOOP code. It READS one file MOOP writes (nginx_wanted.json): opened
    with O_NOFOLLOW, size-capped, and every value re-validated (IPv4 ranges only). The worst a
    compromised web process can do is change which addresses are exempt from rate limits, or
    which existing scripts count as heavy.
  * Outputs go to the cache directory's TOP level (its parent, /var/www, is root-owned, so the
    directory itself cannot be swapped for a link) via mkstemp + rename — a link planted at the
    target name is replaced, never followed.
  * Templates come from /usr/local/share/moop-monitor (root-owned copies made at install time),
    never from the repo, whose docs/ directory the web group can write.
  * Working state lives in /var/lib/moop-monitor (root only).
"""

import json
import os
import re
import subprocess
import sys
import tempfile
import time
import urllib.request

CONF_FILE = '/etc/moop-monitor.conf'
STATE_DIR = '/var/lib/moop-monitor'
SHARE_DIR = '/usr/local/share/moop-monitor'
NGINX_HTTP_CONF = '/etc/nginx/conf.d/moop-ratelimit.conf'
NGINX_SRV_CONF = '/etc/nginx/default.d/moop-ratelimit.conf'
ACCESS_LOG = '/var/log/nginx/access.log'
ERROR_LOG = '/var/log/nginx/error.log'
FPM_STATUS_URL = 'http://127.0.0.1/moop-fpm-status?json'
VERSION = 1

MAX_LINES_PER_RUN = 2_000_000      # a runaway log must not make one run take minutes
WANTED_MAX_BYTES = 65536
TOP_IPS_PER_MINUTE = 10

UA_KINDS = [
    ('ai', re.compile(r'GPTBot|ChatGPT|OAI-SearchBot|ClaudeBot|Claude-Web|anthropic|CCBot|Perplexity|Bytespider|'
                      r'Amazonbot|meta-external|FacebookBot|cohere|Diffbot|ImagesiftBot|omgili|Timpibot|YouBot|'
                      r'AI2Bot|Ai2Bot|PetalBot|Google-Extended|Applebot-Extended', re.I)),
    ('search', re.compile(r'Googlebot|bingbot|Baiduspider|YandexBot|DuckDuckBot|Applebot|Slurp|SeznamBot', re.I)),
    ('script', re.compile(r'curl|Wget|python-requests|python-urllib|aiohttp|Go-http-client|Java/|okhttp|libwww|'
                          r'HeadlessChrome|PhantomJS|node-fetch|axios|Scrapy|httpx|bot|crawl|spider', re.I)),
    ('browser', re.compile(r'Mozilla/.*(Chrome|Firefox|Safari|Edg)')),
]
LOG_RE = re.compile(r'^(\S+) \S+ \S+ \[[^\]]+\] "(?:\S+) (\S+)[^"]*" (\d{3}) (\d+|-) "[^"]*" "([^"]*)"')


def load_conf():
    conf = {}
    with open(CONF_FILE) as fh:
        for line in fh:
            if '=' in line and not line.lstrip().startswith('#'):
                k, v = line.strip().split('=', 1)
                conf[k.strip()] = v.strip()
    return conf


def load_state():
    try:
        with open(os.path.join(STATE_DIR, 'state.json')) as fh:
            return json.load(fh)
    except (OSError, ValueError):
        return {}


def save_state(state):
    os.makedirs(STATE_DIR, mode=0o700, exist_ok=True)
    publish(STATE_DIR, 'state.json', state, mode=0o600)


def publish(directory, name, data, mode=0o644):
    """Write JSON atomically: a fresh O_EXCL temp file renamed over the target (never follows a
    link planted at the target name)."""
    fd, tmp = tempfile.mkstemp(prefix='.' + name + '.', dir=directory)
    try:
        with os.fdopen(fd, 'w') as fh:
            json.dump(data, fh, separators=(',', ':'))
        os.chmod(tmp, mode)
        os.replace(tmp, os.path.join(directory, name))
    except BaseException:
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


# ---------------------------------------------------------------- system --------------------

def sample_system(state, paths):
    out = {}
    with open('/proc/stat') as fh:
        f = [int(x) for x in fh.readline().split()[1:]]
    idle, total = f[3] + f[4], sum(f[:8])
    prev = state.get('cpu')
    if prev and total > prev[1]:
        out['cpu_pct'] = round(100.0 * (1 - (idle - prev[0]) / (total - prev[1])), 1)
    state['cpu'] = [idle, total]

    mem = {}
    with open('/proc/meminfo') as fh:
        for line in fh:
            k, v = line.split(':', 1)
            mem[k] = int(v.split()[0]) // 1024     # MB
    out['mem_total_mb'] = mem.get('MemTotal', 0)
    out['mem_avail_mb'] = mem.get('MemAvailable', 0)
    out['mem_used_pct'] = round(100.0 * (1 - out['mem_avail_mb'] / max(out['mem_total_mb'], 1)), 1)
    out['swap_used_mb'] = mem.get('SwapTotal', 0) - mem.get('SwapFree', 0)
    with open('/proc/loadavg') as fh:
        la = fh.read().split()
    out['load1'], out['load5'], out['load15'] = float(la[0]), float(la[1]), float(la[2])
    out['cpus'] = os.cpu_count()

    disks = {}
    for label, path in paths.items():
        try:
            s = os.statvfs(path)
            size = s.f_blocks * s.f_frsize
            free = s.f_bavail * s.f_frsize
            disks[label] = {'path': path, 'size_gb': round(size / 2**30, 1),
                            'used_pct': round(100.0 * (1 - free / max(size, 1)), 1), 'free_gb': round(free / 2**30, 1)}
        except OSError:
            pass
    out['disks'] = disks
    return out


def sample_fpm(state):
    try:
        with urllib.request.urlopen(FPM_STATUS_URL, timeout=3) as r:
            s = json.load(r)
    except Exception as e:      # noqa: BLE001 — any failure is reported, never fatal
        return {'fpm_error': str(e)[:120]}
    out = {
        'fpm_active': s.get('active processes'), 'fpm_idle': s.get('idle processes'),
        'fpm_total': s.get('total processes'), 'fpm_queue': s.get('listen queue'),
        'fpm_max_active': s.get('max active processes'),
        'fpm_slow_total': s.get('slow requests'),
    }
    # Counters since php-fpm started: report the change since the last run.
    for key, field in (('fpm_accepted', 'accepted conn'), ('fpm_max_children_reached', 'max children reached')):
        cur, prev = s.get(field), state.get(key)
        out[key] = (cur - prev) if (isinstance(cur, int) and isinstance(prev, int) and cur >= prev) else 0
        state[key] = cur
    return out


# ---------------------------------------------------------------- nginx logs ----------------

def read_new_lines(path, state, key):
    """Lines appended since the last run, following logrotate (rename + new file)."""
    pos = state.get(key, {})
    try:
        st = os.stat(path)
    except OSError:
        return []
    lines = []

    def read_from(p, offset):
        with open(p, 'rb') as fh:
            fh.seek(offset)
            for raw in fh:
                if len(lines) >= MAX_LINES_PER_RUN:
                    break
                lines.append(raw.decode('utf-8', 'replace'))
            return fh.tell()

    if not pos:                                    # first run: start at the end, no history
        state[key] = {'inode': st.st_ino, 'offset': st.st_size}
        return []
    if pos.get('inode') != st.st_ino:              # rotated: finish the old file, then the new one
        d, base = os.path.split(path)
        for name in sorted(os.listdir(d)):
            if name.startswith(base + '.') or name.startswith(base + '-'):
                p = os.path.join(d, name)
                try:
                    if os.stat(p).st_ino == pos.get('inode') and not name.endswith('.gz'):
                        read_from(p, pos.get('offset', 0))
                except OSError:
                    pass
        offset = 0
    else:
        offset = pos.get('offset', 0) if pos.get('offset', 0) <= st.st_size else 0   # truncated
    end = read_from(path, offset)
    state[key] = {'inode': st.st_ino, 'offset': end}
    return lines


def load_classes():
    """The request classes from the INSTALLED nginx map, so the monitor and nginx agree."""
    classes = []
    try:
        with open(NGINX_HTTP_CONF) as fh:
            text = fh.read()
        block = re.search(r'map \$uri \$moop_rl_class \{(.*?)\n\}', text, re.S)
        for line in (block.group(1).splitlines() if block else []):
            m = re.match(r'\s*~(\S+)\s+(\w+);', line)
            if m:
                classes.append((re.compile(m.group(1)), m.group(2)))
    except (OSError, re.error):
        pass
    return classes


def classify(uri, classes):
    path = uri.split('?', 1)[0]
    for rx, cls in classes:
        if rx.search(path):
            return cls
    return 'static'


def ua_kind(ua):
    if not ua or ua == '-':
        return 'none'
    for kind, rx in UA_KINDS:
        if rx.search(ua):
            return kind
    return 'other'


def summarise_access(lines, classes):
    out = {'req': 0, 'bytes': 0, 'status': {}, 'cls': {}, 'ua': {}, 's429': 0}
    ips = {}
    for line in lines:
        m = LOG_RE.match(line)
        if not m:
            continue
        ip, uri, status, size, ua = m.groups()
        out['req'] += 1
        out['bytes'] += int(size) if size != '-' else 0
        sc = status[0] + 'xx'
        out['status'][sc] = out['status'].get(sc, 0) + 1
        if status == '429':
            out['s429'] += 1
        c = classify(uri, classes)
        out['cls'][c] = out['cls'].get(c, 0) + 1
        k = ua_kind(ua)
        out['ua'][k] = out['ua'].get(k, 0) + 1
        ips[ip] = ips.get(ip, 0) + 1
    out['top_ips'] = sorted(ips.items(), key=lambda kv: -kv[1])[:TOP_IPS_PER_MINUTE]
    return out


def summarise_errors(lines):
    limited = {}
    for line in lines:
        m = re.search(r'limiting (requests|connections)\b.*?zone "(\w+)"', line)
        if m:
            limited[m.group(2)] = limited.get(m.group(2), 0) + 1
    return limited


# ---------------------------------------------------------------- nginx sync ----------------

def valid_ipv4(s):
    parts = s.split('.')
    return len(parts) == 4 and all(p.isdigit() and len(p) <= 3 and int(p) <= 255 for p in parts)


def ip_int(s):
    a, b, c, d = (int(p) for p in s.split('.'))
    return (a << 24) | (b << 16) | (c << 8) | d


HEAVY_RE = re.compile(r'(api|tools)/[a-z0-9_]+\.php')


def read_wanted(cache, site):
    """MOOP's wanted settings. Opened without following links, size-capped, every value checked."""
    path = os.path.join(cache, 'nginx_wanted.json')
    try:
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    except OSError:
        return None, 'MOOP has not published nginx_wanted.json yet'
    with os.fdopen(fd, 'rb') as fh:
        raw = fh.read(WANTED_MAX_BYTES + 1)
    if len(raw) > WANTED_MAX_BYTES:
        return None, 'nginx_wanted.json is too large — ignored'
    try:
        data = json.loads(raw)
        ranges = []
        for r in data.get('exempt_ranges', [])[:64]:
            s, e = str(r.get('start', '')), str(r.get('end', ''))
            if not (valid_ipv4(s) and valid_ipv4(e) and ip_int(s) <= ip_int(e)):
                return None, f'invalid range in nginx_wanted.json: {s}-{e} — nothing applied'
            ranges.append((s, e))
        heavy = []
        for h in data.get('heavy', [])[:200]:
            h = str(h)
            # A fixed shape AND an existing file — nothing else can reach the nginx map line.
            if not HEAVY_RE.fullmatch(h) or not os.path.isfile(os.path.join(site, h)):
                return None, f'invalid heavy entry in nginx_wanted.json: {h[:80]} — nothing applied'
            heavy.append(h[:-4])
        return {'ranges': ranges, 'heavy': heavy}, None
    except (ValueError, AttributeError, TypeError):
        return None, 'nginx_wanted.json is not valid — nothing applied'


def render_http_conf(ranges, heavy):
    """The template with its placeholders filled — byte-identical to install_nginx_limits.sh's
    output for the same inputs, so an unchanged config never triggers a reload."""
    with open(os.path.join(SHARE_DIR, 'moop-ratelimit-http.conf')) as fh:
        template = fh.read()
    fill = {
        '@EXEMPT_RANGES@': '\n'.join(f'    {s}-{e} 1;' for s, e in ranges),
        '@HEAVY_RULES@': f'    ~^/moop/({"|".join(heavy)})\\.php$   heavy;' if heavy else '',
    }
    out = []
    for line in template.split('\n'):
        key = line.strip()
        if key in fill:
            if fill[key]:
                out.append(fill[key])
        else:
            out.append(line)
    return '\n'.join(out)


def sync_nginx(cache, site, prev):
    wanted, err = read_wanted(cache, site)
    now = int(time.time())
    if err:
        return {**(prev or {}), 'checked': now, 'state': 'waiting' if 'not published' in err else 'error', 'message': err}
    try:
        want_http = render_http_conf(wanted['ranges'], wanted['heavy'])
        with open(os.path.join(SHARE_DIR, 'moop-ratelimit.conf')) as fh:
            want_srv = fh.read()
    except OSError as e:
        return {'checked': now, 'state': 'error', 'message': f'template missing: {e}'}

    def current(p):
        try:
            with open(p) as fh:
                return fh.read()
        except OSError:
            return None

    have_http, have_srv = current(NGINX_HTTP_CONF), current(NGINX_SRV_CONF)
    ranges_txt = ', '.join(f'{s}–{e}' for s, e in wanted['ranges']) or 'none'
    heavy_n = len(wanted['heavy'])
    if have_http == want_http and have_srv == want_srv:
        return {**(prev or {}), 'checked': now, 'state': 'in_sync', 'ranges': ranges_txt, 'heavy': heavy_n,
                'message': 'nginx rate limits match MOOP'}

    for p, content in ((NGINX_HTTP_CONF, want_http), (NGINX_SRV_CONF, want_srv)):
        with open(p + '.moop-new', 'w') as fh:
            fh.write(content)
    backups = {}
    for p, had in ((NGINX_HTTP_CONF, have_http), (NGINX_SRV_CONF, have_srv)):
        backups[p] = had
        os.replace(p + '.moop-new', p)
    subprocess.run(['restorecon', NGINX_HTTP_CONF, NGINX_SRV_CONF], capture_output=True)
    test = subprocess.run(['nginx', '-t'], capture_output=True, text=True)
    if test.returncode != 0:
        for p, had in backups.items():
            if had is None:
                os.unlink(p)
            else:
                with open(p, 'w') as fh:
                    fh.write(had)
        subprocess.run(['nginx', '-t'], capture_output=True)
        return {'checked': now, 'state': 'error', 'ranges': ranges_txt,
                'message': 'generated config failed nginx -t — previous config kept: ' + test.stderr.strip()[-300:]}
    subprocess.run(['systemctl', 'reload', 'nginx'], capture_output=True)
    return {'checked': now, 'state': 'in_sync', 'ranges': ranges_txt, 'heavy': heavy_n, 'changed': now,
            'message': f'nginx rate limits updated to match MOOP ({heavy_n} heavy scripts; exempt: {ranges_txt})'}


# ---------------------------------------------------------------- history -------------------

def rollup_hour(minutes):
    """Combine one hour of minute records into one record."""
    h = {'t': minutes[0]['t'] - minutes[0]['t'] % 3600, 'n': len(minutes)}
    for k in ('cpu_pct', 'mem_used_pct', 'load1', 'fpm_active', 'fpm_queue', 'swap_used_mb'):
        vals = [m[k] for m in minutes if isinstance(m.get(k), (int, float))]
        if vals:
            h[k] = round(sum(vals) / len(vals), 1)
            h[k + '_max'] = max(vals)
    for k in ('req', 'bytes', 's429', 'fpm_max_children_reached', 'fpm_accepted'):
        h[k] = sum(m.get(k, 0) or 0 for m in minutes)
    for k in ('status', 'cls', 'ua', 'limited'):
        agg = {}
        for m in minutes:
            for kk, v in (m.get(k) or {}).items():
                agg[kk] = agg.get(kk, 0) + v
        h[k] = agg
    ips = {}
    for m in minutes:
        for ip, n in m.get('top_ips', []):
            ips[ip] = ips.get(ip, 0) + n
    h['top_ips'] = sorted(ips.items(), key=lambda kv: -kv[1])[:20]
    return h


def main():
    conf = load_conf()
    cache, site = conf['CACHE'], conf['SITE']
    state = load_state()
    now = int(time.time())
    minute = now - now % 60

    rec = {'t': minute}
    rec.update(sample_system(state, {'root': '/', 'var': '/var', 'tmp': '/tmp', 'cache': cache,
                                     'organisms': os.path.realpath(os.path.join(site, 'organisms'))}))
    rec.update(sample_fpm(state))
    acc = summarise_access(read_new_lines(ACCESS_LOG, state, 'access_pos'), load_classes())
    rec.update(acc)
    rec['limited'] = summarise_errors(read_new_lines(ERROR_LOG, state, 'error_pos'))

    sync = sync_nginx(cache, site, state.get('sync'))
    state['sync'] = sync

    hist = [m for m in state.get('minutes', []) if m['t'] > minute - 86400 and m['t'] != minute]
    hist.append(rec)
    state['minutes'] = hist
    # Hourly rollups: finish any hour that is complete and not yet rolled up.
    hours = [h for h in state.get('hours', []) if h['t'] > minute - 7 * 86400]
    done = {h['t'] for h in hours}
    by_hour = {}
    for m in hist:
        by_hour.setdefault(m['t'] - m['t'] % 3600, []).append(m)
    for ht, ms in sorted(by_hour.items()):
        if ht + 3600 <= minute and ht not in done:
            hours.append(rollup_hour(ms))
    hours.sort(key=lambda h: h['t'])
    state['hours'] = hours

    save_state(state)
    publish(cache, 'monitor_now.json', {'version': VERSION, 'generated': now, 'now': rec, 'nginx_sync': sync})
    publish(cache, 'monitor_24h.json', {'version': VERSION, 'generated': now, 'minutes': hist})
    publish(cache, 'monitor_7d.json', {'version': VERSION, 'generated': now, 'hours': hours})
    for name in ('monitor_now.json', 'monitor_24h.json', 'monitor_7d.json'):
        subprocess.run(['restorecon', os.path.join(cache, name)], capture_output=True)


if __name__ == '__main__':
    try:
        main()
    except Exception as e:   # noqa: BLE001 — one failed minute must not stop the timer
        print(f'moop-monitor: {type(e).__name__}: {e}', file=sys.stderr)
        sys.exit(1)
