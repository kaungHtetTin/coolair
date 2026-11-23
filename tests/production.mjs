import {spawn,spawnSync} from 'node:child_process';
import {request} from 'node:http';
import {readFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';

mkdirSync('tests/artifacts',{recursive:true});
const profile=Object.fromEntries(readFileSync('.env.production.example','utf8').split(/\r?\n/).filter(line=>/^[A-Z_]+=/.test(line)).map(line=>{const at=line.indexOf('=');return[line.slice(0,at),line.slice(at+1).replace(/^"|"$/g,'')];}));
const env={...process.env,...profile,DB_PATH:resolve(`tests/artifacts/production-${Date.now()}.sqlite`),TRUSTED_PROXIES:'127.0.0.1,::1'};
function run(args,expected=0){const result=spawnSync('php',args,{env,encoding:'utf8'});assert.equal(result.status,expected,result.stdout+result.stderr);return result.stdout;}
run(['bin/console','deploy:prepare']);run(['bin/console','migrate']);run(['bin/console','db:seed']);
assert.match(run(['bin/console','deploy:check'],1),/FAIL An active office administrator exists/);
run(['bin/console','user:create','deploy-test','TestProduction123!','Deployment Test']);
assert(!run(['bin/console','deploy:check']).includes('FAIL'));
const server=spawn('php',['-S','127.0.0.1:8094','-t','public','public/router.php'],{env,stdio:['ignore','ignore','pipe']});
let logs='';server.stderr.on('data',data=>logs+=data);
function http(path,headers={},data){return new Promise((resolve,reject)=>{const req=request({hostname:'127.0.0.1',port:8094,path,method:data?'POST':'GET',headers:{Host:'coolair.k2studioapp.com','Content-Type':'application/json',...headers}},res=>{let body='';res.on('data',chunk=>body+=chunk);res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body}));});req.on('error',reject);req.end(data?JSON.stringify(data):undefined);});}
try{
 let ready=false;for(let i=0;i<50;i++){try{await http('/');ready=true;break;}catch{}await new Promise(r=>setTimeout(r,100));}assert(ready,logs);
 const redirect=await http('/api/session?lang=my');assert.equal(redirect.status,308);assert.equal(redirect.headers.location,'https://coolair.k2studioapp.com/api/session?lang=my');assert(!redirect.headers['set-cookie']);
 const secure={'X-Forwarded-Proto':'https'};
 const session=await http('/api/session',secure);assert.equal(session.status,200,session.body);const state=JSON.parse(session.body);assert.equal(state.setup,false);
 const cookies=session.headers['set-cookie'];assert(cookies?.some(c=>/secure/i.test(c)&&/httponly/i.test(c)&&/samesite=lax/i.test(c)),'Production session cookie flags');
 assert.equal(session.headers['strict-transport-security'],'max-age=31536000');
 const setup=await http('/api/setup',{...secure,Cookie:cookies[0].split(';')[0],'X-CSRF-Token':state.csrf},{username:'forbidden',password:'NoPublicSetup123!',name:'Forbidden'});
 assert.equal(setup.status,403,setup.body);assert.equal(JSON.parse(setup.body).error_source,'Public setup is disabled. Contact the office administrator.');
 const host=await http('/office',{...secure,Host:'untrusted.example'});assert.equal(host.status,308);assert.equal(host.headers.location,'https://coolair.k2studioapp.com/office');
 console.log('Passed production profile: readiness checks, private administrator creation, HTTPS/canonical redirects, trusted proxy handling, secure session/HSTS and disabled public setup.');
}finally{server.kill();}
