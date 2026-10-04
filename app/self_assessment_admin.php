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
        $values=[$stem,json_encode(array_keys(self_assessment_levels()),JSON_UNESCAPED_UNICODE),json_encode(['type'=>'self_rating_v1','levels'=>self_assessment_levels()],JSON_UNESCAPED_UNICODE),$score,isset($_POST['enabled'])?1:0,max(0,(int)($_POST['sort']??0))];
        if($id){$pdo->prepare("UPDATE question_base SET q_type='rating',stem=?,options=?,answer=?,score=?,status=?,sort=? WHERE id=?")->execute([...$values,$id]);}
        else{$pdo->prepare("INSERT INTO question_base(group_type,q_type,stem,options,answer,score,status,sort) VALUES('suzhi','rating',?,?,?,?,?,?)")->execute($values);$id=(int)$pdo->lastInsertId();}
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
    $rows=$pdo->query("SELECT * FROM question_base WHERE group_type='suzhi' ORDER BY sort,id")->fetchAll();
    ?><article class="panel grouped-standard-intro"><div><b>基本素质五级自评题</b><p>只需填写能力描述或题目及满分。固定五个符合等级，无需正确答案；修改后请重新发布岗位试卷。</p></div></article>
    <form class="panel basic-rating-editor" method="post" action="?page=standards&action=basic_rating_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><h2><?=$edit?'编辑':'新增'?>基本素质题</h2><label>题干<textarea name="stem" required rows="3" placeholder="例如：我能够主动核对工作中的数据，及时发现并纠正差错。"><?=e($edit['stem']??'')?></textarea></label><div class="form-grid"><label>题目满分<input type="number" name="score" min="0.01" step="0.01" value="<?=e((string)($edit['score']??3))?>" required></label><label>排序<input type="number" name="sort" min="0" value="<?=(int)($edit['sort']??1)?>"></label></div><p class="self-rating-help">完全不符合（0%） / 较不符合（25%） / 基本符合（50%） / 比较符合（75%） / 完全符合（100%）</p><label><input type="checkbox" name="enabled" <?=!$edit||$edit['status']?'checked':''?>> 启用此题</label><button class="btn primary">保存题目</button><?php if($edit):?> <a class="btn secondary" href="?page=standards&section=suzhi">取消编辑</a><?php endif;?></form>
    <section class="panel table-wrap"><div class="panel-head"><h2>基本素质题库</h2><span><?=count($rows)?> 题</span></div><table><thead><tr><th>题干</th><th>满分</th><th>状态</th><th>操作</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="4">暂无题目，请新增五级自评题。</td></tr><?php endif;foreach($rows as $q):?><tr><td><?=e($q['stem'])?></td><td><?=e((string)$q['score'])?></td><td><?=$q['status']?'启用':'停用'?></td><td><a href="?page=standards&section=suzhi&basic_edit=<?=$q['id']?>">编辑</a><?php foreach(['basic_rating_toggle'=>$q['status']?'停用':'启用','basic_rating_delete'=>'删除'] as $action=>$label):?><form method="post" action="?page=standards&action=<?=$action?>" <?=$action==='basic_rating_delete'?'data-confirm-message="确认删除此题？"':''?>><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$q['id']?>"><button class="btn secondary"><?=$label?></button></form><?php endforeach;?></td></tr><?php endforeach;?></tbody></table></section><?php
}
