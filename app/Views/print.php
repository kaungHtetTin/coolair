<?php
use App\Controllers\PrintController;
$money=fn($n)=>number_format((int)$n).' '.__('MMK');
$label=$kind==='payment'?'Payment Receipt':['quotation'=>'Quotation','preliminary'=>'Preliminary Invoice','invoice'=>'Final Invoice'][$record['type']];
$customer=$kind==='document'?$record['customer']:['name'=>$job['customer_name'],'phone'=>$job['customer_phone'],'address'=>$job['address']?:$job['customer_address']];
?>
<!doctype html><html lang="<?= e(\App\Services\Locale::current()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($record['number']) ?></title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#182b35;line-height:1.6;margin:0}
h2{font-size:18px;color:#07589a;text-align:center;margin:0 0 10px}h3{font-size:13px;color:#07589a;margin:16px 0 6px}p{margin:4px 0}.muted{color:#66757b}.right{text-align:right}
.meta,.lines,.summary,.signatures{border-collapse:collapse;width:100%}.meta td{vertical-align:top;width:50%;padding:6px 0}.lines th{background:#edf3f9;text-align:left;color:#164c7d}.lines td,.lines th{border:1px solid #a5b8cb;padding:7px;vertical-align:top}.lines .right{text-align:right}thead{display:table-header-group}.summary{margin:12px 0;break-inside:avoid;page-break-inside:avoid}.summary td{padding:4px}.summary .total td{border-top:2px solid #07589a;font-size:14px;font-weight:bold}.signatures{margin-top:40px;break-inside:avoid}.signatures td{text-align:center;padding-top:15px;width:33%;border-top:1px solid #aab9b8}.stamp{color:#b23e24;font-size:14px;font-weight:bold}.words{margin:18px 0}.equipment{font-size:10px;color:#53656d}.paper{margin:0;padding:0}.print-letterhead{display:none}
html[lang=my] body{line-height:1.9}html[lang=my] h2{font-size:17px}html[lang=my] .lines th{font-size:10px}
@page{size:210mm 297mm;margin:43mm 12mm 24mm;<?php if ($isPdf): ?>odd-footer-name:html_documentfooter;even-footer-name:html_documentfooter;margin-footer:21mm;<?php endif; ?>}
<?php if (!$isPdf): ?>
body{background:#e8edf2}.print-actions{padding:15px;background:#fff;border-bottom:1px solid #d3dfde;margin-bottom:18px}button,a{font:inherit}.paper{box-sizing:border-box;width:210mm;min-height:297mm;margin:0 auto 18px;padding:43mm 12mm 24mm;background:#fff;position:relative;box-shadow:0 2px 12px #162b3520}.document-content{position:relative;z-index:1}.print-layout{width:100%;border-collapse:collapse;font:inherit}.print-layout>thead,.print-layout>tfoot{display:none}.document-cell{padding:0;vertical-align:top}.letterhead-art{position:absolute;left:0;width:210mm;background-image:url('<?= e(url('branding/letterhead.jpg')) ?>');background-size:210mm 297mm;background-repeat:no-repeat;pointer-events:none}.letterhead-top{top:0;height:39mm;background-position:0 0}.letterhead-mark{top:75mm;height:160mm;background-position:0 -75mm}.letterhead-bottom{bottom:0;height:20mm;background-position:0 bottom}
@media screen and (max-width:810px){body{overflow-x:auto}.print-actions{min-width:760px}.paper{margin-left:0;margin-right:0}}
@media print{@page{size:210mm 297mm;margin:0}body{background:transparent}.print-actions,.letterhead-art{display:none}.print-letterhead{display:block;position:fixed;top:0;left:0;width:210mm;height:297mm;z-index:0}.paper{width:auto;min-height:0;margin:0;padding:0;background:transparent;box-shadow:none;position:relative;z-index:1}.document-content{position:relative}.print-layout>thead{display:table-header-group}.print-layout>tfoot{display:table-footer-group}.print-layout>thead td{height:43mm;padding:0;line-height:0;font-size:0}.print-layout>tfoot td{height:24mm;padding:0;line-height:0;font-size:0}.document-cell{padding:0 12mm}.lines tr,.meta tr,.summary tr,.signatures tr{break-inside:avoid}a{color:inherit;text-decoration:none}h2,h3{break-after:avoid}}
<?php endif; ?>
</style>
<?php if (!$isPdf): $assets=json_decode(file_get_contents(BASE_PATH.'/public/build/.vite/manifest.json'),true); $font=$assets['resources/fonts/Tharlon-Regular.ttf']['file']??''; ?>
<style>@font-face{font-family:notomyanmar;src:url('<?= e(url('build/'.$font)) ?>')}body{font-family:notomyanmar,sans-serif}</style>
<?php endif; ?>
</head><body<?= $isPdf?' style="font-family:notomyanmar"':'' ?>>
<?php if (!$isPdf): ?><div class="print-actions"><button onclick="window.print()"><?= e(__('Print paper')) ?></button> <a href="?format=pdf&amp;lang=<?= e(\App\Services\Locale::current()) ?>"><?= e(__('Download PDF')) ?></a> <span><?= e(__('Choose A4 paper in your printer settings.')) ?> <?= e(__('Turn off browser headers and footers.')) ?></span></div><?php endif; ?>
<?php if (!$isPdf): ?><img class="print-letterhead" src="<?= e(url('branding/letterhead.jpg')) ?>" alt="" aria-hidden="true"><?php endif; ?>
<div class="paper"><?php if (!$isPdf): ?><div class="letterhead-art letterhead-top" aria-hidden="true"></div><div class="letterhead-art letterhead-mark" aria-hidden="true"></div><div class="letterhead-art letterhead-bottom" aria-hidden="true"></div><?php endif; ?><div class="document-content">
<?php if (!$isPdf): ?><table class="print-layout" role="presentation"><thead aria-hidden="true"><tr><td></td></tr></thead><tfoot aria-hidden="true"><tr><td></td></tr></tfoot><tbody><tr><td class="document-cell"><?php endif; ?>
<h2><?= e(__($label)) ?></h2>
<?php if (($record['status']??'')==='draft'): ?><p class="stamp"><?= e(__('DRAFT — NOT ISSUED')) ?></p><?php endif; ?>
<?php if (in_array($record['status'],['void','reversed'],true)): ?><p class="stamp"><?= e(__(strtoupper($record['status']))) ?> — <?= e($record['reversal_reason']??'') ?></p><?php endif; ?>
<table class="meta"><tr><td><strong><?= e($customer['name']) ?></strong><br><?= nl2br(e($customer['address'])) ?><br><?= e($customer['phone']) ?></td><td class="right"><strong><?= e($record['number']) ?></strong><br><?= e(__('Date')) ?>: <?= e($record['date']) ?><br><?= e(__('Job')) ?>: JOB-<?= str_pad((string)$job['id'],6,'0',STR_PAD_LEFT) ?><br><?= e($job['title']) ?><?php if (!empty($record['due_date'])): ?><br><?= __($record['type']==='quotation'?'Valid until':'Due date') ?>: <?= e($record['due_date']) ?><?php endif; ?></td></tr></table>
<?php if ($kind==='document'): ?>
<?php
$groups=[]; foreach ($record['lines'] as $line) $groups[$line['section']][]=$line;
?>
<?php if (count($groups)>1): ?><h3><?= e(__('Work summary')) ?></h3><table class="lines"><thead><tr><th><?= e(__('Room / equipment')) ?></th><th class="right"><?= e(__('Subtotal')) ?></th></tr></thead><tbody><?php foreach ($groups as $name=>$lines): ?><tr><td><?= e($name) ?></td><td class="right"><?= $money(array_sum(array_column($lines,'amount'))) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php foreach ($groups as $name=>$lines): ?><h3><?= e($name) ?></h3>
<?php $eq=$lines[0]['equipment']; ?><p class="equipment"><?= e(implode(' · ',array_filter([$eq['brand']??'',$eq['model']??'',__($eq['equipment_type']??''),__($eq['technology']??''),empty($eq['hp'])?'':$eq['hp'].' '.__('HP'),empty($eq['equipment_quantity'])?'':__('Equipment qty:').' '.$eq['equipment_quantity'],empty($eq['serial'])?'':__('Serial:').' '.$eq['serial']]))) ?></p>
<table class="lines"><thead><tr><th style="width:5%">#</th><th><?= e(__('Particulars')) ?></th><th><?= e(__('Unit')) ?></th><th class="right"><?= e(__('Qty')) ?></th><th class="right"><?= e(__('Rate')) ?></th><th class="right"><?= e(__('Amount')) ?></th></tr></thead><tbody>
<?php foreach ($lines as $i=>$line): ?><tr><td><?= $i+1 ?></td><td><?= nl2br(e($line['description'])) ?></td><td><?= e($line['unit']) ?></td><td class="right"><?= e($line['quantity_input']) ?></td><td class="right"><?= number_format($line['rate']) ?></td><td class="right"><?= number_format($line['amount']) ?></td></tr><?php endforeach; ?>
</tbody></table><?php endforeach; ?>
<table class="summary"><tr><td><?= e(__('Subtotal')) ?></td><td class="right"><?= $money($record['subtotal']) ?></td></tr><tr><td><?= e(__('Discount (')) ?><?= e($record['discount_bp']/100) ?>%)</td><td class="right"><?= $money($record['discount']) ?></td></tr><tr class="total"><td><?= e(__('Net total')) ?></td><td class="right"><?= $money($record['total']) ?></td></tr>
<?php if ($record['type']==='invoice'): ?><tr><td><?= e(__('Paid to date')) ?></td><td class="right"><?= $money($record['paid']) ?></td></tr><tr><td><?= e(__('Outstanding balance')) ?></td><td class="right"><?= $money($record['balance']) ?></td></tr><?php endif; ?></table>
<p class="words"><strong><?= e(__('In words:')) ?></strong> <?= e(PrintController::words($record['total'])) ?> <?= e(__('Kyats only.')) ?></p>
<?php if ($record['notes']): ?><h3><?= e(__('Remarks')) ?></h3><p><?= nl2br(e($record['notes'])) ?></p><?php endif; ?>
<?php if ($record['terms']): ?><h3><?= e(__('Terms')) ?></h3><p><?= nl2br(e($record['terms'])) ?></p><?php endif; ?>
<?php if ($record['type']==='preliminary'): ?><p class="stamp"><?= e(__('Preliminary charges — this is not a final invoice or payment receipt.')) ?></p><?php endif; ?>
<?php else: ?>
<table class="summary"><tr class="total"><td><?= e(__('Amount received')) ?></td><td class="right"><?= $money($record['amount']) ?></td></tr><tr><td><?= e(__('Payment method')) ?></td><td class="right"><?= e($record['method']) ?></td></tr><tr><td><?= e(__('Reference')) ?></td><td class="right"><?= e($record['reference']) ?></td></tr><tr><td><?= e(__('Collected by')) ?></td><td class="right"><?= e($record['collector']) ?></td></tr></table>
<p class="words"><strong><?= e(__('In words:')) ?></strong> <?= e(PrintController::words($record['amount'])) ?> <?= e(__('Kyats only.')) ?></p>
<h3><?= e(__('Allocation as of printing')) ?></h3><table class="lines"><thead><tr><th><?= e(__('Invoice')) ?></th><th class="right"><?= e(__('Applied amount')) ?></th><th class="right"><?= e(__('Current invoice balance')) ?></th></tr></thead><tbody><?php foreach ($record['allocations'] as $a): ?><tr><td><?= e($a['number']) ?></td><td class="right"><?= $money($a['amount']) ?></td><td class="right"><?= $money($a['invoice_balance']) ?></td></tr><?php endforeach; ?><?php if ($record['unallocated']): ?><tr><td><?= e(__('Unallocated job deposit')) ?></td><td class="right"><?= $money($record['unallocated']) ?></td><td>?</td></tr><?php endif; ?></tbody></table><p><?= nl2br(e($record['notes'])) ?></p>
<?php endif; ?>
<table class="signatures"><tr><td><?= e(__('Customer')) ?></td><td><?= e(__('Cashier')) ?></td><td><?= e(__('Engineer / Technician')) ?></td></tr></table>
<p class="muted" style="margin-top:20px;font-size:9px"><?= e(__('Printed')) ?> <?= e(date('Y-m-d H:i')) ?> · <?= e($record['number']) ?><?php if ($kind==='document' && $record['type']==='invoice'): ?> · <?= e(__('Payment balance shown as of printing.')) ?><?php endif; ?></p>
<?php if (!$isPdf): ?></td></tr></tbody></table><?php endif; ?></div></div></body></html>
