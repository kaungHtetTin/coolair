<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use RuntimeException;

final class Business
{
    public static function fail(string $message, int $code = 422): never { throw new RuntimeException($message, $code); }
    public static function text(array $data, string $key, bool $required = false, int $max = 2000): string {
        if (isset($data[$key]) && !is_scalar($data[$key])) self::fail("Invalid {$key}.");
        $value = trim((string)($data[$key] ?? ''));
        if (($required && $value === '') || mb_strlen($value) > $max) self::fail("Check {$key}: " . ($required ? 'required, ' : '') . "maximum {$max} characters.");
        return $value;
    }
    public static function integer(mixed $value, int $min = 0, int $max = 100000000000): int {
        if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value) || (float)$value > $max || (float)$value < $min) self::fail('Enter a valid whole-number amount within the permitted range.');
        return (int)$value;
    }
    public static function scaled(mixed $value, int $places = 3): int {
        if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,' . $places . '})?$/D', (string)$value)) self::fail('Enter a valid positive decimal with up to ' . $places . ' decimal places.');
        $parts = explode('.', (string)$value);
        return self::integer($parts[0], 0, 1000000) * (10 ** $places) + (int)str_pad($parts[1] ?? '', $places, '0');
    }
    public static function date(array $data, string $key = 'date', bool $optional = false): string {
        $value = self::text($data, $key, !$optional, 10);
        if ($optional && $value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) self::fail('Enter a valid ' . $key . '.');
        return $value;
    }
    public static function user(): array {
        $user = DB::fetch('SELECT id,name,username,role,active FROM users WHERE id=? AND active=1', [$_SESSION['user_id'] ?? 0]);
        if (!$user) self::fail('Please sign in to continue.', 401);
        return $user;
    }
    public static function office(): array { $u = self::user(); if ($u['role'] !== 'office') self::fail('Office access required.', 403); return $u; }
    public static function settings(): array { return DB::fetchPairs('SELECT key,value FROM settings'); }
    public static function creator(array $user): array { return ['creator_username'=>$user['username'],'creator_name'=>$user['name']]; }
    public static function jobScope(string $alias='j'): string {
        $u=self::user();return $u['role']==='office'?'1=1':"({$alias}.created_by={$u['id']} OR {$alias}.assigned_to={$u['id']})";
    }
    public static function documentScope(string $document='d',string $job='j'): string {
        $u=self::user();return $u['role']==='office'?'1=1':'('.self::jobScope($job)." AND ({$document}.created_by={$u['id']} OR {$job}.assigned_to={$u['id']}))";
    }
    public static function audit(string $action, string $entity, ?int $id, array $detail = []): void {
        $actor=DB::fetch('SELECT id,name,username FROM users WHERE id=?',[$_SESSION['user_id']??0]);
        $redact=function(array $values) use (&$redact): array { foreach($values as $key=>$value){if(in_array($key,['password','current_password','password_confirmation','_token','request_key'],true)){unset($values[$key]);continue;}if(is_array($value))$values[$key]=$redact($value);}return $values;};
        DB::insert('audit', ['user_id'=>$actor['id']??null,'actor_username'=>$actor['username']??'System','actor_name'=>$actor['name']??'System','action'=>$action,'entity'=>$entity,'entity_id'=>$id,'detail'=>json_encode($redact($detail), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
    public static function history(string $entity,int $id): array {
        $u=self::user();
        return DB::fetchAll("SELECT a.*,COALESCE(NULLIF(a.actor_name,''),u.name,'System') actor FROM audit a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity=? AND a.entity_id=?".($u['role']==='office'?'':' AND a.user_id='.$u['id']).' ORDER BY a.id DESC LIMIT 100',[$entity,$id]);
    }
    public static function documentSnapshot(array $document): array {
        $snapshot=array_intersect_key($document,array_flip(['type','job_id','source_id','date','due_date','title','notes','terms','discount_bp','subtotal','discount','total','status','version']));
        $snapshot['lines']=array_map(fn($line)=>array_intersect_key($line,array_flip(['origin_id','catalog_id','section','equipment','description','unit','quantity','rate','amount'])),$document['lines']??[]);
        return $snapshot;
    }
    public static function job(int $id): array {
        $u = self::user();
        $job = DB::fetch('SELECT j.*,c.name customer_name,c.phone customer_phone,c.address customer_address,staff.name staff_name,staff.username staff_username FROM jobs j JOIN customers c ON c.id=j.customer_id JOIN users staff ON staff.id=j.assigned_to WHERE j.id=?', [$id]);
        if (!$job) self::fail('Job not found.',404);
        if ($u['role'] !== 'office' && (int)$job['created_by'] !== $u['id'] && (int)$job['assigned_to'] !== $u['id']) self::fail('Access denied.',403);
        return $job;
    }
    public static function document(int $id): array {
        $d = DB::fetch('SELECT * FROM documents WHERE id=?',[$id]);
        if (!$d) self::fail('Document not found.',404);
        self::job((int)$d['job_id']);
        if (!DB::fetchValue('SELECT COUNT(*) FROM documents d JOIN jobs j ON j.id=d.job_id WHERE d.id=? AND '.self::documentScope(),[$id])) self::fail('Access denied.',403);
        $creator=DB::fetch('SELECT name,username FROM users WHERE id=?',[$d['created_by']]);
        $d['creator_username']=$d['creator_username']?:($creator['username']??'');
        $d['creator_name']=$d['creator_name']?:($creator['name']??'');
        $d['customer'] = json_decode($d['customer_snapshot'],true);
        $d['company'] = json_decode($d['company_snapshot'],true);
        $d['lines'] = DB::fetchAll('SELECT * FROM document_lines WHERE document_id=? ORDER BY position,id',[$id]);
        foreach ($d['lines'] as &$line) {
            $line['equipment'] = json_decode($line['equipment'],true);
            $line['quantity_input'] = rtrim(rtrim(number_format($line['quantity']/1000,3,'.',''),'0'),'.');
            $origin = $line['origin_id'] ?: $line['id'];
            $line['billed_quantity'] = (int)DB::fetchValue("SELECT COALESCE(SUM(l.quantity),0) FROM document_lines l JOIN documents d ON d.id=l.document_id WHERE l.origin_id=? AND d.type='invoice' AND d.status='issued' AND d.id<>?",[$origin,$id]);
        }
        $d['paid'] = self::paid($id);
        $d['balance'] = $d['total']-$d['paid'];
        $d['payments'] = self::user()['role']==='office' ? DB::fetchAll('SELECT p.*,a.amount allocated,u.name collector FROM payments p JOIN payment_allocations a ON a.payment_id=p.id JOIN users u ON u.id=p.created_by WHERE a.document_id=? ORDER BY p.id DESC',[$id]) : [];
        $d['related'] = DB::fetchAll('SELECT d.id,d.number,d.type,d.status FROM documents d JOIN jobs j ON j.id=d.job_id WHERE (d.source_id=? OR d.id=?) AND '.self::documentScope().' ORDER BY d.id',[$id,$d['source_id']??0]);
        $d['history'] = self::history('document',$id);
        return $d;
    }
    public static function paid(int $id): int { return (int)DB::fetchValue("SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.document_id=? AND p.status='effective'",[$id]); }
    public static function number(string $table, string $prefix): string { return $prefix . '-' . str_pad((string)((int)DB::fetchValue("SELECT COALESCE(MAX(id),0)+1 FROM {$table}")),6,'0',STR_PAD_LEFT); }
    public static function requestKey(array $data): string {
        $key = self::text($data,'request_key',true,100);
        if (!preg_match('/^[a-zA-Z0-9-]{16,100}$/D',$key)) self::fail('Invalid request reference.');
        return $key;
    }
    public static function saveDocument(array $data): array {
        $u = self::user();
        return DB::transaction(function () use ($data,$u) {
            $id = (int)($data['id']??0);
            $old = $id ? self::document($id) : null;
            if ($old && $old['status'] !== 'draft') self::fail('Issued documents are locked. Create a revision or replacement.');
            if ($old && (int)($data['version']??0) !== $old['version']) self::fail('This draft changed in another window. Reload before editing.',409);
            $key = self::requestKey($data);
            if (!$id && $existing=DB::fetch('SELECT id FROM documents WHERE request_key=?',[$key])) return self::document($existing['id']);
            $type = self::text($data,'type',true);
            if (!in_array($type,['quotation','preliminary','invoice'],true)) self::fail('Invalid document type.');
            if ($old && $old['type']!==$type) self::fail('Use conversion to change document types.');
            $job = self::job((int)($data['job_id']??0));
            if ($job['status']==='cancelled') self::fail('Cannot bill a cancelled job.');
            $sourceId = $old['source_id'] ?? ((int)($data['source_id']??0) ?: null);
            $source = $sourceId ? self::document((int)$sourceId) : null;
            if ($source && ($source['job_id']!==$job['id'] || !in_array($source['status'],['issued','void'],true))) self::fail('Source must be an issued document from this job.');
            if ($source && ($source['type']==='invoice' && $type!=='invoice' || $source['type']==='preliminary' && $type==='quotation')) self::fail('Invalid document conversion.');
            $sourceLines = [];
            foreach ($source['lines']??[] as $l) $sourceLines[$l['origin_id']?:$l['id']]=$l;
            $input = $data['lines']??[];
            if (!is_array($input) || count($input)<1 || count($input)>250) self::fail('Add between 1 and 250 lines.');
            $lines=[]; $subtotal=0;
            foreach ($input as $position=>$line) {
                if (!is_array($line)) self::fail('Invalid line.');
                $quantity=self::scaled($line['quantity']??'');
                if ($quantity<=0) self::fail('Line quantities must be greater than zero.');
                $rate=self::integer($line['rate']??0,0,100000000);
                $amount=intdiv($quantity*$rate+500,1000);
                $origin=(int)($line['origin_id']??0)?:null;
                if ($origin && !isset($sourceLines[$origin])) self::fail('Invalid source line.');
                $catalog=(int)($line['catalog_id']??0)?:null;
                if ($catalog && !DB::fetchValue('SELECT COUNT(*) FROM catalog WHERE id=?',[$catalog])) self::fail('Catalog item not found.');
                $equipment=[];
                foreach (['brand','model','equipment_type','technology','hp','equipment_quantity','serial'] as $field) $equipment[$field]=self::text((array)($line['equipment']??[]),$field,false,150);
                $lines[]=['origin_id'=>$origin,'catalog_id'=>$catalog,'section'=>self::text($line,'section',true,150),'equipment'=>json_encode($equipment,JSON_UNESCAPED_UNICODE),'description'=>self::text($line,'description',true,1000),'unit'=>self::text($line,'unit',true,30),'quantity'=>$quantity,'rate'=>$rate,'amount'=>$amount,'position'=>$position];
                $subtotal+=$amount;
            }
            if ($subtotal>100000000000) self::fail('Document total exceeds the supported limit.');
            $discountBp=self::scaled($data['discount_percent']??'0',2);
            if ($discountBp>10000) self::fail('Discount must be between 0 and 100%.');
            $discount=intdiv($subtotal*$discountBp+5000,10000);
            $row=['type'=>$type,'job_id'=>$job['id'],'source_id'=>$sourceId,'date'=>self::date($data),'due_date'=>self::date($data,'due_date',true),'customer_snapshot'=>json_encode(['name'=>$job['customer_name'],'phone'=>$job['customer_phone'],'address'=>$job['address']?:$job['customer_address']],JSON_UNESCAPED_UNICODE),'company_snapshot'=>json_encode(self::settings(),JSON_UNESCAPED_UNICODE),'title'=>self::text($data,'title',false,200),'notes'=>self::text($data,'notes',false,5000),'terms'=>self::text($data,'terms',false,5000),'discount_bp'=>$discountBp,'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$subtotal-$discount,'version'=>($old['version']??0)+1];
            if ($id) { DB::update('documents',$id,$row); DB::statement('DELETE FROM document_lines WHERE document_id=?',[$id]); }
            else { $row+=['number'=>self::number('documents',['quotation'=>'QT','preliminary'=>'PI','invoice'=>'INV'][$type]),'request_key'=>$key,'created_by'=>$u['id']]+self::creator($u); $id=DB::insert('documents',$row); }
            foreach ($lines as $line) DB::insert('document_lines',['document_id'=>$id]+$line);
            $saved=self::document($id);
            self::audit($old?'updated':($sourceId?($source['type']===$type?'revised':'converted'):'created'),'document',$id,['source_id'=>$sourceId,'before'=>$old?self::documentSnapshot($old):null,'after'=>self::documentSnapshot($saved)]);
            if (!$old && $sourceId) self::audit('derived_document_created','document',(int)$sourceId,['target_id'=>$id,'target_number'=>$saved['number'],'target_type'=>$type]);
            return self::document($id);
        });
    }
    public static function documentAction(array $data): array {
        return DB::transaction(function () use ($data) {
            $id=(int)($data['id']??0); $d=self::document($id); $action=$data['action']??'';
            if ($action==='issue') {
                if ($d['status']==='issued') return $d;
                if ($d['status']!=='draft') self::fail('Only drafts can be issued.');
                if ($d['source_id']) {
                    $source=DB::fetch('SELECT type,status FROM documents WHERE id=?',[$d['source_id']]);
                    if ($source['status']==='void' && !($source['type']==='invoice' && $d['type']==='invoice')) self::fail('Cannot issue a document from a void source.');
                }
                if ($d['type']==='invoice') {
                    if ($d['source_id']) {
                        $source=DB::fetch('SELECT type,status FROM documents WHERE id=?',[$d['source_id']]);
                        if ($source['type']==='invoice' && $source['status']!=='void') self::fail('Void the original invoice before issuing its replacement.');
                    }
                    $totals=[];
                    foreach ($d['lines'] as $l) if ($l['origin_id']) $totals[$l['origin_id']]=($totals[$l['origin_id']]??0)+$l['quantity'];
                    foreach ($totals as $origin=>$quantity) {
                        $original=DB::fetch('SELECT l.*,d.type FROM document_lines l JOIN documents d ON d.id=l.document_id WHERE l.id=?',[$origin]);
                        $billed=(int)DB::fetchValue("SELECT COALESCE(SUM(l.quantity),0) FROM document_lines l JOIN documents d ON d.id=l.document_id WHERE l.origin_id=? AND d.type='invoice' AND d.status='issued'",[$origin]);
                        if ($original && $quantity+$billed>$original['quantity']) self::fail('Source work has already been billed. Reduce quantities or enter additional work separately.');
                    }
                }
                DB::update('documents',$id,['status'=>'issued','version'=>$d['version']+1]);
            } elseif ($action==='void') {
                self::office();
                if ($d['status']==='void') return $d;
                $reason=self::text($data,'reason',true);
                if (self::paid($id)>0) self::fail('Reverse or reallocate payments before voiding this invoice.');
                if (DB::fetchValue("SELECT COUNT(*) FROM documents WHERE source_id=? AND status='issued'",[$id])) self::fail('Void linked issued documents first to preserve the document chain.');
                DB::update('documents',$id,['status'=>'void','version'=>$d['version']+1]);
            } else self::fail('Unknown document action.');
            self::audit($action,'document',$id,['reason'=>$reason??'','before'=>['status'=>$d['status'],'version'=>$d['version']],'after'=>['status'=>$action==='issue'?'issued':'void','version'=>$d['version']+1]]);
            return self::document($id);
        });
    }
    public static function payment(int $id): array {
        self::office();
        $p=DB::fetch('SELECT p.*,u.name collector FROM payments p JOIN users u ON u.id=p.created_by WHERE p.id=?',[$id]);
        if (!$p) self::fail('Payment not found.',404);
        self::job($p['job_id']);
        if (self::user()['role']!=='office' && $p['created_by']!==self::user()['id']) self::fail('Access denied.',403);
        $p['allocations']=DB::fetchAll("SELECT a.*,d.number,d.total-(SELECT COALESCE(SUM(pa.amount),0) FROM payment_allocations pa JOIN payments pp ON pp.id=pa.payment_id WHERE pa.document_id=d.id AND pp.status='effective') invoice_balance FROM payment_allocations a JOIN documents d ON d.id=a.document_id WHERE a.payment_id=?",[$id]);
        $p['unallocated']=$p['amount']-array_sum(array_column($p['allocations'],'amount'));
        $p['attachments']=DB::fetchAll("SELECT id,filename FROM attachments WHERE kind='payment' AND record_id=?",[$id]);
        return $p;
    }
    public static function method(array $data): string {
        $method=self::text($data,'method',true,100);
        if (!DB::fetchValue('SELECT COUNT(*) FROM methods WHERE name=? AND active=1',[$method])) self::fail('Choose an active payment method.');
        return $method;
    }
    public static function collect(array $data): array {
        self::office();
        return DB::transaction(function () use ($data) {
            $key=self::requestKey($data);
            if ($old=DB::fetch('SELECT id FROM payments WHERE request_key=?',[$key])) return self::payment($old['id']);
            $job=self::job((int)($data['job_id']??0));
            if ($job['status']==='cancelled') self::fail('Cannot collect against a cancelled job.');
            $amount=self::integer($data['amount']??0,1);
            $id=DB::insert('payments',['number'=>self::number('payments','RCPT'),'job_id'=>$job['id'],'date'=>self::date($data),'amount'=>$amount,'method'=>self::method($data),'reference'=>self::text($data,'reference',false,200),'notes'=>self::text($data,'notes'),'request_key'=>$key,'created_by'=>self::user()['id']]);
            $document=(int)($data['document_id']??0);
            if ($document) self::allocate($id,$document,$amount);
            self::audit('collected','payment',$id,['after'=>DB::fetch('SELECT * FROM payments WHERE id=?',[$id])]);
            return self::payment($id);
        });
    }
    public static function allocate(int $paymentId,int $documentId,int $amount): void {
        self::office();
        $p=self::payment($paymentId); $d=self::document($documentId);
        if ($p['status']!=='effective' || $d['type']!=='invoice' || $d['status']!=='issued' || $p['job_id']!==$d['job_id']) self::fail('Allocate an effective payment to an issued final invoice from the same job.');
        if ($amount<=0 || $amount>$p['unallocated'] || $amount>$d['balance']) self::fail('Amount exceeds the available payment or invoice balance.');
        DB::statement('INSERT INTO payment_allocations(payment_id,document_id,amount) VALUES(?,?,?) ON CONFLICT(payment_id,document_id) DO UPDATE SET amount=amount+excluded.amount',[$paymentId,$documentId,$amount]);
        self::audit('allocated','payment',$paymentId,['invoice'=>$documentId,'amount'=>$amount]);
        self::audit('payment_allocated','document',$documentId,['receipt_number'=>$p['number'],'amount'=>$amount,'before'=>['balance'=>$d['balance']],'after'=>['balance'=>$d['balance']-$amount]]);
    }
}
