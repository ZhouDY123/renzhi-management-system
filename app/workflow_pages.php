<?php
declare(strict_types=1);

function render_talent_workflow(string $page, PDO $pdo): bool {
    if($page==='talent_pool'){
        // Select the most recent passed interview application, not an unrelated later assessment.
        $source=" FROM candidate c JOIN answer a ON a.id=(SELECT a2.id FROM answer a2 JOIN result r2 ON r2.answer_id=a2.id WHERE a2.candidate_id=c.id AND r2.review_status='final_pass' ORDER BY a2.id DESC LIMIT 1) JOIN result r ON r.answer_id=a.id JOIN post p ON p.id=a.post_id ";
        if(isset($_GET['resume_id'])){
            $st=$pdo->prepare("SELECT c.*,p.name post_name,p.name intent_post_name,r.eval_score,r.suzhi_score,r.postq_score,r.total_score,r.review_status".$source." WHERE c.id=?");
            $st->execute([(int)$_GET['resume_id']]);$row=$st->fetch();
            header('Content-Type: application/json; charset=UTF-8');if(!$row)http_response_code(404);
            echo json_encode($row?:['error'=>'该人员尚未面试通过'],JSON_UNESCAPED_UNICODE);return true;
        }
        $q=trim((string)($_GET['q']??''));$params=[];$where='';
        if($q!==''){$where=" WHERE (c.name LIKE ? OR c.mobile LIKE ? OR c.major LIKE ?)";$params=array_fill(0,3,'%'.$q.'%');}
        $st=$pdo->prepare("SELECT COUNT(*)".$source.$where);$st->execute($params);$total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/12));$current=min($pages,max(1,(int)($_GET['p']??1)));
        $st=$pdo->prepare("SELECT c.*,p.name post_name,r.total_score".$source.$where." ORDER BY c.id DESC LIMIT 12 OFFSET ".(($current-1)*12));$st->execute($params);$rows=$st->fetchAll();
        $editing=null;$editId=(int)($_GET['edit']??0);$intentPosts=[];if($editId){$st=$pdo->prepare("SELECT c.*".$source." WHERE c.id=?");$st->execute([$editId]);$editing=$st->fetch()?:null;$intentPosts=$pdo->query("SELECT id,name,company FROM post ORDER BY id DESC")->fetchAll();}
        admin_header('人才库','talent_pool');page_head('人才管理 / 面试通过','人才库','面试结束并经 HR 确认录用后，人员自动进入人才库。');?>
        <?php if($editing):?>
            <form class="panel form-panel" method="post" action="?page=talent_pool&action=talent_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e((string)($editing['id']??''))?>"><div class="form-title"><h2><?=$editing?'编辑人才':'新增人才'?></h2><p>维护面试通过人员的档案信息。</p></div><label>姓名<input name="name" value="<?=e($editing['name']??'')?>" required></label><label>手机号码<input name="mobile" value="<?=e($editing['mobile']??'')?>" pattern="1[3-9]\d{9}" maxlength="11" required></label><label>性别<select name="gender"><option value="">未填写</option><option <?=$editing&&$editing['gender']==='男'?'selected':''?>>男</option><option <?=$editing&&$editing['gender']==='女'?'selected':''?>>女</option></select></label><label>学历<input name="edu" value="<?=e($editing['edu']??'')?>" placeholder="例如：本科"></label><label>专业<input name="major" value="<?=e($editing['major']??'')?>" placeholder="例如：人力资源管理"></label><label>意向职位<select name="intent_post_id"><option value="">暂未填写</option><?php foreach($intentPosts as $post):?><option value="<?=$post['id']?>" <?=$editing&&(int)$editing['intent_post_id']===(int)$post['id']?'selected':''?>><?=e($post['name'].' · '.$post['company'])?></option><?php endforeach;?></select></label><div class="form-grid"><label>工作年限（可不填）<input name="work_years" type="number" min="0" value="<?=e($editing&&$editing['work_years']!==null?(string)$editing['work_years']:'')?>" placeholder="例如：5"></label><label>专业年限<input name="prof_years" type="number" min="0" value="<?=e((string)($editing['prof_years']??0))?>"></label></div><label>工作经历<textarea name="work_bg" rows="3" placeholder="可选填写"><?=e($editing['work_bg']??'')?></textarea></label><button class="btn primary"><?=$editing?'保存人才档案':'保存到人才库'?></button></form>
        <?php endif;?>
        <form class="panel filter-bar" method="get"><input type="hidden" name="page" value="talent_pool"><input name="q" value="<?=e($q)?>" placeholder="姓名、手机号或专业"><button class="btn primary">搜索人才</button></form>
        <div class="talent-cards"><?php if(!$rows):?><div class="panel empty-state"><b>暂无面试通过人员</b></div><?php endif;foreach($rows as $r):?><article class="panel talent-card"><i><?=e(first_char($r['name']))?></i><div><h3><?=e($r['name'])?></h3><p><?=e($r['post_name'])?></p><small><?=e($r['edu'].' · '.$r['major'])?></small></div><strong><?=number_format((float)$r['total_score'],1)?><small>测评分数</small></strong><div class="talent-card-actions"><a class="resume-preview" href="?page=talent_pool&amp;resume_id=<?=$r['id']?>">简历预览</a><span class="badge green">面试通过</span></div></article><?php endforeach;?></div>
        <div class="table-pagination"><span>共 <?=$total?> 人</span><div><?php if($current>1):?><a class="btn secondary" href="?<?=e(http_build_query(['page'=>'talent_pool','q'=>$q,'p'=>$current-1]))?>">上一页</a><?php endif;?><b><?=$current?> / <?=$pages?></b><?php if($current<$pages):?><a class="btn secondary" href="?<?=e(http_build_query(['page'=>'talent_pool','q'=>$q,'p'=>$current+1]))?>">下一页</a><?php endif;?></div></div>
        <?php admin_footer();return true;
    }
    if($page==='preregister'){
        admin_header('测评记录','preregister');page_head('人才管理 / 在线测评','测评记录','求职者扫码自主选择岗位，交卷自动评分；总分超过 60 分可安排面试。');
        $rows=$pdo->query("SELECT c.name,c.mobile,p.name post_name,a.submit_at,r.total_score,r.score_status FROM answer a JOIN candidate c ON c.id=a.candidate_id JOIN post p ON p.id=a.post_id JOIN result r ON r.answer_id=a.id ORDER BY a.id DESC")->fetchAll();?>
        <section class="panel table-wrap"><table><thead><tr><th>求职者</th><th>应聘岗位</th><th>手机号</th><th>测评分数</th><th>面试资格</th><th>提交时间</th></tr></thead><tbody>
        <?php if(!$rows):?><tr><td colspan="6" class="empty-cell">暂无测评记录</td></tr><?php endif;foreach($rows as $r):?><tr><td><?=e($r['name'])?></td><td><?=e($r['post_name'])?></td><td><?=e($r['mobile'])?></td><td><?=number_format((float)$r['total_score'],1)?></td><td><?=$r['score_status']==='completed'?((float)$r['total_score']>60?'可安排面试':'未达到 60 分以上'):'待评分'?></td><td><?=e($r['submit_at'])?></td></tr><?php endforeach;?></tbody></table></section>
        <?php admin_footer();return true;
    }
    return false;
}
