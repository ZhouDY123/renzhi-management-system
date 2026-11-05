<?php
require_once __DIR__.'/practical.php';
function render_practical_results(PDO $pdo): never {
    if(!can('admin','hr','interviewer')){http_response_code(403);exit('无此操作权限');}
    practical_schema($pdo);$assignmentId=(int)($_GET['candidate_id']??0);
    $st=$pdo->prepare('SELECT ic.session_id,ic.candidate_id,c.name,c.mobile,s.interview_date,s.time_range,p.name post_name,p.company FROM interview_candidate ic JOIN candidate c ON c.id=ic.candidate_id JOIN interview_session s ON s.id=ic.session_id JOIN post p ON p.id=s.post_id WHERE ic.id=?');
    $st->execute([$assignmentId]);$person=$st->fetch();
    if(!$person){http_response_code(404);exit('未找到该应聘者的面试记录');}
    $st=$pdo->prepare('SELECT * FROM practical_attempt WHERE session_id=? AND candidate_id=?');$st->execute([$person['session_id'],$person['candidate_id']]);$attempt=$st->fetch();
    $questions=$attempt?json_decode($attempt['questions'],true):[];
    $files=$attempt?practical_files($pdo,(int)$attempt['id']):[];
    $answers=$attempt?practical_answers($pdo,(int)$attempt['id']):[];
    if(in_array($_GET['action']??'',['download','preview'],true)){
        $file=null;foreach($files as $row)if((int)$row['id']===(int)($_GET['file_id']??0))$file=$row;
        if(!$file){http_response_code(404);exit('该文件不属于当前应聘者的本场实操');}
        if(($_GET['action']??'')==='preview')practical_preview($file);
        practical_download($file);
    }
    admin_header('实操成果','interview_results');
    page_head('人才管理 / 面试结果 / 实操成果',$person['name'].' · 实操成果',$person['post_name'].' · '.$person['company'].' · '.$person['interview_date'].' '.$person['time_range'],'<a class="btn secondary" href="?page=interview_results">返回面试结果</a>');
    ?><link rel="stylesheet" href="/assets/practical.css?v=<?=asset_mtime('practical.css')?>">
    <section class="panel practical-candidate-card">
      <div class="practical-candidate-identity"><span class="practical-candidate-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span><div><h2><?=e($person['name'])?></h2><span class="practical-candidate-phone"><?=e(substr($person['mobile'],0,3).' **** '.substr($person['mobile'],-4))?></span></div></div>
      <div class="practical-candidate-post"><span>应聘岗位</span><strong><?=e($person['post_name'])?></strong><?php if(!empty($person['company'])):?><small><?=e($person['company'])?></small><?php endif;?></div>
      <div class="practical-candidate-progress"><span>成果上传</span><div><strong><?=count($files)?></strong><span>/ <?=count($questions)?> 题</span><span class="badge <?=$attempt&&$attempt['submitted_at']?'green':'amber'?>"><?=$attempt?($attempt['submitted_at']?'已提交':'作答中'):'尚未开始'?></span></div></div>
      <div class="practical-candidate-timeline"><?php if($attempt):?><span>开始作答 <time><?=e($attempt['created_at'])?></time></span><span>提交时间 <time><?=$attempt['submitted_at']?e($attempt['submitted_at']):'尚未提交'?></time></span><?php else:?><span>该应聘者尚未开始本场实操</span><?php endif;?></div>
    </section>
    <script src="/assets/practical-results.js?v=<?=asset_mtime('practical-results.js')?>" defer></script>
    <section class="panel practical-admin-results practical-results-cards"><div class="panel-head"><div><h2>本场实操成果</h2><small>按题查看回答及成果文件，文件仅对应当前应聘者的本场作答。</small></div></div>
    <?php if(!$questions):?><div class="empty-state"><b>暂无实操成果</b><p>应聘者保存回答或提交实操后，可在这里查看。</p></div><?php endif;?>
    <?php foreach($questions as $i=>$q):$file=$files[(int)$q['id']]??null;$answer=$answers[(int)$q['id']]??'';?>
    <article class="practical-result-question"><header><h3><span>题目 <?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span>实操任务</h3><span class="badge <?=$file||$answer!==''?'green':'amber'?>"><?=$file?'已上传':($answer!==''?'已回答':'未填写')?></span></header><p class="practical-result-stem"><?=nl2br(e($q['stem']))?></p>
    <div class="practical-result-note"><b>回答：</b><p><?= $answer!==''?nl2br(e($answer)):'未填写回答' ?></p></div>
    <?php if($file):$ext=strtolower(pathinfo($file['original_name'],PATHINFO_EXTENSION));$preview=in_array($ext,['png','jpg','jpeg','gif','webp','pdf','txt','csv'],true);$url='?page=practical_results&candidate_id='.$assignmentId.'&file_id='.(int)$file['id'];$kind=in_array($ext,['txt','csv'],true)?'text':($ext==='pdf'?'pdf':'image');?>
      <div class="practical-file-card"><span class="practical-file-icon" aria-hidden="true"><?=e(strtoupper($ext))?></span><div class="practical-file-info"><b><?=e($file['original_name'])?></b><small><?=e(practical_size((int)$file['bytes']))?> <span>·</span> 上传于 <?=e($file['uploaded_at'])?></small><?php if(!$preview):?><small>此格式请下载后查看</small><?php endif;?></div><div class="practical-file-actions"><?php if($preview):?><button type="button" class="btn primary" data-result-preview="<?=e($url.'&action=preview')?>" data-kind="<?=$kind?>" data-filename="<?=e($file['original_name'])?>" aria-haspopup="dialog">预览</button><?php endif;?><a class="btn secondary" href="<?=e($url.'&action=download')?>">下载文件</a></div></div>
    <?php else:?><div class="practical-file-empty">未附成果文件（选传）</div><?php endif;?>
    </article><?php endforeach;?></section>
    <dialog class="practical-preview-dialog" id="practical-preview" aria-labelledby="practical-preview-title"><header><div><small>成果预览</small><h2 id="practical-preview-title"></h2></div><button type="button" data-preview-close aria-label="关闭预览">×</button></header><div class="practical-preview-content" aria-live="polite"></div><footer><span>图片、PDF 和文本支持预览；如显示异常，可下载原文件查看。</span><a class="btn secondary" data-preview-download>下载原文件</a></footer></dialog>
    <?php admin_footer();exit;
}

