<?php
require_once __DIR__.'/practical.php';
function render_practical_admin(PDO $pdo): never {
    if(!can('admin','hr')){http_response_code(403);exit('无此操作权限');}
    practical_schema($pdo);$postId=(int)($_GET['post_id']??0);$error='';$reopen=false;
    if(!$postId&&!empty($_GET['session_id'])){
        $st=$pdo->prepare('SELECT post_id FROM interview_session WHERE id=?');$st->execute([(int)$_GET['session_id']]);
        redirect('/index.php?page=practical_questions&post_id='.(int)$st->fetchColumn());
    }
    $posts=ordered_recruitment_posts($pdo,$pdo->query("SELECT id,name || ' · ' || COALESCE(company,'') AS name FROM post")->fetchAll());
    $selected=null;foreach($posts as $post)if((int)$post['id']===$postId)$selected=$post;
    if($postId&&!$selected){http_response_code(404);exit('岗位不存在');}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_csrf();$operation=(string)($_POST['operation']??'');
        try {
            practical_manage_question($pdo,$postId,$operation,(int)($_POST['question_id']??0),(string)($_POST['stem']??''),(int)($_POST['revision']??0));
            audit('practical_config_save','post:'.$postId,['operation'=>$operation,'question_id'=>(int)($_POST['question_id']??0)]);
            flash(['create'=>'实操题目已新增，默认启用','update'=>'实操题目已更新','delete'=>'实操题目已删除，已有答卷和成果不受影响','toggle'=>'题目启用状态已更新'][$operation]);
            redirect('/index.php?page=practical_questions&post_id='.$postId);
        }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'保存失败，请稍后重试';$reopen=in_array($operation,['create','update'],true);if(!($e instanceof InvalidArgumentException))error_log('Practical question: '.$e->getMessage());}
    }
    $qs=$postId?practical_post_questions($pdo,$postId):[];
    admin_header('实操题目','practical_questions');page_head('题目管理 / 实操题目','实操题目','按岗位维护实操题目，同岗位面试场次共用；新增题目默认启用。');
    ?><link rel="stylesheet" href="/assets/practical.css?v=<?=asset_mtime('practical.css')?>"><script src="/assets/practical-question-editor.js?v=<?=asset_mtime('practical-question-editor.js')?>" defer></script>
    <form method="get" class="panel filter-bar practical-post-picker"><input type="hidden" name="page" value="practical_questions"><div class="practical-picker-heading"><span class="practical-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="7" width="16" height="13" rx="3"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M4 12h16M10 12v3h4v-3"/></svg></span><div><b>选择岗位</b><small>搜索岗位，查看并维护对应实操题目</small></div></div><label><span class="sr-only">岗位</span><span class="searchable-post-select" data-searchable-post-select><select name="post_id" required><option value="">请选择岗位</option><?php foreach($posts as $post):?><option value="<?=(int)$post['id']?>" <?=(int)$post['id']===$postId?'selected':''?>><?=e($post['name'])?></option><?php endforeach;?></select></span></label><button class="btn primary"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>查看岗位题目</button></form>
    <?php if($error&&!$reopen):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
    <section class="panel practical-bank"><div class="panel-head"><div><h2><?=$selected?e($selected['name']):'岗位实操题目'?></h2><small>共 <?=count($qs)?> 道 · 已启用 <?=count(array_filter($qs,fn($q)=>(int)$q['enabled']===1))?> 道<?php if($selected):?>　题目变更仅影响后续开始作答的应聘者<?php endif;?></small></div><button type="button" class="btn primary" data-practical-create <?=$selected?'':'disabled'?> aria-haspopup="dialog">＋ 新增题目</button></div>
    <?php if(!$qs):?><div class="empty-state"><b><?=$selected?'暂无实操题目':'请先选择岗位'?></b><p><?=$selected?'点击「新增题目」，填写题目及成果交付要求。':'支持按岗位名称、部门或公司搜索。'?></p></div><?php endif;?>
    <?php foreach($qs as $i=>$q):?>
    <article class="practical-bank-question" data-practical-question="<?=(int)$q['id']?>" data-revision="<?=(int)$q['revision']?>"><header><div><span class="practical-question-number">题目 <?=$i+1?></span><?=status_badge((int)$q['enabled']===1?'enabled':'disabled')?></div><div class="practical-bank-actions"><button type="button" class="btn secondary" data-practical-edit aria-haspopup="dialog">编辑</button><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="operation" value="toggle"><input type="hidden" name="question_id" value="<?=(int)$q['id']?>"><input type="hidden" name="revision" value="<?=(int)$q['revision']?>"><button class="btn secondary"><?=(int)$q['enabled']===1?'停用':'启用'?></button></form><form method="post" data-confirm-message="确认删除这道实操题目？已开始的答卷和已上传成果会保留。"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="operation" value="delete"><input type="hidden" name="question_id" value="<?=(int)$q['id']?>"><input type="hidden" name="revision" value="<?=(int)$q['revision']?>"><button class="btn secondary danger-text">删除</button></form></div></header><p class="practical-bank-text" data-practical-stem><?=e($q['stem'])?></p></article>
    <?php endforeach;?></section>
    <dialog class="practical-editor-dialog" id="practical-question-dialog" aria-labelledby="practical-dialog-title" data-reopen="<?=$reopen?'1':'0'?>">
      <form method="post" id="practical-question-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="operation" value="<?=e($reopen?(string)($_POST['operation']??'create'):'create')?>"><input type="hidden" name="question_id" value="<?=$reopen?(int)($_POST['question_id']??0):0?>"><input type="hidden" name="revision" value="<?=$reopen?(int)($_POST['revision']??0):0?>">
      <div class="practical-editor-head"><h2 id="practical-dialog-title"><?=$reopen&&($_POST['operation']??'')==='update'?'编辑实操题目':'新增实操题目'?></h2><button type="button" data-practical-close aria-label="关闭弹窗" title="关闭"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
      <div class="practical-editor-body"><p class="muted"><?=e($selected['name']??'')?></p><label for="practical-stem">题目内容</label><textarea id="practical-stem" name="stem" rows="12" maxlength="5000" required placeholder="请输入完整题目内容，可分段填写操作要求、完成步骤和成果交付要求。

每道题对应一个独立的成果文件上传区域。"><?=$reopen?e((string)($_POST['stem']??'')):''?></textarea><div class="practical-editor-help"><span>纯文本 · 保留换行 · 新增默认启用</span><span data-practical-length>0 / 5000</span></div><?php if($error&&$reopen):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?></div>
      <div class="practical-editor-footer"><button type="button" class="btn secondary" data-practical-close>取消</button><button class="btn primary">保存题目</button></div></form>
    </dialog>
    <dialog class="practical-discard-dialog" id="practical-question-discard" aria-labelledby="practical-discard-title"><h2 id="practical-discard-title">放弃未保存的修改？</h2><p>关闭后，本次填写的内容不会保存。</p><div><button type="button" class="btn secondary" data-practical-continue>继续填写</button><button type="button" class="btn danger-solid" data-practical-discard>放弃修改</button></div></dialog>
    <?php admin_footer();exit;
}
