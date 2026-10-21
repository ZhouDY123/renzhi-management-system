<?php
require __DIR__.'/document-conditions.php';
$before=document_condition_groups($pdo);$cats=condition_categories($pdo);$target=array_key_first($cats);$codes=[];foreach($before as $code=>$rows)if(condition_category($pdo,$rows[0])===$target)$codes[]=$code;
$wanted=array_reverse($codes);save_condition_order($pdo,$wanted,1,$target);$after=document_condition_groups($pdo);$actual=[];$otherBefore=[];$otherAfter=[];
foreach($before as $code=>$rows)if(condition_category($pdo,$rows[0])!==$target)$otherBefore[]=$code;
foreach($after as $code=>$rows){if(condition_category($pdo,$rows[0])===$target)$actual[]=$code;else $otherAfter[]=$code;}
if($actual!==$wanted||$otherBefore!==$otherAfter)throw new Exception('Scoped order or isolation failed');
try{save_condition_order($pdo,[...$wanted,$otherBefore[0]],1,$target);throw new Exception('Cross-category order accepted');}catch(InvalidArgumentException $e){}
if(array_keys(document_condition_groups($pdo))!==array_keys($after))throw new Exception('Rejected update changed order');
echo "PASS: category-scoped sorting, candidate category grouping, other categories unchanged, cross-category rejection\n";
