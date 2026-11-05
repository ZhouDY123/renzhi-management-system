<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require ROOT_PATH.'/app/template.php';
require ROOT_PATH.'/app/practical.php';
$pdo=db();practical_schema($pdo);
header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: same-origin');
$action=(string)($_GET['action']??'');$sid=(int)($_GET['session_id']??0);$error='';$notice='';
$ajax=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
$auth=$_SESSION['practical_auth']??null;if(!$auth||($auth['expires']??0)<time()){$auth=null;unset($_SESSION['practical_auth']);}
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(empty($_POST)&&!empty($_SERVER['CONTENT_LENGTH']))throw new InvalidArgumentException('上传内容超过服务器限制，请缩小文件后重试');
        check_csrf();
        if($action==='logout'){unset($_SESSION['practical_auth']);redirect('/practical.php');}
        if($action==='verify'){
            unset($_SESSION['practical_auth']);$auth=null;
            $name=trim((string)($_POST['name']??''));$mobile=trim((string)($_POST['mobile']??''));
            if(!rate_limit_check('practical_verify_ip',$_SERVER['REMOTE_ADDR']??'',30,300,300))throw new InvalidArgumentException('验证过于频繁，请稍后再试');
            if($name===''||practical_length($name)>80||!preg_match('/^1[3-9]\d{9}$/D',$mobile))throw new InvalidArgumentException('请输入正确的姓名和11位手机号码');
            $identity=$name.'|'.$mobile;
            if(!rate_limit_check('practical_verify_identity',$identity,8,300,300))throw new InvalidArgumentException('验证过于频繁，请稍后再试');
            $sessions=practical_sessions($pdo,$name,$mobile);
            if(!$sessions)throw new InvalidArgumentException('未找到已开放的实操安排，请核对姓名、手机号，或联系工作人员安排场次。');
            session_regenerate_id(true);$_SESSION['practical_auth']=['name'=>$name,'mobile'=>$mobile,'expires'=>time()+4*3600];
            rate_limit_clear('practical_verify_identity',$identity);
            redirect(count($sessions)===1?'/practical.php?session_id='.$sessions[0]['id']:'/practical.php');
        }
    }
    if($auth){
        $sessions=practical_sessions($pdo,$auth['name'],$auth['mobile']);
        if($sid){
            $selected=null;foreach($sessions as $s)if((int)$s['id']===$sid)$selected=$s;
            if(!$selected)throw new InvalidArgumentException('该场次未开放、已结束或未安排给您，请返回场次列表重新选择。');
            $attempt=practical_start($pdo,$sid,(int)$selected['candidate_id']);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                if((int)($_POST['attempt_id']??0)!==(int)$attempt['id'])throw new InvalidArgumentException('答题页面已变化，请刷新后重试');
                if($action==='upload'){
                    $qid=(int)($_POST['question_id']??0);
                    practical_upload($pdo,(int)$attempt['id'],(int)$selected['candidate_id'],$qid,$_FILES['result_file']??[],trim((string)($_POST['note']??'')));
                    if($ajax){$file=practical_files($pdo,(int)$attempt['id'])[$qid];header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>true,'name'=>$file['original_name'],'bytes'=>(int)$file['bytes'],'uploaded_at'=>$file['uploaded_at'],'url'=>'/practical.php?session_id='.$sid.'&action=download&question_id='.$qid],JSON_UNESCAPED_UNICODE);exit;}
                    redirect('/practical.php?session_id='.$sid.'&saved='.$qid.'#question-'.$qid);
                }
                if($action==='submit'){practical_submit($pdo,(int)$attempt['id'],(int)$selected['candidate_id']);redirect('/practical.php?session_id='.$sid);}
            }
            $files=practical_files($pdo,(int)$attempt['id']);
            if($action==='download'){$file=$files[(int)($_GET['question_id']??0)]??null;if(!$file){http_response_code(404);exit('未找到成果文件');}practical_download($file);}
            $questions=json_decode($attempt['questions'],true);
        }
    }elseif(in_array($action,['upload','submit','download'],true))throw new InvalidArgumentException('身份验证已过期，请重新输入姓名和手机号；已上传成果会保留。');
}catch(Throwable $e){
    $error=$e instanceof InvalidArgumentException?$e->getMessage():'暂时无法保存，请稍后重试或联系工作人员。';
    if(!($e instanceof InvalidArgumentException))error_log('Practical: '.$e->getMessage());
    if($ajax){http_response_code($auth?400:401);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>false,'error'=>$error],JSON_UNESCAPED_UNICODE);exit;}
    if(isset($attempt)){$files=practical_files($pdo,(int)$attempt['id']);$questions=json_decode($attempt['questions'],true);}
}
$limit=practical_upload_limit();$saved=(int)($_GET['saved']??0);
?>
<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>面试实操 · 任职管理</title><link rel="stylesheet" href="/assets/app.css?v=<?=asset_mtime('app.css')?>"><link rel="stylesheet" href="/assets/extra.css?v=<?=asset_mtime('extra.css')?>"><link rel="stylesheet" href="/assets/practical.css?v=<?=asset_mtime('practical.css')?>"><script src="/assets/practical.js?v=<?=asset_mtime('practical.js')?>" defer></script></head>
<body class="practical-body practical-public <?=$auth?'is-answer':'is-entry'?>"><main class="practical-main">
<?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if(!$auth):?>
<div class="practical-entry-layout"><section class="practical-welcome"><div class="practical-entry-brand"><img src="/assets/sanqi-3q-alpha.png" alt="三奇集团"><span>三奇集团 · 人才招聘</span></div><small>INTERVIEW PRACTICE</small><h1>让能力被看见<span>从一次实操开始。</span></h1><p>在真实任务中展示您的专业能力。<br>准备好后，验证身份即可开始。</p><ol class="practical-entry-guide"><li><span>01</span><div><b>验证身份</b><small>使用面试登记的姓名与手机号</small></div></li><li><span>02</span><div><b>完成实操</b><small>阅读任务要求，逐题准备成果文件</small></div></li><li><span>03</span><div><b>提交成果</b><small>确认文件无误后，完成全部提交</small></div></li></ol><div class="practical-entry-note">专注表达您的思考，让每一份成果都有价值。</div></section>
<form class="practical-verify" method="post" action="/practical.php?action=verify"><input type="hidden" name="csrf" value="<?=csrf()?>"><span class="practical-form-kicker">欢迎参加面试实操</span><h2>验证身份，开始作答</h2><p>请填写面试安排中登记的本人信息。</p><label>本人姓名<input name="name" autocomplete="name" maxlength="80" value="<?=e((string)($_POST['name']??''))?>" placeholder="请输入本人姓名" required></label><label>手机号码<input name="mobile" autocomplete="tel" inputmode="numeric" pattern="1[3-9]\d{9}" maxlength="11" value="<?=e((string)($_POST['mobile']??''))?>" placeholder="请输入11位手机号码" required></label><button class="btn primary">验证并进入实操</button><small>如无法进入，请核对登记信息或联系面试工作人员。</small></form></div>
<?php elseif(!isset($attempt,$questions)):?>
<div class="practical-page-title"><div><small>欢迎，<?=e($auth['name'])?></small><h1>选择实操场次</h1><p>仅显示已安排给您且开放实操的面试场次。</p></div><form method="post" action="/practical.php?action=logout"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn secondary">切换身份</button></form></div>
<div class="practical-session-list"><?php foreach($sessions??[] as $s):?><a class="practical-session" href="/practical.php?session_id=<?=(int)$s['id']?>"><div><h2><?=e($s['post_name'])?></h2><p><?=e($s['interview_date'].' '.$s['time_range'].' · '.$s['location'])?></p></div><b><?=$s['submitted_at']?'查看已提交成果':'进入实操 →'?></b></a><?php endforeach;?><?php if(empty($sessions)):?><div class="practical-notice">暂无可进入的实操场次，请联系工作人员。</div><?php endif;?></div>
<?php else:$done=!empty($attempt['submitted_at']);?>
<section class="practical-answer-hero practical-brand-hero">
<div class="practical-answer-brand"><img src="/assets/sanqi-practical-logo.png" alt="三奇集团"><span>人才招聘 · 面试实操</span></div>
<div class="practical-answer-heading"><div><span class="practical-form-kicker">INTERVIEW PRACTICE · <?=$done?'已完成':'进行中'?></span><h1><?=e($selected['post_name'])?> · 面试实操</h1><p><?=e($auth['name'])?> · <?=e(substr($auth['mobile'],0,3).' **** '.substr($auth['mobile'],-4))?> · <?=e($selected['interview_date'].' '.$selected['time_range'])?></p></div><form class="practical-return-login" method="post" action="/practical.php?action=logout"><input type="hidden" name="csrf" value="<?=csrf()?>"><button type="submit" class="btn secondary">返回登录页 <span aria-hidden="true">↗</span></button></form></div>
<div class="practical-answer-summary"><span>在真实任务中，展现您的专业能力。</span><b data-practical-hero-progress>共 <?=count($questions)?> 道任务 · 已保存 <?=count($files)?> 道成果</b></div>
</section>
<?php if($done):?><section class="practical-notice success"><h2>成果已提交</h2><p>提交时间：<?=e($attempt['submitted_at'])?>。您可以查看已上传成果，提交后不可修改。</p></section><?php else:?><div class="practical-notice practical-answer-guide"><b>作答指引</b><p>每道题单独上传一份成果文件。支持 PDF、Word、Excel、PPT、TXT、CSV、ZIP，常见图片及 MP4、MOV、WebM 等视频，单个文件最大 <?=round($limit/1048576,1)?> MB。多份材料请打包为 ZIP。选好每题文件后可直接点击“确认提交全部成果”，系统将自动上传并提交；也可逐题上传保存。</p></div><?php endif;?>
<?php foreach($questions as $i=>$q):$qid=(int)$q['id'];$file=$files[$qid]??null;?>
<section class="practical-question" id="question-<?=$qid?>" data-uploaded="<?=$file?'1':'0'?>"><div class="practical-question-head"><span class="practical-task-number"><i><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></i>实操任务</span><span class="practical-file-state <?=$file?'saved':''?>"><?=$file?'已上传成果':'待上传成果'?></span></div><h2><?=nl2br(e($q['stem']))?></h2>
<div class="practical-upload-result" aria-live="polite"><?php if($file):?><a href="/practical.php?session_id=<?=$sid?>&amp;action=download&amp;question_id=<?=$qid?>"><?=e($file['original_name'])?></a><small><?=e(practical_size((int)$file['bytes']))?> · <?=e($file['uploaded_at'])?></small><?php endif;?></div>
<?php if(!$done):?><form class="practical-upload-form" method="post" enctype="multipart/form-data" action="/practical.php?session_id=<?=$sid?>&amp;action=upload" data-limit="<?=$limit?>"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="attempt_id" value="<?=(int)$attempt['id']?>"><input type="hidden" name="question_id" value="<?=$qid?>"><label class="practical-file-picker">本题成果文件<input type="file" name="result_file" accept="<?=e('.'.implode(',.',practical_extensions()))?>" required><small><?=$file?'重新上传将替换本题原成果':'请选择完成本题后生成的成果文件'?></small></label><label>成果说明 <small>（选填，随文件一并保存）</small><textarea name="note" rows="2" maxlength="2000" placeholder="可补充成果内容或操作说明"><?=e($file['note']??'')?></textarea></label><div class="practical-upload-actions"><span role="status" class="practical-upload-status"><?=$saved===$qid?'本题成果已保存':''?></span><button type="submit" class="btn secondary">上传并保存本题成果</button></div></form><?php elseif(!empty($file['note'])):?><p class="practical-saved-note"><?=nl2br(e($file['note']))?></p><?php endif;?></section>
<?php endforeach;?>
<?php if(!$done):?><form class="practical-submit" method="post" action="/practical.php?session_id=<?=$sid?>&amp;action=submit"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="attempt_id" value="<?=(int)$attempt['id']?>"><div><b data-practical-progress>已上传 <?=count($files)?> / <?=count($questions)?> 道题</b><p data-submit-status role="status">可直接提交，系统会先上传各题选中的文件。提交后不可修改。</p></div><button class="btn primary">确认提交全部成果</button></form><?php endif;?>
<?php endif;?>
</main><footer class="practical-footer">三奇集团 · 以专业发现人才，以实干创造价值</footer></body></html>
