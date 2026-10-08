(() => {
    'use strict';
    const configNode=document.getElementById('server-logs-config');
    if(!configNode)return;
    const config=JSON.parse(configNode.textContent),labels=config.labels,$=id=>document.getElementById('server-logs-'+id);
    const root=$('module'),output=$('output'),tabs=[...root.querySelectorAll('[data-log-source]')];
    let source='gps',paused=false,timer=null,searchTimer=null,controller=null,sequence=0;
    const visible=()=>document.body.dataset.view==='server-logs'&&!document.hidden;
    const translate=(key,values={})=>Object.entries(values).reduce((text,[name,value])=>text.replaceAll(':'+name,String(value)),labels[key]);
    const time=value=>new Intl.DateTimeFormat(config.locale,{timeZone:'Africa/Kinshasa',dateStyle:'short',timeStyle:'medium'}).format(new Date(value));
    function state(value){$('state').textContent=labels[value];$('state').className='server-logs-state is-'+value;}
    function stop(){clearTimeout(timer);controller?.abort();controller=null;sequence++;root.removeAttribute('aria-busy');$('refresh').disabled=false;}
    function schedule(){clearTimeout(timer);if(visible()&&!paused)timer=setTimeout(()=>load(),5000);}
    async function load(manual=false){
        if(!visible()||(paused&&!manual))return;
        stop();const current=sequence,request=new AbortController();controller=request;
        const timeout=setTimeout(()=>request.abort(),10000);
        const url=new URL(config.url,location.origin);
        for(const [key,value] of Object.entries({source,lines:$('lines').value,level:$('level').value,search:$('search').value.trim()}))url.searchParams.set(key,value);
        root.setAttribute('aria-busy','true');$('refresh').disabled=true;
        try{
            const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:request.signal});
            if([401,403,419].includes(response.status)||response.redirected){output.textContent=labels.denied;paused=true;updatePause();throw new Error(labels.denied);}
            if(!response.ok)throw new Error(labels.failed);
            const data=await response.json();if(current!==sequence)return;
            const scroll=output.scrollTop;
            output.textContent=data.available?(data.content||labels.empty):labels.unavailable;
            if($('follow').checked)output.scrollTop=output.scrollHeight;else output.scrollTop=scroll;
            const meta=[translate('count',{count:data.lines}),translate('updated',{time:time(data.checked_at)})];
            if(source==='laravel'&&data.updated_at)meta.push(translate('last_event',{time:time(data.updated_at)}));
            if(data.truncated)meta.push(labels.limited);
            $('meta').textContent=meta.join(' · ');
            state(!data.available?'unavailable':data.stale?'stale':paused?'paused':'live');
        }catch(error){
            if(current!==sequence)return;
            state('failed');$('meta').textContent=error.name==='AbortError'?labels.failed:error.message;
        }finally{
            clearTimeout(timeout);
            if(current===sequence){controller=null;root.removeAttribute('aria-busy');$('refresh').disabled=false;schedule();}
        }
    }
    function updatePause(){
        $('pause').setAttribute('aria-pressed',String(paused));
        $('pause').querySelector('[data-pause-label]').textContent=labels[paused?'resume':'pause'];
        $('pause').querySelector('[data-pause-symbol]').textContent=paused?'▷':'Ⅱ';
    }
    for(const tab of tabs)tab.addEventListener('click',()=>{
        if(tab.dataset.logSource===source)return;
        stop();source=tab.dataset.logSource;output.textContent=labels.loading;$('meta').textContent='';state('loading');
        for(const item of tabs){const selected=item===tab;item.classList.toggle('active',selected);item.setAttribute('aria-pressed',String(selected));}
        void load(true);
    });
    $('lines').addEventListener('change',()=>load(true));$('level').addEventListener('change',()=>load(true));
    $('search').addEventListener('input',()=>{clearTimeout(searchTimer);stop();searchTimer=setTimeout(()=>load(true),300);});
    $('refresh').addEventListener('click',()=>load(true));
    $('pause').addEventListener('click',()=>{paused=!paused;updatePause();stop();if(paused){clearTimeout(searchTimer);state('paused');}else void load();});
    $('follow').addEventListener('change',()=>{if($('follow').checked)output.scrollTop=output.scrollHeight;});
    const visibilityChanged=()=>{stop();clearTimeout(searchTimer);if(visible()&&!paused)void load();else if(paused)state('paused');};
    document.addEventListener('visibilitychange',visibilityChanged);
    document.addEventListener('exadcam:view-changed',visibilityChanged);
    window.addEventListener('pagehide',stop);
    if(visible())void load();
})();
