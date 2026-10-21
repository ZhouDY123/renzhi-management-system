<?php
require __DIR__.'/../app/condition_form.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE app_setting(key TEXT PRIMARY KEY,value TEXT,label TEXT);CREATE TABLE scoring_standard(dim_code TEXT,dim_name TEXT,category TEXT,status TEXT)');
$pdo->exec("INSERT INTO scoring_standard VALUES('edu','学历','education','published'),('years','工作年限','work','published'),('ai','AI能力','skills','published')");
function assert_ok($ok){if(!$ok)throw new Exception('Assertion failed');}
function rejected($pdo,$op,$input){$before=condition_categories($pdo);try{maintain_condition_category($pdo,$op,$input);throw new Exception('Invalid operation accepted');}catch(RuntimeException $e){}assert_ok(condition_categories($pdo)===$before);assert_ok(!$pdo->inTransaction());}
maintain_condition_category($pdo,'create',['category_name'=>'职业资格']);$cats=condition_categories($pdo);$new=array_key_last($cats);assert_ok(count($cats)===4);
maintain_condition_category($pdo,'edit',['category_id'=>'education','category_name'=>'学习经历']);assert_ok(condition_categories($pdo)['education']==='学习经历');assert_ok(condition_category($pdo,['dim_code'=>'edu','dim_name'=>'学历'])==='education');
rejected($pdo,'create',['category_name'=>'学习经历']);rejected($pdo,'create',['category_name'=>'  ']);rejected($pdo,'edit',['category_id'=>$new,'category_name'=>str_repeat('字',21)]);
rejected($pdo,'delete',['category_id'=>'education']);
maintain_condition_category($pdo,'delete',['category_id'=>'education','target_category'=>$new]);assert_ok(!isset(condition_categories($pdo)['education']));assert_ok(condition_category($pdo,['dim_code'=>'edu','dim_name'=>'学历'])===$new);assert_ok(condition_category($pdo,['dim_code'=>'years','dim_name'=>'工作年限'])==='work');
assert_ok($pdo->query("SELECT category FROM scoring_standard WHERE dim_code='edu'")->fetchColumn()==='education');
maintain_condition_category($pdo,'delete',['category_id'=>'skills','target_category'=>'work']);maintain_condition_category($pdo,'delete',['category_id'=>$new,'target_category'=>'work']);rejected($pdo,'delete',['category_id'=>'work']);
echo "PASS: create, rename, duplicates, length, delete transfer, atomic rollback, scoring isolation and last category guard\n";
