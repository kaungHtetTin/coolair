<?php
require dirname(__DIR__).'/vendor/autoload.php';
$parser=new \setasign\Fpdi\PdfParser\PdfParser(\setasign\Fpdi\PdfParser\StreamReader::createByFile($argv[1]));
$reader=new \setasign\Fpdi\PdfReader\PdfReader($parser);
$pages=$reader->getPageCount();
if ($pages<1 || $pages>(int)$argv[2]) { fwrite(STDERR,"Unexpected PDF page count: {$pages}\n");exit(1); }
echo "PDF verified: {$pages} page(s).\n";
