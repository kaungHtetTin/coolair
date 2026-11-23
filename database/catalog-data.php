<?php
declare(strict_types=1);

// Transcribed from the documents in _docs. See _docs/catalog-seed-notes.md.
$materialSource='photo_2026-11-22_15-47-07.jpg';
$materials=[
    1=>['Copper Pipe','1/4" x 3/8" pair; 0.71 mm thick; with accessories','Ft',25500],
    2=>['Copper Pipe','1/4" x 1/2" pair; 0.71 mm thick; with accessories','Ft',28500],
    3=>['Copper Pipe','1/4"; 0.71 mm thick; with accessories','Ft',12500],
    4=>['Copper Pipe','3/8"; 0.71 mm thick; with accessories','Ft',13000],
    5=>['Copper Pipe','1/2"; 0.71 mm thick; with accessories','Ft',25500],
    6=>['Copper Pipe','3/4"; 0.71 mm thick; with accessories','Ft',26500],
    7=>['Wire','1.5 mm; with accessories','Ft',1150],
    8=>['Wire','2.5 mm; with accessories','Ft',1350],
    9=>['Wire','4 mm; with accessories','Ft',1450],
    10=>['Safeguard','15 A; Digital','No',46000],
    11=>['Safeguard','25 A; Digital','No',65500],
    12=>['Safeguard','15 A; Metronic','No',75500],
    13=>['Safeguard','25 A; Metronic','No',79500],
    14=>['Frame','1 / 1.5 / 2 HP','Set',45000],
    15=>['Frame','2.5 / 3 / 4 HP','Set',54000],
    16=>['Frame','5 / 6 HP','Set',60000],
    17=>['Indoor Special Frame','','Set',75000],
    18=>['Trunking','3" x 2"','No',16500],
    19=>['Trunking','4" x 2"','No',19500],
    20=>['Wire Trunking','1"','No',10000],
    21=>['PVC Drain Pipe','1/2"; with accessories','Ft',1200],
    22=>['PVC Drain Pipe','3/4"; with accessories','Ft',1750],
    23=>['PVC Drain Pipe','1"; with accessories','Ft',3450],
    24=>['3 Pin Plug','','No',7500],
    // Rows 25 and 26 have unclear Myanmar descriptions and are intentionally omitted.
    27=>['Capacitor','25 / 30 µF','No',20000],
    28=>['Capacitor','35 / 40 µF','No',25000],
    29=>['Capacitor','45 / 50 / 60 µF','No',30000],
    30=>['Fan Capacitor','1.5 / 2 µF','No',8500],
    31=>['Fan Capacitor','2.5 / 3 µF','No',9500],
    32=>['Compressor CSR Wire','','No',22000],
    33=>['Room Sensor','','No',13000],
    34=>['Coil Sensor','','No',15000],
    35=>['Long Thread','3/8"','No',18000],
    36=>['Iron Wall Plug / Wall Sleeve','3/8"','No',2000],
    37=>['Bolt, Nut & Washer','','Set',750],
    38=>['Aero Stand','With iron wall plug','Set',48000],
    39=>['L-Key Valve','1/4"','No',18000],
    40=>['L-Key Valve','3/8"','No',22000],
    41=>['L-Key Valve','1/2"','No',25000],
    42=>['Compressor Overload','1 / 1.5 / 2 HP','No',12000],
    43=>['Remote Control + Battery','Multi / CHIGO / Mitsu','No',25000],
    44=>['Flare Nut','1/4"','No',5500],
    45=>['Flare Nut','3/8"','No',6500],
    46=>['Flare Nut','1/2"','No',8500],
    47=>['Refrigerator Relay','','No',15000],
    48=>['Refrigerator Overload','','No',15000],
];
$items=[];
foreach ($materials as $row=>[$name,$spec,$unit,$price]) {
    $items[]=['key'=>'material-'.str_pad((string)$row,2,'0',STR_PAD_LEFT),'kind'=>'material','name'=>$name,'specification'=>$spec,'unit'=>$unit,'price'=>$price,'category'=>'Materials & parts','source'=>$materialSource.' · row '.$row];
}
$services=[
    ['installation','Installation Charges',48000],
    ['servicing','Service Charges',45000],
    ['removal','Take Off Charges',35000],
    ['reinstallation','Take Off & Reinstallation Charges',60000],
];
foreach ($services as [$key,$name,$price]) {
    $items[]=['key'=>'service-'.$key,'kind'=>'service','name'=>$name,'specification'=>'Rate depends on equipment type, inverter classification, and horsepower. Default: non-inverter wall-mounted split, 1 HP.','unit'=>'Set','price'=>$price,'category'=>'Air-conditioning services','source'=>'photo_2026-11-22_15-46-53.jpg; photo_2026-11-22_15-47-03.jpg','note'=>'Equipment-specific rates are stored in service_rates.'];
}
foreach ([['check-test-repair','Check & Test and Repair Charges'],['refrigerator-repair','Refrigerator Repair Charges'],['washing-machine-repair','Washing Machine Repair Charges'],['air-water-cooler-repair','Air Cooler & Water Cooler Repair Charges']] as [$key,$name]) {
    $items[]=['key'=>'service-'.$key,'kind'=>'service','name'=>$name,'specification'=>'No fixed price is given in the source. Enter the quoted charge for the job.','unit'=>'Job','price'=>0,'category'=>'Repair services','source'=>'photo_2026-11-22_15-47-15.jpg · income list','note'=>'Description only; price is not supplied. Job is a practical billing unit, not a transcribed source unit.'];
}
foreach (['R-22'=>42000,'R-410a'=>45000,'R-32'=>48000] as $gas=>$price) {
    $items[]=['key'=>'gas-'.strtolower($gas),'kind'=>'gas','name'=>$gas.' Gas Refilling','specification'=>'Per pound (Lb)','unit'=>'Lb','price'=>$price,'category'=>'Refrigerant','source'=>'photo_2026-11-22_15-46-53.jpg; photo_2026-11-22_15-47-03.jpg; quotation PDF page 1'];
}
// English township names transcribe the three readable transportation destinations.
foreach ([['lewe','Lewe Township',100000],['zeyarthiri','Zeyarthiri Township',75000],['tatkon','Tatkon Township',100000]] as [$key,$destination,$price]) {
    $items[]=['key'=>'transport-'.$key,'kind'=>'transport','name'=>'Transportation Charges','specification'=>$destination.'; per trip','unit'=>'Trip','price'=>$price,'category'=>'Transportation','source'=>'photo_2026-11-22_15-46-53.jpg · transportation list'];
}
$pdf='No_3320,Air_Con_Installation_Quotation_Update_c0956d25_ec1b_4e31.pdf';
foreach ([['10','1',2636000],['13','1.5',3061000],['18','2',4815000],['24','2.5',5824000]] as [$model,$hp,$price]) {
    $items[]=['key'=>'equipment-mitsubishi-ms-js'.$model,'kind'=>'equipment','name'=>'MITSUBISHI ELECTRIC MS-JS'.$model.' / MU-JS'.$model,'specification'=>'Non-inverter; R-32 gas; '.$hp.' HP','unit'=>'Set','price'=>$price,'category'=>'Air conditioners','source'=>$pdf.' · page 5 equipment price list'];
}
$items[]=['key'=>'service-wall-hacking','kind'=>'service','name'=>'Wall Hacking Work','specification'=>'For PVC pipe','unit'=>'Ft','price'=>6500,'category'=>'Installation works','source'=>$pdf.' · page 2, Living Room line 8','note'=>'The printed rate is malformed as 65,00. Exact rate recovered from 195,000 MMK / 30 Ft = 6,500 MMK/Ft.'];
$items[]=['key'=>'material-flare-nut-pair','kind'=>'material','name'=>'Flare Nut Pair','specification'=>'1/4" x 3/8"','unit'=>'No','price'=>12000,'category'=>'Materials & parts','source'=>'photo_2026-11-22_15-47-11.jpg · invoice line 9'];

