<?php
require_once __DIR__.'/appendix.php';

function appendix_record_status(?string $status,?string $responses): array {
    if($status==='skipped')return ['label'=>'已跳过','tone'=>'muted','description'=>'求职者已跳过本次附录，未提供补充信息。'];
    if($status==='pending')return ['label'=>'未提交','tone'=>'pending','description'=>'求职者尚未提交本次附录。测评成绩已保存，不受影响。'];
    if($status==='submitted'){
        $filled=count(json_decode($responses??'{}',true)?:[]);
        return $filled?['label'=>'已填写','tone'=>'filled','description'=>'以下为该次测评提交的附录信息。']:['label'=>'未提供','tone'=>'muted','description'=>'求职者已提交附录，但未填写任何补充信息。'];
    }
    return ['label'=>'暂无附录','tone'=>'muted','description'=>'该次测评暂无附录记录，可能提交于附录功能上线前，或未进入附录页。'];
}

function appendix_record_detail(PDO $pdo,int $answerId): ?array {
    $st=$pdo->prepare('SELECT a.id,a.submit_at,c.name,p.name post_name,s.status,s.responses_json,s.questions_json,s.completed_at FROM answer a JOIN candidate c ON c.id=a.candidate_id JOIN post p ON p.id=a.post_id LEFT JOIN appendix_submission s ON s.answer_id=a.id WHERE a.id=?');
    $st->execute([$answerId]);$row=$st->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
    $state=appendix_record_status($row['status'],$row['responses_json']);$questions=[];
    if($row['status']==='submitted'){
        $answers=json_decode($row['responses_json']??'{}',true)?:[];
        foreach(json_decode($row['questions_json']??'[]',true)?:[] as $q){
            $value=$answers[$q['id']]??[];
            $questions[]=['section'=>$q['section'],'title'=>$q['title'],'value'=>$value['value']??'','note'=>$value['note']??''];
        }
    }
    return ['answer_id'=>(int)$row['id'],'name'=>$row['name'],'post_name'=>$row['post_name'],'submit_at'=>$row['submit_at'],'completed_at'=>$row['completed_at'],'state'=>$state,'questions'=>$questions];
}

function render_appendix_detail_dialog(): void {
    ?><dialog class="appendix-modal appendix-detail-modal" aria-labelledby="appendix-detail-title"><header class="appendix-modal-head"><div><small>测评记录 · 自愿登记</small><h2 id="appendix-detail-title">附录详情</h2><p>仅查看本次测评的附录，不计分、不参与录用判断。</p></div><button type="button" data-detail-close class="appendix-close" aria-label="关闭附录详情">×</button></header><div class="appendix-detail-body" aria-live="polite"></div><footer class="appendix-modal-footer"><span>只读信息 · 未提供不影响测评</span><button type="button" class="btn secondary" data-detail-retry hidden>重新加载</button><button type="button" class="btn primary" data-detail-close>关闭</button></footer></dialog><script src="/assets/appendix-detail.js?v=<?=asset_mtime('appendix-detail.js')?>" defer></script><?php
}
