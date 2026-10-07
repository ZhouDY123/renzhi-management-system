<?php
function import_conditions_20261007(PDO $pdo): array {
    $rows=json_decode(file_get_contents(__DIR__.'/conditions-20261007.json'),true,512,JSON_THROW_ON_ERROR);
    $existing=(int)$pdo->query("SELECT COUNT(*) FROM scoring_standard WHERE dim_code LIKE 'custom_doc20261007_%'")->fetchColumn();
    if($existing)throw new RuntimeException('该批标准已导入，请勿重复执行');
    $pdo->beginTransaction();
    try{
        $version=(int)$pdo->query('SELECT COALESCE(MAX(version),0)+1 FROM scoring_standard')->fetchColumn();
        $pdo->exec("UPDATE scoring_standard SET status='retired' WHERE category!='basic_quality' AND status!='retired'");
        $insert=$pdo->prepare("INSERT INTO scoring_standard(category,dim_code,dim_name,tier_label,match_type,match_rule,tier_value,version,status,sort) VALUES('custom',?,?,?,'eq',?,?,?,'published',?)");
        foreach($rows as $i=>$row)$insert->execute([$row['code'],$row['dimension'],$row['label'],json_encode(['value'=>$row['label']],JSON_UNESCAPED_UNICODE),$row['score'],$version,$i+1]);
        $pdo->commit();return ['version'=>$version,'tiers'=>count($rows),'dimensions'=>count(array_unique(array_column($rows,'code')))];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
