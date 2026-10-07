<?php
$root=$argv[1]??'';
if(!str_contains($root,'recruit-basic-post-test-'))throw new RuntimeException('Disposable root required');
require $root.'/app/bootstrap.php';
$pdo=db();
if(($argv[2]??'')==='inspect'){
    echo json_encode(['questions'=>$pdo->query("SELECT id,post_id,stem FROM question_base WHERE stem LIKE '测试岗位%' ORDER BY id")->fetchAll(),'papers'=>$pdo->query("SELECT s.post_id,i.stem_snapshot FROM question_set s JOIN question_set_item i ON i.question_set_id=s.id WHERE i.question_type='base' ORDER BY s.id,i.id")->fetchAll(),'fk'=>$pdo->query('PRAGMA foreign_key_check')->fetchAll()]);exit;
}
$posts=[];$professional=[];
foreach(['甲','乙','空'] as $name){
    $pdo->prepare("INSERT INTO post(name,company,q_apply_token) VALUES(?,'测试公司',?)")->execute(['测试岗位'.$name,'post-test-'.$name]);
    $id=(int)$pdo->lastInsertId();$posts[]=$id;
    $pdo->prepare("INSERT INTO question_post(post_id,q_type,stem,score) VALUES(?,'rating',?,3)")->execute([$id,'专业测试'.$name]);$professional[]=(int)$pdo->lastInsertId();
}
$conditions=[];
if(($argv[2]??'')==='seed-conditions'){
 require $root.'/app/import_conditions_20261007.php';require $root.'/app/condition_form.php';import_conditions_20261007($pdo);
 foreach(document_condition_groups($pdo) as $code=>$rows){usort($rows,fn($a,$b)=>$b['tier_value']<=>$a['tier_value']);$conditions[$code]=$rows[0]['tier_label'];}
}
echo json_encode(['posts'=>$posts,'professional'=>$professional,'conditions'=>$conditions]);
