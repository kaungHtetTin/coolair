import assert from 'node:assert/strict';

export async function checkStaffPermissions({browser,page,context,staffContext,staff,job,final,origin,post,staffPost}) {
  await staffPost('save-customer',{id:job.customer_id,name:'Unauthorized customer edit',phone:'changed'},403);
  const own=await staffPost('save-customer',{name:'Staff-owned customer',phone:'09111',address:'Staff site',notes:'Own customer'});
  await staffPost('save-customer',{id:own.id,name:'Staff-owned customer',phone:'09222',address:'Staff site',notes:'Edited by owner'});
  const ownCustomer=await (await staffContext.request.get(origin+'api/customer?id='+own.id)).json();
  assert.equal(ownCustomer.can_edit,true);assert.equal(ownCustomer.phone,'09222');assert.equal(ownCustomer.creator_username,'staff');
  const sharedCustomer=await (await staffContext.request.get(origin+'api/customer?id='+job.customer_id)).json();
  assert.equal(sharedCustomer.can_edit,false);assert.equal(sharedCustomer.name,'Test Customer မြန်မာ');
  const other=await post('save-user',{name:'Other Staff',username:'other-staff',password:'OtherStaff123!',role:'staff',active:1});
  const otherContext=await browser.newContext();
  try {
    const session=await (await otherContext.request.get(origin+'api/session')).json();
    const login=await otherContext.request.post(origin+'api/login',{headers:{'X-CSRF-Token':session.csrf},data:{username:'other-staff',password:'OtherStaff123!'}});const auth=await login.json();assert.equal(login.status(),200);
    async function otherPost(action,data){const res=await otherContext.request.post(origin+'api/'+action,{headers:{'X-CSRF-Token':auth.csrf},data});const result=await res.json();assert.equal(res.status(),200,JSON.stringify(result));return result;}
    const privateJob=await otherPost('save-job',{customer_id:job.customer_id,title:'Other staff private operation',status:'open',address:'Other site'});
    const make=async(call,jobId,description)=>call('save-document',{request_key:crypto.randomUUID(),type:'quotation',job_id:jobId,date:'2026-11-22',lines:[{section:'General',description,quantity:'1',unit:'Set',rate:100}]});
    const privateDoc=await make(otherPost,privateJob.id,'Private quotation of other staff');
    const ownJob=await staffPost('save-job',{customer_id:job.customer_id,title:'Created by staff, later assigned to another',status:'open'});
    const ownDoc=await make(staffPost,ownJob.id,'Own quotation preserved');
    await post('save-job',{id:ownJob.id,customer_id:job.customer_id,title:'Created by staff, later assigned to another',status:'open',assigned_to:other.id});
    const otherDoc=await make(otherPost,ownJob.id,'Other staff quotation on creator job');
    for(const path of ['api/job?id='+privateJob.id,'api/document?id='+privateDoc.id,'api/document?id='+otherDoc.id,'print/document/'+otherDoc.id+'?format=pdf'])assert.equal((await staffContext.request.get(origin+path)).status(),403,path);
    assert.equal((await staffContext.request.get(origin+'api/document?id='+ownDoc.id)).status(),200);
    const docs=await (await staffContext.request.get(origin+'api/list?resource=documents&job_id='+ownJob.id)).json();assert.deepEqual(docs.rows.map(r=>r.id),[ownDoc.id]);
    const boot=await (await staffContext.request.get(origin+'api/bootstrap')).json();assert(!boot.jobs.some(j=>j.id===privateJob.id));assert(boot.customers.some(c=>c.id===job.customer_id));
    await staffPost('save-job',{id:ownJob.id,customer_id:job.customer_id,title:'Cannot reassign',status:'open',assigned_to:staff.id},403);
    const feed=await (await staffContext.request.get(origin+'api/list?resource=audit')).json();assert(feed.rows.length>0);assert(feed.rows.every(e=>e.user_id===staff.id));assert(feed.rows.every(e=>e.actor_username==='staff'));
    const spoofed=await (await staffContext.request.get(origin+'api/list?resource=audit&user_id='+other.id)).json();assert.equal(spoofed.total,0);
    const officeFeed=await (await context.request.get(origin+'api/list?resource=audit&user_id='+staff.id)).json();assert(officeFeed.rows.length>0);assert(officeFeed.rows.every(e=>e.actor_username==='staff'));
    const invoice=await (await staffContext.request.get(origin+'api/document?id='+final.id)).json();assert.equal(invoice.creator_username,'staff');assert(invoice.history.every(e=>e.user_id===staff.id));
    await page.goto(origin+'staff#customers');await page.reload();await page.getByRole('heading',{name:'Customers',exact:true}).waitFor();
    await page.getByLabel('Search records',{exact:true}).fill('Test Customer');
    const readOnlyRow=page.getByRole('row').filter({hasText:'Test Customer မြန်မာ'});await readOnlyRow.getByRole('button',{name:'View',exact:true}).click();
    const modal=page.getByRole('dialog',{name:'Customer details',exact:true});await modal.getByText('Read only.',{exact:false}).waitFor();assert(await modal.getByLabel('Customer name',{exact:true}).evaluate(el=>el.readOnly));await modal.getByRole('button',{name:'Close',exact:true}).click();
    assert.equal(await readOnlyRow.getByRole('button',{name:/Edit/}).count(),0);
    await page.getByLabel('Search records',{exact:true}).fill('Staff-owned');const ownerRow=page.getByRole('row').filter({hasText:'Staff-owned customer'});assert(await ownerRow.getByRole('button',{name:/Edit/}).isVisible());
    await page.goto(origin+'staff#audit');await page.getByRole('heading',{name:'My activity',exact:true}).waitFor();assert.equal(await page.getByRole('combobox',{name:'Acting user',exact:true}).count(),0);await page.screenshot({path:'tests/artifacts/staff-activity.png',fullPage:true});
    await page.goto(origin+'staff#document/'+final.id);await page.getByRole('heading',{name:final.number,exact:true}).waitFor();assert(await page.getByText('@staff',{exact:true}).first().isVisible());
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Document/history panels must fit mobile width');await page.screenshot({path:'tests/artifacts/document-creator.png',fullPage:true});
    console.log('PASS: customer owner editing / shared reading, staff job/document isolation, related links, immutable creator metadata, own activity feed, office actor filtering, and visible creator usernames.');
  } finally {await otherContext.close();}
}
