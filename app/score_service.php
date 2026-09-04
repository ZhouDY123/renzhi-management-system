<?php
function calculate_eval_score(array $candidate, int $answerId): float {
    $pdo=db();$version=(int)$pdo->query("SELECT COALESCE(MAX(version),1) FROM scoring_standard WHERE status='published'")->fetchColumn();
    $st=$pdo->prepare("SELECT * FROM scoring_standard WHERE version=? AND status='published' ORDER BY dim_code,sort,id");$st->execute([$version]);$tiers=$st->fetchAll();$by=[];foreach($tiers as $t)$by[$t['dim_code']][]=$t;
    $values=['age'=>age_from_birth($candidate['birth_date']??''),'health'=>$candidate['health']??'','edu'=>$candidate['edu']??'','school'=>$candidate['school_tier']??'','major'=>$candidate['major']??'','title'=>$candidate['title']??'','politics'=>$candidate['politics']??'','work_years'=>(int)($candidate['work_years']??0),'prof_years'=>(int)($candidate['prof_years']??0),'group_co'=>['years'=>(int)($candidate['group_co_years']??0),'is_mgmt'=>isset($candidate['group_co_mgmt'])?1:0],'listed_co'=>['years'=>(int)($candidate['listed_co_years']??0),'is_mgmt'=>isset($candidate['listed_co_mgmt'])?1:0],'private_co'=>['years'=>(int)($candidate['private_co_years']??0),'is_mgmt'=>isset($candidate['private_co_mgmt'])?1:0],'work_bg'=>$candidate['work_bg']??'','computer'=>$candidate['computer_skill']??'','language'=>$candidate['language']??''];
    $raw=0;$max=0;$details=[];foreach($by as $code=>$rules){$dimMax=max(array_map(fn($r)=>(float)$r['tier_value'],$rules));$max+=$dimMax;$matched=null;foreach($rules as $rule){if(rule_matches($values[$code]??null,$rule['match_type'],$rule['match_rule'])){$matched=$rule;break;}}$score=$matched?(float)$matched['tier_value']:0;$raw+=$score;$details[]=[$code,$rules[0]['dim_name'],$matched['tier_label']??'未匹配',$score];}
    $pdo->prepare('DELETE FROM eval_score_detail WHERE answer_id=?')->execute([$answerId]);$ins=$pdo->prepare('INSERT INTO eval_score_detail(answer_id,dim_code,dim_name,matched_tier,score) VALUES(?,?,?,?,?)');foreach($details as $d)$ins->execute([$answerId,...$d]);return $max>0?round(min(50,$raw/$max*50),1):0;
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
