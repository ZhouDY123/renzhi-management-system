<?php
function order_condition_groups(PDO $pdo,array $groups): array {
    $st=$pdo->query("SELECT value FROM app_setting WHERE key='condition_dimension_order'");
    $order=json_decode((string)$st->fetchColumn(),true);$sorted=[];
    foreach(is_array($order)?$order:[] as $code)if(is_string($code)&&isset($groups[$code])){$sorted[$code]=$groups[$code];unset($groups[$code]);}
    return $sorted+$groups;
}
function save_condition_order(PDO $pdo,array $order,int $userId): void {
    $codes=$pdo->query("SELECT DISTINCT dim_code FROM scoring_standard WHERE category!='basic_quality' AND status!='retired'")->fetchAll(PDO::FETCH_COLUMN);
    if(array_filter($order,fn($code)=>!is_string($code))||count($order)!==count(array_unique($order))||count($codes)!==count($order)||array_diff($codes,$order)||array_diff($order,$codes))throw new InvalidArgumentException('维度已变化，请刷新页面后重新排序');
    $pdo->prepare("INSERT INTO app_setting(key,value,label,updated_by) VALUES('condition_dimension_order',?,'基本条件维度顺序',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_by=excluded.updated_by,updated_at=datetime('now','localtime')")->execute([json_encode(array_values($order),JSON_UNESCAPED_UNICODE),$userId]);
}
