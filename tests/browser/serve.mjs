#!/usr/bin/env node
/**
 * A static file server for the browser tests: serves the plugin folder, so
 * the fixtures load the real build output (assets/build, blocks/<name>/build).
 */
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const port = Number(process.env.PORT || 9401);
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.svg': 'image/svg+xml' };

createServer(async (request, response) => {
    const url = new URL(request.url, `http://127.0.0.1:${port}`);
    const file = path.join(root, decodeURIComponent(url.pathname));
    if (!file.startsWith(root + path.sep)) {
        response.writeHead(403).end();
        return;
    }
    try {
        const body = await readFile(file);
        response.writeHead(200, { 'Content-Type': `${types[path.extname(file)] || 'application/octet-stream'}; charset=utf-8` });
        response.end(body);
    } catch {
        response.writeHead(404).end();
    }
}).listen(port, '127.0.0.1', () => console.log(`Serving ${root} on http://127.0.0.1:${port}`));
