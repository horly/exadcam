import { mkdirSync, copyFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const root = new URL('../', import.meta.url);
const destination = new URL('public/vendor/hls/', root);
mkdirSync(destination, { recursive: true });
for (const [source, target] of [['dist/hls.min.js', 'hls.min.js'], ['LICENSE', 'LICENSE']]) {
    copyFileSync(new URL(`node_modules/hls.js/${source}`, root), new URL(target, destination));
}
console.log(`HLS.js published to ${fileURLToPath(destination)}`);
