# Generates src/Concerns/*.php (one method per Stedi operation) and tests/Fixtures/operations.json
# from the OpenAPI snapshots in tests/Fixtures/openapi. Run it through bin/generate.
import json, os, re, sys, textwrap

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
specs_dir = os.path.join(root, 'tests', 'Fixtures', 'openapi')
out_dir = os.path.join(root, 'src', 'Concerns')

APIS = [  # spec file, trait name, host config key, description
    ('healthcare.json', 'Healthcare', 'healthcare', 'Eligibility, claims, claim status, remittances, coordination of benefits and insurance discovery.'),
    ('claims.json', 'Claims', 'claims', 'Claim records, timelines, professional claim submissions and claim attachments.'),
    ('manager.json', 'EligibilityManager', 'manager', 'Batch eligibility checks and eligibility PDFs.'),
    ('payers.json', 'Payers', 'payers', 'The payer network.'),
    ('enrollment.json', 'Enrollment', 'enrollment', 'Transaction enrollment: providers, enrollments and documents.'),
    ('event-destinations.json', 'EventDestinations', 'events', 'Event destinations (webhooks) and their events.'),
    ('core.json', 'Core', 'core', 'Stedi Core (EDI platform): transactions, executions, partnerships and EDI generation.'),
]
SKIP = {('healthcare.json', p) for p in ['/payer/{stediId}', '/payers', '/payers/csv', '/payers/search']}
BINARY = {'GetElectronicRemittanceAdvicePdf', 'GetPDF1500', 'ListPayerRecordsCsv', 'GetEligibilityCheckPdf',
          'GetExecutionInputDocument', 'GetExecutionMetadataDocument', 'GetExecutionOutputDocument',
          'GetTransactionAttachmentDocument', 'GetTransactionFragmentOutputDocument',
          'GetTransactionInputDocument', 'GetTransactionOutputDocument'}
RENAME = {'ExportPDF': 'exportPdf', 'GetPDF1500': 'getPdf1500', 'UpdateTaskPost': 'updateTask'}

def res(d, x):
    while isinstance(x, dict) and '$ref' in x:
        y = d
        for p in x['$ref'].lstrip('#/').split('/'):
            y = y[p]
        x = y
    return x

def camel(op):
    return RENAME.get(op, op[0].lower() + op[1:])

def var(name):
    return '$' + name[0].lower() + name[1:]

def summary(o):
    s = (o.get('summary') or o.get('description') or '').strip().split('\n')[0].strip()
    s = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', s)
    return s.rstrip('.') + '.' if s else ''

operations = []
for spec, trait, host, desc in APIS:
    d = json.load(open(os.path.join(specs_dir, spec)))
    methods = []
    for path, item in d['paths'].items():
        if (spec, path) in SKIP:
            continue
        for verb, o in item.items():
            if verb not in ('get', 'post', 'put', 'patch', 'delete'):
                continue
            params = [res(d, p) for p in item.get('parameters', []) + o.get('parameters', [])]
            path_params = re.findall(r'\{(\w+)\}', path)
            query = [p['name'] for p in params if p['in'] == 'query']
            idem = any(p['in'] == 'header' and p['name'] == 'Idempotency-Key' for p in params)
            body = res(d, o['requestBody']) if 'requestBody' in o else None
            name = camel(o['operationId'])
            binary = o['operationId'] in BINARY
            paginated = 'pageToken' in query

            args, doc = [], []
            for p in path_params:
                args.append(f'string {var(p)}')
            if body is not None:
                required = body.get('required', False)
                args.append('array $data' + ('' if required else ' = []'))
                doc.append('     * @param  array<string, mixed>  $data')
            if query:
                args.append('array $query = []')
                shown = [q for q in query if q not in ('pageToken',)]
                doc.append('     * @param  array<string, mixed>  $query' + ('  ' + ', '.join(shown) if shown else ''))
            if idem:
                args.append('?string $idempotencyKey = null')
                doc.append('     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.')

            fmt = re.sub(r'\{\w+\}', '%s', path)
            path_expr = f"'{fmt}'" if not path_params else f"$this->path('{fmt}', {', '.join(var(p) for p in path_params)})"
            call_args = [f"'{host}'", f"'{verb.upper()}'", path_expr]
            if query or body is not None or idem:
                call_args.append('$query' if query else '[]')
            if body is not None or idem:
                call_args.append('$data' if body is not None else 'null')
            if idem:
                call_args.append('$idempotencyKey ?? (string) Str::uuid()')

            if paginated:
                ret, doc_ret = 'CursorPage', None
                code = f"return $this->paginate({', '.join(call_args)});"
            elif binary:
                ret, doc_ret = 'string', None
                code = f"return $this->send({', '.join(call_args)})->body();"
            else:
                ret, doc_ret = 'array', '     * @return array<string, mixed>'
                code = f"return $this->request({', '.join(call_args)});"

            lines = ['    /**']
            s = summary(o)
            if s:
                lines += ['     * ' + l for l in textwrap.wrap(s, 72)]
            if binary:
                lines.append('     * Returns the raw file contents.' if s else '     * The raw file contents.')
            if doc or doc_ret:
                if len(lines) > 1:
                    lines.append('     *')
                lines += doc
                if doc_ret:
                    lines.append(doc_ret)
            lines.append('     */')
            if len(lines) == 2:
                lines = []
            sig = f"    public function {name}({', '.join(args)}): {ret}"
            methods.append('\n'.join(lines + [sig, '    {', f'        {code}', '    }']))
            operations.append((name, verb.upper(), host, path, o['operationId']))

    uses = ['use Cabuz\\Stedi\\Support\\CursorPage;'] if any('CursorPage' in m for m in methods) else []
    if any('Str::uuid' in m for m in methods):
        uses.append('use Illuminate\\Support\\Str;')
    src = ['<?php', '', 'namespace Cabuz\\Stedi\\Concerns;', '']
    if uses:
        src += uses + ['']
    src += ['/**', f' * {desc}', ' *', f' * Generated from Stedi\'s OpenAPI spec ({spec}) by bin/generate.', ' */',
            f'trait {trait}', '{', '\n\n'.join(methods), '}', '']
    open(os.path.join(out_dir, trait + '.php'), 'w').write('\n'.join(src))

names = [o[0] for o in operations]
dupes = {n for n in names if names.count(n) > 1}
print(len(operations), 'operations; duplicates:', dupes or 'none')
json.dump([f'{v} {h} {p}' for _, v, h, p, _ in sorted(operations, key=lambda o: (o[2], o[3], o[1]))],
          open(os.path.join(root, 'tests', 'Fixtures', 'operations.json'), 'w'), indent=2)
