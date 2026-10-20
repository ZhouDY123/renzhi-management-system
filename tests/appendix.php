<?php
require __DIR__.'/../app/appendix_admin.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("PRAGMA foreign_keys=ON; CREATE TABLE app_setting(key TEXT PRIMARY KEY,value TEXT,label TEXT); CREATE TABLE answer(id INTEGER PRIMARY KEY); CREATE TABLE result(answer_id INTEGER PRIMARY KEY,total_score REAL,review_status TEXT); INSERT INTO answer VALUES(1),(2),(3),(4); INSERT INTO result VALUES(1,73.5,'first_pass')");
appendix_schema($pdo);appendix_schema($pdo);
$qs=appendix_questions($pdo);check(count($qs)===8,'Eight source appendix questions imported once');
$original=$pdo->query('SELECT * FROM result')->fetchAll();
foreach($qs as $q)check(!in_array('不提供',json_decode($q['options_json'],true),true),'Removed option absent from seed');
$pdo->prepare('UPDATE appendix_question SET options_json=? WHERE id=?')->execute([json_encode(['是','否','不提供'],JSON_UNESCAPED_UNICODE),$qs[0]['id']]);
appendix_schema($pdo);$qs=appendix_questions($pdo);
check(!in_array('不提供',json_decode($qs[0]['options_json'],true),true),'Existing bank cleaned');

$a=appendix_attempt($pdo,1);check(count(json_decode($a['questions_json'],true))===8,'Snapshot');
$full=[];foreach($qs as $q){$options=json_decode($q['options_json'],true);$full[$q['id']]=['value'=>$options?$options[0]:'测试回答'];}
foreach([[],['appendix'=>[$qs[0]['id']=>['value'=>$full[$qs[0]['id']]['value']]]],['appendix'=>array_replace($full,[$qs[0]['id']=>['value'=>'   ']])]] as $input){
 try{appendix_complete($pdo,1,$qs,$input,false);throw new RuntimeException('Missing answer accepted');}catch(InvalidArgumentException $e){}
 check(appendix_attempt($pdo,1)['status']==='pending','Incomplete submission stays pending');
}
try{appendix_complete($pdo,1,$qs,['appendix'=>$full],true);throw new RuntimeException('Skip accepted');}catch(InvalidArgumentException $e){}
try{appendix_complete($pdo,1,$qs,['appendix'=>array_replace($full,[$qs[0]['id']=>['value'=>'伪造选项']])],false);throw new RuntimeException('Invalid option accepted');}catch(InvalidArgumentException $e){}
appendix_complete($pdo,1,$qs,['appendix'=>$full],false);
check(appendix_attempt($pdo,1)['status']==='submitted','Complete submission succeeds');
$saved=appendix_attempt($pdo,1)['responses_json'];
appendix_complete($pdo,1,$qs,['appendix'=>$full],false);
check(appendix_attempt($pdo,1)['responses_json']===$saved,'Replay unchanged');
appendix_attempt($pdo,2);appendix_complete($pdo,2,[],[],true);
check(appendix_attempt($pdo,2)['status']==='skipped','No questions can finish');
$old=appendix_attempt($pdo,4);
$id=appendix_save_question($pdo,['id'=>$qs[0]['id'],'title'=>'修改题干','section'=>'测试组','type'=>'select','options'=>"是\n否\n不提供",'sort'=>'99','enabled'=>'on']);
check((int)$pdo->query("SELECT enabled FROM appendix_question WHERE id=$id")->fetchColumn()===1,'Enabled state retained');
check(appendix_attempt($pdo,4)['questions_json']===$old['questions_json'],'Editing does not change open snapshots');
$pdo->exec('UPDATE appendix_question SET deleted=1,enabled=0');appendix_schema($pdo);
check(appendix_questions($pdo)===[],'Deleted questions not restored');
check($pdo->query('SELECT COUNT(*) FROM appendix_question')->fetchColumn()===8,'No reimport');
check($pdo->query('SELECT * FROM result')->fetchAll()===$original,'Scores and review states unchanged');
check($pdo->query('PRAGMA foreign_key_check')->fetchAll()===[],'Foreign keys valid');
echo "PASS: appendix import, required answers, rejected blank/partial/skip, validation, replay, snapshot, enabled state, deletion, score isolation\n";