// Each matrix row contains HP followed by installation, service, removal, reinstallation.
$matrices=[
    'Non-inverter'=>[
        'Split (wall mounted)'=>[
            ['1',48000,45000,35000,60000],['1.5',53000,50000,35000,60000],['2',60000,57000,40000,70000],['2.5',65000,60000,40000,75000],['3',70000,65000,45000,80000],
        ],
        'Floor stand'=>[
            ['2',57000,55000,43000,80000],['3',60000,58000,45000,80000],['4',60000,58000,45000,87000],['5',65000,60000,48000,92000],
        ],
        'Ceiling cassette'=>[
            ['2',70000,68000,50000,100000],['3',73000,68000,50000,100000],['4',83000,73000,53000,115000],['5',85000,80000,55000,125000],['6',85000,80000,55000,125000],
        ],
        'Floor ceiling (suspended)'=>[
            ['2',70000,60000,53000,105000],['3',75000,60000,53000,105000],['4',80000,70000,55000,115000],['5',85000,75000,60000,125000],['6',85000,75000,60000,125000],
        ],
    ],
    'Inverter'=>[
        'Split (wall mounted)'=>[
            ['1',53000,50000,35000,65000],['1.5',55000,53000,35000,70000],['2',60000,57000,40000,80000],['2.5',65000,63000,45000,85000],['3',70000,68000,50000,90000],
        ],
        'Floor stand'=>[
            ['2',63000,60000,45000,85000],['3',65000,63000,48000,88000],['4',70000,65000,53000,95000],['5',75000,70000,58000,100000],
        ],
        'Ceiling cassette'=>[
            ['2',75000,70000,53000,105000],['3',80000,75000,58000,115000],['4',85000,83000,63000,120000],['5',90000,87000,73000,130000],['6',90000,87000,73000,130000],
        ],
        'Floor ceiling (suspended)'=>[
            ['2',65000,63000,55000,105000],['3',65000,63000,55000,105000],['4',75000,73000,60000,120000],['5',80000,87000,65000,130000],['6',80000,87000,65000,130000],
        ],
    ],
];
$rates=[];
foreach ($matrices as $technology=>$types) foreach ($types as $equipment=>$rows) foreach ($rows as $row) {
    foreach (array_keys($services) as $column) $rates[]=['service_key'=>'service-'.$services[$column][0],'equipment_type'=>$equipment,'technology'=>$technology,'hp'=>$row[0],'price'=>$row[$column+1]];
}
$incomeCategories=['Service Charges','Installation Charges','Take Off Charges','Take Off & Reinstallation Charges','Check & Test and Repair Charges','Refrigerator Repair Charges','Washing Machine Repair Charges','Air Cooler & Water Cooler Repair Charges','Other income'];
return ['items'=>$items,'rates'=>$rates,'income_categories'=>$incomeCategories];
