<?php
/** Group by the interview assignment, never by name: repeat interviews stay separate. */
function render_interview_results(array $details): void {
    $groups = [];
    $scoreCount = 0;
    foreach ($details as $detail) {
        $groups[(int)$detail['interview_candidate_id']][] = $detail;
        if ($detail['id']) $scoreCount++;
    }
    $labels = ['strong'=>'强烈推荐录用','recommend'=>'推荐录用','pending'=>'待定 / 需要复试','reject'=>'不推荐录用'];
    ?>
    <section class="panel table-wrap interview-grouped-grid">
      <div class="panel-head"><div><h2>面试官评分明细</h2><small>按应聘者及面试场次归组，逐位对比面试官评价；误提交可重置后重新填写。</small></div><span class="interview-group-summary"><?=count($groups)?> 条面试记录 · <?=$scoreCount?> 份评分</span></div>
      <div class="interview-score-scroll" role="region" aria-label="按应聘者分组的面试评价，可横向滚动查看全部列" tabindex="0">
        <table class="interview-group-table" data-pagination-groups>
          <colgroup><col class="col-person"><col class="col-reviewer"><col class="col-score"><col class="col-recommendation"><col class="col-comment"><col class="col-comment"><col class="col-time"><col class="col-actions"></colgroup>
          <thead><tr><th scope="col">应聘者 / 岗位</th><th scope="col">面试官</th><th scope="col">加权总分</th><th scope="col">录用建议</th><th scope="col">优势与亮点</th><th scope="col">不足与风险</th><th scope="col">提交时间</th><th scope="col">操作</th></tr></thead>
          <?php foreach ($groups as $assignmentId=>$reviews): $person=$reviews[0]; $submitted=count(array_filter($reviews,fn($r)=>!empty($r['id']))); ?>
          <tbody data-interview-group="<?=$assignmentId?>">
            <?php foreach ($reviews as $index=>$d): ?>
            <tr>
              <?php if ($index===0): ?>
              <th scope="rowgroup" rowspan="<?=count($reviews)?>" class="interview-person">
                <b><?=e($person['name'])?></b>
                <span class="interview-person-post"><?=e($person['post_name'])?></span>
                <span class="interview-person-session"><?=e($person['interview_date'])?><?=!empty($person['time_range'])?' · '.e($person['time_range']):''?></span>
                <span class="interview-person-count"><?=$submitted?> 份已提交评分</span>
                <div class="interview-person-questionnaire"><?php if ($person['questionnaire_submitted']): ?><a class="table-action" href="?page=interview_questionnaire&amp;candidate_id=<?=$assignmentId?>">查看问卷</a><?php else: ?><span class="muted">问卷未提交</span><?php endif; ?></div>
              </th>
              <?php endif; ?>
              <td class="interview-reviewer"><?=e($d['real_name']??'')?><?php if (!$d['id']): ?><span class="muted">尚未评分</span><?php endif; ?></td>
              <td class="interview-score-value"><?php if ($d['total_score']!==null): ?><strong><?=number_format((float)$d['total_score']*20,2)?></strong><small> / 100</small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
              <td data-recommendation><?=e($labels[$d['recommendation']]??($d['id']?'历史评分':''))?></td>
              <td class="interview-comment"><?=e($d['strengths']??'')?></td>
              <td class="interview-comment"><?=e($d['risks']??'')?></td>
              <td class="interview-submitted"><?php if ($d['submitted_at']): ?><time datetime="<?=e(str_replace(' ','T',$d['submitted_at']))?>"><span><?=e(substr($d['submitted_at'],0,10))?></span><small><?=e(substr($d['submitted_at'],11))?></small></time><?php endif; ?></td>
              <td class="interview-score-actions"><?php if ($d['id']): ?><form method="post" action="?page=interview_results&amp;action=score_reset" data-confirm-message="确认重置这条面试评分？"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="score_id" value="<?=(int)$d['id']?>"><button class="table-action danger-text">重置</button></form><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <?php endforeach; ?>
          <?php if (!$groups): ?><tbody><tr><td colspan="8" class="interview-results-empty"><b>暂无面试评价</b><p>应聘者提交问卷或面试官提交评分后，将在此按人展示。</p></td></tr></tbody><?php endif; ?>
        </table>
      </div>
    </section>
    <?php
}
