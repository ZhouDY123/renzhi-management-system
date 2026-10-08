<?php
// Appendix data is deliberately separate from scoring standards, candidate profiles and hiring decisions.
function appendix_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS appendix_question (
        id INTEGER PRIMARY KEY AUTOINCREMENT, source_key TEXT UNIQUE,
        section TEXT NOT NULL, title TEXT NOT NULL, options_json TEXT NOT NULL DEFAULT '[]',
        help TEXT NOT NULL DEFAULT '', supplement TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1,
        deleted INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE TABLE IF NOT EXISTS appendix_submission (
        answer_id INTEGER PRIMARY KEY REFERENCES answer(id) ON DELETE CASCADE,
        questions_json TEXT NOT NULL, responses_json TEXT NOT NULL DEFAULT '{}',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','submitted','skipped')),
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')), completed_at TEXT
    )");
    // An import marker prevents deleted/edited questions from being restored on later requests.
    $st=$pdo->query("SELECT value FROM app_setting WHERE key='appendix_import_20261008'");
    if($st->fetchColumn()!==false)return;
    $questions=json_decode(file_get_contents(__DIR__.'/appendix_questions.json'),true,512,JSON_THROW_ON_ERROR);
    $pdo->beginTransaction();
    try {
        $ins=$pdo->prepare('INSERT OR IGNORE INTO appendix_question(source_key,section,title,options_json,help,supplement,sort) VALUES(?,?,?,?,?,?,?)');
        foreach($questions as $i=>$q)$ins->execute([$q['key'],$q['section'],$q['title'],json_encode($q['options'],JSON_UNESCAPED_UNICODE),$q['help'],$q['supplement'],$i+1]);
        $pdo->exec("INSERT OR IGNORE INTO app_setting(key,value,label) VALUES('appendix_import_20261008','1','附录题目初次导入')");
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}

function appendix_questions(PDO $pdo): array {
    return $pdo->query('SELECT id,section,title,options_json,help,supplement FROM appendix_question WHERE enabled=1 AND deleted=0 ORDER BY sort,id')->fetchAll(PDO::FETCH_ASSOC);
}

function appendix_attempt(PDO $pdo,int $answerId): array {
    $find=$pdo->prepare('SELECT * FROM appendix_submission WHERE answer_id=?');$find->execute([$answerId]);
    if($row=$find->fetch(PDO::FETCH_ASSOC))return $row;
    $snapshot=json_encode(appendix_questions($pdo),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT OR IGNORE INTO appendix_submission(answer_id,questions_json) VALUES(?,?)')->execute([$answerId,$snapshot]);
    $find->execute([$answerId]);return $find->fetch(PDO::FETCH_ASSOC);
}

function appendix_text(mixed $value,int $limit=2000): string {
    if(!is_string($value)||strlen($value)>$limit*4)throw new InvalidArgumentException('填写内容格式不正确或过长，请缩短后重试。');
    return trim($value);
}

function appendix_complete(PDO $pdo,int $answerId,array $questions,array $input,bool $skip): void {
    $responses=[];
    if(!$skip){
        $raw=$input['appendix']??[];
        if(!is_array($raw))throw new InvalidArgumentException('附录答案格式不正确');
        foreach($questions as $q){
            $item=$raw[$q['id']]??[];
            if(!is_array($item))throw new InvalidArgumentException('附录答案格式不正确');
            $value=appendix_text($item['value']??'');
            $note=$q['supplement']!==''?appendix_text($item['note']??''):'';
            $options=json_decode($q['options_json'],true,512,JSON_THROW_ON_ERROR);
            if($value!==''&&$options&&!in_array($value,$options,true))throw new InvalidArgumentException('“'.$q['title'].'”的选项无效，请重新选择。');
            if($value==='不提供')$note='';
            if($value!==''||$note!=='')$responses[(string)$q['id']]=['value'=>$value,'note'=>$note];
        }
    }
    // Conditional update makes refresh/double-click/replay harmless; scores are never touched.
    $pdo->prepare("UPDATE appendix_submission SET responses_json=?,status=?,completed_at=datetime('now','localtime') WHERE answer_id=? AND status='pending'")
        ->execute([json_encode((object)$responses,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$skip?'skipped':'submitted',$answerId]);
}

function appendix_h5(PDO $pdo,array $post,string $token): never {
    header('Cache-Control: no-store');
    $answerId=(int)($_SESSION['apply_results'][(int)$post['id']]??0);
    $check=$pdo->prepare('SELECT a.id FROM answer a JOIN result r ON r.answer_id=a.id WHERE a.id=? AND a.post_id=?');
    $check->execute([$answerId,$post['id']]);
    if(!$check->fetchColumn()){http_response_code(403);h5_header('附录信息');echo '<section class="h5-card assessment-notice"><h2>请先完成本次测评</h2><p>附录仅限当前本人测评会话填写。</p></section>';h5_footer();exit;}
    $done='/h5.php?m=apply&t='.urlencode($token).'&step=done';
    if($_SERVER['REQUEST_METHOD']==='POST')check_csrf();
    if($_SERVER['REQUEST_METHOD']==='POST'&&(int)($_POST['appendix_answer_id']??0)!==$answerId){http_response_code(409);exit('测评会话已变化，请刷新附录页后重试。');}
    $error='';$questions=[];
    try {
        appendix_schema($pdo);
        $attempt=appendix_attempt($pdo,$answerId);
        if($attempt['status']!=='pending')redirect($done);
        $questions=json_decode($attempt['questions_json'],true,512,JSON_THROW_ON_ERROR);
        if(!$questions){appendix_complete($pdo,$answerId,[],[],true);redirect($done);}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            appendix_complete($pdo,$answerId,$questions,$_POST,($_POST['intent']??'')==='skip');
            redirect($done);
        }
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Appendix unavailable: '.$e->getMessage());$error='附录暂时无法保存，您的测评成绩已保存，可直接查看成绩。';$questions=[];}
    h5_header('附录信息');
    echo '<link rel="stylesheet" href="/assets/appendix.css?v='.asset_mtime('appendix.css').'">';
    ?><section class="h5-card appendix-intro"><span class="appendix-kicker">测评已提交 · 附录选填</span><h1>补充信息</h1><p>所有题目均为选填，您可留空、选择“不提供”或直接跳过。不计分，不影响测评结果、面试安排或录用判断。</p><a href="<?=e($done)?>">不填写，直接查看成绩 →</a></section>
    <form class="h5-card appendix-form" method="post" action="?m=apply&amp;t=<?=e($token)?>&amp;step=appendix">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="appendix_answer_id" value="<?=$answerId?>">
    <?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;
    $section='';foreach($questions as $i=>$q):
        if($q['section']!==$section):$section=$q['section'];?><h2 class="appendix-section"><?=e($section)?></h2><?php endif;
        $item=is_array($_POST['appendix'][$q['id']]??null)?$_POST['appendix'][$q['id']]:[];
        $value=is_string($item['value']??null)?$item['value']:'';$note=is_string($item['note']??null)?$item['note']:'';
        $options=json_decode($q['options_json'],true);
    ?><fieldset class="appendix-item"><legend><span><?=$i+1?></span> <?=e($q['title'])?> <small>选填</small></legend><p><?=e($q['help'])?></p>
    <?php if($options):?><div class="appendix-options"><?php foreach($options as $option):?><label><input type="radio" name="appendix[<?=$q['id']?>][value]" value="<?=e($option)?>" <?=$value===$option?'checked':''?>><span><?=e($option)?></span></label><?php endforeach;?></div>
    <?php else:?><textarea name="appendix[<?=$q['id']?>][value]" rows="3" maxlength="2000" aria-label="<?=e($q['title'])?>" placeholder="自愿填写，也可留空或填写不提供"><?=e($value)?></textarea><?php endif;
    if($q['supplement']!==''):?><label class="appendix-note"><?=e($q['supplement'])?><textarea name="appendix[<?=$q['id']?>][note]" rows="2" maxlength="2000" placeholder="补充说明（选填）"><?=e($note)?></textarea></label><?php endif;?></fieldset><?php endforeach;?>
    <div class="appendix-actions"><button type="submit" class="btn secondary" name="intent" value="skip" formnovalidate>跳过，查看成绩</button><button type="submit" class="btn primary" name="intent" value="submit">提交并查看成绩</button></div></form><?php h5_footer();exit;
}
