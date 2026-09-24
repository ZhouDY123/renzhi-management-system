<?php
// Only run against the disposable copy created by the integration test.
$root=$argv[1]??'';
if(!str_contains($root,'recruit-flow-test-'))throw new RuntimeException('Disposable test root required');
require $root.'/app/bootstrap.php';
$pdo=db();
if(in_array($argv[2]??'',['hire-second','unhire-second'],true)){
    $pdo->prepare('UPDATE result SET review_status=? WHERE id=2')->execute([$argv[2]==='hire-second'?'final_pass':'first_reject']);exit;
}
if(($argv[2]??'')==='answer-counts'){
    $counts=[];foreach(['answer','answer_detail','result','candidate_pre_register','interview_pre_register'] as $table)$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    echo json_encode($counts);exit;
}
if(($argv[2]??'')==='cooldown-expire'){
    $pdo->exec("UPDATE answer SET submit_at=datetime('now','localtime','-7 days')");
    exit;
}
if(($argv[2]??'')==='another-post'){
    echo json_encode(['token'=>$pdo->query("SELECT q_apply_token FROM post WHERE id<>1 AND status='recruiting' ORDER BY id LIMIT 1")->fetchColumn()]);exit;
}
if(($argv[2]??'')==='report-snapshot'){
    $before=$pdo->query('SELECT snapshot FROM interview_pass_report WHERE result_id=1')->fetchColumn();
    $pdo->exec('UPDATE interview_score SET total_score=1');
    echo json_encode(['snapshot'=>json_decode($before,true)]);exit;
}
if(($argv[2]??'')==='boundary'){
    $pdo->exec("UPDATE result SET total_score=60,review_status='first_pass' WHERE id=(SELECT MAX(id) FROM result)");exit;
}
if(($argv[2]??'')==='above-boundary'){
    $pdo->exec("UPDATE result SET total_score=60.1,review_status='first_pass' WHERE id=(SELECT MAX(id) FROM result)");exit;
}
if(($argv[2]??'')==='inspect'){
    echo json_encode(['results'=>$pdo->query('SELECT * FROM result')->fetchAll(),'assignments'=>$pdo->query('SELECT * FROM interview_candidate')->fetchAll(),'fk'=>$pdo->query('PRAGMA foreign_key_check')->fetchAll()]);exit;
}
foreach(['interview_candidate','session_interviewer','interview_session','result','answer','candidate','candidate_pre_register'] as $table){$pdo->exec('DELETE FROM '.$table);$pdo->prepare('DELETE FROM sqlite_sequence WHERE name=?')->execute([$table]);}
$pdo->exec("INSERT INTO question_set(post_id,version,status) VALUES(1,1,'published')");$set=(int)$pdo->lastInsertId();
$st=$pdo->prepare('INSERT INTO question_set_item(question_set_id,question_type,question_id,q_type,stem_snapshot,options_snapshot,answer_snapshot,score_snapshot) VALUES(?,?,?,?,?,?,?,?)');
$st->execute([$set,'base',1,'single','自动测试基本素质','["正确","错误"]','正确',3]);
$st->execute([$set,'post',1,'multi','自动测试专业题','["正确","错误"]','正确',3]);
$st->execute([$set,'post',2,'short','不应出现在自动测评的简答题','[]','答案',3]);
$pdo->prepare('INSERT INTO user(username,password_hash,real_name,role) VALUES(?,?,?,?)')->execute(['oldleader',password_hash('test12345',PASSWORD_DEFAULT),'旧领导','leader']);
echo json_encode(['token'=>$pdo->query('SELECT q_apply_token FROM post WHERE id=1')->fetchColumn()]);
