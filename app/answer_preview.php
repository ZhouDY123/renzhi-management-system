<?php
require_once __DIR__.'/appendix_detail.php';

function assessment_answer_detail(PDO $pdo,int $id): ?array {
 $st=$pdo->prepare('SELECT a.*,c.name candidate_name,c.mobile candidate_mobile,p.name current_post_name FROM answer a JOIN candidate c ON c.id=a.candidate_id JOIN post p ON p.id=a.post_id WHERE a.id=?');$st->execute([$id]);$answer=$st->fetch(PDO::FETCH_ASSOC);if(!$answer)return null;
 $snapshot=json_decode($answer['candidate_snapshot'],true)?:[];$post=json_decode($answer['post_snapshot'],true)?:[];
 $profile=array_merge($snapshot,is_array($snapshot['registration_profile']??null)?$snapshot['registration_profile']:[]);
 $profile['name']=$snapshot['name']??$answer['candidate_name'];$profile['mobile']=$snapshot['mobile']??$answer['candidate_mobile'];
 $st=$pdo->prepare('SELECT * FROM answer_detail WHERE answer_id=? ORDER BY id');$st->execute([$id]);$questions=$st->fetchAll(PDO::FETCH_ASSOC);
 $st=$pdo->prepare('SELECT * FROM eval_score_detail WHERE answer_id=? ORDER BY id');$st->execute([$id]);$conditions=$st->fetchAll(PDO::FETCH_ASSOC);
 $st=$pdo->prepare('SELECT * FROM result WHERE answer_id=?');$st->execute([$id]);$result=$st->fetch(PDO::FETCH_ASSOC)?:[];
 $appendix=appendix_record_detail($pdo,$id);
 if($appendix&&empty($appendix['questions'])){
  $st=$pdo->prepare('SELECT questions_json FROM appendix_submission WHERE answer_id=?');$st->execute([$id]);
  foreach(json_decode((string)$st->fetchColumn(),true)?:[] as $q)$appendix['questions'][]=['section'=>$q['section'],'title'=>$q['title'],'options'=>json_decode($q['options_json']??'[]',true)?:[],'value'=>'','note'=>''];
 }
 return ['id'=>$id,'profile'=>$profile,'post'=>$post['name']??$answer['current_post_name'],'company'=>$post['company']??'','submitted'=>$answer['submit_at'],'snapshot'=>$snapshot,'questions'=>$questions,'conditions'=>$conditions,'result'=>$result,'appendix'=>$appendix];
}
function assessment_answer_text(mixed $value): string {
 if(is_array($value))return implode('、',array_map('assessment_answer_text',$value));
 if(!is_scalar($value)&&$value!==null)return '';
 return (string)$value;
}
function render_assessment_answer_item(string $title,mixed $value,array $options=[],?string $hint=null): void {
 $text=assessment_answer_text($value);$labels=array_map('assessment_answer_text',$options);$picked=is_array($value)?array_map('assessment_answer_text',$value):[$text];$answerShown=$text!==''&&$labels&&!array_diff($picked,$labels);?>
 <article class="answer-preview-question"><h3><?=e($title)?></h3><?php if($options):?><div class="answer-preview-options"><?php foreach($options as $option):$label=assessment_answer_text($option);$selected=is_array($value)?in_array($label,$value,true):$label===$text;?><span class="<?=$selected?'selected':''?>"><?=$selected?'✓ ':''?><?=e($label)?></span><?php endforeach;?></div><?php endif;?><?php if(!$answerShown):?><div class="answer-preview-value"><small>应聘者回答</small><p><?=nl2br(e($text!==''?$text:'未填写'))?></p></div><?php endif;?><?php if($hint):?><p class="answer-preview-note"><?=e($hint)?></p><?php endif;?></article>
 <?php
}
function render_assessment_bonus_line(string $label,bool $selected,float $score): void { ?>
 <div class="answer-bonus-line"><b>补充条件：</b><span class="answer-bonus-choice <?=$selected?'is-selected':''?>"><span aria-label="<?=$selected?'已勾选':'未勾选'?>"><?=$selected?'☑':'☐'?></span> <?=e($label)?></span><small>（+<?=e((string)$score)?> 分）</small></div>
 <?php
}
function render_assessment_answer_preview(array $detail): void {
 $snapshot=$detail['snapshot'];$profile=$detail['profile'];
 $_GET['standalone']='1';
 admin_header($profile['name'].'-答题详情','preregister');?>
 <div class="answer-document-toolbar"><a class="btn secondary" href="?page=preregister">返回测评记录</a><span></span><button type="button" class="btn secondary" onclick="window.print()">打印</button><a class="btn primary" href="?page=preregister&amp;answer_id=<?=$detail['id']?>&amp;download=pdf">导出 PDF</a></div>
 <main class="answer-preview answer-document"><header class="answer-preview-heading"><small>三奇（集团）有限公司</small><h1>应 聘 测 评 答 卷</h1><p><?=e($profile['name'])?> · <?=e($detail['post'].($detail['company']?' · '.$detail['company']:''))?></p><p>提交时间：<?=e($detail['submitted'])?></p></header>
 <?php $scores=$detail['result'];$pending=($scores['score_status']??'')!=='completed';?>
 <div class="answer-score-summary"><b>测评总分：<?=$scores?($pending?'待评分':number_format((float)$scores['total_score'],1).' / 100 分'):'未生成'?></b><?php if($scores):?><p>基本条件 <?=number_format((float)$scores['eval_score'],1)?> / 50　 ·　 基本素质 <?=number_format((float)$scores['suzhi_score'],1)?> / 25　 ·　 专业能力 <?=number_format((float)$scores['postq_score'],1)?> / 25</p><?php endif;?></div>
 <section id="answer-profile" class="answer-preview-section"><h2>01　基本信息</h2><dl class="answer-preview-profile"><?php foreach(['name'=>'姓名','mobile'=>'手机号','gender'=>'性别','age'=>'年龄','edu'=>'学历','school_name'=>'毕业学校','major'=>'专业'] as $key=>$label):?><div><dt><?=$label?></dt><dd><?=e(assessment_answer_text($profile[$key]??'')?:'未记录')?></dd></div><?php endforeach;?></dl></section>
 <section id="answer-conditions" class="answer-preview-section"><h2>02　基本条件</h2><?php
 $groups=$snapshot['condition_questions']??[];$followups=$snapshot['condition_text_responses']??[];$shown=[];
 $renderFollowup=function(string $key)use($followups,&$shown):void{if(!isset($followups[$key])||isset($shown[$key]))return;$shown[$key]=true;$text=$followups[$key];echo '<div class="answer-preview-followup">';render_assessment_answer_item('追问：'.$text['question'],$text['value'],[],!empty($text['required'])?'必填 · 不计分':'选填 · 不计分');echo '</div>';};
 if($groups){foreach($groups as $code=>$rows){echo '<div class="answer-question-group">';render_assessment_answer_item($rows[0]['dim_name'],$snapshot['conditions'][$code]??'',array_column($rows,'tier_label'));$renderFollowup((string)$code);echo '</div>';$bonus=$rows[0]['bonus_options']??[];foreach($bonus as $id=>$option){$selected=in_array($id,(array)($snapshot['condition_bonus'][$code]??[]),true);echo '<div class="answer-question-group">';render_assessment_bonus_line($option['label'],$selected,(float)$option['score']);$renderFollowup('bonus:'.$code.':'.$id);echo '</div>';}}}
 else{foreach($detail['conditions'] as $condition){echo '<div class="answer-question-group">';if(str_contains($condition['dim_name'],'加分（折算后）'))render_assessment_bonus_line($condition['matched_tier'],true,(float)$condition['score']);else render_assessment_answer_item($condition['dim_name'],$condition['matched_tier']);$renderFollowup((string)$condition['dim_code']);$renderFollowup('bonus:'.$condition['dim_code']);echo '</div>';}if(!$detail['conditions']){foreach(($snapshot['conditions']??[]) as $code=>$value){render_assessment_answer_item('基本条件（历史编号：'.$code.'）',$value);$renderFollowup((string)$code);}} ?><p class="answer-preview-note">历史记录未保存完整选项，仅展示交卷时记录的题目与回答。</p><?php }
 // Preserve older follow-ups whose parent identifier was not recorded.
 foreach($followups as $key=>$text)if(!isset($shown[$key]))render_assessment_answer_item($text['dimension'].' · '.$text['question'],$text['value'],[],'追问 · 不计分'.(!empty($text['required'])?' · 必填':''));?>
 </section>
 <?php foreach(['base'=>['03','基本素质'],'post'=>['04','专业题目']] as $scope=>[$number,$label]):?><section id="answer-<?=$scope?>" class="answer-preview-section"><h2><?=$number?>　<?=$label?></h2><?php $count=0;foreach($detail['questions'] as $question){if($question['question_type']!==$scope)continue;$count++;$value=$question['answer_val']??'';$decoded=json_decode($value,true);if(is_array($decoded))$value=$decoded;$options=json_decode($question['options_snapshot']??'[]',true);render_assessment_answer_item($count.'. '.$question['stem_snapshot'],$value,is_array($options)?$options:[], '本题得分 '.$question['score'].' / '.$question['max_score'].($question['score_status']==='manual_pending'?' · 待评分':''));}if(!$count):?><p class="answer-preview-note">本次答卷无此类题目记录。</p><?php endif;?></section><?php endforeach;?>
 <section id="answer-appendix" class="answer-preview-section"><h2>05　附录题目 <small>不计分</small></h2><?php $appendix=$detail['appendix'];if(empty($appendix['questions'])):?><p class="answer-preview-note"><?=e($appendix['state']['description']??'暂无附录记录')?></p><?php else:foreach($appendix['questions'] as $question){render_assessment_answer_item($question['section'].' · '.$question['title'],$question['value'],$question['options']??[],$question['note']!==''?'补充说明：'.$question['note']:null);}endif;?></section>
 <p class="answer-preview-note">只读预览 · 展示本次交卷记录</p></main><?php admin_footer();
}
