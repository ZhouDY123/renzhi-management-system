<?php
require __DIR__.'/document-conditions.php';
function e(?string $s): string {return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8');}
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$prior=$pdo->query('SELECT * FROM eval_score_detail WHERE answer_id=1')->fetchAll();
$add=$pdo->prepare("INSERT INTO scoring_standard(category,dim_code,dim_name,tier_label,match_type,match_rule,tier_value,version,status,sort) VALUES('custom',?,?,?,?,?,?,?,?,?)");
foreach([['未取得',0],['已取得',1]] as [$label,$score])$add->execute(['custom_training','岗位培训认证',$label,'eq',json_encode(['value'=>$label]),$score,1,'published',80]);
$add->execute(['custom_draft','未启用的岗位题','符合','eq','{}',9,10,'draft',90]);
$add->execute(['custom_retired','已归档的岗位题','符合','eq','{}',9,20,'retired',90]);
$add->execute(['custom_identity','是否是党员','是','eq','{"value":"是"}',1,30,'published',90]);
$add->execute(['custom_identity','是否是党员','否','eq','{"value":"否"}',0,30,'published',91]);
$groups=document_condition_groups($pdo);
check(count($groups)===13&&isset($groups['custom_training']),'New arbitrary prefix and independent version loaded');
foreach(['custom_identity','custom_draft','custom_retired'] as $excluded)check(!isset($groups[$excluded]),'Excluded dimension: '.$excluded);
check(array_sum(condition_maxima($groups))===30.0,'Actual maximum');
$input=$high;$input['conditions']['custom_training']='已取得';
check(calculate_eval_score($input,10)===50.0,'New enabled tier scored');
$input['conditions']['custom_training']='未取得';
check(calculate_eval_score($input,11)===round(29/30*50,1),'New low tier scored');
try{calculate_eval_score($high,12);throw new Exception('Missing new required answer accepted');}catch(InvalidArgumentException $e){}
ob_start();render_document_conditions($groups);$html=ob_get_clean();
check(str_contains($html,'原始满分30分'),'Maximum is dynamic');
check(str_contains($html,'conditions[custom_training]')&&!str_contains($html,'是否是党员'),'Rendered scope');
$pdo->exec("UPDATE scoring_standard SET status='draft' WHERE dim_code='custom_training'");
check(array_sum(condition_maxima(document_condition_groups($pdo)))===29.0,'Disable removes question and denominator');
$add->execute(['custom_numeric','设备维护年限','0至3','range','{"min":0,"max":3}',0,1,'published',95]);
$add->execute(['custom_numeric','设备维护年限','3至10','range','{"min":3,"max":10}',2,1,'published',96]);
$input=$high;$input['conditions']['custom_numeric']='3至10';
check(calculate_eval_score($input,13)===50.0,'Numeric range labels are selectable and scored');
$snapshot=condition_maxima(document_condition_groups($pdo));
$pdo->exec("UPDATE scoring_standard SET tier_value=4 WHERE dim_code='custom_numeric' AND tier_value=2");
check($snapshot['custom_numeric']===2.0&&condition_maxima(document_condition_groups($pdo))['custom_numeric']===4.0,'Independent historical maximum snapshot');
check($pdo->query('SELECT * FROM eval_score_detail WHERE answer_id=1')->fetchAll()===$prior,'Historical scores untouched');
$pdo->exec("UPDATE scoring_standard SET status='draft' WHERE category!='basic_quality'");
check(document_condition_groups($pdo)===[],'All disabled yields no scoring questions');
try{calculate_eval_score([],14);throw new Exception('Empty scoring configuration accepted');}catch(InvalidArgumentException $e){}
foreach([['custom_safety','职业健康安全培训证书'],['custom_labor','劳动仲裁业务能力']] as [$code,$name]){
    $add->execute([$code,$name,'已取得','eq','{"value":"已取得"}',2,1,'published',100]);
}
$groups=document_condition_groups($pdo);
check(count($groups)===2,'Job qualifications are not blocked by incidental keywords');
check(calculate_eval_score(['conditions'=>['custom_safety'=>'已取得','custom_labor'=>'已取得']],15)===50.0,'Job qualifications load and score consistently');
echo "PASS: dynamic dimensions, per-dimension versions, enable/disable, missing answers, explicit identity exclusion, no broad keyword blocking, range tiers, live maximum, historical isolation\n";
