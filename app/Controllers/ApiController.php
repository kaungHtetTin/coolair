<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Business as B;
use Mini\Database as DB;
use Mini\Csrf;

final class ApiController
{
    public function handle(string $action): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $data=$_SERVER['REQUEST_METHOD']==='GET' ? $_GET : (json_decode(file_get_contents('php://input'),true) ?? $_POST);
            if (!is_array($data)) B::fail('Invalid request.');
            $result=$this->dispatch($action,$data);
            echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $code=(int)$e->getCode();
            $safe=$e instanceof \RuntimeException && in_array($code,[401,403,404,409,422,429],true);
            if (!$safe) error_log((string)$e);
            http_response_code($safe?$code:500);
            echo json_encode(\App\Services\Locale::error($safe?$e->getMessage():'The request could not be completed. Please retry or contact the office.'));
        }
    }
    private function dispatch(string $action,array $data): mixed {
        if ($action==='session') {
            $user=null;
            if (!empty($_SESSION['user_id'])) { try { $user=B::user(); } catch (\Throwable) { unset($_SESSION['user_id']); } }
            return ['user'=>$user,'csrf'=>Csrf::token(),'setup'=>config('app.allow_setup') && !(bool)DB::fetchValue('SELECT COUNT(*) FROM users'),'today'=>date('Y-m-d')];
        }
        if (in_array($action,['login','setup'],true)) {
            $this->post();
            if ($action==='setup' && !config('app.allow_setup')) B::fail('Public setup is disabled. Contact the office administrator.',403);
            $username=B::text($data,'username',true,100); $password=B::text($data,'password',true,200);
            if ($action==='setup') {
                DB::transaction(function () use ($data,$username,$password) {
                    if (DB::fetchValue('SELECT COUNT(*) FROM users')) B::fail('Setup has already been completed.',403);
                    if (strlen($password)<10) B::fail('Password must contain at least 10 characters.');
                    $_SESSION['user_id']=DB::insert('users',['name'=>B::text($data,'name',true,100),'username'=>$username,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'office']);
                    B::audit('setup','user',$_SESSION['user_id']);
                });
            } else {
                $key=hash('sha256',strtolower($username).'|'.($_SERVER['REMOTE_ADDR']??''));
                $attempt=DB::fetch('SELECT * FROM login_attempts WHERE key=?',[$key]);
                if ($attempt && $attempt['attempts']>=8 && $attempt['last_at']>time()-900) B::fail('Too many login attempts. Try again in 15 minutes.',429);
                $user=DB::fetch('SELECT * FROM users WHERE username=? AND active=1',[$username]);
                if (!$user || !password_verify($password,$user['password'])) {
                    B::audit('sign_in_failed','session',null,['username_attempted'=>$username]);
                    DB::statement('INSERT INTO login_attempts(key,attempts,last_at) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET attempts=CASE WHEN last_at<? THEN 1 ELSE attempts+1 END,last_at=excluded.last_at',[$key,time(),time()-900]);
                    B::fail('Username or password is incorrect.',401);
                }
                DB::statement('DELETE FROM login_attempts WHERE key=?',[$key]);
                $_SESSION['user_id']=$user['id'];
            }
            session_regenerate_id(true); unset($_SESSION['_token']);
            if ($action==='login') B::audit('signed_in','session',null);
            return ['user'=>B::user(),'csrf'=>Csrf::token()];
        }
        $u=B::user();
        if (in_array($action,['payment','ledger-files','reports','collect','allocate','reverse-payment','save-ledger','reverse-ledger'],true)) B::office();
        if ($action==='overview') {
            $scope=B::documentScope();
            return ['documents'=>DB::fetchPairs("SELECT d.type,COUNT(*) FROM documents d JOIN jobs j ON j.id=d.job_id WHERE {$scope} AND d.status<>'void' GROUP BY d.type")];
        }
        if ($action==='lookup') return \App\Services\Lookup::search($data,$u);
        if ($action==='bootstrap') {
            $scope=B::jobScope();
            return [
                'user'=>$u,'settings'=>B::settings(),
                'catalog'=>DB::fetchAll('SELECT * FROM catalog ORDER BY name'),'rates'=>DB::fetchAll('SELECT * FROM service_rates'),
                'methods'=>$u['role']==='office'?DB::fetchAll('SELECT * FROM methods ORDER BY name'):[],
                'categories'=>$u['role']==='office'?DB::fetchAll('SELECT * FROM categories ORDER BY name'):[],
                'users'=>DB::fetchAll('SELECT id,name,username,role,active FROM users '.($u['role']==='office'?'':'WHERE id='.$u['id']).' ORDER BY name'),
                'customers'=>DB::fetchAll('SELECT c.*,owner.username current_creator_username,owner.name current_creator_name FROM customers c JOIN users owner ON owner.id=c.created_by ORDER BY c.name'),
                'jobs'=>DB::fetchAll('SELECT j.*,c.name customer_name,u.name staff_name,owner.username current_creator_username,owner.name current_creator_name FROM jobs j JOIN customers c ON c.id=j.customer_id JOIN users u ON u.id=j.assigned_to JOIN users owner ON owner.id=j.created_by WHERE '.$scope.' ORDER BY j.id DESC'),
            ];
        }
        if ($action==='customer') {
            $id=(int)($data['id']??0);
            $record=DB::fetch('SELECT c.*,owner.username current_creator_username,owner.name current_creator_name FROM customers c JOIN users owner ON owner.id=c.created_by WHERE c.id=?',[$id]);
            if (!$record) B::fail('Customer not found.',404);
            $record['can_edit']=$u['role']==='office'||$record['created_by']===$u['id'];
            $record['history']=B::history('customer',$id);
            B::audit('viewed','customer',$id);
            return $record;
        }
        if ($action==='job') {
            $id=(int)($data['id']??0);$record=B::job($id);
            $creator=DB::fetch('SELECT name,username FROM users WHERE id=?',[$record['created_by']]);
            $record['creator_username']=$record['creator_username']?:$creator['username'];
            $record['creator_name']=$record['creator_name']?:$creator['name'];
            $record['history']=B::history('job',$id);
            B::audit('viewed','job',$id);
            return $record;
        }
        if ($action==='list') return $this->listing($data,$u);
        if ($action==='document') { $record=B::document((int)($data['id']??0));B::audit('viewed','document',$record['id']);return $record; }
        if ($action==='payment') return B::payment((int)($data['id']??0));
        if ($action==='ledger-files') {
            $id=(int)($data['id']??0); $r=DB::fetch('SELECT * FROM ledger WHERE id=?',[$id]);
            if (!$r) B::fail('Entry not found.',404);
            if ($u['role']!=='office' && $r['created_by']!==$u['id']) B::fail('Access denied.',403);
            return DB::fetchAll("SELECT id,filename FROM attachments WHERE kind='ledger' AND record_id=?",[$id]);
        }
        if ($action==='reports') return $this->reports($data,$u);
        $this->post();
        if ($action==='logout') { B::audit('signed_out','session',null); $_SESSION=[]; session_regenerate_id(true); return ['ok'=>true]; }
        if ($action==='password') {
            $old=B::text($data,'current_password',true,200); $new=B::text($data,'password',true,200);
            if (!password_verify($old,DB::fetchValue('SELECT password FROM users WHERE id=?',[$u['id']]))) B::fail('Current password is incorrect.');
            if (strlen($new)<10) B::fail('New password must contain at least 10 characters.');
            DB::update('users',$u['id'],['password'=>password_hash($new,PASSWORD_DEFAULT)]); B::audit('password_changed','user',$u['id']); return ['ok'=>true];
        }
        if ($action==='save-document') return B::saveDocument($data);
        if ($action==='document-action') return B::documentAction($data);
        if ($action==='collect') return B::collect($data);
        if ($action==='allocate') return DB::transaction(function () use ($data) {
            $key=B::requestKey($data); $paymentId=(int)($data['payment_id']??0); $documentId=(int)($data['document_id']??0); $amount=B::integer($data['amount']??0,1);
            B::payment($paymentId);
            if ($old=DB::fetch('SELECT * FROM allocation_requests WHERE request_key=?',[$key])) {
                if ($old['payment_id']!==$paymentId || $old['document_id']!==$documentId || $old['amount']!==$amount) B::fail('This allocation reference has already been used. Reload before allocating again.',409);
                return B::payment($paymentId);
            }
            B::allocate($paymentId,$documentId,$amount);
            DB::insert('allocation_requests',['request_key'=>$key,'payment_id'=>$paymentId,'document_id'=>$documentId,'amount'=>$amount]);
            return B::payment($paymentId);
        });
        if ($action==='reverse-payment') {
            B::office();
            return DB::transaction(function () use ($data,$u) {
                $id=(int)($data['id']??0); $p=B::payment($id);
                if ($p['status']==='reversed') return $p;
                $reason=B::text($data,'reason',true);
                DB::update('payments',$id,['status'=>'reversed','reversal_reason'=>$reason,'reversed_at'=>gmdate('Y-m-d H:i:s'),'reversed_by'=>$u['id']]);
                B::audit('reversed','payment',$id,['reason'=>$reason,'amount'=>$p['amount'],'before'=>['status'=>$p['status']],'after'=>['status'=>'reversed']]);
                foreach ($p['allocations'] as $allocation) B::audit('payment_reversed','document',$allocation['document_id'],['receipt_number'=>$p['number'],'amount'=>$allocation['amount'],'reason'=>$reason]);
                return B::payment($id);
            });
        }
        if ($action==='save-customer') return DB::transaction(function () use ($data,$u) {
            $id=(int)($data['id']??0);
            $old=null;
            if ($id) {
                $old=DB::fetch('SELECT * FROM customers WHERE id=?',[$id]);
                if (!$old) B::fail('Customer not found.',404);
                if ($u['role']!=='office' && $old['created_by']!==$u['id']) B::fail('Only the customer creator or the office can update this customer.',403);
            }
            $row=['name'=>B::text($data,'name',true,150),'phone'=>B::text($data,'phone',false,100),'address'=>B::text($data,'address'),'notes'=>B::text($data,'notes')];
            if ($id) DB::update('customers',$id,$row); else $id=DB::insert('customers',$row+['created_by'=>$u['id']]+B::creator($u));
            B::audit($old?'updated':'created','customer',$id,['before'=>$old?array_intersect_key($old,$row):null,'after'=>$row]); return ['id'=>$id];
        });
        if ($action==='save-job') return DB::transaction(function () use ($data,$u) {
            $id=(int)($data['id']??0); $old=$id?B::job($id):null;
            $customer=(int)($data['customer_id']??0); $c=DB::fetch('SELECT * FROM customers WHERE id=?',[$customer]);
            if (!$c) B::fail('Choose a customer.');
            $staff=$u['role']==='office'?(int)($data['assigned_to']??$u['id']):(int)($old['assigned_to']??$u['id']);
            if ($u['role']!=='office' && isset($data['assigned_to']) && (int)$data['assigned_to']!==$staff) B::fail('Only the office can reassign staff.',403);
            if (!DB::fetchValue('SELECT COUNT(*) FROM users WHERE id=? AND active=1',[$staff])) B::fail('Choose an active staff member.');
            $status=B::text($data,'status',true); if (!in_array($status,['open','in_progress','completed','cancelled'],true)) B::fail('Invalid job status.');
            $row=['customer_id'=>$customer,'title'=>B::text($data,'title',true,200),'address'=>B::text($data,'address'),'assigned_to'=>$staff,'status'=>$status,'notes'=>B::text($data,'notes')];
            if ($id) {
                $old=B::job($id);
                if ($old['customer_id']!==$customer && DB::fetchValue('SELECT COUNT(*) FROM documents WHERE job_id=?',[$id])) B::fail('Cannot change the customer after creating documents.');
                DB::update('jobs',$id,$row);
            } else $id=DB::insert('jobs',$row+['created_by'=>$u['id']]+B::creator($u));
            B::audit($old?'updated':'created','job',$id,['before'=>$old?array_intersect_key($old,$row):null,'after'=>$row]);
            if ($old && $old['assigned_to']!==$staff) B::audit('reassigned','job',$id,['from_user_id'=>$old['assigned_to'],'from_username'=>DB::fetchValue('SELECT username FROM users WHERE id=?',[$old['assigned_to']]),'to_user_id'=>$staff,'to_username'=>DB::fetchValue('SELECT username FROM users WHERE id=?',[$staff])]);
            return ['id'=>$id];
        });
        if ($action==='save-ledger') {
            $kind=B::text($data,'kind',true); if ($kind==='income') B::office();
            if (!in_array($kind,['income','expense'],true)) B::fail('Invalid entry kind.');
            return DB::transaction(function () use ($data,$u,$kind) {
                $key=B::requestKey($data); if ($old=DB::fetch('SELECT id,created_by FROM ledger WHERE request_key=?',[$key])) { if ($old['created_by']!==$u['id']) B::fail('Access denied.',403); return ['id'=>$old['id']]; }
                $category=B::text($data,'category',true,100);
                if (!DB::fetchValue('SELECT COUNT(*) FROM categories WHERE kind=? AND name=?',[$kind,$category])) B::fail('Choose a valid category.');
                $job=(int)($data['job_id']??0)?:null; if ($job) B::job($job);
                $id=DB::insert('ledger',['kind'=>$kind,'date'=>B::date($data),'category'=>$category,'amount'=>B::integer($data['amount']??0,1),'method'=>B::method($data),'description'=>B::text($data,'description',true),'job_id'=>$job,'request_key'=>$key,'created_by'=>$u['id']]);
                B::audit('posted','ledger',$id,['after'=>DB::fetch('SELECT * FROM ledger WHERE id=?',[$id])]); return ['id'=>$id];
            });
        }
        B::office();
        if ($action==='reverse-ledger') return DB::transaction(function () use ($data) {
            $id=(int)($data['id']??0); $before=DB::fetch('SELECT * FROM ledger WHERE id=?',[$id]);if (!$before) B::fail('Entry not found.',404);
            if ($before['status']==='reversed') return ['ok'=>true];
            $reason=B::text($data,'reason',true); DB::update('ledger',$id,['status'=>'reversed','reversal_reason'=>$reason]); B::audit('reversed','ledger',$id,['reason'=>$reason,'before'=>['status'=>$before['status']],'after'=>['status'=>'reversed']]); return ['ok'=>true];
        });
        if ($action==='save-catalog') return DB::transaction(function () use ($data) {
            $id=(int)($data['id']??0); $kind=B::text($data,'kind',true);
            $old=$id?DB::fetch('SELECT * FROM catalog WHERE id=?',[$id]):null;
            if (!in_array($kind,['material','equipment','service','gas','transport'],true)) B::fail('Invalid item kind.');
            $row=['kind'=>$kind,'name'=>B::text($data,'name',true,200),'category'=>B::text($data,'category',false,100),'specification'=>B::text($data,'specification'),'unit'=>B::text($data,'unit',true,30),'price'=>B::integer($data['price']??0,0,100000000),'active'=>(int)!empty($data['active'])];
            if ($id) DB::update('catalog',$id,$row); else $id=DB::insert('catalog',$row);
            B::audit($old?'updated':'created','catalog',$id,['before'=>$old?array_intersect_key($old,$row):null,'after'=>$row]); return ['id'=>$id];
        });
        if ($action==='save-rate') return DB::transaction(function () use ($data) {
            $id=(int)($data['id']??0); $catalog=(int)($data['catalog_id']??0);
            $old=$id?DB::fetch('SELECT * FROM service_rates WHERE id=?',[$id]):null;
            if (!DB::fetchValue("SELECT COUNT(*) FROM catalog WHERE id=? AND kind='service'",[$catalog])) B::fail('Choose a service.');
            $tech=B::text($data,'technology',true); if (!in_array($tech,['Inverter','Non-inverter'],true)) B::fail('Choose inverter classification.');
            $row=['catalog_id'=>$catalog,'equipment_type'=>B::text($data,'equipment_type',true,100),'technology'=>$tech,'hp'=>B::text($data,'hp',true,20),'price'=>B::integer($data['price']??0,0,100000000)];
            if ($id) DB::update('service_rates',$id,$row); else { $existing=DB::fetchValue('SELECT id FROM service_rates WHERE catalog_id=? AND equipment_type=? AND technology=? AND hp=?',[$catalog,$row['equipment_type'],$tech,$row['hp']]); if ($existing) B::fail('This rate already exists. Edit it instead.'); $id=DB::insert('service_rates',$row); }
            B::audit($old?'updated':'created','rate',$id,['before'=>$old?array_intersect_key($old,$row):null,'after'=>$row]); return ['id'=>$id];
        });
        if ($action==='save-user') return DB::transaction(function () use ($data,$u) {
            $id=(int)($data['id']??0); $role=B::text($data,'role',true);
            $old=$id?DB::fetch('SELECT id,name,username,role,active FROM users WHERE id=?',[$id]):null;
            if (!in_array($role,['office','staff'],true)) B::fail('Invalid role.');
            $row=['name'=>B::text($data,'name',true,100),'username'=>B::text($data,'username',true,100),'role'=>$role,'active'=>(int)!empty($data['active'])];
            if ($id===$u['id'] && (!$row['active'] || $role!=='office')) B::fail('You cannot disable or demote your own account.');
            if (DB::fetchValue('SELECT COUNT(*) FROM users WHERE username=? AND id<>?',[$row['username'],$id])) B::fail('This username is already in use.');
            $password=B::text($data,'password',!$id,200);
            if ($password!=='') { if (strlen($password)<10) B::fail('Passwords require at least 10 characters.'); $row['password']=password_hash($password,PASSWORD_DEFAULT); }
            if ($id) DB::update('users',$id,$row); else $id=DB::insert('users',$row);
            B::audit($old?'updated':'created','user',$id,['before'=>$old?array_intersect_key($old,$row):null,'after'=>$row]); return ['id'=>$id];
        });
        if ($action==='save-settings') return DB::transaction(function () use ($data) {
            $before=B::settings();
            foreach (['company_name','company_address','company_phone','company_email','terms'] as $key) DB::statement('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$key,B::text($data,$key,$key==='company_name',5000)]);
            B::audit('updated','settings',null,['before'=>$before,'after'=>B::settings()]); return ['ok'=>true];
        });
        if ($action==='save-category') return DB::transaction(function () use ($data) {
            $kind=B::text($data,'kind',true); if (!in_array($kind,['income','expense'],true)) B::fail('Invalid category kind.');
            $name=B::text($data,'name',true,100);
            $statement=DB::statement('INSERT OR IGNORE INTO categories(kind,name) VALUES(?,?)',[$kind,$name]);
            if ($statement->rowCount()) B::audit('created','category',(int)DB::fetchValue('SELECT id FROM categories WHERE kind=? AND name=?',[$kind,$name]),['kind'=>$kind,'name'=>$name]);
            return ['ok'=>true];
        });
        if ($action==='save-method') return DB::transaction(function () use ($data) {
            $name=B::text($data,'name',true,100);$before=DB::fetch('SELECT * FROM methods WHERE name=?',[$name]);
            DB::statement('INSERT INTO methods(name,active) VALUES(?,?) ON CONFLICT(name) DO UPDATE SET active=excluded.active',[$name,(int)!empty($data['active'])]);
            $after=DB::fetch('SELECT * FROM methods WHERE name=?',[$name]);
            B::audit($before?'updated':'created','method',$after['id'],['before'=>$before,'after'=>$after]);return ['ok'=>true];
        });
        if ($action==='record-export') {
            B::audit('export_requested','report',null,['report'=>B::text($data,'report',true,100),'from'=>B::date($data,'from'),'to'=>B::date($data,'to'),'method'=>B::text($data,'method',false,100),'staff_id'=>(int)($data['staff_id']??0)]);
            return ['ok'=>true];
        }
        B::fail('Unknown action.',404);
    }
    private function post(): void { if ($_SERVER['REQUEST_METHOD']!=='POST') B::fail('Use POST for this action.',422); }
    private function listing(array $data,array $u): array {
        $resource=$data['resource']??'documents'; $where=[]; $params=[];
        if (in_array($resource,['payments','ledger'],true)) B::office();
        if ($resource==='documents') {
            $from='documents r JOIN jobs j ON j.id=r.job_id JOIN customers c ON c.id=j.customer_id JOIN users u ON u.id=r.created_by';
            $select="r.*,j.title job_title,c.name customer_name,u.name staff_name,u.username current_creator_username,(SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.document_id=r.id AND p.status='effective') paid";
            $search="r.number||' '||r.title||' '||c.name||' '||j.title||' '||r.creator_username||' '||u.username";
            if (!empty($data['created_by'])) { $where[]='r.created_by=?';$params[]=(int)$data['created_by']; }
            $where[]=B::documentScope('r','j');
            if (!empty($data['type'])) { $where[]='r.type=?'; $params[]=$data['type']; }
        } elseif ($resource==='payments') {
            $from='payments r JOIN jobs j ON j.id=r.job_id JOIN customers c ON c.id=j.customer_id JOIN users u ON u.id=r.created_by';
            $select='r.*,c.name customer_name,j.title job_title,u.name staff_name,(SELECT COALESCE(SUM(amount),0) FROM payment_allocations WHERE payment_id=r.id) allocated'; $search='r.number||c.name||r.reference';
            if ($u['role']!=='office') $where[]='r.created_by='.$u['id'];
        } elseif ($resource==='ledger') {
            $from='ledger r JOIN users u ON u.id=r.created_by'; $select='r.*,u.name staff_name'; $search='r.description||r.category';
            if ($u['role']!=='office') $where[]='r.created_by='.$u['id'];
            if (!empty($data['kind'])) { $where[]='r.kind=?'; $params[]=$data['kind']; }
        } elseif ($resource==='audit') {
            $from='audit r LEFT JOIN users u ON u.id=r.user_id'; $select="r.*,COALESCE(NULLIF(r.actor_name,''),u.name,'System') actor,u.username current_actor_username"; $search="r.action||' '||r.entity||' '||COALESCE(r.entity_id,'')||' '||r.detail||' '||r.actor_username||' '||r.actor_name||' '||COALESCE(u.username,'')";
            if ($u['role']!=='office') $where[]="r.user_id=".$u['id']." AND r.entity IN ('customer','job','document','session','user')";
            foreach (['entity','entity_id','user_id'] as $filter) if (!empty($data[$filter])) { $where[]='r.'.$filter.'=?'; $params[]=$data[$filter]; }
        } else B::fail('Unknown list.');
        if (!empty($data['q'])) { $where[]='('.$search.') LIKE ?'; $params[]='%'.B::text($data,'q',false,150).'%'; }
        if (!empty($data['status']) && $resource!=='audit') { $where[]='r.status=?'; $params[]=$data['status']; }
        foreach (['from'=>'>=','to'=>'<='] as $key=>$op) if (!empty($data[$key])) { $where[]=($resource==='audit'?'substr(r.created_at,1,10)':'r.date').$op.'?'; $params[]=B::date($data,$key); }
        if (!empty($data['job_id']) && $resource!=='audit') { $where[]='r.job_id=?'; $params[]=(int)$data['job_id']; }
        $sql=$from.($where?' WHERE '.implode(' AND ',$where):'');
        $page=max(1,(int)($data['page']??1)); $limit=25;
        return ['rows'=>DB::fetchAll('SELECT '.$select.' FROM '.$sql.' ORDER BY r.id DESC LIMIT '.$limit.' OFFSET '.(($page-1)*$limit),$params),'total'=>(int)DB::fetchValue('SELECT COUNT(*) FROM '.$sql,$params),'page'=>$page,'per_page'=>$limit];
    }
    private function reports(array $data,array $u): array {
        $from=empty($data['from'])?'2000-01-01':B::date($data,'from'); $to=empty($data['to'])?date('Y-m-d'):B::date($data,'to');
        if ($from>$to) B::fail('Start date must be before end date.');
        $scope=$u['role']==='office'?'':' AND created_by='.$u['id'];
        $billScope=$u['role']==='office'?'':' AND job_id IN (SELECT id FROM jobs WHERE created_by='.$u['id'].' OR assigned_to='.$u['id'].')';
        $staff=$u['role']==='office'?(int)($data['staff_id']??0):0;
        if ($staff) { $scope.=' AND created_by='.$staff; $billScope.=' AND created_by='.$staff; }
        if (!empty($data['method'])) $scope.=' AND method='.DB::connection()->quote(B::text($data,'method',false,100));
        $billed=(int)DB::fetchValue("SELECT COALESCE(SUM(total),0) FROM documents WHERE type='invoice' AND status='issued' AND date BETWEEN ? AND ?".$billScope,[$from,$to]);
        $collections=(int)DB::fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='effective' AND date BETWEEN ? AND ?".$scope,[$from,$to]);
        $income=(int)DB::fetchValue("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE kind='income' AND status='effective' AND date BETWEEN ? AND ?".$scope,[$from,$to]);
        $expense=(int)DB::fetchValue("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE kind='expense' AND status='effective' AND date BETWEEN ? AND ?".$scope,[$from,$to]);
        $outstanding=DB::fetchAll("SELECT d.id,d.number,d.date,d.due_date,d.total,c.name customer_name,j.title job_title,d.total-(SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.document_id=d.id AND p.status='effective') balance FROM documents d JOIN jobs j ON j.id=d.job_id JOIN customers c ON c.id=j.customer_id WHERE d.type='invoice' AND d.status='issued'".($u['role']==='office'?'':' AND (j.created_by='.$u['id'].' OR j.assigned_to='.$u['id'].')').' ORDER BY d.date');
        $outstanding=array_values(array_filter($outstanding,fn($r)=>$r['balance']>0 && (!$staff || (int)DB::fetchValue('SELECT created_by FROM documents WHERE id=?',[$r['id']])===$staff)));
        $cash=DB::fetchAll("SELECT 'collection' kind,number reference,date,'Invoice collections / deposits' category,amount,method,created_by FROM payments WHERE status='effective' AND date BETWEEN ? AND ?".$scope." UNION ALL SELECT kind,'ENTRY-'||id,date,category,amount,method,created_by FROM ledger WHERE status='effective' AND date BETWEEN ? AND ?".$scope.' ORDER BY date DESC',[$from,$to,$from,$to]);
        return ['billed'=>$billed,'collections'=>$collections,'other_income'=>$income,'expenses'=>$expense,'net'=>$collections+$income-$expense,'outstanding'=>array_sum(array_column($outstanding,'balance')),'receivables'=>$outstanding,'cashflow'=>$cash,'from'=>$from,'to'=>$to];
    }
}
