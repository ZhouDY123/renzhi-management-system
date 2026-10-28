<?php
$requestedReport=($_GET['view']??'')==='report';
ob_start();require __DIR__.'/answer-preview.php';ob_end_clean();require_once __DIR__.'/../app/final_review.php';
function csrf(){return 'fixture';}function review_label($v){return $v;}
$pdo->exec("INSERT INTO user(id,username,password_hash,real_name,role) VALUES(1,'one','x','面试官甲','interviewer'),(2,'two','x','面试官乙','interviewer'),(3,'three','x','未提交面试官','interviewer');
INSERT INTO candidate(id,name,mobile) VALUES(2,'其他应聘人','13800000002');
INSERT INTO answer(id,candidate_id,post_id,candidate_snapshot,idempotency_key) VALUES(2,2,1,'{}','other');
INSERT INTO interview_session(id,post_id,interview_date,time_range,location,status,qr_token,feedback_token) VALUES(1,1,'2026-10-28','10:00','第一会议室','done','q1','f1'),(2,1,'2026-10-27','10:00','第二会议室','done','q2','f2');
INSERT INTO interview_candidate(id,session_id,candidate_id,answer_id) VALUES(1,1,1,1),(2,2,2,2);
INSERT INTO session_interviewer(id,session_id,user_id,weight) VALUES(1,1,1,2),(2,1,2,1),(3,1,3,1),(4,2,1,1);
INSERT INTO interview_score(session_id,interviewer_id,interview_candidate_id,suzhi_score,postq_score,total_score,detail_json,strengths,risks,development,recommendation,salary_range,available_date) VALUES(1,1,1,80,80,4,'{\"professional\":4,\"experience\":4,\"communication\":4,\"logic\":4,\"teamwork\":4,\"learning\":4,\"professionalism\":4}','<script>评价转义</script>','经验需核实','安排带教','recommend','8K–10K','2026-11-01'),(1,2,1,60,60,NULL,NULL,'历史优势','历史风险','发展建议','pending','','');");
$pdo->prepare('INSERT INTO interview_feedback(session_id,candidate_id,responses,overall_score,arrangement_score,interviewer_score) VALUES(?,?,?,0,0,0)')->execute([1,1,json_encode(['questions'=>['提交时问卷原题'],'answers'=>['<b>问卷原答</b>']],JSON_UNESCAPED_UNICODE)]);
$pdo->prepare('INSERT INTO interview_feedback(session_id,candidate_id,responses,overall_score,arrangement_score,interviewer_score) VALUES(?,?,?,0,0,0)')->execute([2,2,json_encode(['questions'=>['不应显示的其他问卷'],'answers'=>['other']])]);
$sessions=final_review_interviews($pdo,1);
if(count($sessions)!==1||$sessions[0]['submitted']!==2||count($sessions[0]['reviewers'])!==3||abs($sessions[0]['average']-73.33)>.01)throw new Exception('Scope, completion or weighted score incorrect');
$pdo->prepare('UPDATE answer SET candidate_snapshot=? WHERE id=1')->execute([json_encode($snap)]);
$selected=['id'=>1,'answer_id'=>1,'name'=>'示例候选人','post_name'=>'GEO建设专员 · 示例公司','mobile'=>'13800000000','edu'=>'本科','work_years'=>1,'total_score'=>80.2,'eval_score'=>30.2,'suzhi_score'=>25,'postq_score'=>25,'interview_done'=>1];
$_GET['view']='report';
ob_start();render_final_review($pdo,$selected,[$selected],[],'',0);$html=ob_get_clean();
foreach(['提交时问卷原题','&lt;b&gt;问卷原答&lt;/b&gt;','&lt;script&gt;评价转义&lt;/script&gt;','未提交','73.33','历史优势','未记录','追问快照','专业题快照','附录回答快照','final-decision','打印评审报告'] as $text)if(!str_contains($html,$text))throw new Exception('Missing '.$text);
if(str_contains($html,'不应显示的其他问卷')||str_contains($html,'<script>评价转义'))throw new Exception('Data isolation or escaping failed');
unset($_GET['view']);
ob_start();render_final_review($pdo,$selected,[$selected],[],'筛选词',7);$workspace=ob_get_clean();
foreach(['data-final-review-workspace','final-overview','view=report','target="_blank"','final-review.js','post_id=7','id=1'] as $text)if(!str_contains($workspace,$text))throw new Exception('Workspace missing '.$text);
if(str_contains($workspace,'onclick="window.print()"')||str_contains($html,'data-final-review-workspace'))throw new Exception('Mixed report/workspace modes');
if(defined('FINAL_REVIEW_VISUAL')&&!$requestedReport)$html=$workspace;
foreach([null,0,'0','',5] as $years){
 $sample=$selected;$sample['work_years']=$years;
 foreach(['','report'] as $view){$_GET['view']=$view;ob_start();render_final_review($pdo,$sample,[$sample],[],'',0);$output=ob_get_clean();
 if(str_contains($output,' 年经验')!==($years===5))throw new Exception('Unsourced experience visible or recorded years missing');
 }
}
$selected['interview_done']=0;ob_start();render_final_review($pdo,$selected,[$selected],[],'',0);$pending=ob_get_clean();if(str_contains($pending,'name="op"'))throw new Exception('Premature decision controls');
if(!defined('FINAL_REVIEW_VISUAL'))echo "PASS: interview isolation, weighted/legacy scores, missing submissions, questionnaire snapshots, escaped content, complete answers and decision gating\n";
