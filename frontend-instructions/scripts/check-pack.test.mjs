import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const repo = resolve('.');
const checker = join(repo, 'frontend-instructions/scripts/check-pack.mjs');

function run(root, ...args) {
    const result = spawnSync(process.execPath, [checker, '--root', root, '--json', ...args], {
        encoding: 'utf8',
    });
    assert.ok(result.stdout, `checker produced no JSON: ${result.stderr}`);
    return { status: result.status, ...JSON.parse(result.stdout) };
}

function edit(root, path, transform, check) {
    const file = join(root, path);
    const original = readFileSync(file, 'utf8');
    try {
        const changed = transform(original);
        assert.notEqual(changed, original, `fixture mutation did not apply: ${path}`);
        writeFileSync(file, changed);
        check();
    } finally {
        writeFileSync(file, original);
    }
}

function hasError(result, code) {
    assert.equal(result.status, 1, JSON.stringify({ errors: result.errors, warnings: result.warnings }, null, 2));
    assert.ok(result.errors.some((error) => error.code === code), JSON.stringify(result.errors, null, 2));
}

test('checker detects drift without flagging the corrected pack', async (t) => {
    assert.ok(existsSync(checker), 'the drift checker must exist');
    const parent = mkdtempSync(join(tmpdir(), 'frontend-pack-test-'));
    const root = join(parent, 'repo');
    try {
        for (const path of ['frontend-instructions', 'resources', 'app', 'routes', 'tests', 'database', 'docs', 'AGENTS.md', 'package.json', 'composer.json', 'compose.dev.yaml', 'vite.config.ts']) {
            cpSync(join(repo, path), join(root, path), { recursive: true });
        }
        const profile = 'frontend-instructions/PROJECT-PROFILE.md';
        const guide = 'frontend-instructions/frontend-guides/01-ARCHITECTURE-AND-OWNERSHIP.md';
        const recipe = 'frontend-instructions/frontend-guides/11-RECIPES.md';
        const clean = run(root);
        assert.equal(clean.status, 0, JSON.stringify(clean.errors, null, 2));
        assert.deepEqual(clean.errors, []);

        await t.test('reports an identity version mismatch', () => {
            edit(root, profile, (s) => s.replace('React | `^19.2`', 'React | `^18.0`'), () => hasError(run(root), 'E_VERSION'));
        });
        await t.test('reports a missing guide and broken guide references', () => {
            const path = join(root, 'frontend-instructions/frontend-guides/08-ACCESSIBILITY-AND-RESPONSIVE.md');
            const original = readFileSync(path);
            try {
                rmSync(path);
                const result = run(root);
                hasError(result, 'E_STRUCTURE');
                hasError(result, 'E_GUIDE_REF');
            } finally {
                writeFileSync(path, original);
            }
        });
        await t.test('reports a new guide omitted from the README', () => {
            const file = join(root, 'frontend-instructions/frontend-guides/12-EXTRA-GUIDE.md');
            try {
                writeFileSync(file, '# Additional guide\n');
                hasError(run(root), 'E_STRUCTURE');
            } finally {
                rmSync(file);
            }
        });
        await t.test('reports a missing recipe source', () => {
            edit(root, recipe, (s) => s.replace('// source: resources/js/pages/admin/files/index.tsx', '// source: resources/js/pages/admin/missing.tsx'), () => hasError(run(root), 'E_RECIPE'));
        });
        await t.test('reports additional confirmation calls with their location', () => {
            edit(root, 'resources/js/pages/links/index.tsx', (s) => `${s}\nwindow.confirm('new');\n`, () => {
                const result = run(root);
                hasError(result, 'E_CONFORMANCE');
                assert.match(JSON.stringify(result.errors), /resources\/js\/pages\/links\/index\.tsx/);
            });
        });
        await t.test('reports a new dashboard URL in an already affected file', () => {
            edit(root, 'resources/js/pages/links/index.tsx', (s) => `${s}\nconst extra = '/dashboard/new-route';\n`, () => hasError(run(root), 'E_CONFORMANCE'));
        });
        await t.test('reports an added raw palette class', () => {
            edit(root, 'resources/js/pages/links/index.tsx', (s) => `${s}\nconst extraColor = 'bg-red-500';\n`, () => hasError(run(root), 'E_CONFORMANCE'));
        });
        await t.test('reports a second chart library import', () => {
            edit(root, 'resources/js/pages/links/index.tsx', (s) => `${s}\nimport Chart from 'react-apexcharts';\n`, () => hasError(run(root), 'E_CONFORMANCE'));
        });
        await t.test('reports a chart library subpath import', () => {
            edit(root, 'resources/js/pages/links/index.tsx', (s) => `${s}\nimport Chart from 'chart.js/auto';\n`, () => hasError(run(root), 'E_CONFORMANCE'));
        });
        await t.test('reports guide budget overflow', () => {
            edit(root, guide, (s) => `${s}${'extra\n'.repeat(300)}`, () => hasError(run(root), 'E_BUDGET'));
        });
        await t.test('reports a missing explicit path', () => {
            edit(root, profile, (s) => s.replace('resources/js/lib/money.ts', 'resources/js/lib/missing-money.ts'), () => hasError(run(root), 'E_PATH'));
        });
        await t.test('reports an undocumented layout mapping', () => {
            edit(root, 'resources/js/app.tsx', (s) => s.replace("case name === 'welcome':", "case name === 'new/unmapped':\n            case name === 'welcome':"), () => hasError(run(root), 'E_LAYOUT'));
        });
        await t.test('warns instead of claiming drift when a layout case cannot be parsed', () => {
            edit(root, 'resources/js/app.tsx', (s) => s.replace("case name.startsWith('auth/'):", 'case name.startsWith(authPrefix):'), () => {
                const result = run(root);
                assert.equal(result.status, 0, JSON.stringify(result.errors));
                assert.ok(result.warnings.some((warning) => warning.code === 'W_UNVERIFIED' && warning.file === 'resources/js/app.tsx'));
            });
        });
        await t.test('reports an undocumented shared prop', () => {
            edit(root, 'resources/js/types/global.d.ts', (s) => s.replace('name: string;', 'newSharedProp: string;\n            name: string;'), () => hasError(run(root), 'E_PROPS'));
        });
        await t.test('reports an incorrect utility export', () => {
            edit(root, profile, (s) => s.replace('`useIsMobile` | `@/hooks/use-mobile`', '`useMobile` | `@/hooks/use-mobile`'), () => hasError(run(root), 'E_UTILITY'));
        });
        await t.test('reports a missing npm script', () => {
            edit(root, 'package.json', (s) => s.replace('"build": "vp build",', ''), () => hasError(run(root), 'E_COMMAND'));
        });
        await t.test('warns when the pack hash no longer matches', () => {
            edit(root, guide, (s) => `${s}\nAudit fixture.\n`, () => assert.ok(run(root).warnings.some((warning) => warning.code === 'W_PACK_CHANGED')));
        });
        await t.test('warns without failing when the baseline is missing', () => {
            const file = join(root, 'frontend-instructions/verified.json');
            const original = readFileSync(file);
            try {
                rmSync(file);
                const result = run(root);
                assert.equal(result.status, 0, JSON.stringify(result.errors));
                assert.ok(result.warnings.some((warning) => warning.code === 'W_UNVERIFIED'));
            } finally {
                writeFileSync(file, original);
            }
        });
        await t.test('warns when existing debt falls below its baseline', () => {
            edit(root, 'frontend-instructions/verified.json', (s) => s.replace('"window-confirm": 10', '"window-confirm": 11'), () => {
                const result = run(root);
                assert.equal(result.status, 0, JSON.stringify(result.errors));
                assert.ok(result.warnings.some((warning) => warning.code === 'W_BASELINE_DECREASE'));
            });
        });
        await t.test('reports unavailable Git freshness without failing', () => {
            const result = run(root, '--changed');
            assert.equal(result.status, 0, JSON.stringify(result.errors));
            assert.ok(result.warnings.some((warning) => warning.code === 'W_NO_GIT'));
        });
        await t.test('uses working-tree status in a repository without commits', () => {
            const result = spawnSync('git', ['-C', root, 'init', '-q'], { encoding: 'utf8' });
            assert.equal(result.status, 0, result.stderr);
            try {
                const checked = run(root, '--changed');
                assert.equal(checked.status, 0, JSON.stringify(checked.errors));
                assert.ok(checked.warnings.some((warning) => warning.code === 'W_STALE'), JSON.stringify(checked.warnings));
            } finally {
                rmSync(join(root, '.git'), { recursive: true, force: true });
            }
        });
        await t.test('reports older branch changes merged after the audit', () => {
            const git = (args, input = '', date = '2026-01-01T00:00:00Z') => {
                const result = spawnSync('git', ['-C', root, ...args], {
                    encoding: 'utf8', input, env: {
                        ...process.env, GIT_AUTHOR_NAME: 'Fixture', GIT_AUTHOR_EMAIL: 'fixture@example.invalid',
                        GIT_COMMITTER_NAME: 'Fixture', GIT_COMMITTER_EMAIL: 'fixture@example.invalid',
                        GIT_AUTHOR_DATE: date, GIT_COMMITTER_DATE: date,
                    },
                });
                assert.equal(result.status, 0, result.stderr);
                return result.stdout.trim();
            };
            const changedFile = join(root, 'resources/js/pages/checker-merged.tsx');
            try {
                git(['init', '-q']);
                git(['symbolic-ref', 'HEAD', 'refs/heads/main']);
                writeFileSync(join(root, '.git/info/exclude'), '*\n');
                const contents = 'export const merged = true;\n';
                writeFileSync(changedFile, contents);
                const blob = git(['hash-object', '-w', '--stdin'], contents);
                let tree = git(['mktree'], `100644 blob ${blob}\tchecker-merged.tsx\n`);
                for (const name of ['pages', 'js', 'resources']) tree = git(['mktree'], `040000 tree ${tree}\t${name}\n`);
                const empty = git(['mktree']);
                const base = git(['commit-tree', empty], 'base\n');
                const branch = git(['commit-tree', tree, '-p', base], 'branch\n', '2026-01-02T00:00:00Z');
                const merge = git(['commit-tree', tree, '-p', base, '-p', branch], 'merge\n', '2026-12-01T00:00:00Z');
                git(['update-ref', 'refs/heads/main', merge]);
                git(['read-tree', merge]);
                assert.equal(git(['status', '--porcelain']), '');
                const result = run(root, '--changed');
                assert.equal(result.status, 0, JSON.stringify(result.errors));
                const stale = result.warnings.find((warning) => warning.code === 'W_STALE');
                assert.ok(stale, JSON.stringify(result.warnings));
                assert.ok(stale.files.includes('resources/js/pages/checker-merged.tsx'));
            } finally {
                rmSync(changedFile, { force: true });
                rmSync(join(root, '.git'), { recursive: true, force: true });
            }
        });
        await t.test('scopes untracked UI changes to guide 06', () => {
            const git = (args, input = '') => {
                const result = spawnSync('git', ['-C', root, ...args], {
                    encoding: 'utf8', input, env: {
                        ...process.env, GIT_AUTHOR_NAME: 'Fixture', GIT_AUTHOR_EMAIL: 'fixture@example.invalid',
                        GIT_COMMITTER_NAME: 'Fixture', GIT_COMMITTER_EMAIL: 'fixture@example.invalid',
                    },
                });
                assert.equal(result.status, 0, result.stderr);
                return result.stdout.trim();
            };
            git(['init', '-q']);
            git(['symbolic-ref', 'HEAD', 'refs/heads/main']);
            const tree = git(['mktree']);
            const commit = git(['commit-tree', tree], 'fixture\n');
            git(['update-ref', 'refs/heads/main', commit]);
            writeFileSync(join(root, '.git/info/exclude'), '/*\n!/resources/\n/resources/*\n!/resources/js/\n/resources/js/*\n!/resources/js/components/\n/resources/js/components/*\n!/resources/js/components/ui/\n/resources/js/components/ui/*\n!/resources/js/components/ui/checker-drift.tsx\n');
            writeFileSync(join(root, 'resources/js/components/ui/checker-drift.tsx'), 'export const drift = true;\n');
            const result = run(root, '--changed');
            assert.equal(result.status, 0, JSON.stringify(result.errors));
            const stale = result.warnings.find((warning) => warning.code === 'W_STALE');
            assert.ok(stale, JSON.stringify(result.warnings));
            assert.deepEqual(stale.guides, [6]);
            assert.deepEqual(stale.files, ['resources/js/components/ui/checker-drift.tsx']);
        });
    } finally {
        rmSync(parent, { recursive: true, force: true });
    }
});
