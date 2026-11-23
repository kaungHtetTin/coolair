<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Business as B;
use Mini\Database as DB;
final class FileController
{
    private function access(string $kind,int $id): void {
        B::office();
        if ($kind==='payment') { B::payment($id); return; }
        if ($kind!=='ledger') B::fail('Invalid attachment type.');
        $row=DB::fetch('SELECT * FROM ledger WHERE id=?',[$id]); $u=B::user();
        if (!$row) B::fail('Entry not found.',404);
        if ($u['role']!=='office' && $row['created_by']!==$u['id']) B::fail('Access denied.',403);
    }
    public function upload(): void {
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        try {
            $kind=B::text($_POST,'kind',true); $id=(int)($_POST['id']??0); $this->access($kind,$id);
            $file=$_FILES['file']??null;
            if (!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) B::fail('Choose a PDF, PNG, or JPEG file up to 5 MB.');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions=['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg'];
            if (!isset($extensions[$mime])) B::fail('Only PDF, PNG, and JPEG attachments are accepted.');
            $dir=BASE_PATH.'/storage/uploads'; if (!is_dir($dir)) mkdir($dir,0770,true);
            $name=bin2hex(random_bytes(24)).'.'.$extensions[$mime];
            if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) B::fail('Upload failed.');
            $attachment=DB::insert('attachments',['kind'=>$kind,'record_id'=>$id,'filename'=>mb_substr(basename($file['name']),0,200),'stored_name'=>$name,'mime'=>$mime,'created_by'=>B::user()['id']]);
            B::audit('attached',$kind,$id,['attachment_id'=>$attachment,'filename'=>mb_substr(basename($file['name']),0,200),'mime'=>$mime]); echo json_encode(['id'=>$attachment]);
        } catch (\Throwable $e) { http_response_code(in_array($e->getCode(),[401,403,404,422],true)?$e->getCode():500); echo json_encode(\App\Services\Locale::error($e instanceof \RuntimeException?$e->getMessage():'Upload failed.')); }
    }
    public function download(string $id): void {
        try {
            B::office();
            $a=DB::fetch('SELECT * FROM attachments WHERE id=?',[(int)$id]); if (!$a) B::fail('File not found.',404);
            $this->access($a['kind'],$a['record_id']);
            header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff'); header('Content-Type: '.$a['mime']);
            header("Content-Disposition: attachment; filename=attachment.".pathinfo($a['stored_name'],PATHINFO_EXTENSION)."; filename*=UTF-8''".rawurlencode($a['filename']));
            B::audit('attachment_downloaded',$a['kind'],$a['record_id'],['attachment_id'=>$a['id'],'filename'=>$a['filename']]);
            readfile(BASE_PATH.'/storage/uploads/'.$a['stored_name']);
        } catch (\Throwable $e) { http_response_code(in_array($e->getCode(),[401,403,404,422],true)?$e->getCode():500); echo e(__('File unavailable.')); }
    }
}
