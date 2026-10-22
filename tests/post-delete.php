<?php
require __DIR__.'/../app/post_management.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec(file_get_contents(__DIR__.'/../migrations/001_init.sql'));
$p->exec('ALTER TABLE question_base ADD COLUMN post_id INTEGER;CREATE TABLE interview_pre_register(id INTEGER PRIMARY KEY,post_id INTEGER)');
$p->exec("INSERT INTO post(id,name) VALUES(1,'删除目标'),(2,'保留职位');INSERT INTO question_post(post_id,q_type,stem) VALUES(1,'text','题目'),(2,'text','保留题目');INSERT INTO question_base(post_id,group_type,q_type,stem) VALUES(1,'suzhi','text','专属题目'),(2,'suzhi','text','其他题目'),(NULL,'suzhi','text','公共题目');INSERT INTO question_set(id,post_id,version) VALUES(1,1,1),(2,2,1);INSERT INTO question_set_item(question_set_id,question_type,question_id) VALUES(1,'post',1),(2,'post',2)");
foreach(['candidate_pre_register','answer','interview_pre_register','interview_session'] as $table){
 if($table==='candidate_pre_register')$p->exec("INSERT INTO candidate_pre_register(post_id,name,mobile) VALUES(1,'测试','0')");
 if($table==='answer')$p->exec("INSERT INTO candidate(id,name,mobile) VALUES(1,'测试','0');INSERT INTO answer(candidate_id,post_id,candidate_snapshot,idempotency_key) VALUES(1,1,'{}','test')");
 if($table==='interview_pre_register')$p->exec('INSERT INTO interview_pre_register(post_id) VALUES(1)');
 if($table==='interview_session')$p->exec("INSERT INTO interview_session(post_id,interview_date,qr_token,feedback_token) VALUES(1,'2026-10-22','q','f')");
 try{delete_recruitment_post($p,1);throw new Exception('Related records not protected');}catch(RuntimeException $e){}
 if($p->query('SELECT COUNT(*) FROM question_post')->fetchColumn()!=2)throw new Exception('Rejected delete changed questions');
 $p->exec('DELETE FROM '.$table);
}
delete_recruitment_post($p,1);
foreach(['post','question_post','question_set','question_set_item'] as $table)if($p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!=1)throw new Exception('Wrong deletion scope');
if($p->query('SELECT COUNT(*) FROM question_base')->fetchColumn()!=2||$p->query('PRAGMA foreign_key_check')->fetchAll())throw new Exception('Question isolation or integrity failed');
try{delete_recruitment_post($p,1);throw new Exception('Repeated delete accepted');}catch(RuntimeException $e){}
echo "PASS: deletion, question cleanup, other/shared questions preserved, related records blocked, repeated deletion guarded\n";
