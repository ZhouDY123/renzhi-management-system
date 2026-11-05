<?php
$root=$argv[1]??'';if(!str_contains($root,'renzhi-practical-test-'))throw new RuntimeException('Disposable test root required');
require $root.'/app/bootstrap.php';require $root.'/app/practical.php';$pdo=db();practical_schema($pdo);
if(($argv[2]??'')==='close'){$pdo->exec("UPDATE interview_session SET status='canceled' WHERE id=1");exit;}
if(($argv[2]??'')==='inspect'){
 echo json_encode(['attempts'=>$pdo->query('SELECT id,session_id,candidate_id,submitted_at FROM practical_attempt')->fetchAll(),'files'=>$pdo->query('SELECT question_id,original_name,storage_key FROM practical_file')->fetchAll(),'fk'=>$pdo->query('PRAGMA foreign_key_check')->fetchAll()]);exit;
}
if(($argv[2]??'')==='second'){
 $pdo->exec("INSERT INTO interview_candidate(session_id,candidate_id,answer_id) VALUES(2,1,1)");exit;
}
$pdo->exec("INSERT INTO candidate(name,mobile) VALUES('另一应聘者','13800138001')");$cid=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO answer(candidate_id,post_id,candidate_snapshot,idempotency_key) VALUES(?,1,'{}','practical-test')")->execute([$cid]);$aid=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO interview_session(post_id,interview_date,time_range,location,qr_token,feedback_token) VALUES(1,date('now'),'09:00-10:00','第二会议室','practical-2','practical-feedback-2')");
$pdo->prepare('INSERT INTO interview_candidate(session_id,candidate_id,answer_id) VALUES(2,?,?)')->execute([$cid,$aid]);
practical_save_config($pdo,2,['另一场次的实操题目'],true);
echo "fixture ready\n";
