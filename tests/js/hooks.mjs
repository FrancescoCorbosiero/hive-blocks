// Loaded with node --import: lets the tests import '@hive/<name>' the way
// WordPress's import map serves it in the browser (assets/modules/<name>.js).
import { register } from 'node:module';

register('./resolve.mjs', import.meta.url);
