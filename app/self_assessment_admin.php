<?php
declare(strict_types=1);

function handle_basic_rating_action(PDO $pdo,string $action): void {
    if(!in_array($action,['basic_rating_save','basic_rating_toggle','basic_rating_delete'],true))return;
    if(!can('admin','hr'))throw new RuntimeException('无题库维护权限');
    $id=(int)($_POST['id']??0);
    if($id){$st=$pdo->prepare("SELECT * FROM question_base WHERE id=? AND group_type='suzhi'");$st->execute([$id]);if(!$st->fetch())throw new RuntimeException('题目不存在');}
    if($action==='basic_rating_save'){
        $stem=trim((string)($_POST['stem']??''));$score=filter_var($_POST['score']??'',FILTER_VALIDATE_FLOAT);
        if($stem===''||$score===false||$score<=0)throw new RuntimeException('请填写题干和大于 0 的满分');
        $postId=basic_rating_post_id($pdo,$_POST['post_id']??0);
        $values=[$stem,json_encode(array_keys(self_assessment_levels()),JSON_UNESCAPED_UNICODE),json_encode(['type'=>'self_rating_v1','levels'=>self_assessment_levels()],JSON_UNESCAPED_UNICODE),$score,isset($_POST['enabled'])?1:0,max(0,(int)($_POST['sort']??0))];
        if($id){$pdo->prepare("UPDATE question_base SET q_type='rating',stem=?,options=?,answer=?,score=?,status=?,sort=?,post_id=? WHERE id=?")->execute([...$values,$postId,$id]);}
        else{$pdo->prepare("INSERT INTO question_base(group_type,q_type,stem,options,answer,score,status,sort,post_id) VALUES('suzhi','rating',?,?,?,?,?,?,?)")->execute([...$values,$postId]);$id=(int)$pdo->lastInsertId();}
    }elseif($action==='basic_rating_toggle'){$pdo->prepare('UPDATE question_base SET status=1-status WHERE id=?')->execute([$id]);}
    else{
        $st=$pdo->prepare("SELECT 1 FROM question_set_item WHERE question_type='base' AND question_id=? LIMIT 1");$st->execute([$id]);
        if($st->fetchColumn())throw new RuntimeException('该题已被试卷引用，请停用以保留历史记录');
        $pdo->prepare('DELETE FROM question_base WHERE id=?')->execute([$id]);
    }
    audit($action,'question_base:'.$id);flash('基本素质题已更新；修改后请重新发布岗位试卷');redirect('/index.php?page=standards&section=suzhi');
}

