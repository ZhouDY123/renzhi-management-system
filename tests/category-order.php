<?php
require __DIR__.'/document-conditions.php';
$before=document_condition_groups($pdo);$wanted=array_reverse(array_keys($before));save_condition_order($pdo,$wanted,1);
if(array_keys(document_condition_groups($pdo))!==$wanted)throw new Exception('Global candidate order failed');
try{save_condition_order($pdo,array_slice($wanted,0,2),1,'education');throw new Exception('Legacy category sort accepted');}catch(InvalidArgumentException $e){}
try{save_condition_order($pdo,array_slice($wanted,1),1);throw new Exception('Partial order accepted');}catch(InvalidArgumentException $e){}
if(array_keys(document_condition_groups($pdo))!==$wanted)throw new Exception('Rejected request changed order');
echo "PASS: global order across former categories, stale category and partial requests rejected\n";
