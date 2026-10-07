<?php
// Exercise the production save handlers against a disposable in-memory database.
class SavedRedirect extends Exception {}
function audit(...$args): void {}
function flash(...$args): void {}
function redirect($url): never {throw new SavedRedirect($url);}
function standard_dimension_meta(): array {return ['edu'=>['category'=>'education','name'=>'学历']];}
$source=str_replace("\r\n","\n",file_get_contents(__DIR__.'/../public/index.php'));
$start=strpos($source,'  // Saving tiers must preserve');
$end=strpos($source,"  if(\$action==='standard_custom_group_save'){",$start);
$prelude=substr($source,$start,$end-$start);
foreach(['standard_group_save'=>'edu','standard_custom_group_save'=>'custom_test'] as $action=>$code){
 $start=strpos($source,"  if(\$action==='".$action."'){");$end=strpos($source,"\n  }",$start)+4;$handler=substr($source,$start,$end-$start);
 foreach(['published','draft'] as $status){
  $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $pdo->exec('CREATE TABLE scoring_standard(id INTEGER PRIMARY KEY,category TEXT,dim_code TEXT,dim_name TEXT,tier_label TEXT,match_type TEXT,match_rule TEXT,tier_value REAL,version INTEGER,status TEXT,sort INTEGER)');
  $pdo->prepare("INSERT INTO scoring_standard VALUES(1,'education',?,'学历','本科','eq','{}',2,3,?,1)")->execute([$code,$status]);
  $_POST=['dim_code'=>$code,'rule_id'=>[1],'tier_value'=>[2.5],'new_label'=>['硕士'],'new_value'=>[3]];
  try{eval($prelude.$handler);}catch(SavedRedirect $e){}
  $rows=$pdo->query('SELECT tier_value,status,version FROM scoring_standard ORDER BY id')->fetchAll();
  if(count($rows)!==2||$rows[0]['tier_value']!=2.5||$rows[1]['tier_value']!=3)throw new Exception('Scores not saved');
  foreach($rows as $row)if($row['status']!==$status||$row['version']!==3)throw new Exception('State/version changed');
  $_POST=['dim_code'=>$code,'rule_id'=>[999],'tier_value'=>[4]];
  try{eval($prelude.$handler);throw new Exception('Invalid ID accepted');}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();}
  if($rows!==$pdo->query('SELECT tier_value,status,version FROM scoring_standard ORDER BY id')->fetchAll())throw new Exception('Failed save changed state');
 }
}
echo "PASS: built-in/custom save, enabled/disabled state, added tiers, version inheritance and failed-save rollback\n";
