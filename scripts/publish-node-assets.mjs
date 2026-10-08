import { mkdirSync, copyFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const root = new URL('../', import.meta.url);
const destination = new URL('public/vendor/hls/', root);
mkdirSync(destination, { recursive: true });
for (const [source, target] of [['dist/hls.min.js', 'hls.min.js'], ['LICENSE', 'LICENSE']]) {
    copyFileSync(new URL(`node_modules/hls.js/${source}`, root), new URL(target, destination));
}
console.log(`HLS.js published to ${fileURLToPath(destination)}`);

const reports = new URL('public/vendor/reports/', root);
mkdirSync(reports, { recursive: true });
for (const [source, target] of [
    ['jspdf/dist/jspdf.umd.min.js','jspdf.umd.min.js'], ['jspdf/LICENSE','LICENSE-jspdf'],
    ['jspdf-autotable/dist/jspdf.plugin.autotable.min.js','jspdf.plugin.autotable.min.js'], ['jspdf-autotable/LICENSE.txt','LICENSE-autotable'],
    ['exceljs/dist/exceljs.min.js','exceljs.min.js'], ['exceljs/LICENSE','LICENSE-exceljs'],
]) copyFileSync(new URL('node_modules/'+source, root), new URL(target, reports));
console.log('Local report export libraries published');
