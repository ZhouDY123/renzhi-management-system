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
 $text=assessment_answer_text($value);?>
 <article class="answer-preview-question"><h3><?=e($title)?></h3><?php if($options):?><div class="answer-preview-options"><?php foreach($options as $option):$label=assessment_answer_text($option);$selected=is_array($value)?in_array($label,$value,true):$label===$text;?><span class="<?=$selected?'selected':''?>"><?=$selected?'✓ ':''?><?=e($label)?></span><?php endforeach;?></div><?php endif;?><div class="answer-preview-value"><small>应聘者回答</small><p><?=nl2br(e($text!==''?$text:'未填写'))?></p></div><?php if($hint):?><p class="answer-preview-note"><?=e($hint)?></p><?php endif;?></article>
 <?php
}
function render_assessment_answer_preview(array $detail): void {
 $snapshot=$detail['snapshot'];$profile=$detail['profile'];
 $_GET['standalone']='1';
 admin_header($profile['name'].'-答题详情','preregister');?>
 <div class="answer-document-toolbar"><a class="btn secondary" href="?page=preregister">返回测评记录</a><span>打印窗口中选择“另存为 PDF”即可导出</span><button type="button" class="btn primary" onclick="window.print()">打印 / 导出 PDF</button></div>
 <main class="answer-preview answer-document"><header class="answer-preview-heading"><small>三奇（集团）有限公司</small><h1>应 聘 测 评 答 卷</h1><p><?=e($profile['name'])?> · <?=e($detail['post'].($detail['company']?' · '.$detail['company']:''))?></p><p>提交时间：<?=e($detail['submitted'])?></p></header>
 <section id="answer-profile" class="answer-preview-section"><h2>01　基本信息</h2><dl class="answer-preview-profile"><?php foreach(['name'=>'姓名','mobile'=>'手机号','gender'=>'性别','age'=>'年龄','edu'=>'学历','school_name'=>'毕业学校','major'=>'专业'] as $key=>$label):?><div><dt><?=$label?></dt><dd><?=e(assessment_answer_text($profile[$key]??'')?:'未记录')?></dd></div><?php endforeach;?></dl></section>
 <section id="answer-conditions" class="answer-preview-section"><h2>02　基本条件</h2><?php
 $groups=$snapshot['condition_questions']??[];
 if($groups){foreach($groups as $code=>$rows){render_assessment_answer_item($rows[0]['dim_name'],$snapshot['conditions'][$code]??'',array_column($rows,'tier_label'));$bonus=$rows[0]['bonus_options']??[];foreach($bonus as $id=>$option){$selected=in_array($id,(array)($snapshot['condition_bonus'][$code]??[]),true);render_assessment_answer_item('额外加分 · '.$option['label'],$selected?'已勾选':'未勾选',[], '+'.$option['score'].' 分');}}}
 else{foreach($detail['conditions'] as $condition)render_assessment_answer_item($condition['dim_name'],$condition['matched_tier']);if(!$detail['conditions']){foreach(($snapshot['conditions']??[]) as $code=>$value)render_assessment_answer_item('基本条件（历史编号：'.$code.'）',$value);} ?><p class="answer-preview-note">历史记录未保存完整选项，仅展示交卷时记录的题目与回答。</p><?php }
 foreach(($snapshot['condition_text_responses']??[]) as $text)render_assessment_answer_item($text['dimension'].' · '.$text['question'],$text['value'],[],'追问 · 不计分'.(!empty($text['required'])?' · 必填':''));?>
 </section>
 <?php foreach(['base'=>['03','基本素质'],'post'=>['04','专业题目']] as $scope=>[$number,$label]):?><section id="answer-<?=$scope?>" class="answer-preview-section"><h2><?=$number?>　<?=$label?></h2><?php $count=0;foreach($detail['questions'] as $question){if($question['question_type']!==$scope)continue;$count++;$value=$question['answer_val']??'';$decoded=json_decode($value,true);if(is_array($decoded))$value=$decoded;$options=json_decode($question['options_snapshot']??'[]',true);render_assessment_answer_item($count.'. '.$question['stem_snapshot'],$value,is_array($options)?$options:[], '本题得分 '.$question['score'].' / '.$question['max_score'].($question['score_status']==='manual_pending'?' · 待评分':''));}if(!$count):?><p class="answer-preview-note">本次答卷无此类题目记录。</p><?php endif;?></section><?php endforeach;?>
 <section id="answer-appendix" class="answer-preview-section"><h2>05　附录题目 <small>不计分</small></h2><?php $appendix=$detail['appendix'];if(empty($appendix['questions'])):?><p class="answer-preview-note"><?=e($appendix['state']['description']??'暂无附录记录')?></p><?php else:foreach($appendix['questions'] as $question){render_assessment_answer_item($question['section'].' · '.$question['title'],$question['value'],$question['options']??[],$question['note']!==''?'补充说明：'.$question['note']:null);}endif;?></section>
 <p class="answer-preview-note">只读预览 · 展示本次交卷记录</p></main><?php admin_footer();
}
