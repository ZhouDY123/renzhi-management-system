<?php
require __DIR__.'/../app/interview_results_view.php';
function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function csrf(): string { return 'fixture-csrf'; }
$rows=[];
for($group=1;$group<=11;$group++) {
    for($review=1;$review<=($group===11?3:2);$review++) {
        $rows[]=['id'=>$group*10+$review,'interview_candidate_id'=>$group,'name'=>$group<=2?'同名应聘者':'应聘者'.$group,
            'post_name'=>'客服专员 · 三奇集团','interview_date'=>'2026-11-05','time_range'=>'14:00-16:00',
            'real_name'=>$review===1?'刘雪':'张春国','total_score'=>$review===1?3.05:4,
            'recommendation'=>$review===1?'reject':'pending','strengths'=>$group===1?str_repeat('有客服经验，沟通清晰。',8):'有客服经验',
            'risks'=>'待核实 <script>alert(1)</script>','submitted_at'=>'2026-11-05 10:13:44','questionnaire_submitted'=>$group%2===0];
    }
}
$rows[]=['id'=>null,'interview_candidate_id'=>12,'name'=>'仅提交问卷','post_name'=>'前台接待 · 三奇集团',
    'interview_date'=>'2026-11-06','time_range'=>'09:00-10:00','real_name'=>null,'total_score'=>null,
    'recommendation'=>null,'strengths'=>null,'risks'=>null,'submitted_at'=>null,'questionnaire_submitted'=>1];
ob_start();render_interview_results($rows);$html=ob_get_clean();
if(substr_count($html,'data-interview-group=')!==12)throw new Exception('Assignments merged');
if(substr_count($html,'rowspan="3"')!==1)throw new Exception('Three-reviewer group missing');
if(substr_count($html,'<b>同名应聘者</b>')!==2)throw new Exception('Same-name assignments merged');
if(str_contains($html,'<script>'))throw new Exception('Unescaped review');
if(!str_contains($html,'尚未评分'))throw new Exception('Questionnaire-only candidate missing');
ob_start();render_interview_results([]);$empty=ob_get_clean();
if(!str_contains($empty,'暂无面试评价'))throw new Exception('Empty state missing');
echo in_array('--html',$argv,true)?$html:"PASS: assignment groups, same-name isolation, three reviewers, questionnaire-only, escaping, empty state\n";
