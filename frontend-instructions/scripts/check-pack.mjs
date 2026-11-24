#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

const CONFIG = {
    pack: 'frontend-instructions',
    profile: 'frontend-instructions/PROJECT-PROFILE.md',
    surfaces: 'frontend-instructions/PROJECT-SURFACES.md',
    readme: 'frontend-instructions/README.md',
    layoutGuide:
        'frontend-instructions/frontend-guides/02-PAGES-LAYOUTS-AND-NAVIGATION.md',
    layoutSource: 'resources/js/app.tsx',
    propsSource: 'resources/js/types/global.d.ts',
    baseline: 'frontend-instructions/verified.json',
    guides: [
        '01-ARCHITECTURE-AND-OWNERSHIP.md',
        '02-PAGES-LAYOUTS-AND-NAVIGATION.md',
        '03-DATA-ROUTES-AND-PERMISSIONS.md',
        '04-FORMS-AND-VALIDATION.md',
        '05-TABLES-AND-LISTS.md',
        '06-COMPONENTS-AND-STYLING.md',
        '07-FEEDBACK-STATES-AND-OVERLAYS.md',
        '08-ACCESSIBILITY-AND-RESPONSIVE.md',
        '09-TESTING-AND-VERIFICATION.md',
        '10-AGENT-WORKFLOW.md',
        '11-RECIPES.md',
    ],
    budgets: {
        'README.md': 140,
        'FRONTEND_INSTRUCTIONS.md': 165,
        'PROJECT-PROFILE.md': 300,
        'PROJECT-SURFACES.md': 220,
        'AGENTS_FRONTEND_SNIPPET.md': 20,
    },
    guideBudgets: { standard: 260, recipes: 450 },
    versions: {
        Framework: ['composer', 'laravel/framework'],
        PHP: ['composer', 'php'],
        'Inertia Laravel': ['composer', 'inertiajs/inertia-laravel'],
        'Inertia React': ['npm', '@inertiajs/react'],
        React: ['npm', 'react'],
        'Tailwind CSS': ['npm', 'tailwindcss'],
        Wayfinder: ['composer', 'laravel/wayfinder'],
        'Vite+': ['npm', 'vite-plus'],
        TypeScript: ['npm', 'typescript'],
    },
    commands: [
        'npm run build',
        'npm run types:check',
        'npx vp check',
        'npx vp test',
        'composer ci:check',
        'vendor/bin/pint',
    ],
    generated: /^resources\/js\/(?:routes|actions|wayfinder)(?:\/|$)/,
    excluded: new Set([
        'resources/js/components/ui',
        'resources/js/routes',
        'resources/js/actions',
        'resources/js/wayfinder',
    ]),
    conformance: {
        'raw-palette':
            /\b(?:bg|text|border|ring|fill|stroke|from|via|to|decoration|outline)-(?:red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/g,
        'raw-dashboard-url': /['"`]\/dashboard\//g,
        'window-confirm': /\bwindow\s*\.\s*confirm\s*\(/g,
        'router-put': /\brouter\s*\.\s*put\s*\(/g,
        'second-chart-library':
            /(?:\bfrom\s*|\bimport\s*\(|\brequire\s*\(|\bimport\s*)['"](?:chart\.js|react-chartjs-2|apexcharts|react-apexcharts|@nivo\/[^'"]+|victory(?:-[^'"]+)?|highcharts(?:-react-official)?|echarts(?:-for-react)?|d3(?:-[^'"]+)?)(?:\/[^'"]+)?['"]/g,
    },
};

const args = process.argv.slice(2);
let root = process.cwd();
let jsonOutput = false;
let changed = false;
for (let i = 0; i < args.length; i++) {
    if (args[i] === '--root' && args[i + 1]) root = path.resolve(args[++i]);
    else if (args[i] === '--json') jsonOutput = true;
    else if (args[i] === '--changed') changed = true;
    else {
        console.error(`Unknown or incomplete option: ${args[i]}`);
        process.exit(1);
    }
}
root = path.resolve(root);
const errors = [];
const warnings = [];
const report = (list, code, message, file, extra = {}) => {
    list.push({ code, ...(file ? { file } : {}), message, ...extra });
};
const error = (code, message, file, extra) =>
    report(errors, code, message, file, extra);
const warn = (code, message, file, extra) =>
    report(warnings, code, message, file, extra);
const abs = (relative) => path.resolve(root, relative);
const exists = (relative) => fs.existsSync(abs(relative));
const compareStrings = (left, right) =>
    left < right ? -1 : left > right ? 1 : 0;
const text = (relative) => {
    try {
        return fs.readFileSync(abs(relative), 'utf8');
    } catch {
        return null;
    }
};
const parseJson = (relative) => {
    const contents = text(relative);
    if (contents === null) return null;
    try {
        return JSON.parse(contents);
    } catch (exception) {
        warn(
            'W_UNVERIFIED',
            `Cannot parse JSON: ${exception.message}`,
            relative,
        );
        return null;
    }
};

// Follow symlinks, but visit a real directory only once to avoid cycles.
function walk(relative, excluded = new Set()) {
    const files = [];
    const visited = new Set();
    function visit(item) {
        if (excluded.has(item)) return;
        try {
            const full = abs(item);
            const stat = fs.statSync(full);
            if (stat.isDirectory()) {
                const real = fs.realpathSync(full);
                if (visited.has(real)) return;
                visited.add(real);
                for (const name of fs.readdirSync(full).sort(compareStrings))
                    visit(path.posix.join(item, name));
            } else if (stat.isFile()) files.push(item);
        } catch (exception) {
            warn(
                'W_UNVERIFIED',
                `Cannot inspect path: ${exception.message}`,
                item,
            );
        }
    }
    if (exists(relative)) visit(relative);
    return files.sort(compareStrings);
}

function packHash(files) {
    const hash = crypto.createHash('sha256');
    for (const file of files.filter((item) => item !== CONFIG.baseline)) {
        hash.update(path.posix.relative(CONFIG.pack, file));
        hash.update('\0');
        hash.update(fs.readFileSync(abs(file)));
        hash.update('\0');
    }
    return hash.digest('hex');
}

function section(contents, title) {
    const escaped = title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = contents?.match(
        new RegExp(
            `^## ${escaped}\\s*$([\\s\\S]*?)(?=^## |$(?![\\s\\S]))`,
            'm',
        ),
    );
    return match?.[1] ?? null;
}

function tableRows(contents, title, file) {
    const body = section(contents, title);
    if (body === null) {
        warn('W_UNVERIFIED', `Cannot find ${title} section`, file);
        return null;
    }
    const rows = body
        .split('\n')
        .filter((line) => /^\s*\|/.test(line))
        .map((line) =>
            line
                .trim()
                .replace(/^\||\|$/g, '')
                .split('|')
                .map((cell) => cell.trim()),
        );
    if (rows.length < 3 || !rows[1].every((cell) => /^:?-+:?$/.test(cell))) {
        warn('W_UNVERIFIED', `Cannot parse ${title} table`, file);
        return null;
    }
    return rows.slice(2);
}

const packFiles = walk(CONFIG.pack);
const markdownFiles = packFiles.filter((file) => file.endsWith('.md'));
const hash = packHash(packFiles);
const profile = text(CONFIG.profile);
const readme = text(CONFIG.readme);
const baseline = parseJson(CONFIG.baseline);
const npm = parseJson('package.json');
const composer = parseJson('composer.json');

// Structure, explicit budgets, guide references, and relative Markdown links.
for (const file of [
    CONFIG.readme,
    `${CONFIG.pack}/FRONTEND_INSTRUCTIONS.md`,
    CONFIG.profile,
    CONFIG.surfaces,
    `${CONFIG.pack}/AGENTS_FRONTEND_SNIPPET.md`,
    ...CONFIG.guides.map((name) => `${CONFIG.pack}/frontend-guides/${name}`),
]) {
    if (!exists(file))
        error('E_STRUCTURE', 'Required pack file is missing', file);
}
const foundGuides = packFiles.filter((file) =>
    /^frontend-instructions\/frontend-guides\/\d{2}-[^/]+\.md$/.test(file),
);
const guideNumbers = new Set();
for (const file of foundGuides) {
    const number = path.basename(file).slice(0, 2);
    if (guideNumbers.has(number))
        error('E_STRUCTURE', `Duplicate guide number ${number}`, file);
    guideNumbers.add(number);
}
for (const name of new Set([
    ...CONFIG.guides,
    ...foundGuides.map((file) => path.posix.basename(file)),
])) {
    if (readme !== null && !readme.includes(name))
        error('E_STRUCTURE', `README does not list ${name}`, CONFIG.readme);
}
if (
    !text('AGENTS.md')?.includes(
        'frontend-instructions/FRONTEND_INSTRUCTIONS.md',
    )
)
    error(
        'E_STRUCTURE',
        'AGENTS.md does not point to the pack entry',
        'AGENTS.md',
    );
for (const file of markdownFiles) {
    const contents = text(file);
    const basename = path.posix.basename(file);
    const guideMatch = basename.match(/^(\d{2})-.*\.md$/);
    const budget =
        CONFIG.budgets[basename] ??
        (guideMatch
            ? guideMatch[1] === '11'
                ? CONFIG.guideBudgets.recipes
                : CONFIG.guideBudgets.standard
            : null);
    const lines =
        contents === '' ? 0 : contents.replace(/\n$/, '').split('\n').length;
    if (budget !== null && lines > budget)
        error(
            'E_BUDGET',
            `${lines} lines exceed the ${budget}-line budget`,
            file,
        );
    for (const match of contents.matchAll(/\b\d{2}-[A-Z][A-Z0-9-]*\.md\b/g)) {
        if (!exists(`${CONFIG.pack}/frontend-guides/${match[0]}`))
            error(
                'E_GUIDE_REF',
                `Guide reference does not resolve: ${match[0]}`,
                file,
            );
    }
    for (const match of contents.matchAll(/\]\(([^)]+)\)/g)) {
        const target = match[1]
            .trim()
            .split(/\s+["']/)[0]
            .split('#')[0];
        if (!target || /^(?:[a-z][a-z0-9+.-]*:|\/)/i.test(target)) continue;
        let decoded;
        try {
            decoded = decodeURIComponent(target);
        } catch {
            decoded = target;
        }
        if (!fs.existsSync(path.resolve(path.dirname(abs(file)), decoded)))
            error(
                'E_GUIDE_REF',
                `Markdown link does not resolve: ${target}`,
                file,
            );
    }
}

const identityRows = tableRows(profile, 'Project identity', CONFIG.profile);
const identity = new Map(identityRows?.map((row) => [row[0], row[1]]) ?? []);
const visualPath = identity.get('Visual spec')?.match(/`([^`]+)`/)?.[1];
if (!visualPath)
    warn(
        'W_UNVERIFIED',
        'Cannot extract visual specification path',
        CONFIG.profile,
    );
else if (!exists(visualPath))
    error('E_STRUCTURE', 'Visual specification is missing', visualPath);
else {
    const design = text(visualPath);
    for (let number = 2; number <= 9; number++)
        if (!new RegExp(`^#{1,6}\\s+${number}\\.`, 'm').test(design))
            error(
                'E_STRUCTURE',
                `Visual specification lacks §${number}`,
                visualPath,
            );
    for (const letter of ['a', 'b', 'c'])
        if (!new RegExp(`^#{1,6}\\s+7\\.2${letter}\\b`, 'm').test(design))
            error(
                'E_STRUCTURE',
                `Visual specification lacks §7.2${letter}`,
                visualPath,
            );
}

// Package requirements may omit patch precision in the profile: compare every
// numeric component the profile actually specifies.
function numericVersion(value) {
    return (
        value
            ?.match(/\d+(?:\.\d+)*/)?.[0]
            .split('.')
            .map(Number) ?? null
    );
}
if (identityRows && npm && composer) {
    const requirements = {
        npm: {
            ...npm.dependencies,
            ...npm.devDependencies,
            ...npm.optionalDependencies,
        },
        composer: { ...composer.require, ...composer['require-dev'] },
    };
    for (const [label, [source, dependency]] of Object.entries(
        CONFIG.versions,
    )) {
        const documented =
            label === 'PHP'
                ? identity.get('Framework')?.match(/PHP\s*`([^`]+)`/)?.[1]
                : identity.get(label);
        const actual = requirements[source][dependency];
        const expectedParts = numericVersion(documented);
        const actualParts = numericVersion(actual);
        if (!expectedParts || !actualParts) {
            if (!actual)
                error(
                    'E_VERSION',
                    `${dependency} is missing from ${source === 'npm' ? 'package.json' : 'composer.json'}`,
                    CONFIG.profile,
                );
            else
                warn(
                    'W_UNVERIFIED',
                    `Cannot parse ${label} version`,
                    CONFIG.profile,
                );
        } else if (
            expectedParts.some(
                (part, index) => part !== (actualParts[index] ?? 0),
            )
        ) {
            error(
                'E_VERSION',
                `${label}: profile ${documented}, ${source === 'npm' ? 'package.json' : 'composer.json'} ${actual}`,
                CONFIG.profile,
            );
        }
    }
} else
    warn(
        'W_UNVERIFIED',
        'Cannot compare identity versions without both manifests and the identity table',
        CONFIG.profile,
    );

const commands = section(profile, 'Commands');
if (commands === null)
    warn('W_UNVERIFIED', 'Cannot parse commands section', CONFIG.profile);
else {
    for (const command of CONFIG.commands)
        if (!commands.includes(command))
            error(
                'E_COMMAND',
                `Profile does not list ${command}`,
                CONFIG.profile,
            );
    const compose = commands.match(/\bcompose(?:\.[\w-]+)?\.ya?ml\b/)?.[0];
    if (!compose)
        warn(
            'W_UNVERIFIED',
            'Cannot extract compose file from commands',
            CONFIG.profile,
        );
    else if (!exists(compose))
        error(
            'E_STRUCTURE',
            `Compose file ${compose} is missing`,
            CONFIG.profile,
        );
}
if (npm) {
    for (const script of ['build', 'types:check'])
        if (!npm.scripts?.[script])
            error(
                'E_COMMAND',
                `npm script ${script} is missing`,
                'package.json',
            );
    const dependencies = {
        ...npm.dependencies,
        ...npm.devDependencies,
        ...npm.optionalDependencies,
    };
    if (!dependencies['vite-plus'])
        error(
            'E_COMMAND',
            'vite-plus is not declared for npx vp',
            'package.json',
        );
}
if (composer && !composer.scripts?.['ci:check'])
    error('E_COMMAND', 'Composer script ci:check is missing', 'composer.json');
if (!exists('vendor'))
    warn(
        'W_UNVERIFIED',
        'vendor/ is absent; Pint cannot be verified',
        'vendor/bin/pint',
    );
else if (!exists('vendor/bin/pint'))
    error('E_COMMAND', 'Pint is missing from vendor/bin', 'vendor/bin/pint');

// Only unambiguous, concrete repository and alias references are checked.
function resolveReference(token) {
    const relative = token.startsWith('@/')
        ? `resources/js/${token.slice(2)}`
        : token;
    if (CONFIG.generated.test(relative)) return { generated: true };
    for (const candidate of [
        relative,
        `${relative}.ts`,
        `${relative}.tsx`,
        `${relative}/index.ts`,
        `${relative}/index.tsx`,
    ]) {
        if (exists(candidate)) return { file: candidate };
    }
    return {};
}
function isConcretePath(token) {
    return (
        /^(?:resources\/|app\/|routes\/|tests\/|database\/|docs\/|frontend-instructions\/|@\/)/.test(
            token,
        ) &&
        !/[\s*…<>|{}]/.test(token) &&
        !token.includes('...') &&
        !token.includes('$')
    );
}
for (const file of markdownFiles) {
    const contents = text(file);
    for (const match of contents.matchAll(/`([^`\n]+)`/g)) {
        const token = match[1];
        if (!isConcretePath(token)) continue;
        const found = resolveReference(token);
        if (!found.file && !found.generated)
            error('E_PATH', `Reference does not resolve: ${token}`, file);
    }
}

// Compare page names, not layout expressions. A trailing slash denotes a prefix.
function layoutTable(contents, title, file) {
    const rows = tableRows(contents, title, file);
    if (!rows) return null;
    const names = rows
        .flatMap((row) =>
            [...(row[0] ?? '').matchAll(/`([^`]+)`/g)].map((match) =>
                match[1].replace(/\*$/, ''),
            ),
        )
        .filter(
            (name) =>
                name !== 'links/revisions.tsx' &&
                name !== 'resources/js/pages/links/revisions.tsx',
        );
    if (!names.length) {
        warn('W_UNVERIFIED', `Cannot extract page names from ${title}`, file);
        return null;
    }
    return new Set(names);
}
const app = text(CONFIG.layoutSource);
const layoutSwitch = app?.match(
    /layout\s*:\s*\(\s*name\s*\)\s*=>\s*\{\s*switch\s*\(\s*true\s*\)\s*\{([\s\S]*?)^\s*}\s*$/m,
)?.[1];
const sourceNames = new Set();
let parsedCases = 0;
if (layoutSwitch) {
    for (const match of layoutSwitch.matchAll(
        /\bcase\s+name\s*===\s*['"]([^'"]+)['"]\s*:/g,
    )) {
        sourceNames.add(match[1]);
        parsedCases++;
    }
    for (const match of layoutSwitch.matchAll(
        /\bcase\s+name\.startsWith\(\s*['"]([^'"]+)['"]\s*\)\s*:/g,
    )) {
        sourceNames.add(match[1]);
        parsedCases++;
    }
}
const totalCases = [...(layoutSwitch ?? '').matchAll(/^\s*case\b/gm)].length;
if (!sourceNames.size || totalCases !== parsedCases)
    warn(
        'W_UNVERIFIED',
        'Cannot fully extract layout switch page names',
        CONFIG.layoutSource,
    );
else {
    for (const [contents, title, file] of [
        [profile, 'Layout registry', CONFIG.profile],
        [text(CONFIG.layoutGuide), 'Layout assignment', CONFIG.layoutGuide],
    ]) {
        const documented = layoutTable(contents, title, file);
        if (!documented) continue;
        for (const name of sourceNames)
            if (!documented.has(name))
                error(
                    'E_LAYOUT',
                    `Layout switch page ${name} is missing from the table`,
                    file,
                );
        for (const name of documented)
            if (!sourceNames.has(name))
                error(
                    'E_LAYOUT',
                    `Table page ${name} is missing from the layout switch`,
                    file,
                );
    }
}

function sharedProps(contents) {
    const start = contents?.match(/\bsharedPageProps\s*:\s*\{/);
    if (!start) return null;
    const bodyStart = start.index + start[0].length;
    let depth = 0;
    const names = new Set();
    for (const line of contents.slice(bodyStart).split('\n')) {
        if (depth === 0 && /^\s*}/.test(line)) return names;
        const prop =
            depth === 0 ? line.match(/^\s*([A-Za-z_$][\w$]*)\??\s*:/) : null;
        if (prop) names.add(prop[1]);
        for (const char of line) {
            if (char === '{') depth++;
            else if (char === '}') depth--;
        }
    }
    return null;
}
const propRows = tableRows(
    profile,
    'Shared props and permissions',
    CONFIG.profile,
);
const sourceProps = sharedProps(text(CONFIG.propsSource));
const documentedProps = new Set(
    propRows?.map((row) => row[0]?.match(/^`([^`]+)`$/)?.[1]).filter(Boolean) ??
        [],
);
if (!sourceProps?.size || !documentedProps.size)
    warn(
        'W_UNVERIFIED',
        'Cannot extract shared prop keys or table',
        CONFIG.propsSource,
    );
else {
    for (const name of sourceProps)
        if (!documentedProps.has(name))
            error(
                'E_PROPS',
                `Shared prop ${name} is missing from the profile`,
                CONFIG.profile,
            );
    for (const name of documentedProps)
        if (!sourceProps.has(name))
            error(
                'E_PROPS',
                `Profile prop ${name} is missing from sharedPageProps`,
                CONFIG.profile,
            );
}

const utilities = tableRows(profile, 'Utilities', CONFIG.profile);
if (utilities) {
    for (const row of utilities) {
        const name = row[0]?.match(/^`([^`]+)`$/)?.[1];
        const imported = row[1]?.match(/^`([^`]+)`$/)?.[1];
        if (!name || !imported) {
            warn(
                'W_UNVERIFIED',
                `Cannot parse utility row: ${row.join(' | ')}`,
                CONFIG.profile,
            );
            continue;
        }
        const resolved = resolveReference(imported);
        if (!resolved.file) {
            error(
                'E_UTILITY',
                `Utility import ${imported} does not resolve`,
                CONFIG.profile,
            );
            continue;
        }
        const source = text(resolved.file);
        const safeName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        if (
            !new RegExp(
                `\\bexport\\s+(?:(?:async|declare)\\s+)?(?:function|const|let|class|type|interface|enum)\\s+${safeName}\\b|\\bexport\\s*\\{[^}]*\\b${safeName}\\b[^}]*\\}`,
                's',
            ).test(source)
        ) {
            error(
                'E_UTILITY',
                `${name} is not a named export of ${resolved.file}`,
                CONFIG.profile,
            );
        }
    }
}
for (const guideFile of foundGuides) {
    const guide = text(guideFile);
    if (guide === null) continue;
    for (const match of guide.matchAll(/\/\/\s*source:\s*([^\s)]+)/g)) {
        if (!exists(match[1]))
            error(
                'E_RECIPE',
                `Recipe source does not exist: ${match[1]}`,
                guideFile,
            );
    }
}

const conformance = Object.fromEntries(
    Object.keys(CONFIG.conformance).map((name) => [name, 0]),
);
const conformanceMatches = [];
for (const file of walk('resources/js', CONFIG.excluded).filter((item) =>
    /\.[cm]?[jt]sx?$/.test(item),
)) {
    const contents = text(file);
    for (const [name, pattern] of Object.entries(CONFIG.conformance)) {
        for (const match of contents.matchAll(pattern)) {
            conformance[name]++;
            const line = contents.slice(0, match.index).split('\n').length;
            conformanceMatches.push({ rule: name, file, line });
        }
    }
}
for (const [name, count] of Object.entries(conformance)) {
    const previous = baseline?.conformance?.[name];
    if (!Number.isInteger(previous) || previous < 0) {
        warn(
            'W_UNVERIFIED',
            `No conformance baseline for ${name}; observed ${count}`,
            CONFIG.baseline,
        );
    } else if (count > previous) {
        const locations = conformanceMatches
            .filter((match) => match.rule === name)
            .map((match) => `${match.file}:${match.line}`);
        error(
            'E_CONFORMANCE',
            `${name}: ${count} exceeds baseline ${previous}; matches: ${locations.join(', ')}`,
            CONFIG.baseline,
        );
    } else if (count < previous)
        warn(
            'W_BASELINE_DECREASE',
            `${name}: ${count} is below baseline ${previous}`,
            CONFIG.baseline,
        );
}
if (!baseline?.packHash || baseline.packHash !== hash)
    warn(
        'W_PACK_CHANGED',
        'Pack hash differs from the last full audit; run a full audit',
        CONFIG.baseline,
    );

// Read the impact map itself so documentation and freshness scopes cannot drift.
function impactRules() {
    const rows = tableRows(profile, 'Change impact map', CONFIG.profile);
    if (!rows) return [];
    return rows.flatMap((row) => {
        const patterns = [...(row[0] ?? '').matchAll(/`([^`]+)`/g)].map(
            (match) => match[1],
        );
        const guides = [...(row[1] ?? '').matchAll(/\b(0[1-9]|1[01])\b/g)].map(
            (match) => Number(match[1]),
        );
        const documents = [...(row[1] ?? '').matchAll(/`([^`]+)`/g)].map(
            (match) => match[1],
        );
        return patterns.map((pattern) => ({
            pattern,
            guides,
            documents,
            exceptUi: row[0].includes('(except `ui`)'),
        }));
    });
}
function impacts(file, rule) {
    if (rule.exceptUi && file.startsWith('resources/js/components/ui/'))
        return false;
    return rule.pattern.endsWith('/**')
        ? file.startsWith(rule.pattern.slice(0, -2))
        : file === rule.pattern;
}
if (changed) {
    if (
        !baseline?.verifiedAt ||
        Number.isNaN(Date.parse(baseline.verifiedAt))
    ) {
        warn(
            'W_NO_GIT',
            'A valid verifiedAt timestamp is required for freshness; audit manually',
            CONFIG.baseline,
        );
    } else {
        const files = new Set();
        const failures = [];
        try {
            const log = execFileSync(
                'git',
                [
                    '-C',
                    root,
                    'log',
                    `--since=${baseline.verifiedAt}`,
                    '--pretty=format:',
                    '--name-only',
                    '--diff-merges=first-parent',
                    '--relative',
                    '--',
                ],
                { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
            );
            for (const file of log.split('\n').filter(Boolean)) files.add(file);
        } catch {
            failures.push('history');
        }
        try {
            const status = execFileSync(
                'git',
                [
                    '-C',
                    root,
                    'status',
                    '--porcelain=v1',
                    '-z',
                    '--untracked-files=all',
                ],
                { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
            );
            const entries = status.split('\0');
            for (let index = 0; index < entries.length; index++) {
                const entry = entries[index];
                if (!entry) continue;
                files.add(entry.slice(3));
                if (/[RC]/.test(entry.slice(0, 2)) && entries[index + 1])
                    files.add(entries[++index]);
            }
        } catch {
            failures.push('status');
        }
        if (failures.length)
            warn(
                'W_NO_GIT',
                `Git ${failures.join(' and ')} unavailable; audit manually`,
            );
        const rules = impactRules();
        const matching = [...files].flatMap((file) =>
            rules
                .filter((rule) => impacts(file, rule))
                .map((rule) => ({ file, rule })),
        );
        if (matching.length) {
            const guides = [
                ...new Set(matching.flatMap(({ rule }) => rule.guides)),
            ].sort((a, b) => a - b);
            const documents = [
                ...new Set(matching.flatMap(({ rule }) => rule.documents)),
            ].sort(compareStrings);
            const affectedFiles = [
                ...new Set(matching.map(({ file }) => file)),
            ].sort(compareStrings);
            warn(
                'W_STALE',
                `Changes since ${baseline.verifiedAt} or current uncommitted files affect ${[guides.length ? `guides ${guides.map((n) => String(n).padStart(2, '0')).join(', ')}` : '', ...documents].filter(Boolean).join('; ')}`,
                undefined,
                { guides, documents, files: affectedFiles },
            );
        }
    }
}

const result = {
    root,
    packHash: hash,
    conformance,
    conformanceMatches,
    errors,
    warnings,
};
if (jsonOutput) console.log(JSON.stringify(result, null, 2));
else {
    console.log(
        `Frontend instruction pack check\nRoot: ${root}\nPack hash: ${hash}\nErrors: ${errors.length} | Warnings: ${warnings.length}`,
    );
    for (const item of errors)
        console.log(
            `ERROR ${item.code} ${item.file ? `${item.file}: ` : ''}${item.message}`,
        );
    for (const item of warnings)
        console.log(
            `WARN  ${item.code} ${item.file ? `${item.file}: ` : ''}${item.message}`,
        );
    console.log(
        `Conformance: ${Object.entries(conformance)
            .map(([name, count]) => `${name}=${count}`)
            .join(', ')}`,
    );
    for (const item of conformanceMatches)
        console.log(`  ${item.rule} ${item.file}:${item.line}`);
}
process.exitCode = errors.length ? 1 : 0;
