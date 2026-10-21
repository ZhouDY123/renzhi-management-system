<?php
function order_condition_groups(PDO $pdo,array $groups): array {
    $st=$pdo->query("SELECT value FROM app_setting WHERE key='condition_dimension_order'");
    $order=json_decode((string)$st->fetchColumn(),true);$sorted=[];
    foreach(is_array($order)?$order:[] as $code)if(is_string($code)&&isset($groups[$code])){$sorted[$code]=$groups[$code];unset($groups[$code]);}
    $ordered=$sorted+$groups;
    if(!function_exists('condition_categories'))return $ordered;
    $grouped=[];
    foreach(condition_categories($pdo) as $category=>$label)foreach($ordered as $code=>$rules)if(condition_category($pdo,$rules[0])===$category)$grouped[$code]=$rules;
    return $grouped;
}
function save_condition_order(PDO $pdo,array $order,int $userId,?string $category=null): void {
    if($category!==null){
        if(!isset(condition_categories($pdo)[$category]))throw new InvalidArgumentException('分类已变化，请刷新页面');
        $rows=$pdo->query("SELECT dim_code,dim_name FROM scoring_standard WHERE category!='basic_quality' AND status!='retired' GROUP BY dim_code")->fetchAll(PDO::FETCH_ASSOC);
        $expected=[];foreach($rows as $row)if(condition_category($pdo,$row)===$category)$expected[]=$row['dim_code'];
        if(array_filter($order,fn($code)=>!is_string($code))||count($order)!==count(array_unique($order))||count($expected)!==count($order)||array_diff($expected,$order)||array_diff($order,$expected))throw new InvalidArgumentException('当前分类的维度已变化，请刷新后重新排序');
        $old=json_decode((string)$pdo->query("SELECT value FROM app_setting WHERE key='condition_dimension_order'")->fetchColumn(),true)?:[];
        $all=array_column($rows,'dim_code');$merged=[];
        foreach(array_merge($old,$all) as $code)if(in_array($code,$all,true)&&!in_array($code,$expected,true)&&!in_array($code,$merged,true))$merged[]=$code;
        $order=array_merge($merged,$order);
    }
    $codes=$pdo->query("SELECT DISTINCT dim_code FROM scoring_standard WHERE category!='basic_quality' AND status!='retired'")->fetchAll(PDO::FETCH_COLUMN);
    if(array_filter($order,fn($code)=>!is_string($code))||count($order)!==count(array_unique($order))||count($codes)!==count($order)||array_diff($codes,$order)||array_diff($order,$codes))throw new InvalidArgumentException('维度已变化，请刷新页面后重新排序');
    $pdo->prepare("INSERT INTO app_setting(key,value,label,updated_by) VALUES('condition_dimension_order',?,'基本条件维度顺序',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_by=excluded.updated_by,updated_at=datetime('now','localtime')")->execute([json_encode(array_values($order),JSON_UNESCAPED_UNICODE),$userId]);
}
