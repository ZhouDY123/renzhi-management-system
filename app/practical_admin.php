<?php
require_once __DIR__.'/practical.php';
function render_practical_admin(PDO $pdo): never {
    if(!can('admin','hr')){http_response_code(403);exit('无此操作权限');}
    practical_schema($pdo);$sid=(int)($_GET['session_id']??0);$error='';
    $st=$pdo->prepare('SELECT s.*,p.name post_name FROM interview_session s JOIN post p ON p.id=s.post_id WHERE s.id=?');$st->execute([$sid]);$session=$st->fetch();
    if(!$session){http_response_code(404);exit('请从面试管理选择已有场次');}
    $st=$pdo->prepare('SELECT COUNT(*) FROM practical_attempt WHERE session_id=?');$st->execute([$sid]);$locked=(int)$st->fetchColumn()>0;
    $closed=in_array($session['status'],['done','canceled'],true);
    if(($_GET['action']??'')==='download'){
        $st=$pdo->prepare('SELECT f.* FROM practical_file f JOIN practical_attempt a ON a.id=f.attempt_id WHERE a.session_id=? AND f.id=?');$st->execute([$sid,(int)($_GET['file_id']??0)]);$file=$st->fetch();
        if(!$file){http_response_code(404);exit('成果文件不存在');}practical_download($file);
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_csrf();
        try {
            $posted=$_POST['questions']??[];if(!is_array($posted))throw new InvalidArgumentException('题目格式无效');
            practical_save_config($pdo,$sid,$posted,isset($_POST['enabled']));
            audit('practical_config_save','session:'.$sid);flash('实操题目与开放状态已保存');redirect('/index.php?page=interview_practical&session_id='.$sid);
        }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'设置保存失败，请稍后重试';if(!($e instanceof InvalidArgumentException))error_log('Practical admin: '.$e->getMessage());}
    }
    $qs=practical_questions($pdo,$sid);$st=$pdo->prepare('SELECT enabled FROM practical_exam WHERE session_id=?');$st->execute([$sid]);$enabled=(bool)$st->fetchColumn();
    $st=$pdo->prepare('SELECT a.*,c.name,c.mobile FROM practical_attempt a JOIN candidate c ON c.id=a.candidate_id WHERE a.session_id=? ORDER BY a.submitted_at DESC,a.id DESC');$st->execute([$sid]);$attempts=$st->fetchAll();
    $st=$pdo->prepare('SELECT COUNT(*) FROM interview_candidate WHERE session_id=?');$st->execute([$sid]);$assigned=(int)$st->fetchColumn();
    $submitted=count(array_filter($attempts,fn($a)=>!empty($a['submitted_at'])));
    if($error&&!$locked&&isset($_POST['questions'])&&is_array($_POST['questions']))$qs=array_map(fn($s)=>['stem'=>is_string($s)?$s:''],array_slice($_POST['questions'],0,30));
    if(!$qs)$qs=[['stem'=>'']];
    admin_header('面试实操','interviews');page_head('人才管理 / 面试管理 / 实操配置','面试实操',$session['post_name'].' · '.$session['interview_date'].' '.$session['time_range'],'<a class="btn secondary" href="?page=interviews#session-'.$sid.'">返回面试场次</a><a class="btn primary" href="/practical.php" target="_blank" rel="noopener">打开应聘者入口</a>');
    ?><link rel="stylesheet" href="/assets/practical.css?v=<?=asset_mtime('practical.css')?>"><script src="/assets/practical.js?v=<?=asset_mtime('practical.js')?>" defer></script>
    <section class="panel practical-admin-guide"><div><b>应聘者独立入口</b><a href="/practical.php" target="_blank" rel="noopener" data-practical-entry>/practical.php</a><small>无需后台登录。姓名、手机号必须与本场次已安排的应聘者一致。</small></div><div class="practical-admin-stats"><span>已安排 <b><?=$assigned?></b> 人</span><span>已开始 <b><?=count($attempts)?></b> 人</span><span>已提交 <b><?=$submitted?></b> 人</span></div></section>
    <?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
    <section class="panel practical-admin-config"><div class="panel-head"><div><h2>本场实操题目</h2><small><?=$locked?'已有应聘者开始作答，题目内容已锁定。可暂停入口，已上传成果仍会保留。':'按题目顺序填写要求，每道题对应一个独立的成果上传区域。'?></small></div><?=status_badge($enabled&&!$closed?'enabled':'disabled')?></div>
    <form method="post" class="practical-config-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><div data-practical-questions><?php foreach($qs as $i=>$q):?><div class="practical-config-question"><label><b data-question-label>题目 <?=$i+1?></b><textarea name="questions[]" rows="4" maxlength="5000" placeholder="填写题目、操作要求及成果交付要求" <?=$locked||$closed?'readonly':''?> required><?=e($q['stem'])?></textarea></label><?php if(!$locked&&!$closed):?><button type="button" class="table-action danger-text" data-remove-question>移除本题</button><?php endif;?></div><?php endforeach;?></div>
    <?php if(!$closed):?><div class="practical-config-actions"><?php if(!$locked):?><button type="button" class="btn secondary" data-add-question>＋ 添加一道题</button><?php endif;?><label class="practical-enabled"><input type="checkbox" name="enabled" value="1" <?=$enabled?'checked':''?>>开放本场实操入口</label><button class="btn primary">保存实操设置</button></div><?php else:?><div class="practical-notice">本场次已结束或取消，仅可查看已有题目和成果。</div><?php endif;?></form></section>
    <section class="panel practical-admin-results"><div class="panel-head"><div><h2>应聘者成果</h2><small>每位应聘者、每一道题的成果单独保存；未确认提交的记录显示为作答中。</small></div></div>
    <?php if(!$attempts):?><div class="empty-state"><b>暂无实操作答记录</b><p>设置题目并开放入口后，将链接发给本场已安排的应聘者。</p></div><?php endif;?>
    <?php foreach($attempts as $attempt):$files=practical_files($pdo,(int)$attempt['id']);$questions=json_decode($attempt['questions'],true);?>
    <details class="practical-result-person"><summary><span><b><?=e($attempt['name'])?></b><small><?=e(substr($attempt['mobile'],0,3).' **** '.substr($attempt['mobile'],-4))?></small></span><span><?=count($files)?> / <?=count($questions)?> 题已上传</span><span class="badge <?=$attempt['submitted_at']?'green':'amber'?>"><?=$attempt['submitted_at']?'已提交':'作答中'?></span></summary><p class="practical-result-time">开始：<?=e($attempt['created_at'])?><?=$attempt['submitted_at']?'　提交：'.e($attempt['submitted_at']):''?></p>
    <?php foreach($questions as $i=>$q):$file=$files[(int)$q['id']]??null;?><article class="practical-result-question"><h3>题目 <?=$i+1?></h3><p><?=nl2br(e($q['stem']))?></p><?php if($file):?><div><a class="btn secondary" href="?page=interview_practical&amp;session_id=<?=$sid?>&amp;action=download&amp;file_id=<?=(int)$file['id']?>">下载 <?=e($file['original_name'])?></a><small><?=e(practical_size((int)$file['bytes']))?> · <?=e($file['uploaded_at'])?></small></div><?php if($file['note']):?><p class="practical-saved-note">成果说明：<?=nl2br(e($file['note']))?></p><?php endif;?><?php else:?><small>本题尚未上传成果</small><?php endif;?></article><?php endforeach;?></details><?php endforeach;?></section>
    <?php admin_footer();exit;
}
