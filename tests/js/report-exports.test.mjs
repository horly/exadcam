import test from 'node:test';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {cellValue,exportReport} from '../../public/js/report-exports.mjs';
const require=createRequire(import.meta.url),ExcelJS=require('exceljs'),{jsPDF}=require('jspdf');
require('jspdf-autotable').applyPlugin(jsPDF);
test('exports readable PDF and a genuine workbook with typed values and literal user text',async()=>{
    let pdf=null,blob=null,name=null;
    global.window={ExcelJS,jspdf:{jsPDF:function(...args){const doc=new jsPDF(...args);doc.save=filename=>{pdf=Buffer.from(doc.output('arraybuffer'));name=filename;};return doc;}}};
    global.document={head:{append(el){queueMicrotask(()=>el.onload());}},body:{append(){}},createElement(){return{remove(){},click(){name=this.download;}};}};
    const originalCreate=URL.createObjectURL,originalTimer=global.setTimeout;
    URL.createObjectURL=value=>{blob=value;return 'blob:test';};global.setTimeout=(fn,ms)=>ms===60000?0:originalTimer(fn,ms);
    try{
        const config={locale:'fr',vendor:'/vendor/reports',labels:{summary:'Synthèse de flotte',fleet:'Flotte',period:'Période',generated:'Généré le',quality:'Qualité',quality_hint:':known documentées · :unknown sans données',method:'Distance GPS estimée. Horaires de Kinshasa.',daily:'Activité quotidienne',distance:'Distance',moving:'Mouvement',alerts:'Alarmes'}};
        const data={type:'summary',branding:{name:'EXADCAM',fleet:'Flotte éàœ',color:'#203d65',logo:null},period:{from:'2026-09-28',to:'2026-09-28',generated_at:'2026-09-28T12:00:00Z'},metrics:{known_seconds:3661,unknown_seconds:100},daily:[{day:'2026-09-28',distance_km:12.345,moving_seconds:3600,alerts:1,known_seconds:3661}],columns:[{key:'vehicle',label:'Véhicule',format:'text'},{key:'distance_km',label:'Distance',format:'km'},{key:'moving_seconds',label:'Durée',format:'duration'}],rows:[{vehicle:'=HYPERLINK("https://invalid.test")',distance_km:12.345,moving_seconds:3661}]};
        await exportReport('pdf',data,config);assert.equal(pdf.subarray(0,5).toString(),'%PDF-');assert.match(name,/\.pdf$/);assert.ok(pdf.length>1000);
        await exportReport('excel',data,config);assert.match(name,/\.xlsx$/);const workbook=new ExcelJS.Workbook();await workbook.xlsx.load(await blob.arrayBuffer());
        const sheet=workbook.getWorksheet('Synthèse de flotte');assert.equal(sheet.getCell('A2').value,data.rows[0].vehicle);assert.equal(sheet.getCell('A2').type,ExcelJS.ValueType.String);assert.equal(sheet.getCell('B2').value,12.345);assert.equal(sheet.getCell('C2').value.toISOString(),'1899-12-30T01:01:01.000Z');assert.equal(sheet.views[0].state,'frozen');assert.ok(sheet.autoFilter);assert.equal(workbook.getWorksheet('Informations').getCell('D1').value,null);
        assert.equal(cellValue('2026-09-28T23:30:00Z','datetime','fr').includes('29/09/2026'),true);
    }finally{URL.createObjectURL=originalCreate;global.setTimeout=originalTimer;delete global.window;delete global.document;}
});
