import {tr, useLocale} from './i18n';
import React,{useEffect,useId,useLayoutEffect,useMemo,useRef,useState} from 'react';
import {createPortal} from 'react-dom';
import {ChevronDown,Check,LoaderCircle,Search,X} from 'lucide-react';
import {api} from './shared';

/** Controlled, searchable single selection. Remote sources use /api/lookup in pages of 25. */
export default function SearchSelect({label,value='',onChange,resource,params={},options=[],placeholder=tr("Choose…"),required=false,disabled=false,wide=false,name,clearable=true}) {
  const language=useLocale();
  const id=useId(),root=useRef(null),input=useRef(null),popup=useRef(null),sequence=useRef(0),moreBusy=useRef(false);
  const [open,setOpen]=useState(false),[query,setQuery]=useState(''),[selected,setSelected]=useState(null),[rows,setRows]=useState([]),[page,setPage]=useState(1),[hasMore,setHasMore]=useState(false),[total,setTotal]=useState(0),[loading,setLoading]=useState(false),[loadingMore,setLoadingMore]=useState(false),[error,setError]=useState(''),[selectionError,setSelectionError]=useState(''),[resolving,setResolving]=useState(false),[active,setActive]=useState(-1),[position,setPosition]=useState(null),[retry,setRetry]=useState(0);
  const paramKey=JSON.stringify(params),stringValue=String(value??'');
  const localRows=useMemo(()=>options.filter(o=>o.value!=='' && `${o.label} ${o.description||''}`.toLocaleLowerCase().includes(query.toLocaleLowerCase())),[options,query]);
  const choices=resource?rows:localRows;

  useEffect(()=>{
    let alive=true;const controller=new AbortController();setSelectionError('');
    if (!stringValue) {setSelected(null);setResolving(false);return;}
    if (!resource) {const option=options.find(o=>String(o.value)===stringValue);setSelected(option||null);setResolving(false);return;}
    setResolving(true);
    api('lookup',{...params,resource,id:stringValue},'GET',{signal:controller.signal}).then(result=>{
      if(!alive)return;setSelected(result.rows[0]||null);if(!result.rows.length)setSelectionError("This selection is no longer available. Choose another record.");
    }).catch(e=>{if(alive&&e.name!=='AbortError')setSelectionError(e.message);}).finally(()=>{if(alive)setResolving(false);});
    return ()=>{alive=false;controller.abort();};
  },[stringValue,resource,paramKey,retry,language]);

  useEffect(()=>{
    if(!open||!resource)return;
    const current=++sequence.current,controller=new AbortController();
    setRows([]);setPage(1);setHasMore(false);setActive(-1);setError('');setLoading(true);setLoadingMore(false);moreBusy.current=false;
    const timer=setTimeout(()=>{
      api('lookup',{...params,resource,q:query,page:1},'GET',{signal:controller.signal}).then(result=>{
        if(sequence.current!==current)return;setRows(result.rows);setHasMore(result.has_more);setTotal(result.total);setActive(result.rows.findIndex(o=>!o.disabled));
      }).catch(e=>{if(sequence.current===current&&e.name!=='AbortError')setError(e.message);}).finally(()=>{if(sequence.current===current)setLoading(false);});
    },query?180:0);
    return ()=>{clearTimeout(timer);controller.abort();sequence.current++;};
  },[open,resource,paramKey,query,retry,language]);

  useEffect(()=>{if(!resource&&open)setActive(localRows.findIndex(o=>!o.disabled));},[query,open,resource,localRows]);
  useEffect(()=>{if(disabled)setOpen(false);},[disabled]);
  useEffect(()=>{
    const invalid=tr(selectionError) || (required&&!stringValue?tr("Choose a record from the list."):'');
    input.current?.setCustomValidity(invalid);
  },[required,stringValue,selectionError,language]);
  useEffect(()=>{if(active>=0)popup.current?.querySelector(`[data-choice-index="${active}"]`)?.scrollIntoView({block:'nearest'});},[active]);

  useLayoutEffect(()=>{
    if(!open)return;
    const place=()=>{
      const rect=root.current.querySelector('.lookup-control').getBoundingClientRect();
      const viewport=window.visualViewport;
      const top=viewport?.offsetTop||0,bottom=top+(viewport?.height||innerHeight),below=bottom-rect.bottom-12,above=rect.top-top-12;
      const height=Math.max(100,Math.min(320,Math.max(below,above))),up=below<180&&above>below;
      const width=Math.min(rect.width,innerWidth-16);
      setPosition({position:'fixed',width,left:Math.max(8,Math.min(rect.left,innerWidth-width-8)),maxHeight:height,...(up?{bottom:innerHeight-rect.top+4}:{top:rect.bottom+4})});
    };
    place();window.addEventListener('resize',place);window.addEventListener('scroll',place,true);window.visualViewport?.addEventListener('resize',place);
    const outside=e=>{if(!root.current?.contains(e.target)&&!popup.current?.contains(e.target)){setOpen(false);setQuery('');}};
    document.addEventListener('pointerdown',outside);
    return ()=>{window.removeEventListener('resize',place);window.removeEventListener('scroll',place,true);window.visualViewport?.removeEventListener('resize',place);document.removeEventListener('pointerdown',outside);};
  },[open]);

  const show=()=>{if(!disabled){setQuery('');setOpen(true);}};
  const choose=option=>{
    if(option?.disabled)return;
    setSelected(option);setSelectionError('');setOpen(false);setQuery('');
    const next=option?String(option.value):'';
    input.current?.setCustomValidity(required&&!next?tr("Choose a record from the list."):'');
    onChange?.({target:{name,value:next}},option||null);
    input.current?.focus({preventScroll:true});
  };
  const loadMore=async()=>{
    if(!resource||!hasMore||loading||moreBusy.current)return;
    const current=sequence.current;moreBusy.current=true;setLoadingMore(true);setError('');
    try {
      const result=await api('lookup',{...params,resource,q:query,page:page+1},'GET');
      if(sequence.current===current){setRows(old=>[...old,...result.rows.filter(n=>!old.some(o=>o.value===n.value))]);setPage(result.page);setHasMore(result.has_more);setTotal(result.total);}
    } catch(e){if(sequence.current===current)setError(e.message);}
    finally{if(sequence.current===current){moreBusy.current=false;setLoadingMore(false);}}
  };
  const keyDown=e=>{
    if(e.key==='Escape'&&open){e.preventDefault();e.stopPropagation();setOpen(false);setQuery('');return;}
    if(e.key==='Tab'){setOpen(false);setQuery('');return;}
    if(['ArrowDown','ArrowUp','Home','End'].includes(e.key)&&(open||e.key.startsWith('Arrow'))){
      e.preventDefault();if(!open){show();return;}
      let next=active;
      if(e.key==='Home')next=0;else if(e.key==='End')next=choices.length-1;else next+=e.key==='ArrowDown'?1:-1;
      if(next>=choices.length){if(hasMore)loadMore();return;}
      const direction=e.key==='ArrowUp'?-1:1;
      while(next>=0&&next<choices.length&&choices[next].disabled)next+=direction;
      if(next>=0&&next<choices.length)setActive(next);
    }
    if(e.key==='Enter'&&open){e.preventDefault();if(active>=0&&choices[active])choose(choices[active]);}
  };
  const current=selected&&String(selected.value)===stringValue?selected:null;
  const display=current?.selectedLabel||current?.label||(resolving?tr("Loading selection…"):'');
  const menu=open&&position?<div className="lookup-menu" style={position} ref={popup} onMouseDown={e=>e.preventDefault()}>
    <div className="lookup-hint"><Search size={13}/><span>{tr("Type to search · ↑ ↓ to move · Enter to select")}</span></div>
    <div className="lookup-options" id={`${id}-list`} role="listbox" aria-label={tr('{name} options',{name:label})} aria-busy={loading} onScroll={e=>{if(e.currentTarget.scrollHeight-e.currentTarget.scrollTop-e.currentTarget.clientHeight<40)loadMore();}}>
      {!loading&&choices.map((option,index)=><div id={`${id}-option-${index}`} key={option.value} role="option" aria-selected={String(option.value)===stringValue} aria-disabled={!!option.disabled} data-choice-index={index} className={`lookup-option ${index===active?'highlighted':''} ${option.disabled?'disabled':''}`} onMouseMove={()=>!option.disabled&&setActive(index)} onClick={()=>choose(option)}><div><span className="lookup-title">{option.label}</span>{option.description&&<small>{option.description}</small>}</div>{String(option.value)===stringValue&&<Check size={15}/>}</div>)}
      {loading&&<div className="lookup-message" role="status"><LoaderCircle size={15} className="spin"/> {tr("Searching…")}</div>}
      {!loading&&!choices.length&&!error&&<div className="lookup-message" role="status">{tr("No matching records.")}</div>}
    </div>
    {error&&<div className="lookup-error" role="alert">{tr(error)}<button type="button" tabIndex={-1} onClick={()=>hasMore?loadMore():setRetry(v=>v+1)}>{tr("Retry")}</button></div>}
    {!loading&&choices.length>0&&<div className="lookup-foot"><small>{tr('{count} of {total} records',{count:choices.length,total:resource?total:choices.length})}</small>{hasMore&&<button type="button" tabIndex={-1} disabled={loadingMore} onClick={loadMore}>{loadingMore?tr("Loading…"):tr("Load more")}</button>}</div>}
  </div>:null;
  return <div className={`field lookup-field ${wide?'wide':''}`} ref={root}>
    <label htmlFor={id}>{label}</label>
    <div className={`lookup-control ${open?'is-open':''} ${disabled?'is-disabled':''}`} data-lookup-control>
      <input id={id} ref={input} className="lookup-input" role="combobox" aria-label={label} aria-expanded={open} aria-controls={open?`${id}-list`:undefined} aria-autocomplete="list" aria-required={required} aria-invalid={!!selectionError} aria-activedescendant={open&&active>=0?`${id}-option-${active}`:undefined} aria-describedby={selectionError?`${id}-error`:undefined} autoComplete="off" disabled={disabled} placeholder={open?tr("Type to search…"):placeholder} value={open?query:display} onClick={()=>!open&&show()} onChange={e=>{setQuery(e.target.value);setOpen(true);setActive(-1);}} onKeyDown={keyDown} onBlur={e=>{if(!root.current?.contains(e.relatedTarget)){setOpen(false);setQuery('');}}} onInvalid={()=>{if(!open)show();}}/>
      {clearable&&stringValue&&!disabled&&<button type="button" tabIndex={-1} className="lookup-icon" aria-label={tr('Clear {name}',{name:label})} onMouseDown={e=>e.preventDefault()} onClick={()=>choose(null)}><X size={14}/></button>}
      <button type="button" tabIndex={-1} className="lookup-icon" disabled={disabled} aria-label={tr('Open {name} options',{name:label})} onMouseDown={e=>e.preventDefault()} onClick={()=>{if(open){setOpen(false);setQuery('');}else show();input.current?.focus({preventScroll:true});}}>{resolving?<LoaderCircle size={15} className="spin"/>:<ChevronDown size={15}/>}</button>
    </div>
    {name&&<input type="hidden" name={name} value={stringValue} disabled={disabled}/>}
    {selectionError&&<small className="lookup-selection-error" id={`${id}-error`} role="alert">{tr(selectionError)} <button type="button" className="text-link" onClick={()=>setRetry(v=>v+1)}>{tr("Retry")}</button></small>}
    {menu&&createPortal(menu,root.current.closest('.admin-root')||document.body)}
  </div>;
}
