const configNode=document.getElementById('recordings-config');
if(configNode){
    const config=JSON.parse(configNode.textContent),labels=config.labels,$=id=>document.getElementById('recordings-'+id);
    const search=$('search'),prepare=$('prepare'),vehicle=$('vehicle'),channel=$('channel'),message=$('message'),rows=$('rows'),player=$('player');
    const csrf=document.querySelector('meta[name="csrf-token"]').content;
    let query=null,selected=null,activeJob=null,pollTimer=null,generation=0,busy=false,tableLoading=false,filterTimer=null;
    const tableState={search:'',sort:'start',direction:'desc',per_page:5};
    const sortButtons=[...document.querySelectorAll('[data-recording-sort]')];
    const translate=(key,values)=>Object.entries(values).reduce((text,[name,value])=>text.replaceAll(':'+name,String(value)),labels[key]);
    const format=value=>new Intl.DateTimeFormat(config.locale,{timeZone:'Africa/Kinshasa',dateStyle:'short',timeStyle:'medium'}).format(new Date(value));
    const inputTime=value=>new Date(Date.parse(value)+3600000).toISOString().slice(0,19);
    const utc=value=>new Date(value+'+01:00').toISOString();
    const size=value=>value?`${(value/1048576).toFixed(1)} Mo`:'—';
    const make=(tag,text)=>{const el=document.createElement(tag);el.textContent=text;return el;};
    async function request(url,data,method='POST'){
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),45000);
        try{
            const response=await fetch(url,{method,credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},...(method==='GET'?{}:{body:JSON.stringify(data||{})})});
            const result=await response.json().catch(()=>({}));
            if(!response.ok||response.redirected){const error=Error(result.message||labels.unavailable);error.status=response.redirected?401:response.status;throw error;}
            return result;
        }finally{clearTimeout(timeout);}
    }
    const base=device=>`${config.baseUrl}/${encodeURIComponent(device)}/recordings`;
    function syncTableControls(){
        const disabled=busy||tableLoading||!query;
        $('filter').disabled=busy||!query;
        $('page-size').disabled=disabled;
        for(const button of sortButtons)button.disabled=disabled;
        for(const button of $('page-numbers').querySelectorAll('button'))button.disabled=disabled;
        for(const button of rows.querySelectorAll('button'))button.disabled=disabled;
        $('previous').disabled=disabled||query.page<=1;
        $('next').disabled=disabled||query.page>=query.last_page;
        search.querySelector('button[type="submit"]').disabled=busy||tableLoading;
        $('table').setAttribute('aria-busy',String(tableLoading));
    }
    function setBusy(value){busy=value;for(const field of [...search.elements,...prepare.elements])field.disabled=value;$('cancel').hidden=!value;syncTableControls();}
    function clearPlayer(){player.pause();player.removeAttribute('src');player.load();player.hidden=true;$('download').hidden=true;}
    function updateChannels(){
        const count=Number(vehicle.selectedOptions[0]?.dataset.channels||0);
        channel.replaceChildren(new Option(labels.all_channels,'0'),...Array.from({length:count},(_,i)=>new Option(`${labels.channel} ${i+1}`,String(i+1))));
    }
    function clearSelection(){selected=null;clearPlayer();$('preview').hidden=true;}
    function emptyRows(text){const row=make('tr',''),cell=make('td',text);cell.colSpan=7;cell.className='users-empty';row.append(cell);rows.replaceChildren(row);}
    function updatePages(result={page:1,last_page:1,from:0,to:0,total:0,unfiltered_total:0}){
        $('summary').textContent=translate('pagination_summary',{first:result.from,last:result.to,total:result.total})+(result.total<result.unfiltered_total?' '+translate('filtered_from',{total:result.unfiltered_total}):'');
        $('page-numbers').replaceChildren();
        for(let number=Math.max(1,result.page-2);number<=Math.min(result.last_page,result.page+2);number++){
            const button=make('button',String(number));button.type='button';button.className='users-page-button'+(number===result.page?' active':'');
            button.setAttribute('aria-label',translate('page_label',{page:number}));if(number===result.page)button.setAttribute('aria-current','page');
            button.addEventListener('click',()=>loadPage(number));$('page-numbers').append(button);
        }
        for(const button of sortButtons){const active=button.dataset.recordingSort===tableState.sort;button.closest('th').setAttribute('aria-sort',active?(tableState.direction==='asc'?'ascending':'descending'):'none');button.querySelector('span').textContent=active?(tableState.direction==='asc'?'↑':'↓'):'↕';}
        syncTableControls();
    }
    function resetResults(){
        clearTimeout(filterTimer);query=null;tableLoading=false;tableState.search='';tableState.sort='start';tableState.direction='desc';$('filter').value='';
        clearSelection();emptyRows(labels.initial);updatePages();message.textContent=labels.initial;
    }
    vehicle.addEventListener('change',()=>{if(busy)return;generation++;resetResults();updateChannels();});
    function show(result,device){
        query={...result,device};rows.replaceChildren();
        message.textContent=result.total?`${result.total} ${labels.records}`:labels.empty;
        if(!result.total){const text=result.unfiltered_total?labels.no_results:labels.empty;emptyRows(text);message.textContent=text;}
        updatePages(result);
        for(const [position,record] of result.records.entries()){
            const row=document.createElement('tr'),seconds=Math.round((Date.parse(record.end)-Date.parse(record.start))/1000);
            const number=make('td',String(result.from+position));number.className='users-row-number';row.append(number);
            for(const text of [`${labels.channel} ${record.channel}`,format(record.start),format(record.end),`${Math.floor(seconds/60)} min ${seconds%60} s`,size(record.size)])row.append(make('td',text));
            const actions=document.createElement('td'),button=make('button',labels.open);button.type='button';button.className='btn btn-sm btn-outline-primary';
            button.addEventListener('click',()=>{
                if(busy||tableLoading)return;selected={...record,queryId:result.query_id,device};clearPlayer();$('preview').hidden=false;
                $('clip-title').textContent=`${labels.channel} ${record.channel} · ${format(record.start)}`;
                for(const id of ['from','to']){$(id).min=inputTime(record.start);$(id).max=inputTime(record.end);}
                $('from').value=inputTime(record.start);$('to').value=inputTime(record.end);$('transfer-status').textContent='';$('progress').hidden=true;
                $('preview').scrollIntoView({behavior:'smooth',block:'nearest'});
            });actions.append(button);row.append(actions);rows.append(row);
        }
        syncTableControls();
    }
    search.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||tableLoading)return;resetResults();const current=++generation,device=vehicle.value;tableLoading=true;syncTableControls();message.textContent=labels.searching;
        try{const result=await request(base(device)+'/search',{date:$('date').value,channel:Number(channel.value),per_page:tableState.per_page});if(current===generation)show(result,device);}
        catch(error){if(current===generation){emptyRows(error.message);message.textContent=error.message;}}
        finally{if(current===generation){tableLoading=false;syncTableControls();}}
    });
    async function loadPage(number=1){
        if(!query||busy)return;clearTimeout(filterTimer);const current=++generation,device=query.device;tableLoading=true;syncTableControls();
        try{const data=await request(`${base(device)}/search/${query.query_id}?${new URLSearchParams({...tableState,page:number})}`,null,'GET');if(current===generation)show(data,device);}
        catch(error){if(current===generation)message.textContent=error.message;}
        finally{if(current===generation){tableLoading=false;syncTableControls();}}
    }
    $('previous').addEventListener('click',()=>loadPage(query.page-1));$('next').addEventListener('click',()=>loadPage(query.page+1));
    $('page-size').addEventListener('change',event=>{tableState.per_page=Number(event.target.value);void loadPage();});
    $('filter').addEventListener('input',event=>{clearTimeout(filterTimer);generation++;tableState.search=event.target.value.trim();filterTimer=setTimeout(()=>loadPage(),250);});
    for(const button of sortButtons)button.addEventListener('click',()=>{tableState.direction=tableState.sort===button.dataset.recordingSort&&tableState.direction==='asc'?'desc':'asc';tableState.sort=button.dataset.recordingSort;void loadPage();});
    function finishJob(text){$('transfer-status').textContent=text;$('progress').hidden=true;setBusy(false);activeJob=null;clearTimeout(pollTimer);}
    async function poll(){
        const job=activeJob;if(!job)return;
        try{
            const state=await request(`${base(job.device)}/jobs/${job.id}`,null,'GET');if(activeJob!==job)return;
            $('transfer-status').textContent=labels[state.status]||labels.preparing;
            if(state.status==='receiving')$('transfer-status').textContent+=` · ${state.progress}%`;
            $('progress').value=state.progress||0;
            if(state.status==='ready'){
                const url=`${base(job.device)}/jobs/${job.id}/video`;player.src=url;player.hidden=false;player.load();$('download').href=url+'?download=1';$('download').hidden=false;
                finishJob(`${labels.ready} · ${size(state.size)}`);return;
            }
            if(['failed','cancelled'].includes(state.status)){finishJob(labels[state.error]||labels[state.status]||labels.failed);return;}
            pollTimer=setTimeout(poll,2000);
        }catch(error){if(activeJob===job){if([401,403,404,419].includes(error.status)||Date.now()-job.started>65*60000){finishJob(error.message);return;}$('transfer-status').textContent=error.message;pollTimer=setTimeout(poll,5000);}}
    }
    prepare.addEventListener('submit',async event=>{
        event.preventDefault();if(!selected||busy)return;const record=selected;
        const start=utc($('from').value),end=utc($('to').value);
        if(Date.parse(end)<=Date.parse(start)||Date.parse(end)-Date.parse(start)>1800000){$('transfer-status').textContent=labels.duration_limit;return;}
        clearPlayer();setBusy(true);$('progress').hidden=false;$('progress').value=0;$('transfer-status').textContent=labels.waiting;
        try{const result=await request(base(record.device)+'/jobs',{query_id:record.queryId,index:record.index,start,end});activeJob={id:result.job_id,device:record.device,started:Date.now()};void poll();}
        catch(error){finishJob(error.message);}
    });
    $('cancel').addEventListener('click',async()=>{const job=activeJob;if(!job)return;try{await request(`${base(job.device)}/jobs/${job.id}/cancel`);finishJob(labels.cancelled);}catch(error){$('transfer-status').textContent=error.message;}});
    document.addEventListener('exadcam:view-changed',event=>{if(event.detail.view!=='recordings')player.pause();});
    document.addEventListener('exadcam:recordings-open',event=>{
        const context=event.detail;
        if(busy||tableLoading){message.textContent=context.busy;return;}
        if(![...vehicle.options].some(option=>option.value===String(context.cameraId)))return;
        generation++;resetResults();vehicle.value=String(context.cameraId);vehicle.dispatchEvent(new Event('change',{bubbles:true}));
        $('date').value=context.date;channel.value='0';message.textContent=context.hint;
        search.scrollIntoView({behavior:'smooth',block:'start'});
    });
    updateChannels();updatePages();
}
