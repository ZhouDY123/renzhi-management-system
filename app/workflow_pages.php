<?php
declare(strict_types=1);

function render_talent_workflow(string $page, PDO $pdo): bool {
    if ($page === 'talent_pool') {
        if (isset($_GET['resume_id'])) {
            $candidateId = (int)$_GET['resume_id'];
            $st = $pdo->prepare("SELECT c.*, a.post_id AS applied_post_id, p.name AS post_name, r.eval_score, r.suzhi_score, r.postq_score, r.total_score, r.review_status FROM candidate c LEFT JOIN answer a ON a.candidate_id=c.id LEFT JOIN post p ON p.id=a.post_id LEFT JOIN result r ON r.answer_id=a.id WHERE c.id=? ORDER BY r.id DESC LIMIT 1");
            $st->execute([$candidateId]); $resume = $st->fetch();
            header('Content-Type: application/json; charset=UTF-8');
            if (!$resume) { http_response_code(404); echo json_encode(['error'=>'人才档案不存在'], JSON_UNESCAPED_UNICODE); return true; }
            echo json_encode($resume, JSON_UNESCAPED_UNICODE); return true;
        }
        admin_header('人才库', 'talent_pool');
        $q = trim((string)($_GET['q'] ?? ''));
        $intentPosts=$pdo->query("SELECT id,name,company FROM post WHERE status IN ('recruiting','reserve') ORDER BY id DESC")->fetchAll();
        $sql = "SELECT c.*, a.post_id AS applied_post_id, p.name AS applied_post_name, intent_p.name AS intent_post_name, r.total_score, r.review_status,
                ip.id AS interview_registration_id, ip.status AS interview_registration_status, ip.post_id AS interview_post_id
                FROM candidate c
                LEFT JOIN answer a ON a.candidate_id=c.id
                LEFT JOIN post p ON p.id=a.post_id
                LEFT JOIN post intent_p ON intent_p.id=c.intent_post_id
                LEFT JOIN result r ON r.answer_id=a.id
                LEFT JOIN interview_pre_register ip ON ip.candidate_id=c.id";
        $params=[];
        if ($q !== '') {$sql .= ' WHERE c.name LIKE ? OR c.mobile LIKE ? OR c.major LIKE ?';$params=array_fill(0,3,'%'.$q.'%');}
        $sql .= ' ORDER BY c.id DESC';
        $st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
        page_head('人才管理 / 人才库','人才库','先将人才建档或批量导入，再由 HR 选择人才进行测评登记。','<a class="btn secondary" href="?page=talent_pool&action=talent_import_template">下载导入模板</a>'); ?>
        <div class="split">
          <div>
            <form class="panel form-panel" method="post" action="?page=talent_pool&action=talent_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="form-title"><h2>新增人才</h2><p>建档后即可登记面试前测评。</p></div><label>姓名<input name="name" required></label><label>手机号码<input name="mobile" pattern="1[3-9]\d{9}" maxlength="11" required></label><label>性别<select name="gender"><option value="">未填写</option><option>男</option><option>女</option></select></label><label>学历<input name="edu" placeholder="例如：本科"></label><label>专业<input name="major" placeholder="例如：人力资源管理"></label><label>意向职位<select name="intent_post_id"><option value="">暂未填写</option><?php foreach($intentPosts as $post):?><option value="<?=$post['id']?>"><?=e($post['name'].' · '.$post['company'])?></option><?php endforeach;?></select></label><div class="form-grid"><label>工作年限<input name="work_years" type="number" min="0" value="0"></label><label>专业年限<input name="prof_years" type="number" min="0" value="0"></label></div><label>工作经历<textarea name="work_bg" rows="3" placeholder="可选填写"></textarea></label><button class="btn primary">保存到人才库</button></form>
            <form class="panel form-panel" method="post" enctype="multipart/form-data" action="?page=talent_pool&action=talent_file"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="form-title"><h2>批量导入人才</h2><p>CSV 列：姓名、手机号、学历、专业、工作年限。</p></div><label>CSV 文件（最大 2MB）<input type="file" name="csv" accept=".csv,text/csv" required></label><button class="btn secondary">上传并导入</button></form>
          </div>
          <section><form class="panel filter-bar" method="get"><input type="hidden" name="page" value="talent_pool"><input name="q" value="<?=e($q)?>" placeholder="姓名、手机号或专业"><button class="btn primary">搜索人才</button><a class="btn secondary" href="?page=talent_pool">重置</a></form><div class="talent-cards"><?php if(!$rows):?><div class="panel empty-state"><b>人才库暂无记录</b><p>请先新增人才或批量导入人才信息。</p></div><?php endif;foreach($rows as $r):$source=$r['applied_post_id']?'线上测评':'HR 导入';?><article class="panel talent-card"><i><?=e(first_char($r['name']))?></i><div><h3><?=e($r['name'])?></h3><p><?=e($r['edu']?:'学历待补充').' · '.e($r['major']?:'专业待补充')?></p><small><?=e($source)?> · <?=e((string)$r['work_years'])?> 年工作经验</small></div><?php if($r['total_score']!==null):?><strong><?=number_format((float)$r['total_score'],1)?><small>综合分</small></strong><?php endif;?><div class="talent-card-actions"><a class="resume-preview" href="?page=talent_pool&resume_id=<?=$r['id']?>">简历预览</a><?php if($r['review_status']==='final_pass'):?><span class="badge green">面试通过</span><?php elseif($r['interview_registration_status']==='registered'):?><span class="status pending">已登记测评</span><?php elseif($r['interview_registration_status']==='canceled'):?><a href="?page=preregister&candidate_id=<?=$r['id']?>">重新测评登记</a><?php else:?><a href="?page=preregister&candidate_id=<?=$r['id']?>">测评登记</a><?php endif;?></div></article><?php endforeach;?></div></section>
        </div><?php admin_footer();return true;
    }
    if ($page === 'preregister') {
        admin_header('测评登记', 'preregister');
        $selected=(int)($_GET['candidate_id']??0);$posts=$pdo->query("SELECT * FROM post WHERE status IN ('recruiting','reserve') ORDER BY id DESC")->fetchAll();$candidates=$pdo->query("SELECT c.id,c.name,c.mobile,c.edu,c.major FROM candidate c LEFT JOIN interview_pre_register ip ON ip.candidate_id=c.id WHERE (ip.id IS NULL OR ip.status='canceled') AND NOT EXISTS(SELECT 1 FROM answer a JOIN result r ON r.answer_id=a.id WHERE a.candidate_id=c.id AND r.review_status='final_pass') ORDER BY c.id DESC LIMIT 50")->fetchAll();
        $rows=$pdo->query('SELECT ip.*,c.name,c.mobile,c.edu,c.major,p.name post_name FROM interview_pre_register ip JOIN candidate c ON c.id=ip.candidate_id JOIN post p ON p.id=ip.post_id ORDER BY ip.id DESC')->fetchAll();
        page_head('人才管理 / 测评登记','测评登记','HR 从人才库选择已建档人才，指定测评岗位后生成面试前在线测评。','<a class="btn secondary" href="?page=talent_pool">前往人才库</a>'); ?>
        <div class="split"><form class="panel form-panel" method="post" action="?page=preregister&action=interview_pre_save"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="form-title"><h2>新增测评登记</h2><p>只能选择已在人才库建档的人才。</p></div><label>选择人才<select name="candidate_id" data-selected="<?=$selected?>" required><option value="">请选择人才</option><?php foreach($candidates as $c):?><option value="<?=$c['id']?>"><?=e($c['name'].' · '.substr($c['mobile'],0,3).'****'.substr($c['mobile'],-4).' · '.($c['major']?:'专业待补充'))?></option><?php endforeach;?></select></label><label>测评岗位<select name="post_id" required><?php foreach($posts as $p):?><option value="<?=$p['id']?>"><?=e($p['name'].' · '.$p['company'])?></option><?php endforeach;?></select></label><button class="btn primary">确认测评登记</button><p class="form-tip">同一人才只能保留一条有效的测评登记。</p></form><article class="panel table-wrap"><table><thead><tr><th>人才</th><th>测评岗位</th><th>联系方式</th><th>登记状态</th><th>登记时间</th><th>操作</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-cell">暂无测评登记，请先从人才库选择人才。</td></tr><?php endif;foreach($rows as $r):?><tr><td><b><?=e($r['name'])?></b><small><?=e(($r['edu']?:'学历待补充').' · '.($r['major']?:'专业待补充'))?></small></td><td><?=e($r['post_name'])?></td><td><?=e(substr($r['mobile'],0,3).' **** '.substr($r['mobile'],-4))?></td><td><?=status_badge($r['status'])?></td><td><?=e($r['created_at'])?></td><td><?php if($r['status']==='registered'):?><form class="inline compact" method="post" action="?page=preregister&action=interview_pre_update"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="post_id"><?php foreach($posts as $p):?><option value="<?=$p['id']?>" <?=$p['id']==$r['post_id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select><button name="op" value="change_post" class="table-action">修改岗位</button><button name="op" value="cancel" class="table-action danger-text">取消登记</button></form><?php else:?><small>已取消</small><?php endif;?></td></tr><?php endforeach;?></tbody></table></article></div><?php admin_footer();return true;
    }
    return false;
}
