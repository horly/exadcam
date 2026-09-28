// Expand the existing panel; never move/recreate a video or its live session.
export function createVideoFullscreen({panel,button,document=globalThis.document,onChange=()=>{}}) {
    let active=false,native=false,generation=0,focusBefore,scrollBefore=0,savedAttributes=[],background=[];
    const restoreAttribute=(name,value)=>value===null?panel.removeAttribute(name):panel.setAttribute(name,value);
    const render=()=>{
        panel.classList.toggle('is-expanded',active);
        document.body.classList.toggle('map-video-expanded',active);
        button.setAttribute('aria-pressed',String(active));
        button.setAttribute('aria-label',active?button.dataset.exitLabel:button.dataset.enterLabel);
        button.title=active?button.dataset.exitLabel:button.dataset.enterLabel;
        button.querySelector('[data-fullscreen-label]').textContent=active?button.dataset.exitShort:button.dataset.enterShort;
        button.querySelector('[data-video-expand-icon]').toggleAttribute('hidden',active);
        button.querySelector('[data-video-reduce-icon]').toggleAttribute('hidden',!active);
        onChange(active);
    };
    const restore=(moveFocus=true)=>{
        if(!active)return;
        active=false;native=false;generation++;
        for(const [node,inert] of background)node.inert=inert;
        background=[];
        for(const [name,value] of savedAttributes)restoreAttribute(name,value);
        savedAttributes=[];render();panel.scrollTop=scrollBefore;
        if(moveFocus&&focusBefore?.isConnected)focusBefore.focus({preventScroll:true});
    };
    const close=async({restoreFocus=true}={})=>{
        const ownsFullscreen=document.fullscreenElement===panel;
        restore(restoreFocus);
        if(ownsFullscreen)await document.exitFullscreen?.().catch(()=>{});
    };
    const open=async()=>{
        if(active||panel.hidden)return;
        active=true;const current=++generation;
        focusBefore=document.activeElement;scrollBefore=panel.scrollTop;
        savedAttributes=['role','aria-modal','tabindex'].map(name=>[name,panel.getAttribute(name)]);
        panel.setAttribute('role','dialog');panel.setAttribute('aria-modal','true');panel.setAttribute('tabindex','-1');
        // Keep keyboard focus in the enlarged panel, including the CSS fallback.
        for(let node=panel;node.parentElement&&node!==document.body;node=node.parentElement){
            for(const sibling of node.parentElement.children)if(sibling!==node){background.push([sibling,sibling.inert]);sibling.inert=true;}
        }
        render();button.focus({preventScroll:true});
        try{
            if(panel.requestFullscreen){
                await panel.requestFullscreen();
                if(current!==generation||!active){
                    if(!active&&document.fullscreenElement===panel)await document.exitFullscreen?.();
                    return;
                }
                native=document.fullscreenElement===panel;
            }
        }catch{
            // Mobile browsers without element fullscreen keep the full-window layout.
        }
    };
    const toggle=()=>{void(active?close():open());};
    const fullscreenChanged=()=>{
        if(document.fullscreenElement&&panel.contains(document.fullscreenElement)){if(active)native=true;}
        else if(active&&native)restore();
    };
    const keydown=event=>{
        if(!active)return;
        if(event.key==='Escape'){event.preventDefault();void close();return;}
        if(event.key!=='Tab')return;
        const focusable=[...panel.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),a[href],video[controls],[tabindex="0"]')].filter(node=>!node.closest('[hidden]')&&node.getClientRects().length);
        const first=focusable[0]||panel,last=focusable.at(-1)||panel;
        if(event.shiftKey&&(document.activeElement===first||document.activeElement===panel)){event.preventDefault();last.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    };
    button.addEventListener('click',toggle);
    document.addEventListener('fullscreenchange',fullscreenChanged);
    document.addEventListener('keydown',keydown);
    render();
    return {open,close,get active(){return active;},destroy(){void close({restoreFocus:false});button.removeEventListener('click',toggle);document.removeEventListener('fullscreenchange',fullscreenChanged);document.removeEventListener('keydown',keydown);}};
}

export function observeMapLayout(workspace,{ResizeObserver=globalThis.ResizeObserver}={}) {
    const update=()=>{
        const {width,height}=workspace.getBoundingClientRect();
        if(!width||!height)return;
        workspace.style.setProperty('--tracking-height',`${height}px`);
        workspace.classList.toggle('is-stacked',width<900&&(height>=480||height>=width));
        workspace.classList.toggle('is-narrow',width<600);
    };
    const observer=new ResizeObserver(update);observer.observe(workspace);update();
    return ()=>observer.disconnect();
}
