import {tr, enumText, locale} from './i18n';
import React, { useEffect, useRef, useState } from 'react';
import { X, Search, ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-react';
import SearchSelect from './SearchSelect';
export {default as SearchSelect} from './SearchSelect';

export const base = document.querySelector('meta[name="app-base"]').content;
export const url = path => `${base}${path}`;
export const money = n => `${Number(n || 0).toLocaleString(locale()==='my'?'my-MM':'en-US')} ${tr('MMK')}`;
export const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Yangon', year:'numeric', month:'2-digit', day:'2-digit' }).format(new Date());
export const key = () => crypto.randomUUID();
export let csrf = '';
export const setCsrf = value => { csrf = value; };
export async function api(action, data, method, options={}) {
  const verb = method || (data ? 'POST' : 'GET');
  if (verb==='POST' && !navigator.onLine) throw new Error("Reconnect to the internet before saving. No offline transactions are queued.");
  const query = verb === 'GET' && data ? `?${new URLSearchParams(data)}` : '';
  let response;
  try { response = await fetch(url(`api/${action}${query}`), { method:verb, credentials:'same-origin', cache:'no-store', signal:options.signal, headers: { 'Content-Type':'application/json', 'X-CSRF-Token':csrf, 'X-App-Locale':locale() }, ...(verb==='POST'?{body:JSON.stringify(data || {})}:{}) }); }
  catch (e) { if(e.name==='AbortError')throw e; throw new Error("Connection lost. Reconnect and check whether your last action was saved before retrying."); }
  let result; try { result = await response.json(); } catch { throw new Error("The server returned an unexpected response. Please check the installation."); }
  if (!response.ok) { if (response.status===401 && action!=='login') window.dispatchEvent(new Event('session-expired')); throw new Error(result.error_source || result.error || tr("Request failed.")); }
  return result;
}
export function go(route) { location.hash = route; }
export function useLoad(loader, deps = []) {
  const [data,setData]=useState(null), [error,setError]=useState(''), [tick,reload]=useState(0);
  useEffect(()=>{ let alive=true; setError(''); setData(null); Promise.resolve().then(loader).then(value=>{if(alive)setData(value);}).catch(e=>{if(alive)setError(e.message);}); return ()=>{alive=false;}; },[...deps,tick]);
  return {data,error,reload:()=>reload(v=>v+1),setData};
}
export function Field({label,children,wide=false,hint}) { return <label className={`field ${wide?'wide':''}`}><span>{tr(label)}</span>{children}{hint&&<small>{tr(hint)}</small>}</label>; }
export function Input({label,wide,hint,...props}) { return <Field label={label} wide={wide} hint={hint}><input aria-label={tr(label)} {...props}/></Field>; }
export function Select({label,children,wide,searchable=false,resource,params,placeholder,...props}) {
  if(searchable||resource){
    const options=React.Children.toArray(children).filter(React.isValidElement).map(child=>{const text=React.Children.toArray(child.props.children).join('');return {value:String(child.props.value??text),label:text,disabled:!!child.props.disabled};});
    return <SearchSelect label={label} wide={wide} resource={resource} params={params} placeholder={placeholder||options.find(o=>o.value==='')?.label} options={options} {...props}/>;
  }
  return <Field label={label} wide={wide}><select aria-label={tr(label)} {...props}>{React.Children.map(children,child=>React.isValidElement(child)?React.cloneElement(child,{},typeof child.props.children==='string'?tr(child.props.children):child.props.children):child)}</select></Field>;
}
export function Textarea({label,wide=true,...props}) { return <Field label={label} wide={wide}><textarea rows="3" aria-label={tr(label)} {...props}/></Field>; }
export function Btn({children,variant='',className='',...props}) { return <button type="button" className={`btn ${variant} ${className}`} {...props}>{children}</button>; }
export function Badge({value}) { const label=locale()==='my'?enumText(value):String(value||'').replaceAll('_',' '); return <span className={`badge ${value}`}>{label}</span>; }
export function ErrorBox({error}) { return error?<div className="error" role="alert">{tr(error)}</div>:null; }
export function Loading() { return <div className="loading" role="status"><LoaderCircle className="spin" size={20}/> {tr("Loading records…")}</div>; }
export function Empty({text=tr("No records yet."),children}) { return <div className="empty"><div className="empty-mark">○</div><strong>{text}</strong><p>{tr("Records will appear here when you add them.")}</p>{children}</div>; }
export function PageHead({title,subtitle,action}) { return <div className="page-head"><div><div className="eyebrow">COOL AIR SOLUTION</div><h1>{title}</h1>{subtitle&&<p>{subtitle}</p>}</div><div className="actions">{action}</div></div>; }
export function Panel({title,children,action,className=''}) { return <section className={`panel ${className}`}>{title&&<div className="panel-head"><h2>{title}</h2>{action}</div>}{children}</section>; }
export function Actor({name,username}) { return <span className="actor"><strong>{name||username||tr("System")}</strong>{username&&<small>@{username}</small>}</span>; }
export function HistoryEntries({rows=[],compact=false}) {
  const human=key=>locale()==='my'?tr(key):key.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
  const value=(data,field)=>data===null||data===undefined?'—':typeof data==='object'?Object.entries(data).map(([k,v])=>`${human(k)}: ${value(v,k)}`).join('; '):["type","kind","status","role","target_type","equipment_type","technology","source"].includes(field)?enumText(data):String(data);
  return rows.length?<div className="timeline">{rows.map(row=>{
    let detail={};try{detail=JSON.parse(row.detail||'{}');}catch{}
    const before=detail.before||{},after=detail.after||{};
    const changes=[...new Set([...Object.keys(before),...Object.keys(after)])].filter(k=>JSON.stringify(before[k])!==JSON.stringify(after[k]));
    return <div key={row.id}>{!compact&&<><strong>{human(row.action)}</strong><span>{!row.user_id?tr("System"):row.actor_name||row.actor||tr("System")} · @{row.actor_username||row.current_actor_username||tr("System")} · {row.created_at} UTC</span></>}{detail.reason&&<p>{detail.reason}</p>}{(changes.length>0||Object.keys(detail).some(k=>!['before','after','reason'].includes(k)))&&<details><summary>{tr("View changes")}</summary><div className="history-changes">{changes.map(field=><div key={field}><strong>{human(field)}</strong><p>{field==='lines'?tr('{before} charge lines → {after} charge lines',{before:before.lines?.length||0,after:after.lines?.length||0}): `${value(before[field],field)} → ${value(after[field],field)}`}</p>{field==='lines'&&<div>{[[tr("Before"),before.lines||[]],[tr("After"),after.lines||[]]].map(([title,lines])=><section key={title}><strong>{title}</strong>{lines.length?<ol>{lines.map((line,i)=><li key={i}>{line.section}: {line.description} · {Number(line.quantity)/1000} {line.unit} × {money(line.rate)} = {money(line.amount)}</li>)}</ol>:<p>{tr("No charge lines.")}</p>}</section>)}</div>}</div>)}{Object.entries(detail).filter(([k])=>!['before','after','reason'].includes(k)).map(([k,v])=><p key={k}><strong>{human(k)}: </strong>{value(v,k)}</p>)}</div></details>}</div>;
  })}</div>:<p className="muted">{tr("No recorded operations in your access scope.")}</p>;
}
export function Modal({title,onClose,children}) {
  const ref=useRef(),closeRef=useRef(onClose); closeRef.current=onClose;
  useEffect(()=>{ const previous=document.activeElement; ref.current?.querySelector('input,select,button,textarea')?.focus(); const handle=e=>{if(e.key==='Escape')closeRef.current(); if(e.key==='Tab'){const items=[...ref.current.querySelectorAll('button,input,select,textarea,a[href]')].filter(n=>!n.disabled); const first=items[0],last=items.at(-1); if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}}}; document.addEventListener('keydown',handle); return ()=>{document.removeEventListener('keydown',handle);previous?.focus();};},[]);
  return <div className="modal-backdrop"><section className="modal" role="dialog" aria-modal="true" aria-label={title} ref={ref}><div className="panel-head"><h2>{title}</h2><Btn onClick={onClose} aria-label={tr("Close dialog")}><X size={16}/></Btn></div>{children}</section></div>;
}
export function FormModal({title,onClose,onSave,children,saveLabel=tr("Save record")}) {
  const [busy,setBusy]=useState(false),[error,setError]=useState('');
  return <Modal title={title} onClose={()=>!busy&&onClose()}><form onSubmit={async e=>{e.preventDefault();setBusy(true);setError('');try{await onSave();onClose();}catch(e){setError(e.message);}finally{setBusy(false);}}}><div className="modal-body"><ErrorBox error={error}/><div className="form-grid">{children}</div></div><div className="modal-foot"><Btn onClick={onClose} disabled={busy}>{tr("Cancel")}</Btn><button className="btn primary" disabled={busy||!navigator.onLine}>{busy?tr("Saving…"):saveLabel}</button></div></form></Modal>;
}
export function Confirm({title=tr("Confirm action"),message,requireReason=false,onClose,onConfirm}) {
  const [reason,setReason]=useState(''); return <FormModal title={title} onClose={onClose} onSave={()=>onConfirm(reason)} saveLabel={tr("Confirm")}><p className="wide">{message}</p>{requireReason&&<Textarea label={tr("Reason")} required value={reason} onChange={e=>setReason(e.target.value)}/>}</FormModal>;
}
export function Filters({value,onChange,typeOptions,kindOptions,searchPlaceholder=tr("Number, customer, or description"),children}) {
  const change=(k,v)=>onChange({...value,[k]:v,page:1});
  return <div className="filters"><Field label={tr("Search")}><div className="search"><Search size={15}/><input placeholder={searchPlaceholder} value={value.q||''} onChange={e=>change('q',e.target.value)}/></div></Field>{typeOptions&&<Select label={tr("Status")} value={value.status||''} onChange={e=>change('status',e.target.value)}><option value="">{tr("All statuses")}</option>{typeOptions.map(s=><option key={s} value={s}>{s}</option>)}</Select>}{kindOptions&&<Select label={tr("Type")} value={value.kind||''} onChange={e=>change('kind',e.target.value)}><option value="">{tr("All types")}</option>{kindOptions.map(s=><option key={s} value={s}>{s}</option>)}</Select>}<Input label={tr("From")} type="date" value={value.from||''} onChange={e=>change('from',e.target.value)}/><Input label={tr("To")} type="date" value={value.to||''} onChange={e=>change('to',e.target.value)}/>{children}<Btn onClick={()=>onChange({page:1})}>{tr("Reset")}</Btn></div>;
}
export function Pagination({data,onPage}) { return data&&<div className="pagination"><span>{tr('{count} records · Page {page} of {pages}',{count:data.total,page:data.page,pages:Math.max(1,Math.ceil(data.total/data.per_page))})}</span><div className="actions"><Btn disabled={data.page<=1} onClick={()=>onPage(data.page-1)} aria-label={tr("Previous page")}><ChevronLeft size={16}/></Btn><Btn disabled={data.page*data.per_page>=data.total} onClick={()=>onPage(data.page+1)} aria-label={tr("Next page")}><ChevronRight size={16}/></Btn></div></div>; }
export function PrintActions({kind='document',id}) { return <><a className="btn" target="_blank" rel="noreferrer" href={url(`print/${kind}/${id}?lang=${locale()}`)}>{tr("Print paper")}</a><a className="btn" href={url(`print/${kind}/${id}?format=pdf&lang=${locale()}`)}>{tr("Download PDF")}</a></>; }
export function AttachmentUpload({kind,id,onDone}) {
  const [error,setError]=useState(''),[busy,setBusy]=useState(false);
  return <div><Field label={tr("Attach proof (PDF, PNG, JPEG; up to 5 MB)")}><input type="file" accept=".pdf,.png,.jpg,.jpeg" disabled={busy} onChange={async e=>{if(!e.target.files[0])return;setBusy(true);setError('');const data=new FormData();data.append('file',e.target.files[0]);data.append('kind',kind);data.append('id',id);try{const res=await fetch(url('upload'),{method:'POST',headers:{'X-CSRF-Token':csrf, 'X-App-Locale':locale()},body:data});const result=await res.json();if(!res.ok)throw new Error(result.error_source || result.error);onDone?.();}catch(e){setError(e.message);}finally{setBusy(false);}}}/></Field><ErrorBox error={error}/></div>;
}
