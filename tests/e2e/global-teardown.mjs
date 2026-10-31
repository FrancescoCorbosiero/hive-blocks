// Fails the e2e run on any PHP notice, warning, deprecation or "doing it
// wrong" that went through Hive Blocks (recorded by tests/e2e/mu-plugin.php).
// Prints the end of WordPress's debug.log too: when a test fails on an error
// page, the reason is there (artifacts can't always be opened).
import { existsSync, readFileSync } from 'node:fs';

export default function globalTeardown() {
    const debug = new URL('./artifacts/debug.log', import.meta.url);
    const lines = existsSync(debug) ? readFileSync(debug, 'utf8').trim().split('\n').filter(Boolean) : [];
    if (lines.length) {
        console.log(`\nWordPress debug.log (last ${Math.min(lines.length, 80)} of ${lines.length} lines):\n${lines.slice(-80).join('\n')}\n`);
    }
    const log = new URL('./artifacts/issues.log', import.meta.url);
    const issues = existsSync(log) ? readFileSync(log, 'utf8').trim() : '';
    if (issues) {
        throw new Error(`Hive Blocks raised PHP issues during the run:\n${issues}`);
    }
}
