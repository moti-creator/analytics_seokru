"""Create a Code Snippet on www.seokru.com that cleans the stray viewport text leak from wp_head."""
import json, urllib.request, urllib.error, base64

USER = 'n8n_seokru'
PASS = 'gkef aSUL Cg8f nvDl Rhz6 d1gm'
BASE = 'https://www.seokru.com/wp-json'

CODE = r"""add_action('wp_head', function () { ob_start(); }, -PHP_INT_MAX);
add_action('wp_head', function () {
    $s = ob_get_clean();
    // Remove stray viewport text that appears outside a tag (between `>` and `<`)
    $s = preg_replace('/>\s*width=device-width,\s*initial-scale=1\s*</', '><', $s);
    // Also strip a bare leading instance right after <head>
    $s = preg_replace('/<head([^>]*)>\s*width=device-width,\s*initial-scale=1/', '<head$1>', $s);
    // Ensure one proper viewport meta exists
    if (stripos($s, 'name="viewport"') === false && stripos($s, "name='viewport'") === false) {
        $s = '<meta name="viewport" content="width=device-width, initial-scale=1">' . $s;
    }
    echo $s;
}, PHP_INT_MAX);"""

def auth():
    t = base64.b64encode(f'{USER}:{PASS}'.encode()).decode()
    return {'Authorization': f'Basic {t}', 'Content-Type': 'application/json',
            'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json'}

def api(method, path, body=None):
    data = json.dumps(body).encode() if body else None
    req = urllib.request.Request(BASE + path, data=data, method=method, headers=auth())
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')

# Check if already exists
code, body = api('GET', '/code-snippets/v1/snippets?per_page=100')
existing = None
if code == 200:
    for s in body:
        if s.get('name') == 'Fix stray viewport leak':
            existing = s['id']; break

payload = {
    'name': 'Fix stray viewport leak',
    'desc': 'Strips the stray "width=device-width, initial-scale=1" text appearing outside any meta tag in wp_head output.',
    'code': CODE,
    'scope': 'front-end',
    'active': True,
    'priority': 10,
    'tags': ['viewport','head','fix'],
}

if existing:
    code, body = api('POST', f'/code-snippets/v1/snippets/{existing}', payload)
    print(f'UPDATE id={existing}: {code}')
else:
    code, body = api('POST', '/code-snippets/v1/snippets', payload)
    print(f'CREATE: {code}')

if isinstance(body, dict):
    print(f"id={body.get('id')} active={body.get('active')} code_error={body.get('code_error')}")
else:
    print(str(body)[:400])
