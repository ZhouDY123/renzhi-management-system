<?php
require __DIR__.'/../app/score_service.php';require __DIR__.'/../app/import_conditions_20261007.php';
function db(): PDO {global $pdo;return $pdo;}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(__DIR__.'/../migrations/001_init.sql'));
$pdo->exec('PRAGMA foreign_keys=OFF'); // Scoring unit test: answer IDs are synthetic.
$pdo->exec("INSERT INTO scoring_standard(category,dim_code,dim_name,tier_label,match_rule,tier_value,version,status) VALUES('education','edu','旧学历','旧本科','{}',4,1,'published')");
$result=import_conditions_20261007($pdo);if($result['tiers']!==71||$result['dimensions']!==12)throw new Exception('Wrong import');
$groups=document_condition_groups($pdo);$max=0;$high=[];$low=[];
foreach($groups as $code=>$rows){$max+=max(array_column($rows,'tier_value'));usort($rows,fn($a,$b)=>$a['tier_value']<=>$b['tier_value']);$low['conditions'][$code]=$rows[0]['tier_label'];$high['conditions'][$code]=end($rows)['tier_label'];}
if($max!=29)throw new Exception('Maximum must be 29');
validate_document_conditions($high,$groups);
if(calculate_eval_score($high,1)!=50)throw new Exception('Maximum score');
if(calculate_eval_score($low,2)!=round(2/29*50,1))throw new Exception('Minimum score');
foreach($groups as $code=>$rows)foreach($rows as $row){$single=$high;$single['conditions'][$code]=$row['tier_label'];$expected=round((29-max(array_column($rows,'tier_value'))+$row['tier_value'])/29*50,1);if(calculate_eval_score($single,3)!=$expected)throw new Exception('Tier mismatch');}
try{validate_document_conditions([],$groups);throw new Exception('Missing answers accepted');}catch(InvalidArgumentException $e){}
try{import_conditions_20261007($pdo);throw new Exception('Duplicate import accepted');}catch(RuntimeException $e){}
if($pdo->query("SELECT status FROM scoring_standard WHERE dim_code='edu'")->fetchColumn()!=='retired')throw new Exception('Old rules active');
echo "PASS: 12 dimensions, 71 tiers, 29-to-50 conversion, all tiers, required responses, duplicate guard, preserved old rules\n";
