<?php
require __DIR__.'/condition-bonus.php';
$code=array_key_first($groups);
save_condition_bonus($pdo,$code,['condition_bonus_present'=>1,'bonus_label'=>['必填加分项','选填加分项'],'bonus_score'=>[1,2],'bonus_question'=>['请说明相关经历','请补充说明'],'bonus_required'=>['1','0']]);
$snapshot=document_condition_groups($pdo);$ids=array_keys($snapshot[$code][0]['bonus_options']);
$answer=$low;unset($answer['condition_bonus']);
if(condition_bonus_text_responses($answer,$snapshot)!==[])throw new Exception('Unchecked follow-up required');
$answer['condition_bonus']=[$code=>[$ids[0]]];
foreach(['', '   ', ['bad'], str_repeat('a',8001)] as $value){$answer['condition_bonus_text']=[$code=>[$ids[0]=>$value]];try{condition_text_responses($answer,$snapshot);throw new Exception('Invalid required answer accepted');}catch(InvalidArgumentException $e){}}
$answer['condition_bonus_text']=[$code=>[$ids[0]=>'相关经历说明']];
$answers=condition_text_responses($answer,$snapshot);$key='bonus:'.$code.':'.$ids[0];
if($answers[$key]['value']!=='相关经历说明'||$answers[$key]['question']!=='请说明相关经历')throw new Exception('Response snapshot missing');
$scored=calculate_eval_score($answer,16,$snapshot);
$answer['condition_bonus_text'][$code][$ids[0]]='不同说明';if(calculate_eval_score($answer,17,$snapshot)!==$scored)throw new Exception('Text affected score');
save_condition_bonus($pdo,$code,['condition_bonus_present'=>1,'bonus_id'=>$ids,'bonus_label'=>['必填加分项','选填加分项'],'bonus_score'=>[1,2],'bonus_question'=>['改后的问题',''],'bonus_required'=>['0','1']]);
if(condition_text_responses($answer,$snapshot)[$key]['question']!=='请说明相关经历')throw new Exception('Historical question mutated');
$now=condition_bonus_config($pdo,$code);if($now[$ids[1]]['required'])throw new Exception('Empty question marked required');
$answer['condition_bonus']=[$code=>[$ids[1]]];unset($answer['condition_bonus_text']);if(count(condition_bonus_text_responses($answer,$snapshot))!==1)throw new Exception('Optional response missing');
$answer['condition_bonus']=[];$answer['condition_bonus_text']=[$code=>[$ids[0]=>'stale']];if(condition_bonus_text_responses($answer,$snapshot)!==[])throw new Exception('Unchecked stale response saved');
echo "PASS: bonus follow-up required/optional validation, ignored unchecked answers, immutable snapshots, no score impact\n";
