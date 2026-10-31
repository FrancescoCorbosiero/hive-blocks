#!/usr/bin/env node
/**
 * Build: everything the browser loads goes through esbuild, and every output
 * gets a sibling .asset.php holding a hash of its content (the version
 * WordPress puts in ?ver=, so a URL changes only when its file does) and,
 * for scripts, what they depend on.
 *
 *   blocks/<name>/edit.js        → build/edit.js     editor script: classic, WordPress globals
 *   blocks/<name>/view.js        → build/view.js     front-end script module, @hive/* left as imports
 *   blocks/<name>/style.css      → build/style.css
 *   assets/<name>.css            → assets/build/<name>.css           style hive-<name>
 *   assets/modules/<name>.js     → assets/build/modules/<name>.js   script module @hive/<name>
 *   assets/editor/index.js       → assets/build/editor.js           script hive-editor (window.hive.editor)
 *
 * It also writes blocks/blocks-manifest.php: every block.json in one PHP
 * array, so WordPress registers the blocks without reading JSON files.
 *
 * The output is committed (the plugin runs from the repository as is), and
 * CI fails when it doesn't match the sources.
 *
 * Usage: node tools/build.mjs [--watch]
 */
import * as esbuild from 'esbuild';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, watch, writeFileSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const rel = (file) => path.relative(root, file);

/** The browsers the store supports: color-mix(), CSS nesting is lowered for the older ones. */
const BROWSERS = ['chrome111', 'edge111', 'firefox113', 'safari16.2', 'ios16.2'];

/** Script modules WordPress itself registers, which view modules may import. */
const WORDPRESS_MODULES = ['@wordpress/interactivity', '@wordpress/interactivity-router', '@wordpress/a11y'];

/* -------------------------------------------------------------------- *
 * PHP output
 * -------------------------------------------------------------------- */

/** A JSON-like value as PHP source: array( 'key' => 'value' ), with tabs. */
function php(value, depth = 0) {
    const pad = '\t'.repeat(depth + 1);
    const end = '\t'.repeat(depth);
    if (value === null || value === undefined) return 'null';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (typeof value === 'number') return String(value);
    if (typeof value === 'string') return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    if (Array.isArray(value)) {
        if (!value.length) return 'array()';
        return `array(\n${value.map((v) => `${pad}${php(v, depth + 1)},`).join('\n')}\n${end})`;
    }
    const entries = Object.entries(value);
    if (!entries.length) return 'array()';
    return `array(\n${entries.map(([k, v]) => `${pad}${php(k)} => ${php(v, depth + 1)},`).join('\n')}\n${end})`;
}

const hash = (contents) => createHash('sha256').update(contents).digest('hex').slice(0, 20);

function write(file, contents) {
    mkdirSync(path.dirname(file), { recursive: true });
    writeFileSync(file, contents);
}

/** The output file, and its .asset.php with the version (and the dependencies, for scripts). */
function emit(outfile, contents, dependencies) {
    write(outfile, contents);
    const asset = dependencies ? { dependencies, version: hash(contents) } : { version: hash(contents) };
    write(outfile.replace(/\.(js|css)$/, '.asset.php'), `<?php return ${php(asset)};\n`);
}

/* -------------------------------------------------------------------- *
 * esbuild
 * -------------------------------------------------------------------- */

const common = {
    bundle: true,
    minify: true,
    target: BROWSERS,
    write: false,
    charset: 'utf8',
    legalComments: 'none',
    logLevel: 'warning',
};

/** Classic scripts: these imports are globals WordPress (or hive-editor) provides, recorded as script dependencies. */
function globalFor(request) {
    const fixed = {
        react: ['React', 'react'],
        'react-dom': ['ReactDOM', 'react-dom'],
        'react/jsx-runtime': ['ReactJSXRuntime', 'react-jsx-runtime'],
        '@hive/editor': ['hive.editor', 'hive-editor'],
    };
    if (fixed[request]) return fixed[request];
    const wp = /^@wordpress\/([a-z0-9-]+)$/.exec(request);
    return wp ? [`wp.${wp[1].replace(/-([a-z0-9])/g, (_, c) => c.toUpperCase())}`, `wp-${wp[1]}`] : null;
}

function wordpressGlobals(dependencies) {
    return {
        name: 'wordpress-globals',
        setup(build) {
            build.onResolve({ filter: /^(@wordpress\/|@hive\/|react)/ }, (args) => {
                const found = globalFor(args.path);
                if (!found) {
                    return { errors: [{ text: `${args.path} isn't available to editor scripts: they can import @wordpress/*, react and @hive/editor.` }] };
                }
                dependencies.add(found[1]);
                return { path: args.path, namespace: 'wordpress-global', pluginData: found[0] };
            });
            build.onLoad({ filter: /.*/, namespace: 'wordpress-global' }, (args) => ({
                contents: `module.exports = window.${args.pluginData};`,
                loader: 'js',
            }));
        },
    };
}

