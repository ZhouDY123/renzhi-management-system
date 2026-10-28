<?php
require_once __DIR__.'/condition_form.php';
function calculate_eval_score(array $candidate, int $answerId, ?array $groups=null): float {
    $pdo=db();
    $groups=array_filter($groups??document_condition_groups($pdo),'condition_is_job_related');
    validate_document_conditions($candidate,$groups);
    $raw=0;$max=array_sum(condition_maxima($groups));$details=[];
    foreach($groups as $code=>$rules){
        $value=$candidate['conditions'][$code];$matched=null;
        foreach($rules as $rule)if($value===$rule['tier_label']){$matched=$rule;break;}
        $score=(float)$matched['tier_value'];$raw+=$score;
        $details[]=[$code,$rules[0]['dim_name'],$matched['tier_label'],$score];
    }
    $bonus=0;foreach(selected_condition_bonuses($candidate,$groups) as $item){$bonus+=(float)$item['score'];$details[]=[$item['code'].':'.$item['id'],$item['dimension'].'加分（折算后）',$item['label'],(float)$item['score']];}
    $pdo->prepare('DELETE FROM eval_score_detail WHERE answer_id=?')->execute([$answerId]);
    $ins=$pdo->prepare('INSERT INTO eval_score_detail(answer_id,dim_code,dim_name,matched_tier,score) VALUES(?,?,?,?,?)');
    foreach($details as $detail)$ins->execute([$answerId,...$detail]);
    return round(min(50,($max>0?$raw/$max*50:0)+$bonus),1);
}
function rule_matches(mixed $value,string $type,string $json): bool {
    $r=json_decode($json,true);if(!is_array($r))return false;
    return match($type){
        'eq'=>is_numeric($value)&&preg_match('/^(\d+(?:\.\d+)?)至(\d+(?:\.\d+)?)(?:岁|年)?.*$/u',(string)($r['value']??''),$m)?((float)$value>=(float)$m[1]&&(float)$value<(float)$m[2]):(string)$value===(string)($r['value']??''),
        'in'=>in_array((string)$value,array_map('strval',$r['values']??[]),true),
        'range'=>is_numeric($value)&&(!isset($r['min'])||(float)$value>=(float)$r['min'])&&(!isset($r['max'])||(float)$value<(float)$r['max']),
        'bool_range'=>is_array($value)&&(!isset($r['min'])||(float)($value['years']??0)>=(float)$r['min'])&&(!isset($r['max'])||(float)($value['years']??0)<(float)$r['max'])&&(!isset($r['is_mgmt'])||(int)($value['is_mgmt']??0)===(int)$r['is_mgmt']),
        default=>false
    };
}
function age_from_birth(string $birth): ?int { if(!$birth)return null;try{return (new DateTimeImmutable($birth))->diff(new DateTimeImmutable('today'))->y;}catch(Throwable){return null;} }
