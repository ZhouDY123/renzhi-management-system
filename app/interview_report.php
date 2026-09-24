<?php
declare(strict_types=1);

function interview_report_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS interview_pass_report (result_id INTEGER PRIMARY KEY, snapshot TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime(\'now\',\'localtime\')), FOREIGN KEY(result_id) REFERENCES result(id) ON DELETE CASCADE)');
}

// Freeze the evidence at final approval; later score resets must not rewrite it.
function interview_pass_report(PDO $pdo, int $id): ?array {
    $st=$pdo->prepare('SELECT r.*,c.name,c.mobile,p.name post_name,p.company FROM result r JOIN answer a ON a.id=r.answer_id JOIN candidate c ON c.id=a.candidate_id JOIN post p ON p.id=a.post_id WHERE r.id=? AND r.review_status=\'final_pass\'');
    $st->execute([$id]); $person=$st->fetch();
    if(!$person)return null;
    $st=$pdo->prepare('SELECT snapshot FROM interview_pass_report WHERE result_id=?');$st->execute([$id]);
    if($saved=$st->fetchColumn())return json_decode($saved,true,512,JSON_THROW_ON_ERROR);
    $st=$pdo->prepare("SELECT s.*,ic.id assignment_id FROM interview_candidate ic JOIN interview_session s ON s.id=ic.session_id WHERE ic.answer_id=? AND s.status='done' ORDER BY s.interview_date DESC,s.id DESC LIMIT 1");
    $st->execute([$person['answer_id']]);$session=$st->fetch()?:null;
    $scores=[];$weighted=0;$weights=0;
    if($session){
        $st=$pdo->prepare('SELECT u.real_name,si.weight,COALESCE(sc.total_score,(sc.suzhi_score+sc.postq_score)/40.0) score,sc.strengths,sc.risks,sc.recommendation,sc.comment,sc.submitted_at FROM interview_score sc JOIN session_interviewer si ON si.id=sc.interviewer_id JOIN user u ON u.id=si.user_id WHERE sc.interview_candidate_id=? ORDER BY sc.id');
        $st->execute([$session['assignment_id']]);$scores=$st->fetchAll();
        foreach($scores as $score){$weighted+=(float)$score['score']*(float)$score['weight'];$weights+=(float)$score['weight'];}
    }
    $st=$pdo->prepare("SELECT rv.note,rv.created_at,u.real_name FROM review rv JOIN user u ON u.id=rv.user_id WHERE rv.result_id=? AND rv.opinion='final_pass' ORDER BY rv.id DESC LIMIT 1");$st->execute([$id]);
    $report=['person'=>$person,'session'=>$session,'scores'=>$scores,'score'=>$weights>0?round($weighted/$weights,2):null,'review'=>$st->fetch()?:null];
    $pdo->prepare('INSERT OR IGNORE INTO interview_pass_report(result_id,snapshot) VALUES(?,?)')->execute([$id,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    return $report;
}

function render_interview_pass_report(PDO $pdo,int $id): void {
    $report=interview_pass_report($pdo,$id);
    header('Cache-Control: no-store');
    if(!$report){http_response_code(404);echo '该人员尚未面试通过，暂无面试报告。';return;}
    $p=$report['person'];$s=$report['session'];$review=$report['review'];
    $recommendations=['strong'=>'强烈推荐录用','recommend'=>'推荐录用','pending'=>'待定 / 需要复试','reject'=>'不推荐录用'];
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($p['name'])?> · 面试通过报告</title><style>
    *{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#18283e;font:15px/1.7 "Microsoft YaHei",sans-serif}.toolbar{max-width:900px;margin:24px auto;text-align:right}button{background:#2852cc;color:white;border:0;border-radius:8px;padding:12px 26px;cursor:pointer}.paper{max-width:900px;margin:0 auto 40px;background:white;padding:48px 56px;box-shadow:0 8px 30px #15274412}header{text-align:center;border-bottom:2px solid #203a60;padding-bottom:24px}h1{font-size:28px;letter-spacing:4px;margin:12px 0}h2{font-size:18px;margin:28px 0 12px}small,.muted{color:#65758a}.info{display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;margin:24px 0}.info b{margin-right:12px}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #d9e1eb;padding:12px;text-align:left;overflow-wrap:anywhere}th{background:#f4f7fb}.notes{white-space:pre-wrap;overflow-wrap:anywhere}.verdict{padding:18px;background:#eef8f4;border-left:4px solid #238269}footer{margin-top:28px;font-size:12px;color:#65758a}@media(max-width:600px){.paper{padding:24px 18px}.toolbar{margin:16px}.info{grid-template-columns:1fr}th,td{padding:8px}}@page{size:A4;margin:16mm}@media print{body{background:white;color:black}.toolbar{display:none}.paper{max-width:none;margin:0;padding:0;box-shadow:none}h2,header{break-after:avoid}tr,.verdict{break-inside:avoid}thead{display:table-header-group}}
    </style></head><body><div class="toolbar"><button type="button" onclick="window.print()">打印报告</button></div><main class="paper"><header><div>三奇（集团）有限公司</div><h1>面试通过报告</h1><small>报告编号：IP-<?=str_pad((string)$id,6,'0',STR_PAD_LEFT)?></small></header>
    <div class="info"><div><b>姓名</b><?=e($p['name'])?></div><div><b>手机号码</b><?=e($p['mobile'])?></div><div><b>应聘岗位</b><?=e($p['post_name'])?></div><div><b>所属单位</b><?=e($p['company']??'—')?></div><div><b>面试日期</b><?=e($s['interview_date']??'历史记录未保留')?></div><div><b>面试地点</b><?=e($s['location']??'—')?></div></div>
    <h2>一、测评与面试成绩</h2><table><thead><tr><th>测评总分</th><th>面试分数</th></tr></thead><tbody><tr><td><?=number_format((float)$p['total_score'],1)?> / 100</td><td><?=$report['score']===null?'暂无评分记录':number_format($report['score']*20,2).' / 100'?></td></tr></tbody></table><p class="muted">面试分数取最近一次已完成场次，按各面试官权重加权计算；统一换算为百分制，与测评总分分别展示。</p>
    <h2>二、面试官评价</h2><?php if(!$report['scores']):?><p>历史记录暂无面试评分明细。</p><?php endif;?><?php foreach($report['scores'] as $score):?><section><h3><?=e($score['real_name'])?> · <?=number_format((float)$score['score']*20,2)?> / 100 <small>（权重 <?=e((string)$score['weight'])?>）</small></h3><p>录用建议：<?=e($recommendations[$score['recommendation']]??'历史评分')?></p><p class="notes"><b>优势与亮点：</b><?=e($score['strengths']?:'未填写')?></p><p class="notes"><b>不足与风险：</b><?=e($score['risks']?:'未填写')?></p></section><?php endforeach;?>
    <h2>三、终审结论</h2><div class="verdict"><strong>面试通过，纳入人才库</strong><div class="notes"><?=e($review['note']??'未填写终审意见')?></div></div><div class="info"><div><b>审核人</b><?=e($review['real_name']??'历史记录未保留')?></div><div><b>通过时间</b><?=e($review['created_at']??'—')?></div></div><footer>本报告由系统根据终审通过记录生成并存档，仅供内部招聘管理使用。</footer></main></body></html><?php
}
