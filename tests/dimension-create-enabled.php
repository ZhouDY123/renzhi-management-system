<?php
require __DIR__.'/standard-save-status.php';
$start=strpos($source,"  if(\$action==='standard_dimension_create'){");$end=strpos($source,"  if(\$action==='standard_dimension_delete')",$start);$handler=substr($source,$start,$end-$start);
foreach(['answer','range'] as $type){
 $action='standard_dimension_create';$_POST=['dim_name'=>'新增测试'.$type,'standard_scope'=>'conditions','dimension_category'=>'education','dimension_type'=>$type,'answer_label'=>['是','否'],'range_min'=>[0,5],'range_max'=>[5,10],'tier_value'=>[2,0]];
 try{eval($handler);}catch(SavedRedirect $e){}
 $st=$pdo->prepare('SELECT status FROM scoring_standard WHERE dim_name=?');$st->execute([$_POST['dim_name']]);$states=$st->fetchAll(PDO::FETCH_COLUMN);
 if($states!==['published','published'])throw new Exception('New dimension not enabled');
}
echo "PASS: new answer/range dimensions are enabled by default\n";
