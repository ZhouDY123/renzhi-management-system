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
