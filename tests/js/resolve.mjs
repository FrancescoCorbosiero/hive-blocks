// Module resolve hook (see hooks.mjs): '@hive/<name>' → assets/modules/<name>.js.
const modules = new URL('../../assets/modules/', import.meta.url);

export async function resolve(specifier, context, nextResolve) {
    const match = /^@hive\/([a-z0-9-]+)$/.exec(specifier);
    if (match) {
        return { url: new URL(`${match[1]}.js`, modules).href, shortCircuit: true };
    }
    return nextResolve(specifier, context);
}
