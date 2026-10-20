<?php
require __DIR__.'/standard-save-status.php';
$pdo->exec("UPDATE scoring_standard SET match_type='bool_range',match_rule='{\"min\":0,\"max\":5,\"is_mgmt\":1}' WHERE id=1");
$pdo->beginTransaction();edit_condition_tiers($pdo,$code,['edit_tier_label'=>[1=>'五至十年'],'edit_tier_min'=>[1=>'5'],'edit_tier_max'=>[1=>'10']]);$pdo->commit();
$before=$pdo->query('SELECT * FROM scoring_standard ORDER BY id')->fetchAll();$rule=json_decode($before[0]['match_rule'],true);
if($rule['min']!=5||$rule['max']!=10||$rule['is_mgmt']!=1)throw new Exception('Range edit lost bounds or attributes');
foreach([['edit_tier_label'=>[1=>'']],['edit_tier_label'=>[1=>'坏区间'],'edit_tier_min'=>[1=>'10'],'edit_tier_max'=>[1=>'5']],['edit_tier_label'=>[1=>'硕士'],'edit_tier_min'=>[1=>'5'],'edit_tier_max'=>[1=>'10']]] as $input){
 $pdo->beginTransaction();try{edit_condition_tiers($pdo,$code,$input);throw new Exception('Invalid edit accepted');}catch(RuntimeException $e){$pdo->rollBack();}
 if($pdo->query('SELECT * FROM scoring_standard ORDER BY id')->fetchAll()!==$before)throw new Exception('Failed edit changed data');
}
echo "PASS: tier labels, followup association, ranges, preserved rule attributes and validation rollback\n";
