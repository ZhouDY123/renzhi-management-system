<?php
require __DIR__.'/../app/appendix_detail.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE candidate(id INTEGER PRIMARY KEY,name TEXT);CREATE TABLE post(id INTEGER PRIMARY KEY,name TEXT);CREATE TABLE answer(id INTEGER PRIMARY KEY,candidate_id INTEGER,post_id INTEGER,submit_at TEXT);CREATE TABLE appendix_submission(answer_id INTEGER,status TEXT,responses_json TEXT,questions_json TEXT,completed_at TEXT);
INSERT INTO candidate VALUES(1,'同一测试人员');INSERT INTO post VALUES(1,'测试岗位');INSERT INTO answer VALUES(1,1,1,'2026-10-01'),(2,1,1,'2026-10-08'),(3,1,1,'2026-10-09'),(4,1,1,'2026-10-10'),(5,1,1,'2026-10-11')");
$snapshot=json_encode([['id'=>1,'section'=>'历史分组','title'=>'历史题目']]);
$st=$pdo->prepare('INSERT INTO appendix_submission VALUES(?,?,?,?,?)');
$st->execute([1,'submitted','{"1":{"value":"第一次回答","note":"补充说明"}}',$snapshot,'2026-10-01']);
$st->execute([2,'submitted','{}',$snapshot,'2026-10-08']);
$st->execute([3,'skipped','{}',$snapshot,'2026-10-09']);
$st->execute([4,'pending','{}',$snapshot,null]);
check(appendix_record_detail($pdo,1)['questions'][0]['value']==='第一次回答','Correct attempt');
check(appendix_record_detail($pdo,1)['questions'][0]['title']==='历史题目','Snapshot title');
check(appendix_record_detail($pdo,2)['questions'][0]['value']==='','No cross-attempt mixing');
foreach([1=>'已填写',2=>'未提供',3=>'已跳过',4=>'未提交',5=>'暂无附录'] as $id=>$label)check(appendix_record_detail($pdo,$id)['state']['label']===$label,'State '.$id);
check(appendix_record_detail($pdo,3)['questions']===[],'Skipped record empty');
check(appendix_record_detail($pdo,999)===null,'Not found');
echo "PASS: appendix details by assessment, snapshots, all five states, missing record\n";
