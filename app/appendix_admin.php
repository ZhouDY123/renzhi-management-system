<?php
require_once __DIR__.'/appendix.php';

function appendix_save_question(PDO $pdo,array $input): int {
    $id=filter_var($input['id']??'0',FILTER_VALIDATE_INT);
    $title=appendix_text($input['title']??'',200);$section=appendix_text($input['section']??'',100);
    $help=appendix_text($input['help']??'',1000);$supplement=appendix_text($input['supplement']??'',300);
    $sort=filter_var($input['sort']??'',FILTER_VALIDATE_INT);
    if($id===false||$id<0||$title===''||$section===''||$sort===false||$sort<0||$sort>99999)throw new InvalidArgumentException('请填写题目、分组及有效的排序数字。');
    $type=$input['type']??'';
    if(!in_array($type,['text','select'],true))throw new InvalidArgumentException('题型无效。');
    $options=[];
    if($type==='select'){
        $options=array_values(array_unique(array_filter(array_map('trim',preg_split('/\R/u',appendix_text($input['options']??'',4000))),fn($v)=>$v!=='')));
        if(count($options)<2||count($options)>20)throw new InvalidArgumentException('选择题请设置 2–20 个选项，每行一个。');
        foreach($options as $option)if(strlen($option)>400)throw new InvalidArgumentException('单个选项过长。');
    }
    $values=[$section,$title,json_encode($options,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$help,$supplement,$sort,isset($input['enabled'])?1:0];
    if($id){
        $check=$pdo->prepare('SELECT id FROM appendix_question WHERE id=? AND deleted=0');$check->execute([$id]);
        if(!$check->fetchColumn())throw new InvalidArgumentException('题目不存在或已删除。');
        $pdo->prepare("UPDATE appendix_question SET section=?,title=?,options_json=?,help=?,supplement=?,sort=?,enabled=?,updated_at=datetime('now','localtime') WHERE id=? AND deleted=0")->execute([...$values,$id]);
    }else{
        $pdo->prepare('INSERT INTO appendix_question(section,title,options_json,help,supplement,sort,enabled) VALUES(?,?,?,?,?,?,?)')->execute($values);$id=(int)$pdo->lastInsertId();
    }
    return $id;
}

function appendix_admin(PDO $pdo,string $action): never {
    if(!can('admin','hr')){http_response_code(403);exit('无访问权限');}
    header('Cache-Control: no-store');
    appendix_schema($pdo);
    $error='';$editing=null;
    if($action!==''){
        if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('请通过表单操作');}
        check_csrf();
        try{
            if($action==='appendix_save'){
                $id=appendix_save_question($pdo,$_POST);audit('appendix_question_save','appendix:'.$id);
            }elseif(in_array($action,['appendix_toggle','appendix_delete'],true)){
                $id=(int)($_POST['id']??0);
                $sql=$action==='appendix_delete'?'deleted=1,enabled=0':'enabled=1-enabled';
                $st=$pdo->prepare("UPDATE appendix_question SET $sql,updated_at=datetime('now','localtime') WHERE id=? AND deleted=0");$st->execute([$id]);
                if(!$st->rowCount())throw new InvalidArgumentException('题目不存在或已删除。');
                audit($action,'appendix:'.$id);
            }else{http_response_code(400);exit('操作无效');}
            flash('已保存。变更用于后续新打开的附录，不影响历史填写记录。');redirect('/index.php?page=appendix');
        }catch(InvalidArgumentException $e){
            $error=$e->getMessage();
            $editing=['id'=>0,'title'=>'','section'=>'补充信息','help'=>'','supplement'=>'','sort'=>0];
            foreach($editing as $key=>$default)if(is_string($_POST[$key]??null))$editing[$key]=$_POST[$key];
            $editing['options_json']=json_encode(explode("\n",is_string($_POST['options']??null)?$_POST['options']:''));$editing['enabled']=isset($_POST['enabled'])?1:0;
        }
    }
    if(isset($_GET['question'])){$st=$pdo->prepare('SELECT * FROM appendix_question WHERE id=? AND deleted=0');$st->execute([(int)$_GET['question']]);$editing=$st->fetch()?:null;}
    admin_header('附录题目','appendix');
    echo '<link rel="stylesheet" href="/assets/appendix.css?v='.asset_mtime('appendix.css').'">';
    page_head('题目管理 / 自愿登记','附录题目','所有附录均为选填，不计分、不参与面试安排及录用判断。','<a class="btn secondary" href="?page=appendix&view=responses">填写记录</a>'.(($_GET['view']??'')==='responses'?'<a class="btn primary" href="?page=appendix&create=1">＋ 新增附录题目</a>':'<button type="button" class="btn primary" data-appendix-create>＋ 新增附录题目</button>'));
    if(($_GET['view']??'')==='responses'){appendix_admin_responses($pdo);admin_footer();exit;}
    $rows=$pdo->query('SELECT * FROM appendix_question WHERE deleted=0 ORDER BY sort,id')->fetchAll();
    $defaults=['id'=>0,'section'=>'补充信息','title'=>'','options_json'=>'[]','help'=>'','supplement'=>'','sort'=>count($rows)+1,'enabled'=>1];
    $autoOpen=$editing!==null||isset($_GET['create']);
    $editing=$editing??$defaults;
    $options=json_decode($editing['options_json'],true)?:[];
    $isSelect=$error!==''?($_POST['type']??'text')==='select':(bool)$options;
    ?><div class="appendix-admin-grid"><section class="panel appendix-library"><header><div><h2>附录题库</h2><p>已导入评分标准表附录一至三，可继续新增和调整。</p></div><span><?=count($rows)?> 题</span></header>
    <?php if(!$rows):?><div class="empty-state">暂无附录题目。全部停用或删除后，交卷将直接显示成绩。</div><?php endif;
    foreach($rows as $q):?><article class="appendix-library-row"><span class="appendix-order"><?=(int)$q['sort']?></span><div><small><?=e($q['section'])?> · <?=json_decode($q['options_json'],true)?'单选':'自由填写'?></small><h3><?=e($q['title'])?></h3><p><?=e($q['help'])?></p><?=status_badge($q['enabled']?'enabled':'disabled')?></div><div class="appendix-row-actions"><button type="button" class="btn secondary" data-appendix-edit="<?=e(json_encode($q,JSON_UNESCAPED_UNICODE))?>">编辑</button><form method="post" action="?page=appendix&action=appendix_toggle"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$q['id']?>"><button class="btn secondary"><?=$q['enabled']?'停用':'启用'?></button></form><form method="post" action="?page=appendix&action=appendix_delete" data-confirm-message="确认删除这道附录题？历史填写记录会保留。"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$q['id']?>"><button class="btn secondary danger-text">删除</button></form></div></article><?php endforeach;?></section>
    </div><dialog class="appendix-modal" data-auto-open="<?=$autoOpen?'1':'0'?>" data-defaults="<?=e(json_encode($defaults,JSON_UNESCAPED_UNICODE))?>" aria-labelledby="appendix-modal-title">
    <header class="appendix-modal-head"><div><small>附录题库 · 全部选填</small><h2 id="appendix-modal-title"><?=$editing['id']?'编辑':'新增'?>附录题目</h2><p>设置求职者在手机上看到的题目内容。</p></div><button type="button" data-appendix-close class="appendix-close" aria-label="关闭弹窗">×</button></header>
    <form id="appendix-editor" class="appendix-editor" method="post" action="?page=appendix&action=appendix_save"><div class="appendix-modal-body">
    <?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
    <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e((string)$editing['id'])?>">
    <label>题目内容 <em>必填</em><textarea name="title" maxlength="200" rows="2" required placeholder="例如：是否为相关行业"><?=e($editing['title'])?></textarea></label>
    <div class="appendix-field-pair"><label>所属分组<input name="section" maxlength="100" value="<?=e($editing['section'])?>" required placeholder="例如：工作经历"></label>
    <label>答题方式<select name="type"><option value="text" <?=$isSelect?'':'selected'?>>自由填写 · 求职者输入文字</option><option value="select" <?=$isSelect?'selected':''?>>单选 · 求职者选择一个答案</option></select></label></div>
    <label data-appendix-options <?=$isSelect?'':'hidden'?>>可选择的答案<small>每行一个答案，至少填写两个。例如：是、否、不提供。</small><textarea name="options" rows="4" placeholder="是&#10;否&#10;不提供" <?=$isSelect?'required':'disabled'?>><?=e(implode("\n",$options))?></textarea></label>
    <label>题目下方的提示 <em>选填</em><textarea name="help" rows="2" maxlength="1000" placeholder="例如：填写兄弟姐妹人数时，不含本人。"><?=e($editing['help'])?></textarea></label>
    <label>额外补充输入框的名称 <em>选填</em><small>需要求职者另外补充文字时填写；留空则不显示额外输入框。</small><input name="supplement" maxlength="300" placeholder="例如：请补充说明具体情况" value="<?=e($editing['supplement'])?>"></label>
    <div class="appendix-field-pair appendix-settings-row"><label>显示顺序<small>数字越小，越靠前。</small><input type="number" name="sort" min="0" max="99999" step="1" value="<?=e((string)$editing['sort'])?>" required></label>
    <label class="appendix-enabled"><input type="checkbox" name="enabled" <?=$editing['enabled']?'checked':''?>> 启用此题</label>
    </div></div><footer class="appendix-modal-footer"><span>选填 · 不计分</span><button type="button" class="btn secondary" data-appendix-close>取消</button><button type="submit" class="btn primary">保存题目</button></footer></form></dialog><script src="/assets/appendix-admin.js?v=<?=asset_mtime('appendix-admin.js')?>" defer></script><?php admin_footer();exit;
}

