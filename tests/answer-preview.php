<?php
require __DIR__.'/../app/answer_preview.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function admin_header(...$args){}function admin_footer(){}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(__DIR__.'/../migrations/001_init.sql'));appendix_schema($pdo);
$pdo->exec("INSERT INTO candidate(id,name,mobile) VALUES(1,'当前姓名','13800000000');INSERT INTO post(id,name,company,series,duty) VALUES(1,'当前岗位','公司','系列','职责')");
$snap=['name'=>'交卷姓名','mobile'=>'13900000000','registration_profile'=>['gender'=>'女','age'=>25,'edu'=>'本科','major'=>'专业快照','school_name'=>'学校快照'],'conditions'=>['edu'=>'本科'],'condition_questions'=>['edu'=>[['dim_name'=>'学历快照','tier_label'=>'本科','bonus_options'=>['b1'=>['label'=>'额外条件','score'=>1]]],['dim_name'=>'学历快照','tier_label'=>'硕士']]],'condition_bonus'=>['edu'=>['b1']],'condition_text_responses'=>['edu'=>['dimension'=>'学历','question'=>'追问快照','value'=>'<script>危险内容</script>','required'=>true]]];
$pdo->prepare('INSERT INTO answer(id,candidate_id,post_id,candidate_snapshot,post_snapshot,idempotency_key) VALUES(1,1,1,?,?,?)')->execute([json_encode($snap,JSON_UNESCAPED_UNICODE),json_encode(['name'=>'交卷岗位']), 'test']);
$pdo->exec("INSERT INTO answer_detail(answer_id,question_type,question_id,answer_val,stem_snapshot,options_snapshot) VALUES(1,'base',1,'比较符合','素质题快照','[\"基本符合\",\"比较符合\"]'),(1,'post',2,'[\"甲\",\"乙\"]','专业题快照','[\"甲\",\"乙\"]')");
$q=[['id'=>1,'section'=>'家庭关系','title'=>'附录题快照','options_json'=>'[]']];$responses=[1=>['value'=>'附录回答快照','note'=>'补充说明快照']];
$pdo->prepare("INSERT INTO appendix_submission(answer_id,questions_json,responses_json,status) VALUES(1,?,?,'submitted')")->execute([json_encode($q),json_encode($responses)]);
$d=assessment_answer_detail($pdo,1);if($d['profile']['name']!=='交卷姓名'||$d['post']!=='交卷岗位')throw new Exception('Live data replaced snapshot');
ob_start();render_assessment_answer_preview($d);$html=ob_get_clean();
foreach(['交卷姓名','专业快照','学校快照','学历快照','硕士','额外条件','追问快照','素质题快照','专业题快照','附录题快照','附录回答快照','补充说明快照','&lt;script&gt;'] as $text)if(!str_contains($html,$text))throw new Exception('Missing '.$text);
if(strpos($html,'追问快照')>strpos($html,'补充条件：'))throw new Exception('Follow-up not directly below parent');
if(substr_count($html,'>本科</p>')!==0)throw new Exception('Selected answer repeated');
if(str_contains($html,'<script>危险内容'))throw new Exception('Unescaped answer');
if(assessment_answer_detail($pdo,999)!==null)throw new Exception('Missing ID accepted');
$pdo->exec("UPDATE answer SET candidate_snapshot='{}';INSERT INTO eval_score_detail(answer_id,dim_code,dim_name,matched_tier,score) VALUES(1,'edu','历史学历','本科',2)");
ob_start();render_assessment_answer_preview(assessment_answer_detail($pdo,1));$old=ob_get_clean();if(!str_contains($old,'历史学历')||!str_contains($old,'历史记录未保存完整选项'))throw new Exception('Legacy fallback failed');
require __DIR__.'/../app/workflow_pages.php';function can(...$roles){return false;}
$_GET=['answer_id'=>1];ob_start();render_talent_workflow('preregister',$pdo);$denied=ob_get_clean();if(http_response_code()!==403||$denied!=='')throw new Exception('Unauthorized access');
echo "PASS: complete answer preview, submission snapshots, legacy fallback, missing IDs, XSS escaping and role restriction\n";
