<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;

final class Lookup
{
    public static function search(array $data,array $user): array {
        $resource=Business::text($data,'resource',true,30);
        if (in_array($resource,['invoices','methods','categories'],true)) Business::office();
        $query=Business::text($data,'q',false,150);
        $selected=Business::text($data,'id',false,150);
        $where=[];$params=[];$value='r.id';$activeOnly=(string)($data['active_only']??'1')!=='0';
        $staff=$user['role']!=='office';$uid=(int)$user['id'];
        switch ($resource) {
            case 'customers':
                $from='customers r';$columns='r.*';$search="r.name||' '||r.phone||' '||r.address";$order='r.name,r.id';
                break;
            case 'jobs':
                $from='jobs r JOIN customers c ON c.id=r.customer_id';$columns='r.*,c.name customer_name,c.phone customer_phone';$search="r.title||' '||c.name||' '||c.phone||' '||r.address||' JOB-'||r.id||' JOB-'||printf('%06d',r.id)";$order='r.id DESC';
                if ($staff) $where[]="(r.created_by={$uid} OR r.assigned_to={$uid})";
                if ($activeOnly && $selected==='') $where[]="r.status<>'cancelled'";
                break;
            case 'catalog':
                $from='catalog r';$columns='r.*';$search="r.name||' '||r.specification||' '||r.category||' '||r.unit";$order='r.name,r.specification,r.id';
                if (!empty($data['kind'])) { $where[]='r.kind=?';$params[]=Business::text($data,'kind',true,30); }
                if ($activeOnly && $selected==='') $where[]='r.active=1';
                break;
            case 'users':
                Business::office();
                $from='users r';$columns='r.id,r.name,r.username,r.role,r.active';$search="r.name||' '||r.username";$order='r.name,r.id';
                if ($activeOnly && $selected==='') $where[]='r.active=1';
                break;
            case 'invoices':
                $job=Business::job((int)($data['job_id']??0));
                $from='documents r';$columns="r.id,r.number,r.date,r.total,r.status,r.total-(SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.document_id=r.id AND p.status='effective') balance";
                $search="r.number||' '||r.title";$order='r.id DESC';
                $where[]="r.type='invoice' AND r.status='issued' AND r.job_id=?";$params[]=$job['id'];
                if ($selected==='') $where[]="r.total>(SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.document_id=r.id AND p.status='effective')";
                break;
            case 'categories':
                $kind=Business::text($data,'kind',true,20);
                if (!in_array($kind,['income','expense'],true)) Business::fail('Choose an income or expense category.');
                $from='categories r';$columns='r.*';$search='r.name';$order='r.name,r.id';$value='r.name';
                $where[]='r.kind=?';$params[]=$kind;
                break;
            case 'methods':
                $from='methods r';$columns='r.*';$search='r.name';$order='r.name,r.id';$value='r.name';
                if ($activeOnly && $selected==='') $where[]='r.active=1';
                break;
            default: Business::fail('Unknown lookup resource.',404);
        }
        if ($selected!=='') { $where[]=$value.'=?';$params[]=$selected; }
        elseif ($query!=='') {
            // Treat %, _ and backslashes as search text rather than wildcard instructions.
            $where[]="({$search}) LIKE ? ESCAPE '\\'";
            $params[]='%'.strtr($query,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']).'%';
        }
        $sql=$from.($where?' WHERE '.implode(' AND ',$where):'');
        $page=max(1,min(1000000,(int)($data['page']??1)));$size=$selected!==''?1:25;
        $total=(int)DB::fetchValue('SELECT COUNT(*) FROM '.$sql,$params);
        $records=DB::fetchAll('SELECT '.$columns.' FROM '.$sql.' ORDER BY '.$order.' LIMIT '.$size.' OFFSET '.(($page-1)*$size),$params);
        $rows=[];
        foreach ($records as $record) {
            $label=$record['name']??'';$description='';$disabled=false;
            switch ($resource) {
                case 'customers': $description=self::join([$record['phone'],$record['address']]); break;
                case 'jobs': $label='JOB-'.str_pad((string)$record['id'],6,'0',STR_PAD_LEFT).' · '.$record['title'];$description=self::join([$record['customer_name'],$record['customer_phone'],$record['address']]);$disabled=$activeOnly && $record['status']==='cancelled';break;
                case 'catalog':
                    $description=self::join([$record['specification'],$record['unit'],number_format($record['price']).' '.__('MMK')]);$disabled=!$record['active'];
                    // Selection includes exactly the item's rates; no full-matrix dependency is needed.
                    if ($record['kind']==='service') $record['service_rates']=DB::fetchAll('SELECT * FROM service_rates WHERE catalog_id=?',[$record['id']]);
                    break;
                case 'users': $description=self::join([$record['username'],__($record['role'])]);$disabled=$activeOnly && !$record['active'];break;
                case 'invoices': $label=$record['number'];$description=__('Balance:').' '.number_format($record['balance']).' '.__('MMK').' · '.$record['date'];$disabled=$record['balance']<=0;break;
            }
            $selectedLabel=$label;
            if ($resource==='catalog' && $record['specification']!=='') $selectedLabel.=' · '.$record['specification'];
            if ($resource==='customers' && $record['phone']!=='') $selectedLabel.=' · '.$record['phone'];
            if ($resource==='jobs') $selectedLabel.=' · '.$record['customer_name'];
            $rows[]=['value'=>(string)($value==='r.name'?$record['name']:$record['id']),'label'=>$label,'description'=>$description,'selectedLabel'=>$selectedLabel,'disabled'=>(bool)$disabled,'record'=>$record];
        }
        return ['rows'=>$rows,'page'=>$page,'per_page'=>$size,'total'=>$total,'has_more'=>$page*$size<$total];
    }
    private static function join(array $parts): string { return implode(' · ',array_filter($parts,fn($s)=>$s!=='')); }
}
