<?php
require __DIR__.'/answer-preview.php';require __DIR__.'/../app/answer_pdf.php';
$d['result']=['total_score'=>86.5,'eval_score'=>43,'suzhi_score'=>22,'postq_score'=>21.5,'score_status'=>'completed'];
ob_start();render_assessment_answer_preview($d);$html=ob_get_clean();
if(!str_contains($html,'测评总分：86.5 / 100 分')||!str_contains($html,'download=pdf'))throw new Exception('Score/download missing');
$pdf=assessment_pdf_from_html($html);
if(!str_starts_with($pdf,'%PDF-1.4')||!str_ends_with($pdf,"%%EOF\n"))throw new Exception('Invalid PDF envelope');
foreach(['附录回答快照','86.5','专业题快照'] as $text)if(!str_contains($pdf,strtoupper(bin2hex(iconv('UTF-8','UTF-16BE',$text)))))throw new Exception('PDF content missing');
if(str_contains($pdf,strtoupper(bin2hex(iconv('UTF-8','UTF-16BE','返回测评记录')))))throw new Exception('Toolbar leaked into PDF');
$long=assessment_pdf_document([['text'=>str_repeat('中文长回答',3000),'size'=>11,'bold'=>false,'center'=>false,'space'=>8]]);
if(substr_count($long,'/Type /Page ')<2)throw new Exception('Long answers not paginated');
$d['result']['score_status']='manual_pending';ob_start();render_assessment_answer_preview($d);$pending=ob_get_clean();if(!str_contains($pending,'测评总分：待评分'))throw new Exception('Pending score misrepresented');
echo "PASS: direct PDF content, score summary, pending status, toolbar exclusion and long-answer pagination\n";
