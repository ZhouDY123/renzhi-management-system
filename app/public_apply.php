<?php
function assessment_already_hired(PDO $pdo, string $name, string $mobile): bool {
    $st=$pdo->prepare("SELECT 1 FROM candidate c JOIN answer a ON a.candidate_id=c.id JOIN result r ON r.answer_id=a.id WHERE TRIM(c.name)=? AND TRIM(c.mobile)=? AND r.review_status='final_pass' LIMIT 1");
    $st->execute([trim($name),trim($mobile)]);
    return (bool)$st->fetchColumn();
}
function show_assessment_hired(): never {
    unset($_SESSION['apply_auth']);
    header('Cache-Control: no-store');
    h5_header('测评提示');
    echo '<section class="h5-card"><h2>您已被录用，无需再次测评</h2><p>本次答题不会保存，也不会进入待安排面试。</p></section>';
    h5_footer();exit;
}
// A submitted assessment starts a rolling seven-day cooldown across all posts.
function assessment_next_allowed(PDO $pdo, string $name, string $mobile): ?string {
    $st=$pdo->prepare('SELECT MAX(a.submit_at) FROM answer a JOIN candidate c ON c.id=a.candidate_id WHERE TRIM(c.name)=? AND TRIM(c.mobile)=?');
    $st->execute([trim($name),trim($mobile)]);
    $last=$st->fetchColumn();
    if(!$last)return null;
    $submitted=strtotime((string)$last);
    if($submitted===false)return null;
    $next=$submitted+7*24*60*60;
    return time()<$next?date('Y-m-d H:i:s',$next):null;
}
function assessment_cooldown_message(string $next): string {
    return '您在最近 7 天内已完成测评，不能重复测评（含其他岗位）。可再次测评时间：'.$next.'。';
}
function show_assessment_cooldown(string $next): never {
    unset($_SESSION['apply_auth']);
    header('Cache-Control: no-store');
    h5_header('测评暂不可用');
    echo '<section class="h5-card"><h2>暂不能重复测评</h2><p>'.e(assessment_cooldown_message($next)).'</p></section>';
    h5_footer();exit;
}
// Public entry: registration rows are created by the application, not by HR.
if($m==='apply'&&$t==='unified'){
    $rows=$pdo->query("SELECT p.* FROM post p WHERE p.status='recruiting' AND EXISTS(SELECT 1 FROM question_set qs WHERE qs.post_id=p.id AND qs.status='published') ORDER BY p.id DESC")->fetchAll();
    h5_header('岗位测评'); ?>
    <div class="h5-title"><small>在线应聘</small><h1>请选择应聘岗位</h1><p>选择岗位并填写个人信息，即可开始测评。</p></div>
    <section class="h5-card candidates">
    <?php if(!$rows):?><div class="h5-empty"><b>暂无开放测评的岗位</b></div><?php endif;?>
    <?php foreach($rows as $row):?><a href="?m=apply&amp;t=<?=e($row['q_apply_token'])?>"><i>测</i><span><b><?=e($row['name'])?></b><small><?=e($row['company'])?></small></span><em>开始测评 ›</em></a><?php endforeach;?>
    </section><?php h5_footer();exit;
}
if($m==='apply'&&$_SERVER['REQUEST_METHOD']==='POST'&&$step==='verify'){
    check_csrf();
    $name=trim((string)($_POST['name']??''));$mobile=trim((string)($_POST['mobile']??''));
    $st=$pdo->prepare("SELECT id FROM post WHERE q_apply_token=? AND status='recruiting'");$st->execute([$t]);$postId=(int)$st->fetchColumn();
    if(!$postId||$name===''||!preg_match('/^1[3-9]\d{9}$/',$mobile)){$error='请填写本人姓名和正确的手机号码';$step='';}
    elseif(!rate_limit_check('public_apply',($_SERVER['REMOTE_ADDR']??'').':'.$mobile,10,300,300)){$error='操作过于频繁，请稍后再试';$step='';}
    elseif(assessment_already_hired($pdo,$name,$mobile)){show_assessment_hired();}
    elseif($next=assessment_next_allowed($pdo,$name,$mobile)){$error=assessment_cooldown_message($next);$step='';unset($_SESSION['apply_auth']);}
    else {
        $questions=load_questions($postId);
        if(!$questions){$error='该岗位暂无可自动评分的客观题，请联系 HR';$step='';}
        else {
            $st=$pdo->prepare('SELECT id,name FROM candidate WHERE mobile=?');$st->execute([$mobile]);$candidate=$st->fetch();
            if($candidate&&$candidate['name']!==$name){$error='姓名与该手机号已有信息不一致，请核对或联系 HR';$step='';}
            else {
                $pdo->beginTransaction();
                try {
                    if(!$candidate){$pdo->prepare('INSERT INTO candidate(name,mobile) VALUES(?,?)')->execute([$name,$mobile]);$candidate=['id'=>(int)$pdo->lastInsertId()];}
                    $pdo->prepare("INSERT INTO interview_pre_register(candidate_id,post_id,status) VALUES(?,?,'registered')")->execute([$candidate['id'],$postId]);$assessmentId=(int)$pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO candidate_pre_register(post_id,name,mobile,status) VALUES(?,?,?,'verified')")->execute([$postId,$name,$mobile]);$regId=(int)$pdo->lastInsertId();
                    $pdo->commit();
                    $_SESSION['apply_auth']=['reg_id'=>$regId,'post_id'=>$postId,'candidate_id'=>(int)$candidate['id'],'assessment_id'=>$assessmentId,'mobile'=>$mobile,'expires'=>time()+max(10,(int)setting('apply_timeout','30'))*60];
                    redirect('/h5.php?m=apply&t='.urlencode($t).'&step=form');
                }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            }
        }
    }
}
