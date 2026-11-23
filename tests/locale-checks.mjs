import assert from 'node:assert/strict';
import {writeFileSync,readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';

export async function checkLocales({browser,page,context,staffPage,origin,quoteId}){
 const choose=async(tab,language)=>{
  const toggle=tab.locator('.language-picker');
  const selected=toggle.getByRole('button',{name:language==='my'?'မြန်မာ':'English',exact:true});
  await selected.click();
  assert.equal(await selected.getAttribute('aria-pressed'),'true');
  assert.equal(await toggle.locator('[aria-pressed=true]').count(),1,'Exactly one language is active');
 };
 // Signed-out users can select a language; it survives a full page load.
 const anonymous=await browser.newContext();const login=await anonymous.newPage();
 await login.goto(origin);await choose(login,'my');
 await login.getByRole('heading',{name:'သင့်လုပ်ငန်းခွင်သို့ ဝင်ရန်',exact:true}).waitFor();
 await login.reload();assert.equal(await login.locator('html').getAttribute('lang'),'my');
 await login.getByLabel('အကောင့်အမည်',{exact:true}).fill('missing-user');
 await login.getByLabel('စကားဝှက်',{exact:true}).fill('wrong-password');
 await login.getByRole('button',{name:'ဝင်ရန်',exact:true}).click();
 await login.getByText('အကောင့်အမည် သို့မဟုတ် စကားဝှက် မမှန်ပါ။',{exact:true}).waitFor();
 await choose(login,'en');
 await login.getByText('Username or password is incorrect.',{exact:true}).waitFor();
 await anonymous.close();

 await page.setViewportSize({width:1440,height:960});

 await page.goto(origin+'#customers');await page.getByRole('heading',{name:'Customers',exact:true}).waitFor();
 await choose(page,'my');await page.getByRole('heading',{name:'ဖောက်သည်များ',exact:true}).waitFor();
 await page.getByRole('button',{name:'ဖောက်သည် ထည့်ရန်',exact:true}).click();
 const name=page.getByLabel('ဖောက်သည်အမည်',{exact:true});await name.fill('Locale draft မြန်မာ');
 await page.screenshot({path:'tests/artifacts/myanmar-customer.png',fullPage:true});
 // A modal intentionally blocks the top bar. Change language in another tab to
 // exercise the supported cross-tab preference sync without clicking through it.
 const languageTab=await context.newPage();await languageTab.goto(origin);
 await choose(languageTab,'en');await languageTab.close();
 await page.getByLabel('Customer name',{exact:true}).waitFor();
 assert.equal(await page.getByLabel('Customer name',{exact:true}).inputValue(),'Locale draft မြန်မာ','Language switch retains entered data');
 await page.getByRole('button',{name:'Cancel',exact:true}).click();

 await page.goto(origin+'#new/invoice');await page.getByRole('heading',{name:'New invoice',exact:true}).waitFor();
 await page.getByLabel('Description',{exact:true}).fill('Keep this draft');
 await choose(page,'my');
 await page.getByText('စက်အသေးစိတ်နှင့် ဝန်ဆောင်မှုဈေးနှုန်း ရှာရန်',{exact:true}).click();
 await page.getByLabel('နည်းပညာ',{exact:true}).selectOption('Inverter');
 assert.equal(await page.getByLabel('နည်းပညာ',{exact:true}).inputValue(),'Inverter','Translated option retains pricing machine value');
 const dims=await page.evaluate(()=>[document.querySelector('input[type=date]'),document.querySelector('.lookup-control'),document.querySelector('select[aria-label="နည်းပညာ"]')].map(e=>Math.round(e.getBoundingClientRect().height)));
 assert(dims.every(d=>d===dims[0]),`Myanmar control heights match: ${dims}`);
 await page.screenshot({path:'tests/artifacts/myanmar-editor.png',fullPage:true});
 await choose(page,'en');assert.equal(await page.getByLabel('Description',{exact:true}).inputValue(),'Keep this draft');
 // Discard test draft explicitly so the subsequent route check is not blocked.
 page.once('dialog',dialog=>dialog.accept());await page.goto(origin+'#dashboard');
 await page.getByRole('heading',{name:'Overview',exact:true}).waitFor();
 await choose(page,'my');await page.reload();
 await page.getByRole('heading',{name:'ခြုံငုံသုံးသပ်ချက်',exact:true}).waitFor();
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:900});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Myanmar viewport fits');
  await page.screenshot({path:`tests/artifacts/myanmar-${width}.png`,fullPage:true});
 }
 await page.getByRole('button',{name:'အရောင်မြင်ကွင်း ပြောင်းရန်',exact:true}).click();
 await page.screenshot({path:'tests/artifacts/myanmar-dark-mobile.png',fullPage:true});
 await page.getByRole('button',{name:'အရောင်မြင်ကွင်း ပြောင်းရန်',exact:true}).click();
 await page.setViewportSize({width:1440,height:960});

 const manifest=await(await context.request.get(await page.locator('link[rel=manifest]').getAttribute('href'))).json();
 assert.equal(manifest.lang,'my');assert.equal(manifest.id,'./');assert.match(manifest.description,/အင်တာနက်/);
 await page.goto(origin+'#reports');await page.getByRole('heading',{name:'ငွေစာရင်း အစီရင်ခံစာများ',exact:true}).waitFor();
 const downloaded=page.waitForEvent('download');await page.getByRole('button',{name:'CSV ထုတ်ယူရန်',exact:true}).click();
 const csv=readFileSync(await(await downloaded).path(),'utf8');
 assert.match(csv,/အမျိုးအစား/);assert.match(csv,/ငွေတောင်းခံလွှာ ကောက်ခံငွေ \/ ကြိုတင်ငွေ/);assert(csv.includes('Cash'),'Export preserves configured payment-method names');

 const serverError=await context.request.get(origin+'api/document?id=999999');
 assert.equal((await serverError.json()).error,'စာရွက်စာတမ်း မတွေ့ပါ။');
 const invalid=await context.request.post(origin+'api/save-customer',{headers:{'X-CSRF-Token':(await(await context.request.get(origin+'api/session')).json()).csrf},data:{name:''}});
 assert.match((await invalid.json()).error,/အမည်/,'Parameterized server validation is translated');
 const csrf=await context.request.post(origin+'api/save-customer',{data:{name:'No token'}});
 assert.equal((await csrf.json()).error,'အကောင့်ဝင်ရောက်ချိန် ကုန်ဆုံးပြီ။ စာမျက်နှာ ပြန်ဖွင့်ပြီး ထပ်ကြိုးစားပါ။');

 const paper=await context.newPage();await paper.goto(origin+'print/document/1?lang=my');
 await paper.getByRole('heading',{name:'နောက်ဆုံးငွေတောင်းခံလွှာ',exact:true}).waitFor();
 assert(await paper.getByText('ခုနစ်သိန်းလေးသောင်းခုနစ်ရာ ကျပ်တိတိ။',{exact:false}).count());
 await paper.screenshot({path:'tests/artifacts/myanmar-print.png',fullPage:true});await paper.close();
 for(const [path,name,max]of [['document/1','myanmar-invoice',2],[`document/${quoteId}`,'myanmar-quotation',14],['payment/1','myanmar-receipt',2]]){
  const response=await context.request.get(origin+`print/${path}?format=pdf&lang=my`);assert.equal(response.status(),200);
  const bytes=await response.body();assert.equal(bytes.subarray(0,5).toString(),'%PDF-');
  writeFileSync(`tests/artifacts/${name}.pdf`,bytes);
  console.log(execFileSync('php',['tests/pdf-check.php',`tests/artifacts/${name}.pdf`,String(max)],{encoding:'utf8'}).trim());
 }

 await choose(staffPage,'my');await staffPage.goto(origin+'staff#dashboard');
 await staffPage.getByRole('heading',{name:'ခြုံငုံသုံးသပ်ချက်',exact:true}).waitFor();
 await staffPage.getByRole('button',{name:'မီနူး ဖွင့်ရန်',exact:true}).click();
 for(const label of ['ငွေကောက်ခံမှုများ','ဝင်ငွေနှင့် အသုံးစရိတ်','အစီရင်ခံစာများ','စီမံခန့်ခွဲမှု'])assert.equal(await staffPage.getByRole('navigation').getByText(label,{exact:true}).count(),0,'Language cannot expose staff-restricted navigation');
 await staffPage.screenshot({path:'tests/artifacts/myanmar-staff.png',fullPage:true});
 await staffPage.getByRole('button',{name:'မီနူး ပိတ်ရန်',exact:true}).click();
 await choose(staffPage,'en');await choose(page,'en');
 console.log('PASS: Myanmar/English sign-in, persistence, validation/CSRF errors, unsaved forms, machine values, control heights, mobile/dark layouts, staff permissions and Myanmar paper/PDF.');
}
