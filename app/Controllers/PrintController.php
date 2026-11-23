<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Business as B;
use Mini\Database as DB;
final class PrintController
{
    public function document(string $id): void { $this->render('document',(int)$id); }
    public function receipt(string $id): void { $this->render('payment',(int)$id); }
    private function render(string $kind,int $id): void {
        header('Cache-Control: no-store, private');
        try {
            $user=B::user();
            $record=$kind==='document'?B::document($id):B::payment($id);
            $company=$kind==='document'?$record['company']:B::settings();
            $job=B::job($record['job_id']);
            $isPdf=($_GET['format']??'')==='pdf';
            ob_start(); require BASE_PATH.'/app/Views/print.php'; $html=ob_get_clean();
            if ($isPdf) {
                if (!class_exists(\Mpdf\Mpdf::class)) B::fail('PDF dependencies missing. Run composer install.',422);
                $tmp=BASE_PATH.'/storage/pdf'; if (!is_dir($tmp)) mkdir($tmp,0770,true);
                $fontData=(new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
                $fontDirs=(new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
                $fontData['notomyanmar']=['R'=>'Tharlon-Regular.ttf','useOTL'=>0xFF];
                $mpdf=new \Mpdf\Mpdf(['tempDir'=>$tmp,'format'=>'A4','fontDir'=>array_merge($fontDirs,[BASE_PATH.'/resources/fonts']),'fontdata'=>$fontData,'default_font'=>'notomyanmar','margin_top'=>43,'margin_bottom'=>24,'margin_left'=>12,'margin_right'=>12,'margin_footer'=>21]);
                // The supplied artwork already contains its faint watermark and contact footer.
                $mpdf->SetWatermarkImage(BASE_PATH.'/public/branding/letterhead.jpg',1,[210,297],[0,0]);
                $mpdf->watermarkImgBehind=true;
                $mpdf->showWatermarkImage=true;
                $mpdf->SetTitle($record['number']);
                $mpdf->DefHTMLFooterByName('documentfooter','<div style="font-family:notomyanmar;font-size:9px;text-align:right">'.e($record['number']).' · '.e(__('Page {page} of {pages}',['page'=>'{PAGENO}','pages'=>'{nbpg}'])).'</div>');
                $mpdf->WriteHTML($html);
                B::audit('pdf_downloaded',$kind,$id,['number'=>$record['number']]);
                $mpdf->Output($record['number'].'.pdf',\Mpdf\Output\Destination::DOWNLOAD);
            } else { B::audit('print_viewed',$kind,$id,['number'=>$record['number']]);echo $html; }
        } catch (\Throwable $e) {
            $code=(int)$e->getCode(); http_response_code(in_array($code,[401,403,404,422],true)?$code:500);
            if ($code===401) { redirect(''); }
            echo '<h1>'.e(__('Unable to open document')).'</h1><p>'.e(__(in_array($code,[401,403,404,422],true)?$e->getMessage():'Please contact the office.')).'</p>';
            error_log((string)$e);
        }
    }
    public static function words(int $number): string {
        if (\App\Services\Locale::current()==='my') return self::myanmarWords($number);
        if ($number===0) return 'Zero';
        $ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        $tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        if ($number<20) return $ones[$number];
        if ($number<100) return $tens[intdiv($number,10)].($number%10?' '.self::words($number%10):'');
        foreach ([1000000000=>'Billion',1000000=>'Million',1000=>'Thousand',100=>'Hundred'] as $base=>$name) if ($number>=$base) return self::words(intdiv($number,$base)).' '.$name.($number%$base?' '.self::words($number%$base):'');
        return '';
    }
    private static function myanmarWords(int $number): string {
        $digits=['သုည','တစ်','နှစ်','သုံး','လေး','ငါး','ခြောက်','ခုနစ်','ရှစ်','ကိုး'];
        if ($number<10) return $digits[$number];
        if ($number<100) return (intdiv($number,10)===1?'':$digits[intdiv($number,10)]).($number%10?'ဆယ့်'.$digits[$number%10]:'ဆယ်');
        foreach ([1000000000=>'ဘီလီယံ',1000000=>'သန်း',100000=>'သိန်း',10000=>'သောင်း',1000=>'ထောင်',100=>'ရာ'] as $base=>$unit) {
            if ($number>=$base) return self::myanmarWords(intdiv($number,$base)).$unit.($number%$base?self::myanmarWords($number%$base):'');
        }
        return '';
    }
}
