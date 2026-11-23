import React, {useSyncExternalStore} from 'react';
import my from '../lang/my.json';
import messages from '../lang/messages.my.json';

const catalog={...my,...messages};

const storageKey='cool-air-locale';
const base=document.querySelector('meta[name="app-base"]')?.content||'/';
const cookieKey='cool_air_locale';
const supported=value=>['en','my'].includes(value);
let current=supported(document.documentElement.lang)?document.documentElement.lang:'en';
try {const saved=localStorage.getItem(`${storageKey}:${base}`);if(supported(saved))current=saved;} catch {}
const listeners=new Set();
export const locale=()=>current;
export const subscribe=listener=>{listeners.add(listener);return()=>listeners.delete(listener);};
function reflect(){
 document.documentElement.lang=current;
 const manifest=document.querySelector('link[rel=manifest]');
 if(manifest){const address=new URL(manifest.href);address.searchParams.set('lang',current);manifest.href=address.href;}
 document.cookie=`${cookieKey}=${current}; Path=${new URL(base,location.href).pathname}; Max-Age=31536000; SameSite=Lax${location.protocol==='https:'?'; Secure':''}`;
}
reflect();
export function setLocale(value){
 if(!supported(value))return;
 current=value;
 try {localStorage.setItem(`${storageKey}:${base}`,value);}catch{}
 reflect();listeners.forEach(fn=>fn());
}
window.addEventListener('storage',event=>{if(event.key===`${storageKey}:${base}`&&supported(event.newValue))setLocale(event.newValue);});
export function tr(source,params={}){
 if(typeof source!=='string')return source;
 let text=current==='my'?(catalog[source]??source):source;
 if(current==='my'&&!Object.hasOwn(catalog,source)){
  const patterns=[[/^Invalid ([a-z_]+)\.$/,'Invalid {field}.',['field']],[/^Check ([a-z_]+): required, maximum (\d+) characters\.$/,'Check {field}: required, maximum {max} characters.',['field','max']],[/^Check ([a-z_]+): maximum (\d+) characters\.$/,'Check {field}: maximum {max} characters.',['field','max']],[/^Enter a valid ([a-z_]+)\.$/,'Enter a valid {field}.',['field']],[/^Enter a valid positive decimal with up to (\d+) decimal places\.$/,'Enter a valid positive decimal with up to {places} decimal places.',['places']]];
  for(const [pattern,key,names] of patterns){const match=source.match(pattern);if(match){text=catalog[key];params={...params};names.forEach((name,index)=>params[name]=name==='field'?tr(match[index+1]):match[index+1]);break;}}
 }
 return text.replace(/\{(\w+)\}/g,(match,key)=>Object.hasOwn(params,key)?String(params[key]):match);
}
export const enumText=value=>tr(String(value??''));
export function useLocale(){return useSyncExternalStore(subscribe,locale);}
export function LanguagePicker(){
 const value=useLocale();
 return <div className="language-picker" role="group" aria-label={tr('Language')}>
  <button type="button" lang="en" aria-label="English" aria-pressed={value==='en'} onClick={()=>setLocale('en')}><span className="language-full">English</span><span className="language-short" aria-hidden="true">EN</span></button>
  <button type="button" lang="my" aria-label="မြန်မာ" aria-pressed={value==='my'} onClick={()=>setLocale('my')}>မြန်မာ</button>
 </div>;
}

// Native browser messages otherwise follow the browser language rather than the app.
function validate(control){
 if(!(control instanceof HTMLInputElement||control instanceof HTMLTextAreaElement||control instanceof HTMLSelectElement)||control.classList.contains('lookup-input'))return;
 control.setCustomValidity('');const v=control.validity;
 if(v.valid)return;
 const message=v.valueMissing?tr('Please fill out this field.'):v.tooShort?tr('Enter at least {count} characters.',{count:control.minLength}):v.tooLong?tr('Enter no more than {count} characters.',{count:control.maxLength}):v.rangeUnderflow?tr('Enter a value of at least {value}.',{value:control.min}):v.rangeOverflow?tr('Enter a value no greater than {value}.',{value:control.max}):tr('Enter a valid value.');
 control.setCustomValidity(message);
}
document.addEventListener('invalid',event=>validate(event.target),true);
document.addEventListener('input',event=>{const c=event.target;if(c instanceof HTMLInputElement||c instanceof HTMLTextAreaElement||c instanceof HTMLSelectElement){if(!c.classList.contains('lookup-input'))c.setCustomValidity('');}},true);
subscribe(()=>document.querySelectorAll('input,select,textarea').forEach(validate));
