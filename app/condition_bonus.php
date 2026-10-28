<?php
function condition_bonus_config(PDO $pdo,string $code): array {$st=$pdo->prepare('SELECT value FROM app_setting WHERE key=?');$st->execute(['condition_bonus:'.$code]);return json_decode((string)$st->fetchColumn(),true)?:[];}
function save_condition_bonus(PDO $pdo,string $code,array $input): void {
 if(!isset($input['condition_bonus_present']))return;
 $st=$pdo->prepare('SELECT dim_code,dim_name FROM scoring_standard WHERE dim_code=? LIMIT 1');$st->execute([$code]);$row=$st->fetch(PDO::FETCH_ASSOC);
 if(!$row)throw new RuntimeException('评分维度不存在');
 $old=condition_bonus_config($pdo,$code);$items=[];$labels=[];
 foreach((array)($input['bonus_label']??[]) as $i=>$label){
  if(!is_string($label))throw new RuntimeException('加分条件格式错误');$label=trim($label);if($label==='')continue;
  $score=$input['bonus_score'][$i]??null;if(!is_numeric($score)||!is_finite((float)$score)||(float)$score<=0||(float)$score>50)throw new RuntimeException('加分值须大于 0 且不超过 50');
  if(in_array($label,$labels,true)||strlen($label)>600)throw new RuntimeException('加分条件不能重复，且最多 200 字');$labels[]=$label;
  $id=(string)($input['bonus_id'][$i]??'');if(!isset($old[$id]))$id='bonus_'.bin2hex(random_bytes(6));if(isset($items[$id]))throw new RuntimeException('加分项重复');
  $question=$input['bonus_question'][$i]??($old[$id]['question']??'');
  if(!is_string($question)||strlen($question)>2000)throw new RuntimeException('加分项追问格式错误或过长');
  $question=trim($question);$required=($input['bonus_required'][$i]??(!empty($old[$id]['required'])?'1':'0'))==='1';
  $items[$id]=['label'=>$label,'score'=>round((float)$score,2),'question'=>$question,'required'=>$question!==''&&$required];
 }
 $pdo->prepare("INSERT INTO app_setting(key,value,label) VALUES(?,?,'基本条件加分项') ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute(['condition_bonus:'.$code,json_encode($items,JSON_UNESCAPED_UNICODE)]);
}
function selected_condition_bonuses(array $data,array $groups): array {
 $input=$data['condition_bonus']??[];if(!is_array($input))throw new InvalidArgumentException('加分项格式错误');$selected=[];
 foreach($input as $code=>$ids){
  if(!isset($groups[$code])||!is_array($ids))throw new InvalidArgumentException('加分项无效，请刷新后重试');
  $options=$groups[$code][0]['bonus_options']??[];
  if(array_filter($ids,fn($id)=>!is_string($id)))throw new InvalidArgumentException('加分项格式错误');
  foreach(array_unique($ids) as $id){if(!is_string($id)||!isset($options[$id]))throw new InvalidArgumentException('加分项无效，请刷新后重试');$selected[]=['code'=>$code,'dimension'=>$groups[$code][0]['dim_name'],'id'=>$id,...$options[$id]];}
 }
 return $selected;
}
function condition_bonus_text_responses(array $data,array $groups): array {
 $answers=[];
 foreach(selected_condition_bonuses($data,$groups) as $bonus){
  $question=$bonus['question']??'';if($question==='')continue;
  $value=$data['condition_bonus_text'][$bonus['code']][$bonus['id']]??'';
  if(!is_string($value)||strlen($value)>8000)throw new InvalidArgumentException('文本回答格式不正确或过长：'.$question);
  $value=trim($value);if(!empty($bonus['required'])&&$value==='')throw new InvalidArgumentException('请填写：'.$question);
  $answers['bonus:'.$bonus['code'].':'.$bonus['id']]=['dimension'=>$bonus['dimension'].' · 加分项：'.$bonus['label'],'option'=>$bonus['label'],'question'=>$question,'required'=>!empty($bonus['required']),'value'=>$value];
 }
 return $answers;
}
function render_condition_bonus_row(string $id,array $option): void { ?>
 <div class="bonus-edit-row"><input type="hidden" name="bonus_id[]" value="<?=e($id)?>"><input name="bonus_label[]" value="<?=e($option['label']??'')?>" placeholder="加分条件" maxlength="200" aria-label="加分条件"><input name="bonus_score[]" type="number" min="0.01" max="50" step="0.01" value="<?=e((string)($option['score']??1))?>" aria-label="加分值"><button type="button" data-bonus-followup aria-expanded="false"><?=empty($option['question'])?'＋追问':(!empty($option['required'])?'已设置 · 必填':'已设置追问')?></button><button type="button" data-remove-bonus>删除</button><div class="bonus-followup-editor" hidden><label>追问内容 <small>勾选此加分项后显示，不计分；留空取消</small><textarea name="bonus_question[]" rows="2" maxlength="500" placeholder="请输入追问内容"><?=e($option['question']??'')?></textarea></label><label class="bonus-required-control">填写要求<select name="bonus_required[]"><option value="0">选填</option><option value="1" <?=!empty($option['required'])?'selected':''?>>必填</option></select></label></div></div>
 <?php }
function render_condition_bonus_editor(PDO $pdo,string $code,array $row): void {
 $options=condition_bonus_config($pdo,$code); ?>
 <section class="condition-bonus-editor"><input type="hidden" name="condition_bonus_present" value="1"><div class="bonus-editor-heading"><h3>额外加分项</h3><button type="button" data-add-bonus>＋ 添加</button></div><div class="bonus-column-labels"><span>加分条件</span><span>加分值</span><span>追问</span><span></span></div><div data-bonus-rows><?php foreach($options as $id=>$option)render_condition_bonus_row($id,$option);?></div><template data-bonus-template><?php render_condition_bonus_row('',[]);?></template><p>应聘者可多选，加分计入折算后的基本条件得分，合计不超过 50 分。</p></section>
 <?php
}
function render_condition_bonus_fields(string $code,array $row): void {
 $options=$row['bonus_options']??[];if(!$options)return;?>
 <fieldset class="condition-bonus-fields"><legend>补充条件 <small>可多选</small></legend><?php foreach($options as $id=>$option):$checked=in_array($id,(array)($_POST['condition_bonus'][$code]??[]),true);$question=$option['question']??'';?><div class="bonus-answer-item"><label><input type="checkbox" data-bonus-choice name="condition_bonus[<?=e($code)?>][]" value="<?=e($id)?>" <?=$checked?'checked':''?>><span><?=e($option['label'])?></span></label><?php if($question!==''):?><div class="bonus-text-answer" <?=$checked?'':'hidden'?>><label><span class="condition-followup-heading"><?php if(!empty($option['required'])):?><span class="condition-required-star">*</span><?php endif;?><span><?=e($question)?></span><small><?=!empty($option['required'])?'必填':'选填'?></small></span><textarea name="condition_bonus_text[<?=e($code)?>][<?=e($id)?>]" rows="3" maxlength="2000" data-required="<?=!empty($option['required'])?'1':'0'?>" <?=$checked?(!empty($option['required'])?'required':''):'disabled'?> placeholder="请填写您的回答"><?=e(is_string($_POST['condition_bonus_text'][$code][$id]??null)?$_POST['condition_bonus_text'][$code][$id]:'')?></textarea></label><small class="condition-followup-note">请根据实际情况补充说明</small></div><?php endif;?></div><?php endforeach;?><p>请选择符合自身情况的条件。</p></fieldset>
 <?php
}
