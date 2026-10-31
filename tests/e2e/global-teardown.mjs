// Fails the e2e run on any PHP notice, warning, deprecation or "doing it
// wrong" that went through Hive Blocks (recorded by tests/e2e/mu-plugin.php).
import { existsSync, readFileSync } from 'node:fs';

export default function globalTeardown() {
    const log = new URL('./artifacts/issues.log', import.meta.url);
    const issues = existsSync(log) ? readFileSync(log, 'utf8').trim() : '';
    if (issues) {
        throw new Error(`Hive Blocks raised PHP issues during the run:\n${issues}`);
    }
}
