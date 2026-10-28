<?php
require __DIR__.'/answer-preview.php';
$d['result']=['total_score'=>86.5,'eval_score'=>43,'suzhi_score'=>22,'postq_score'=>21.5,'score_status'=>'completed'];
ob_start();render_assessment_answer_preview($d);$html=ob_get_clean();
if(!str_contains($html,'测评总分：86.5 / 100 分')||!str_contains($html,'id="answer-export-pdf"') || !str_contains($html,'/assets/answer-export.js'))throw new Exception('Score/download missing');
$d['result']['score_status']='manual_pending';ob_start();render_assessment_answer_preview($d);$pending=ob_get_clean();if(!str_contains($pending,'测评总分：待评分'))throw new Exception('Pending score misrepresented');
echo "PASS: PDF export control, score summary and pending status\n";
