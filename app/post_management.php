<?php
function delete_recruitment_post(PDO $pdo,int $id): array {
    if($id<=0)throw new RuntimeException('请选择有效的职位');
    $pdo->beginTransaction();
    try{
        $find=$pdo->prepare('SELECT id,name FROM post WHERE id=?');$find->execute([$id]);$post=$find->fetch(PDO::FETCH_ASSOC);
        if(!$post)throw new RuntimeException('职位已删除或不存在，请刷新页面');
        foreach(['candidate_pre_register','answer','interview_pre_register','interview_session'] as $table){
            $check=$pdo->prepare('SELECT 1 FROM '.$table.' WHERE post_id=? LIMIT 1');$check->execute([$id]);
            if($check->fetchColumn())throw new RuntimeException('该职位已有应聘或面试记录，无法删除。可编辑职位，将招聘状态设为“已停止”。');
        }
        $pdo->prepare('DELETE FROM question_set_item WHERE question_set_id IN (SELECT id FROM question_set WHERE post_id=?)')->execute([$id]);
        foreach(['question_set','question_post','question_base','suzhi_item'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE post_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM post WHERE id=?')->execute([$id]);
        $pdo->commit();return $post;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ordered_recruitment_posts(PDO $pdo,array $rows): array {
 $order=json_decode((string)$pdo->query("SELECT value FROM app_setting WHERE key='recruitment_post_order'")->fetchColumn(),true)?:[];$rank=array_flip($order);
 usort($rows,fn($a,$b)=>(($rank[$a['id']]??PHP_INT_MAX)<=>($rank[$b['id']]??PHP_INT_MAX))?:($b['id']<=>$a['id']));return $rows;
}
function save_recruitment_post_order(PDO $pdo,array $order): void {
 $ids=$pdo->query('SELECT id FROM post')->fetchAll(PDO::FETCH_COLUMN);$order=array_map('intval',$order);
 if(count($order)!==count(array_unique($order))||count($order)!==count($ids)||array_diff($order,$ids))throw new RuntimeException('岗位已变化，请刷新页面后重新排序');
 $pdo->prepare("INSERT INTO app_setting(key,value,label) VALUES('recruitment_post_order',?,'招聘岗位顺序') ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([json_encode($order)]);
}
function render_post_order_editor(PDO $pdo): void {
 $posts=ordered_recruitment_posts($pdo,$pdo->query('SELECT id,name,company FROM post')->fetchAll(PDO::FETCH_ASSOC)); ?>
 <details class="panel post-order"><summary>调整岗位显示顺序</summary><p>拖动调整，保存后同步到职位列表、题库发布岗位、面试场次招聘岗位和手机扫码选岗页。</p><form method="post" action="?page=posts&action=post_order_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><ol><?php foreach($posts as $post):?><li draggable="true"><input type="hidden" name="order[]" value="<?=$post['id']?>"><span aria-hidden="true">⠿</span><b><?=e($post['name'])?><small><?=e($post['company'])?></small></b><button type="button" data-post-move="-1" aria-label="上移<?=e($post['name'])?>">↑</button><button type="button" data-post-move="1" aria-label="下移<?=e($post['name'])?>">↓</button></li><?php endforeach;?></ol><button class="btn primary">保存顺序</button></form></details>
 <?php
}
