<?php
require __DIR__.'/document-conditions.php';
function e(?string $value): string {return htmlspecialchars($value??'',ENT_QUOTES,'UTF-8');}
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$code=array_key_first($groups);$old=$groups;$maxima=condition_maxima($groups);
save_condition_text($pdo,$code,'请说明相关经历',true);
$groups=document_condition_groups($pdo);
check($groups[$code][0]['text_question']['required']===true,'Required setting saved');
foreach(['', '   ', ['bad']] as $value){$data=$high;$data['condition_text'][$code]=$value;try{validate_document_conditions($data,$groups);throw new RuntimeException('Missing/invalid text accepted');}catch(InvalidArgumentException $e){}}
$data=$high;$data['condition_text'][$code]=' 项目经历 ';
validate_document_conditions($data,$groups);
$answers=condition_text_responses($data,$groups);
check($answers[$code]['value']==='项目经历'&&$answers[$code]['question']==='请说明相关经历','Answer and question snapshot');
check(calculate_eval_score($data,80,$groups)===50.0,'Text does not change score');
check(condition_maxima($groups)==$maxima,'Text does not change maxima');
ob_start();render_document_conditions($groups);$html=ob_get_clean();check(str_contains($html,'condition_text['.$code.']')&&str_contains($html,'不计分'),'Text question rendered');
save_condition_text($pdo,$code,'选填问题',false);$optional=document_condition_groups($pdo);validate_document_conditions($high,$optional);
check($groups[$code][0]['text_question']['question']==='请说明相关经历','Open attempt remains stable');
validate_document_conditions($high,$old);
save_condition_text($pdo,$code,'',true);check(condition_text_responses($high,document_condition_groups($pdo))===[],'Blank question disables text');
echo "PASS: text question config, required/optional validation, snapshot isolation, rendering and score isolation\n";
