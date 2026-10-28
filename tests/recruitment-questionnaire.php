<?php
require __DIR__.'/../app/recruitment_questionnaire.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE app_setting(key TEXT PRIMARY KEY,value TEXT,label TEXT,updated_by INTEGER,updated_at TEXT DEFAULT '')");
$defaults=recruitment_questionnaire_questions($pdo);
if(count($defaults)!==15)throw new Exception('Default questions lost');
$auth=[];$frozen=recruitment_questionnaire_for_attempt($pdo,$auth);
$version=recruitment_questionnaire_version($defaults);
save_recruitment_questionnaire($pdo,['新题目乙','新题目甲'],$version,1);
if(recruitment_questionnaire_questions($pdo)!==['新题目乙','新题目甲'])throw new Exception('Save/order failed');
if(recruitment_questionnaire_for_attempt($pdo,$auth)!==$frozen)throw new Exception('In-progress questionnaire changed');
$next=[];if(recruitment_questionnaire_for_attempt($pdo,$next)!==['新题目乙','新题目甲'])throw new Exception('New form did not update');
$legacy=[];if(recruitment_questionnaire_for_attempt($pdo,$legacy,true)!==$defaults)throw new Exception('Old open form mismatched');
try{save_recruitment_questionnaire($pdo,['stale'],$version,1);throw new Exception('Stale update accepted');}catch(InvalidArgumentException $e){}
foreach([[],[''],[['invalid']],array_fill(0,61,'题目'),[str_repeat('长',1001)]] as $invalid){
 try{save_recruitment_questionnaire($pdo,$invalid,recruitment_questionnaire_version(recruitment_questionnaire_questions($pdo)),1);throw new Exception('Invalid questions accepted');}catch(InvalidArgumentException $e){}
}
$current=recruitment_questionnaire_questions($pdo);
save_recruitment_questionnaire($pdo,['保留一道题'],recruitment_questionnaire_version($current),1);
if(recruitment_questionnaire_questions($pdo)!==['保留一道题'])throw new Exception('Delete failed');
require __DIR__.'/../app/recruitment_questionnaire_admin.php';function can(...$roles){return false;}
ob_start();render_recruitment_questionnaire_admin($pdo);$denied=ob_get_clean();
if(http_response_code()!==403||$denied!=='')throw new Exception('Unauthorized access');
echo "PASS: defaults, edit/add/delete/order, validation, stale-update protection, frozen forms, legacy forms and authorization\n";