function render_basic_rating_editor(PDO $pdo): void {
    $edit=null;if(isset($_GET['basic_edit'])){$st=$pdo->prepare("SELECT * FROM question_base WHERE id=? AND group_type='suzhi'");$st->execute([(int)$_GET['basic_edit']]);$edit=$st->fetch()?:null;}
    $posts=$pdo->query("SELECT id,name,company FROM post ORDER BY id")->fetchAll();
    $filter=(string)($_GET['basic_post']??'');
    $sql="SELECT q.*,p.name post_name,p.company post_company FROM question_base q LEFT JOIN post p ON p.id=q.post_id WHERE q.group_type='suzhi'";
    $args=[];if($filter==='unassigned')$sql.=' AND q.post_id IS NULL';elseif(ctype_digit($filter)&& (int)$filter>0){$sql.=' AND q.post_id=?';$args[]=(int)$filter;}
    $st=$pdo->prepare($sql.' ORDER BY q.post_id,q.sort,q.id');$st->execute($args);$rows=$st->fetchAll();
    ?><article class="panel grouped-standard-intro"><div><b>基本素质五级自评题</b><p>按岗位维护能力描述和满分，固定五个符合等级。未分配岗位的旧题不会纳入新试卷；修改后请重新发布对应岗位试卷。</p></div></article>
    <form hidden class="panel basic-rating-editor" method="post" action="?page=standards&action=basic_rating_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><header class="rating-editor-head"><div><h2><?=$edit?'编辑':'新增'?>基本素质题</h2><p>描述一个具体的行为或能力，让求职者选择符合程度。</p></div><span>五级自评</span></header><label>所属岗位 <em>必填</em><select name="post_id" required data-default-post="<?=ctype_digit($filter)?(int)$filter:0?>"><option value="">请选择所属岗位</option><?php foreach($posts as $p):?><option value="<?=$p['id']?>" <?=(int)($edit['post_id']??(ctype_digit($filter)?$filter:0))===(int)$p['id']?'selected':''?>><?=e($p['name'].' · '.$p['company'])?></option><?php endforeach;?></select></label><label class="rating-stem">题干 <em>必填</em><textarea name="stem" required rows="3" placeholder="例如：我能够主动核对工作中的数据，及时发现并纠正差错。"><?=e($edit['stem']??'')?></textarea></label><div class="rating-editor-grid"><label>题目满分<input type="number" name="score" min="0.01" step="0.01" value="<?=e((string)($edit['score']??3))?>" required><small>完全符合时获得的分数</small></label><label>排序<input type="number" name="sort" min="0" value="<?=(int)($edit['sort']??1)?>"><small>数字越小，题目越靠前</small></label></div><section class="rating-level-preview" aria-label="固定评分等级"><div><b>评分等级</b><small>系统固定按题目满分折算，无需设置正确答案</small></div><ol><?php foreach(self_assessment_levels() as $label=>$ratio):?><li><span><?=e($label)?></span><b><?=round($ratio*100)?>%</b></li><?php endforeach;?></ol></section><footer class="rating-editor-footer"><label class="rating-enabled"><input type="checkbox" role="switch" name="enabled" <?=!$edit||$edit['status']?'checked':''?>><span class="rating-switch" aria-hidden="true"></span><span>启用此题<small>启用后可加入新试卷</small></span></label><div class="rating-editor-actions"><button class="btn primary">保存题目</button><button type="button" class="btn secondary" data-basic-rating-cancel>取消</button></div></footer></form>
    <section class="panel table-wrap basic-rating-library"><div class="panel-head"><h2>基本素质题库</h2><span class="rating-count"><?=count($rows)?> 题</span></div><form class="rating-post-filter" method="get"><input type="hidden" name="page" value="standards"><input type="hidden" name="section" value="suzhi"><label>筛选岗位<select name="basic_post"><option value="">全部岗位</option><option value="unassigned" <?=$filter==='unassigned'?'selected':''?>>未分配岗位</option><?php foreach($posts as $p):?><option value="<?=$p['id']?>" <?=$filter===(string)$p['id']?'selected':''?>><?=e($p['name'].' · '.$p['company'])?></option><?php endforeach;?></select></label><button class="btn secondary">筛选</button></form><table><thead><tr><th>题干</th><th>满分</th><th>状态</th><th>操作</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="4">暂无题目，请新增五级自评题。</td></tr><?php endif;foreach($rows as $q):?><tr><td><small class="rating-post-name"><?=e($q['post_name']?($q['post_name'].' · '.$q['post_company']):'未分配岗位')?></small><?=e($q['stem'])?></td><td><strong class="rating-score"><?=e((string)$q['score'])?></strong></td><td><span class="rating-status <?=$q['status']?'is-enabled':'is-disabled'?>"><?=$q['status']?'启用':'停用'?></span></td><td><div class="rating-row-actions"><a class="btn rating-edit" href="?page=standards&section=suzhi&basic_edit=<?=$q['id']?>">编辑</a><?php foreach(['basic_rating_toggle'=>$q['status']?'停用':'启用','basic_rating_delete'=>'删除'] as $action=>$label):?><form method="post" action="?page=standards&action=<?=$action?>" <?=$action==='basic_rating_delete'?'data-confirm-message="确认删除此题？"':''?>><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$q['id']?>"><button class="btn <?=$action==='basic_rating_delete'?'rating-delete':'rating-toggle'?>"><?=$label?></button></form><?php endforeach;?></div></td></tr><?php endforeach;?></tbody></table></section><?php
}