/** Script modules: @hive/* and WordPress's own modules stay imports, resolved by WordPress's import map. */
function moduleImports(dependencies) {
    return {
        name: 'module-imports',
        setup(build) {
            build.onResolve({ filter: /^@(hive|wordpress)\// }, (args) => {
                if (!args.path.startsWith('@hive/') && !WORDPRESS_MODULES.includes(args.path)) {
                    return { errors: [{ text: `${args.path} isn't a script module: view modules can import @hive/* and ${WORDPRESS_MODULES.join(', ')}.` }] };
                }
                const kind = args.kind === 'dynamic-import' ? 'dynamic' : 'static';
                if (dependencies.get(args.path) !== 'static') dependencies.set(args.path, kind);
                return { path: args.path, external: true };
            });
        },
    };
}

async function editorScript(entry, outfile) {
    const dependencies = new Set();
    const result = await esbuild.build({
        ...common,
        entryPoints: [entry],
        outfile,
        format: 'iife',
        jsx: 'automatic',
        loader: { '.js': 'jsx' },
        plugins: [wordpressGlobals(dependencies)],
    });
    emit(outfile, result.outputFiles[0].contents, [...dependencies].sort());
}

async function scriptModule(entry, outfile) {
    const dependencies = new Map();
    const result = await esbuild.build({
        ...common,
        entryPoints: [entry],
        outfile,
        format: 'esm',
        plugins: [moduleImports(dependencies)],
    });
    const list = [...dependencies.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([id, kind]) => (kind === 'static' ? id : { id, import: 'dynamic' }));
    emit(outfile, result.outputFiles[0].contents, list);
}

async function stylesheet(entry, outfile) {
    const result = await esbuild.build({ ...common, entryPoints: [entry], outfile });
    emit(outfile, result.outputFiles[0].contents);
}

/* -------------------------------------------------------------------- *
 * The plugin
 * -------------------------------------------------------------------- */

const dirs = (dir) => (existsSync(dir) ? readdirSync(dir, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name).sort() : []);
const files = (dir, ext) => (existsSync(dir) ? readdirSync(dir).filter((f) => f.endsWith(ext)).sort() : []);

async function build() {
    const started = Date.now();
    const jobs = [];

    // Shared assets.
    const assets = path.join(root, 'assets');
    const assetsOut = path.join(assets, 'build');
    rmSync(assetsOut, { recursive: true, force: true });
    for (const file of files(assets, '.css')) {
        jobs.push(stylesheet(path.join(assets, file), path.join(assetsOut, file)));
    }
    for (const file of files(path.join(assets, 'modules'), '.js')) {
        jobs.push(scriptModule(path.join(assets, 'modules', file), path.join(assetsOut, 'modules', file)));
    }
    jobs.push(editorScript(path.join(assets, 'editor', 'index.js'), path.join(assetsOut, 'editor.js')));

    // Blocks.
    const blocks = path.join(root, 'blocks');
    const manifest = {};
    for (const name of dirs(blocks)) {
        const dir = path.join(blocks, name);
        const json = path.join(dir, 'block.json');
        if (!existsSync(json)) continue;
        manifest[name] = JSON.parse(readFileSync(json, 'utf8'));

        const out = path.join(dir, 'build');
        rmSync(out, { recursive: true, force: true });
        if (existsSync(path.join(dir, 'edit.js'))) jobs.push(editorScript(path.join(dir, 'edit.js'), path.join(out, 'edit.js')));
        if (existsSync(path.join(dir, 'view.js'))) jobs.push(scriptModule(path.join(dir, 'view.js'), path.join(out, 'view.js')));
        if (existsSync(path.join(dir, 'style.css'))) jobs.push(stylesheet(path.join(dir, 'style.css'), path.join(out, 'style.css')));
    }
    write(
        path.join(blocks, 'blocks-manifest.php'),
        `<?php\n// Generated by tools/build.mjs from blocks/*/block.json: edit those, then run npm run build.\nreturn ${php(manifest)};\n`
    );

    await Promise.all(jobs);
    console.log(`Built ${Object.keys(manifest).length} block(s) and the shared assets in ${Date.now() - started} ms.`);
}

await build();

if (process.argv.includes('--watch')) {
    let timer = null;
    const sources = ['blocks', 'assets'].map((d) => path.join(root, d));
    for (const dir of sources) {
        watch(dir, { recursive: true }, (_event, file) => {
            if (!file || /(^|[\\/])build([\\/]|$)|blocks-manifest\.php$/.test(file)) return;
            clearTimeout(timer);
            timer = setTimeout(() => build().catch((error) => console.error(error.message)), 100);
        });
    }
    console.log(`Watching ${sources.map(rel).join(', ')}…`);
}
