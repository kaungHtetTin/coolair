import { chromium } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
import {checkSearchSelectors} from './search-select-checks.mjs';
import {checkStaffPermissions} from './staff-permissions-checks.mjs';
import {checkLocales} from './locale-checks.mjs';

mkdirSync('tests/artifacts',{recursive:true});
const db=resolve(`tests/artifacts/e2e-${Date.now()}.sqlite`);
const env={...process.env,DB_PATH:db,APP_DEBUG:'false'};
execFileSync('php',['bin/console','migrate'],{env});
execFileSync('php',['bin/console','db:seed'],{env});
const server=spawn('php',['-S','localhost:8092','-t','public','public/router.php'],{env,stdio:['ignore','pipe','pipe']});
let serverLog='';server.stderr.on('data',d=>serverLog+=d);
const origin='http://localhost:8092/';
async function chooseLookup(page,label,query){const input=page.getByRole('combobox',{name:label,exact:true});await input.fill(query);const list=page.getByRole('listbox',{name:label+' options',exact:true});await list.getByRole('option').first().click();}
let browser,page;
try{
  for(let i=0;i<50;i++){try{const res=await fetch(origin+'api/session');if(res.ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({headless:true,...(process.platform==='win32'?{channel:'msedge'}:{})});
  const context=await browser.newContext({viewport:{width:1440,height:900},reducedMotion:'reduce'});page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(origin);await page.getByLabel('Your full name').fill('Test Office');await page.getByLabel('Username',{exact:true}).fill('office');await page.getByLabel('Password',{exact:true}).fill('OfficePass123!');await page.getByRole('button',{name:'Create office account'}).click();await page.getByRole('heading',{name:'Overview',exact:true}).waitFor();
  const session=await (await context.request.get(origin+'api/session')).json();const token=session.csrf;
  async function post(action,data,expected=200){const res=await context.request.post(origin+'api/'+action,{data,headers:{'X-CSRF-Token':token}});const body=await res.json();assert.equal(res.status(),expected,JSON.stringify(body));return body;}
  await page.getByRole('navigation').getByRole('button',{name:'Customers',exact:true}).click();await page.getByRole('button',{name:'Add customer',exact:true}).click();await page.getByLabel('Customer name').fill('Test Customer မြန်မာ');await page.getByLabel('Phone',{exact:true}).fill('09123456789');await page.getByLabel('Address',{exact:true}).fill('Nay Pyi Taw');await page.getByRole('button',{name:'Save record'}).click();await page.getByRole('cell',{name:'Test Customer မြန်မာ',exact:true}).waitFor();
  execFileSync('php',['tests/lookup-fixture.php'],{env});await page.getByRole('navigation').getByRole('button',{name:'Jobs',exact:true}).click();await page.getByRole('button',{name:'Add job',exact:true}).click();await checkSearchSelectors(page);await chooseLookup(page,'Customer','Test Customer');await page.getByLabel('Job / site title').fill('Seven-room installation');await page.getByRole('button',{name:'Save record'}).click();await page.getByText('Seven-room installation',{exact:true}).waitFor();
  const bootstrap=await (await context.request.get(origin+'api/bootstrap')).json();const job=bootstrap.jobs[0];
  assert.equal(bootstrap.catalog.length,66,'Document-source catalog is available to the app');
  assert.equal(bootstrap.rates.length,152,'Document-source service matrix is available to the app');
  await page.getByRole('navigation').getByRole('button',{name:'Final invoices',exact:true}).click();await page.getByRole('button',{name:'Create invoice',exact:true}).click();await chooseLookup(page,'Job','Seven-room installation');await page.getByRole('combobox',{name:'Catalog item (or custom charge)',exact:true}).fill('Copper Pipe');const copperList=page.getByRole('listbox',{name:'Catalog item (or custom charge) options',exact:true});await copperList.getByRole('option').nth(5).waitFor();const copperOptions=await copperList.getByRole('option').allTextContents();assert.equal(copperOptions.length,6);assert.equal(new Set(copperOptions).size,6,'Pipe size labels must be distinct');await page.getByRole('combobox',{name:'Catalog item (or custom charge)',exact:true}).press('Escape');await page.getByLabel('Description',{exact:true}).fill('Installation and materials မြန်မာ');await page.getByLabel('Rate (whole MMK)').fill('740700');await page.getByRole('button',{name:'Save draft',exact:true}).first().click();await page.getByRole('heading',{name:'INV-000001',exact:true}).waitFor();await page.getByRole('button',{name:'Issue document',exact:true}).click();await page.getByRole('button',{name:'Confirm',exact:true}).click();await page.getByRole('button',{name:'Collect payment',exact:true}).waitFor();
  for(const amount of [300000,200000,240700]){await page.getByRole('button',{name:'Collect payment',exact:true}).click();await page.getByLabel('Amount (whole MMK)').fill(String(amount));await page.getByRole('button',{name:'Record collection',exact:true}).click();await page.getByRole('dialog').waitFor({state:'hidden'});await page.getByText(`RCPT-${String([300000,200000,240700].indexOf(amount)+1).padStart(6,'0')}`,{exact:true}).waitFor();}
  const invoice=await (await context.request.get(origin+'api/document?id=1')).json();assert.equal(invoice.balance,0);assert.equal(invoice.paid,740700);
  await post('collect',{request_key:crypto.randomUUID(),job_id:job.id,document_id:1,date:'2026-11-22',method:'Cash',amount:1},422);
  const ledger=await post('save-ledger',{request_key:crypto.randomUUID(),kind:'expense',date:'2026-11-22',category:'Transportation',amount:25000,method:'Cash',description:'Site travel',job_id:job.id});
  const report=await (await context.request.get(origin+'api/reports?from=2026-01-01&to=2027-12-31')).json();assert.equal(report.collections,740700);assert.equal(report.expenses,25000);assert.equal(report.net,715700);assert.equal(report.outstanding,0);assert.equal(report.cashflow.length,4);
  await page.goto(origin+'#dashboard');await page.getByRole('heading',{name:'Overview',exact:true}).waitFor();await page.screenshot({path:'tests/artifacts/office.png',fullPage:true});
  await page.getByRole('button',{name:'Toggle color theme'}).click();await page.setViewportSize({width:1280,height:720});await page.screenshot({path:'tests/artifacts/dark.png',fullPage:true});await page.getByRole('button',{name:'Toggle color theme'}).click();
  await page.getByRole('button',{name:'Comfortable',exact:true}).click();await page.screenshot({path:'tests/artifacts/comfortable.png',fullPage:true});await page.getByRole('button',{name:'Compact',exact:true}).click();
  for(const width of [1024,390]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Viewport overflow');if(width<760){const box=await page.locator('.sidebar').boundingBox();assert(box.x+box.width<=1,'Closed mobile navigation must stay outside the viewport');}else{const sidebar=await page.locator('.sidebar').boundingBox();const content=await page.locator('.content').boundingBox();assert(sidebar.x+sidebar.width<=content.x+1,'Sidebar must not cover content');}await page.screenshot({path:`tests/artifacts/viewport-${width}.png`,fullPage:true});}
  const staff=await post('save-user',{name:'Mobile Staff',username:'staff',password:'StaffPass123!',role:'staff',active:1});
  const sc=await browser.newContext({viewport:{width:390,height:844}});const sp=await sc.newPage();await sp.goto(origin+'staff');await sp.getByLabel('Username',{exact:true}).fill('staff');await sp.getByLabel('Password',{exact:true}).fill('StaffPass123!');await sp.getByRole('button',{name:'Sign in',exact:true}).click();await sp.getByRole('heading',{name:'Overview',exact:true}).waitFor();
  assert.equal((await sc.request.get(origin+'api/document?id=1')).status(),403);
  const ss=await (await sc.request.get(origin+'api/session')).json();const forbidden=await sc.request.post(origin+'api/save-catalog',{headers:{'X-CSRF-Token':ss.csrf},data:{name:'Forbidden'}});assert.equal(forbidden.status(),403);
  const csrfFail=await sc.request.post(origin+'api/save-customer',{data:{name:'No token'}});assert.equal(csrfFail.status(),419);
  await sp.getByRole('button',{name:'Open navigation'}).click();await sp.getByRole('navigation').getByRole('button',{name:'Customers',exact:true}).click();await sp.getByRole('heading',{name:'Customers',exact:true}).waitFor();assert((await (await sc.request.get(origin+'api/customer?id='+job.customer_id)).json()).can_edit===false);
  await sp.screenshot({path:'tests/artifacts/staff.png',fullPage:true});
  await post('save-job',{...job,assigned_to:staff.id});
  assert.equal((await sc.request.get(origin+'api/document?id=1')).status(),200,'Assigned staff can see job documents');
  async function staffPost(action,data,expected=200){const res=await sc.request.post(origin+'api/'+action,{data,headers:{'X-CSRF-Token':ss.csrf}});const result=await res.json();assert.equal(res.status(),expected,JSON.stringify(result));return result;}
  const quoteLines=[812100,1804100,805800,663800,1171800,1312650,749150].flatMap((total,section)=>Array.from({length:10},(_,i)=>({section:`Room ${section+1}`,description:`Installation material ${i+1} — မြန်မာ specification with insulation and accessories`,unit:'Ft',quantity:'1',rate:total/10,equipment:{brand:'Test brand',hp:'1.5',technology:'Non-inverter'}})));
  const quote=await staffPost('save-document',{request_key:crypto.randomUUID(),type:'quotation',job_id:job.id,date:'2026-11-22',lines:quoteLines,terms:'Estimated quantities and actual work.'});assert.equal(quote.total,7319400);
  await staffPost('document-action',{id:quote.id,action:'issue'});
  const first=quote.lines[0];
  const preliminary=await staffPost('save-document',{request_key:crypto.randomUUID(),type:'preliminary',job_id:job.id,source_id:quote.id,date:'2026-11-22',lines:[{...first,origin_id:first.id,quantity:'1'}]});
  await staffPost('document-action',{id:preliminary.id,action:'issue'});
  const final=await staffPost('save-document',{request_key:crypto.randomUUID(),type:'invoice',job_id:job.id,source_id:preliminary.id,date:'2026-11-22',lines:[{...first,origin_id:first.id,quantity:'1'}]});
  await staffPost('document-action',{id:final.id,action:'issue'});
  const deposit=await post('collect',{request_key:crypto.randomUUID(),job_id:job.id,date:'2026-11-22',method:'Cash',amount:1000});
  const allocation={request_key:crypto.randomUUID(),payment_id:deposit.id,document_id:final.id,amount:1000};await post('allocate',allocation);await post('allocate',allocation);
  const allocated=await (await context.request.get(origin+`api/payment?id=${deposit.id}`)).json();assert.equal(allocated.allocations[0].amount,1000,'Allocation retry does not duplicate');

  await staffPost('collect',{request_key:crypto.randomUUID(),job_id:job.id,date:'2026-11-22',method:'Cash',amount:1000},403);
  await staffPost('allocate',{...allocation,request_key:crypto.randomUUID()},403);
  await staffPost('save-ledger',{request_key:crypto.randomUUID(),kind:'expense',date:'2026-11-22',category:'Transportation',amount:1,method:'Cash',description:'Staff cannot post'},403);
  for(const path of ['api/reports','api/list?resource=payments','api/list?resource=ledger','api/payment?id='+deposit.id,'api/ledger-files?id='+ledger.id,'api/lookup?resource=methods','api/lookup?resource=categories&kind=expense','api/lookup?resource=invoices&job_id='+job.id,'print/payment/'+deposit.id,'print/payment/'+deposit.id+'?format=pdf'])assert.equal((await sc.request.get(origin+path)).status(),403,path);
  const staffBoot=await (await sc.request.get(origin+'api/bootstrap')).json();assert.deepEqual(staffBoot.methods,[]);assert.deepEqual(staffBoot.categories,[]);
  const staffInvoice=await (await sc.request.get(origin+'api/document?id=1')).json();assert.deepEqual(staffInvoice.payments,[]);assert.equal(staffInvoice.total,740700,'Invoice amounts remain available for document work');
  await sp.goto(origin+'staff#dashboard');await sp.reload();await sp.getByRole('heading',{name:'Overview',exact:true}).waitFor();
  assert.equal(await sp.getByText('Net cash movement',{exact:true}).count(),0);assert.equal(await sp.getByText('Outstanding',{exact:true}).count(),0);
  await sp.getByRole('button',{name:'Open navigation',exact:true}).click();
  for(const label of ['Collections','Income & expenses','Reports'])assert.equal(await sp.getByRole('navigation').getByRole('button',{name:label,exact:true}).count(),0);
  await sp.getByRole('button',{name:'Close navigation',exact:true}).click();
  for(const route of ['payments','payment/1','ledger','reports']){await sp.goto(origin+'staff#'+route);await sp.getByText('This screen requires office access.',{exact:true}).waitFor();}
  await sp.goto(origin+'staff#document/'+final.id);await sp.getByRole('heading',{name:final.number,exact:true}).waitFor();assert.equal(await sp.getByRole('button',{name:'Collect payment',exact:true}).count(),0);assert.equal(await sp.getByRole('heading',{name:'Payment history',exact:true}).count(),0);assert(await sp.getByRole('link',{name:'Print paper',exact:true}).isVisible());
  await sp.goto(origin+'staff#job/'+job.id);await sp.getByRole('heading',{name:'Job details',exact:true}).waitFor();assert.equal(await sp.getByRole('button',{name:'Collect deposit',exact:true}).count(),0);
  await sp.goto(origin+'staff#dashboard');await sp.reload();await sp.getByRole('heading',{name:'Overview',exact:true}).waitFor();await sp.screenshot({path:'tests/artifacts/staff-no-finance.png',fullPage:true});
  await staffPost('reverse-payment',{id:deposit.id,reason:'Not allowed'},403);
  await post('reverse-payment',{id:deposit.id,reason:'Test correction'});
  const reopened=await (await context.request.get(origin+`api/document?id=${final.id}`)).json();assert.equal(reopened.balance,81210);
  const upload=await context.request.post(origin+'upload',{headers:{'X-CSRF-Token':token},multipart:{kind:'payment',id:'1',file:{name:'proof.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF')}}});assert.equal(upload.status(),200,await upload.text());
  const attachment=await upload.json();assert.equal((await context.request.get(origin+`attachment/${attachment.id}`)).status(),200);
  assert.equal((await sc.request.get(origin+`attachment/${attachment.id}`)).status(),403,'Staff cannot download another collector’s proof');
  const deniedUpload=await sc.request.post(origin+'upload',{headers:{'X-CSRF-Token':ss.csrf},multipart:{kind:'payment',id:String(deposit.id),file:{name:'proof.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF')}}});assert.equal(deniedUpload.status(),403);

  await checkStaffPermissions({browser,page:sp,context,staffContext:sc,staff,job,final,origin,post,staffPost});
  const noauth=await browser.newContext();assert.equal((await noauth.request.get(origin+'api/bootstrap')).status(),401);const denied=await noauth.request.get(origin+'print/document/1?format=pdf',{maxRedirects:0});assert.equal(denied.status(),302);
  const pdf=await context.request.get(origin+'print/document/1?format=pdf');assert.equal(pdf.status(),200,await pdf.text());const bytes=await pdf.body();assert.equal(bytes.subarray(0,5).toString(),'%PDF-');writeFileSync('tests/artifacts/invoice.pdf',bytes);
  const paper=await context.newPage();await paper.goto(origin+'print/document/1');await paper.getByRole('button',{name:'Print paper',exact:true}).waitFor();await paper.screenshot({path:'tests/artifacts/print.png',fullPage:true});await paper.pdf({path:'tests/artifacts/browser-invoice.pdf',preferCSSPageSize:true,printBackground:false,displayHeaderFooter:false});await paper.close();
  const receipt=await context.request.get(origin+'print/payment/1?format=pdf');assert.equal((await receipt.body()).subarray(0,5).toString(),'%PDF-');
  const quotationPdf=await context.request.get(origin+`print/document/${quote.id}?format=pdf`);assert.equal(quotationPdf.status(),200,await quotationPdf.text());writeFileSync('tests/artifacts/quotation.pdf',await quotationPdf.body());
  console.log(execFileSync('php',['tests/pdf-check.php','tests/artifacts/invoice.pdf','2'],{encoding:'utf8'}).trim());
  console.log(execFileSync('php',['tests/pdf-check.php','tests/artifacts/quotation.pdf','12'],{encoding:'utf8'}).trim());
  const longPaper=await context.newPage();await longPaper.goto(origin+`print/document/${quote.id}`);await longPaper.pdf({path:'tests/artifacts/browser-quotation.pdf',preferCSSPageSize:true,printBackground:false,displayHeaderFooter:false});await longPaper.close();
  console.log(execFileSync('php',['tests/pdf-check.php','tests/artifacts/browser-invoice.pdf','2'],{encoding:'utf8'}).trim());
  console.log(execFileSync('php',['tests/pdf-check.php','tests/artifacts/browser-quotation.pdf','14'],{encoding:'utf8'}).trim());
  await checkLocales({browser,page,context,staffPage:sp,origin,quoteId:quote.id});
  await context.setOffline(true);await page.reload().catch(()=>{});assert.equal(await page.getByRole('heading',{name:'Overview',exact:true}).count(),0,'No offline business shell');await context.setOffline(false);
  assert.deepEqual(errors,[]);
  console.log('PASS: setup, customer/job UI, invoice issue, three installments, overpayment rejection, collections/expense reconciliation, PDF/print, themes/density, responsive widths, mobile staff, authorization, CSRF, online-only behavior.');
}catch(e){for(const ctx of browser?.contexts()||[])for(const tab of ctx.pages())if(tab!==page){console.error('Additional page:',tab.url(),await tab.locator('body').innerText());await tab.screenshot({path:'tests/artifacts/staff-failure.png',fullPage:true});}if(page){console.error(await page.locator('body').innerText());await page.screenshot({path:'tests/artifacts/failure.png',fullPage:true});}console.error(serverLog.slice(-2500));throw e;}finally{await browser?.close();server.kill();}
