<?php
require __DIR__.'/document-conditions.php';
$code=$pdo->query("SELECT dim_code FROM scoring_standard WHERE dim_name='学历' AND status='published' LIMIT 1")->fetchColumn();
save_condition_bonus($pdo,$code,['condition_bonus_present'=>1,'bonus_label'=>['额外学历条件','额外资格'],'bonus_score'=>[3,2]]);
$groups=document_condition_groups($pdo);$options=$groups[$code][0]['bonus_options'];$ids=array_keys($options);
$data=$low;$base=calculate_eval_score($data,10,$groups);$data['condition_bonus']=[$code=>[$ids[0],$ids[1],$ids[0]]];$score=calculate_eval_score($data,11,$groups);
if(abs($score-($base+5))>0.11)throw new Exception('Checkbox bonus not added once');
$high['condition_bonus']=[$code=>$ids];if(calculate_eval_score($high,12,$groups)!==50.0)throw new Exception('50 point cap failed');
if($pdo->query("SELECT COUNT(*) FROM eval_score_detail WHERE answer_id=11 AND dim_name LIKE '%加分（折算后）'")->fetchColumn()!=2)throw new Exception('Bonus audit details missing');
save_condition_bonus($pdo,$code,['condition_bonus_present'=>1,'bonus_label'=>[]]);if(calculate_eval_score($data,13,$groups)!==$score)throw new Exception('Snapshot changed');
foreach([[$code=>['fake']],['unknown'=>[$ids[0]]],[$code=>[[$ids[0]]]]] as $bad){try{selected_condition_bonuses(['condition_bonus'=>$bad],$groups);throw new Exception('Invalid bonus accepted');}catch(InvalidArgumentException $e){}}
try{save_condition_bonus($pdo,$code,['condition_bonus_present'=>1,'bonus_label'=>['bad'],'bonus_score'=>[-1]]);throw new Exception('Negative bonus accepted');}catch(RuntimeException $e){}
echo "PASS: optional checkbox bonuses, duplicate deduplication, cap, immutable snapshot, audit details and tamper validation\n";