function practical_preview(array $file): never {
    $path=practical_file_path($file['storage_key']);
    if(!is_file($path)){http_response_code(404);exit('成果文件不存在');}
    $ext=strtolower(pathinfo($file['original_name'],PATHINFO_EXTENSION));
    $types=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf','txt'=>'text/plain','csv'=>'text/plain'];
    if(!isset($types[$ext])){http_response_code(415);exit('此格式请下载后查看');}
    $mime=$types[$ext];
    $valid=true;
    if(str_starts_with($mime,'image/'))$valid=(@getimagesize($path)['mime']??'')===$mime;
    elseif($mime==='application/pdf')$valid=file_get_contents($path,false,null,0,5)==='%PDF-';
    if(!$valid){http_response_code(415);exit('文件内容与格式不一致，请下载后查看');}
    header('Content-Type: '.$mime.($mime==='text/plain'?'; charset=UTF-8':''));
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'none'");
    header("Content-Disposition: inline; filename=preview; filename*=UTF-8''".rawurlencode($file['original_name']));
    if($mime==='text/plain'){
        $handle=fopen($path,'rb');$text=fread($handle,1048576);fclose($handle);
        if(!preg_match('//u',$text)&&function_exists('mb_convert_encoding'))$text=mb_convert_encoding($text,'UTF-8','GB18030');
        echo $text;if(filesize($path)>1048576)echo "\n\n[仅预览前 1 MB，请下载查看完整内容]";exit;
    }
    header('Content-Length: '.filesize($path));readfile($path);exit;
}
