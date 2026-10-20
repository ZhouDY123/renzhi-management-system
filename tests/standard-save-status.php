<?php
require_once __DIR__.'/../app/condition_form.php';
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
  $pdo->exec('CREATE TABLE app_setting(key TEXT PRIMARY KEY,value TEXT,label TEXT)');
  $pdo->prepare("INSERT INTO scoring_standard VALUES(1,'education',?,'学历','本科','eq','{}',2,3,?,1)")->execute([$code,$status]);
  $_POST=['dim_code'=>$code,'rule_id'=>[1],'tier_value'=>[2.5],'new_label'=>['硕士'],'new_value'=>[3],'edit_tier_label'=>[1=>'本科及以上'],'tier_order'=>['new:0','existing:1'],'dimension_name'=>'学历要求','condition_text_present'=>1,'condition_text_question'=>[1=>'本科专业说明'],'condition_text_required'=>[1=>1],'new_text_question'=>['研究方向'],'new_text_required'=>[0=>1]];
  try{eval($prelude.$handler);}catch(SavedRedirect $e){}
  $rows=$pdo->query('SELECT tier_value,status,version FROM scoring_standard ORDER BY id')->fetchAll();
  if(count($rows)!==2||$rows[0]['tier_value']!=2.5||$rows[1]['tier_value']!=3)throw new Exception('Scores not saved');
  foreach($rows as $row)if($row['status']!==$status||$row['version']!==3)throw new Exception('State/version changed');
  if($pdo->query("SELECT COUNT(*) FROM scoring_standard WHERE dim_name='学历要求'")->fetchColumn()!=2)throw new Exception('Rename did not cover existing and new tiers');
  if($pdo->query('SELECT id FROM scoring_standard ORDER BY sort,id')->fetchAll(PDO::FETCH_COLUMN)!==[2,1])throw new Exception('Mixed new/existing tier order not saved');
  $config=condition_text_config($pdo,$code);if(($config['tiers']['本科及以上']['question']??'')!=='本科专业说明'||($config['tiers']['硕士']['question']??'')!=='研究方向'||empty($config['tiers']['硕士']['required']))throw new Exception('Combined follow-up save failed');
  $_POST=['dim_code'=>$code,'rule_id'=>[999],'tier_value'=>[4]];
  try{eval($prelude.$handler);throw new Exception('Invalid ID accepted');}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();}
  if($rows!==$pdo->query('SELECT tier_value,status,version FROM scoring_standard ORDER BY id')->fetchAll())throw new Exception('Failed save changed state');
 }
}
echo "PASS: built-in/custom save, enabled/disabled state, added tiers, version inheritance and failed-save rollback\n";