function appendix_admin_responses(PDO $pdo): void {
    // Separate, restricted reference page; do not add these answers to score sheets or hiring reports.
    $page=max(1,(int)($_GET['p']??1));$offset=($page-1)*20;
    $st=$pdo->prepare("SELECT s.*,c.name,p.name post_name FROM appendix_submission s JOIN answer a ON a.id=s.answer_id JOIN candidate c ON c.id=a.candidate_id JOIN post p ON p.id=a.post_id WHERE s.status IN ('submitted','skipped') ORDER BY s.completed_at DESC,s.answer_id DESC LIMIT 21 OFFSET ?");$st->bindValue(1,$offset,PDO::PARAM_INT);$st->execute();$rows=$st->fetchAll();$more=count($rows)>20;$rows=array_slice($rows,0,20);
    ?><section class="panel appendix-records"><h2>自愿填写记录</h2><p>仅管理员与 HR 可查看。不提供信息不按负面表现处理，禁止将这些信息用于评分、岗位匹配或录用判断。</p><a href="?page=appendix">返回题目维护</a><?php if(!$rows):?><p>暂无填写记录。</p><?php endif;
    foreach($rows as $row):$answers=json_decode($row['responses_json'],true)?:[];?><details><summary><?=e($row['name'].' · '.$row['post_name'])?> <small><?=e($row['completed_at'])?> · <?=$row['status']==='skipped'?'已跳过':'已提交'?></small></summary><?php if(!$answers):?><p>未提供附录信息。</p><?php else:foreach(json_decode($row['questions_json'],true) as $q):$value=$answers[$q['id']]??null;if(!$value)continue;?><div class="appendix-record-item"><b><?=e($q['title'])?></b><p><?=nl2br(e($value['value']))?></p><?php if($value['note']!==''):?><p><?=nl2br(e($value['note']))?></p><?php endif;?></div><?php endforeach;endif;?></details><?php endforeach;?>
    <footer><?php if($page>1):?><a class="btn secondary" href="?page=appendix&view=responses&p=<?=$page-1?>">上一页</a><?php endif;?><span>第 <?=$page?> 页</span><?php if($more):?><a class="btn secondary" href="?page=appendix&view=responses&p=<?=$page+1?>">下一页</a><?php endif;?></footer></section><?php
}
