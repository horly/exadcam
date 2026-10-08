export function cellValue(value, format, locale = 'fr') {
    if (value === null || value === undefined) return '—';
    if (format === 'datetime') return new Intl.DateTimeFormat(locale, {timeZone:'Africa/Kinshasa', dateStyle:'short', timeStyle:'medium'}).format(new Date(value));
    if (format === 'duration') { const seconds=Math.round(value); return `${Math.floor(seconds/3600)} h ${String(Math.floor(seconds%3600/60)).padStart(2,'0')} min`; }
    if (['km','speed','percent'].includes(format)) return new Intl.NumberFormat(locale,{maximumFractionDigits:1}).format(value)+({km:' km',speed:' km/h',percent:' %'}[format]);
    return String(value);
}
const scripts = new Map();
async function script(url) {
    if (!scripts.has(url)) scripts.set(url,new Promise((resolve,reject)=>{const el=document.createElement('script');el.src=url;el.onload=resolve;el.onerror=()=>{scripts.delete(url);el.remove();reject(Error('export_failed'));};document.head.append(el);}));
    return scripts.get(url);
}
async function logoData(url) {
    if (!url || new URL(url,location.href).origin !== location.origin) return null;
    try { return await new Promise(resolve=>{const img=new Image();const timer=setTimeout(()=>resolve(null),7000);img.onload=()=>{clearTimeout(timer);try{const canvas=document.createElement('canvas');canvas.width=400;canvas.height=180;const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,400,180);const ratio=Math.min(380/img.naturalWidth,160/img.naturalHeight);ctx.drawImage(img,(400-img.naturalWidth*ratio)/2,(180-img.naturalHeight*ratio)/2,img.naturalWidth*ratio,img.naturalHeight*ratio);resolve(canvas.toDataURL('image/png'));}catch{resolve(null);}};img.onerror=()=>{clearTimeout(timer);resolve(null);};img.src=url;}); } catch { return null; }
}
function download(blob,name) { const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000); }
export async function exportReport(kind, data, config) {
    const l=config.labels,title=l[data.type],logo=await logoData(data.branding.logo),file=`EXADCAM-${data.type}-${data.period.from}-${data.period.to}`;
    const period=`${data.period.from} — ${data.period.to} · Kinshasa`;
    const generated=`${l.generated} ${cellValue(data.period.generated_at,'datetime',config.locale)}`;
    const notes=l.quality_hint.replace(':known',cellValue(data.metrics.known_seconds,'duration',config.locale)).replace(':unknown',cellValue(data.metrics.unknown_seconds,'duration',config.locale));
    if(kind==='pdf') {
        await script(config.vendor+'/jspdf.umd.min.js');await script(config.vendor+'/jspdf.plugin.autotable.min.js');
        const doc=new window.jspdf.jsPDF({orientation:'landscape',unit:'mm',format:'a4'}),color=/^#[0-9a-f]{6}$/i.test(data.branding.color)?data.branding.color:'#213e65';
        doc.setTextColor(color);doc.setFontSize(18);doc.text(data.branding.name,14,18);doc.setFontSize(13);doc.text(title,14,27);
        if(logo)doc.addImage(logo,'PNG',244,8,38,17);
        doc.setTextColor('#536781');doc.setFontSize(9);
        let y=35;for(const text of [data.branding.fleet,period,generated,notes,l.method,...(data.type==='safety'?[l.safety_hint]:[])]){const lines=doc.splitTextToSize(text,269);doc.text(lines,14,y);y+=lines.length*4+3;}
        doc.autoTable({startY:y+2,head:[data.columns.map(c=>c.label)],body:data.rows.map(r=>data.columns.map(c=>cellValue(r[c.key],c.format,config.locale))),styles:{fontSize:8,cellPadding:3,overflow:'linebreak',textColor:'#213e65'},headStyles:{fillColor:color,textColor:'#ffffff'},alternateRowStyles:{fillColor:'#f3f6fa'},margin:{left:14,right:14,bottom:16},didDrawPage:()=>{doc.setFontSize(8);doc.setTextColor('#71849b');doc.text(`${data.branding.name} · ${title} · ${doc.internal.getNumberOfPages()}`,14,203);}});
        doc.save(file+'.pdf');
    } else {
        await script(config.vendor+'/exceljs.min.js');
        const workbook=new window.ExcelJS.Workbook();workbook.creator=data.branding.name;workbook.created=new Date(data.period.generated_at);
        const info=workbook.addWorksheet(config.locale==='fr'?'Informations':'Information');info.columns=[{width:28},{width:95}];
        info.addRow([data.branding.name,title]);info.addRow([l.fleet,data.branding.fleet]);info.addRow([l.period,period]);info.addRow([l.generated,cellValue(data.period.generated_at,'datetime',config.locale)]);info.addRow([l.quality,notes]);info.addRow([l.quality,l.method]);if(data.type==='safety')info.addRow([l.safety,l.safety_hint]);
        info.eachRow(row=>{row.alignment={vertical:'top',wrapText:true};row.height=row.number>=5?70:28;});info.getRow(1).font={bold:true,size:14,color:{argb:'FF213E65'}};
        if(logo){const id=workbook.addImage({base64:logo,extension:'png'});info.addImage(id,{tl:{col:0,row:9},ext:{width:200,height:90}});}
        const sheet=workbook.addWorksheet(title.slice(0,31),{views:[{state:'frozen',ySplit:1}]});
        sheet.columns=data.columns.map(c=>({header:c.label,key:c.key,width:c.format==='datetime'?24:c.key==='vehicle'?36:c.format==='text'?28:20}));
        for(const record of data.rows){const row=sheet.addRow(data.columns.map(c=>{const v=record[c.key];if(v==null)return null;if(c.format==='datetime')return cellValue(v,c.format,config.locale);if(c.format==='duration')return Number(v)/86400;if(c.format==='percent')return Number(v)/100;return c.format==='text'?String(v):Number(v);}));row.alignment={vertical:'middle',wrapText:true};row.height=30;}
        const color='FF'+(data.branding.color||'#213e65').slice(1).toUpperCase();sheet.getRow(1).font={bold:true,color:{argb:'FFFFFFFF'}};sheet.getRow(1).fill={type:'pattern',pattern:'solid',fgColor:{argb:color}};sheet.getRow(1).height=30;
        data.columns.forEach((c,i)=>{const format={duration:'[h]" h "mm" min"',km:'0.0" km"',speed:'0.0" km/h"',percent:'0.0%',integer:'0'}[c.format];if(format)sheet.getColumn(i+1).numFmt=format;});
        sheet.autoFilter={from:{row:1,column:1},to:{row:Math.max(1,sheet.rowCount),column:data.columns.length}};
        const daily=workbook.addWorksheet(l.daily);daily.columns=[{header:l.period,key:'day',width:20},{header:l.distance,key:'distance_km',width:24},{header:l.moving,key:'moving_seconds',width:24},{header:l.alerts,key:'alerts',width:20}];
        data.daily.forEach(d=>daily.addRow({...d,distance_km:d.known_seconds?d.distance_km:null,moving_seconds:d.known_seconds?d.moving_seconds/86400:null}));daily.getColumn(2).numFmt='0.0" km"';daily.getColumn(3).numFmt='[h]" h "mm" min"';
        download(new Blob([await workbook.xlsx.writeBuffer()],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),file+'.xlsx');
    }
}
