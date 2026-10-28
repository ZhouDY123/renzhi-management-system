<?php
function condition_bonus_eligible(array $row): bool {return in_array($row['dim_code']??'',['edu','language'],true)||(bool)preg_match('/学历|英语|英文/u',$row['dim_name']??'');}
function condition_bonus_config(PDO $pdo,string $code): array {$st=$pdo->prepare('SELECT value FROM app_setting WHERE key=?');$st->execute(['condition_bonus:'.$code]);return json_decode((string)$st->fetchColumn(),true)?:[];}
function save_condition_bonus(PDO $pdo,string $code,array $input): void {
 if(!isset($input['condition_bonus_present']))return;
 $st=$pdo->prepare('SELECT dim_code,dim_name FROM scoring_standard WHERE dim_code=? LIMIT 1');$st->execute([$code]);$row=$st->fetch(PDO::FETCH_ASSOC);
 if(!$row||!condition_bonus_eligible($row))throw new RuntimeException('仅学历、英语能力维度可设置加分项');
 $old=condition_bonus_config($pdo,$code);$items=[];$labels=[];
 foreach((array)($input['bonus_label']??[]) as $i=>$label){
  if(!is_string($label))throw new RuntimeException('加分条件格式错误');$label=trim($label);if($label==='')continue;
  $score=$input['bonus_score'][$i]??null;if(!is_numeric($score)||!is_finite((float)$score)||(float)$score<=0||(float)$score>50)throw new RuntimeException('加分值须大于 0 且不超过 50');
  if(in_array($label,$labels,true)||strlen($label)>600)throw new RuntimeException('加分条件不能重复，且最多 200 字');$labels[]=$label;
  $id=(string)($input['bonus_id'][$i]??'');if(!isset($old[$id]))$id='bonus_'.bin2hex(random_bytes(6));if(isset($items[$id]))throw new RuntimeException('加分项重复');
  $items[$id]=['label'=>$label,'score'=>round((float)$score,2)];
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
function render_condition_bonus_editor(PDO $pdo,string $code,array $row): void {
 if(!condition_bonus_eligible($row))return;$options=condition_bonus_config($pdo,$code); ?>
 <section class="condition-bonus-editor"><input type="hidden" name="condition_bonus_present" value="1"><h3>额外加分项</h3><p>应聘者可多选。加分直接计入折算后的基本条件得分，合计不超过 50 分。</p><div data-bonus-rows><?php foreach($options as $id=>$option):?><div class="bonus-edit-row"><input type="hidden" name="bonus_id[]" value="<?=e($id)?>"><input name="bonus_label[]" value="<?=e($option['label'])?>" placeholder="加分条件" maxlength="200" aria-label="加分条件"><input name="bonus_score[]" type="number" min="0.01" max="50" step="0.01" value="<?=e((string)$option['score'])?>" aria-label="加分值"><button type="button" data-remove-bonus>删除</button></div><?php endforeach;?></div><button type="button" class="btn secondary" data-add-bonus>＋ 新增加分项</button></section>
 <?php
}
function render_condition_bonus_fields(string $code,array $row): void {
 $options=$row['bonus_options']??[];if(!$options)return;?>
 <fieldset class="condition-bonus-fields"><legend>额外加分 <small>可多选</small></legend><?php foreach($options as $id=>$option):?><label><input type="checkbox" name="condition_bonus[<?=e($code)?>][]" value="<?=e($id)?>" <?=in_array($id,(array)($_POST['condition_bonus'][$code]??[]),true)?'checked':''?>><span><?=e($option['label'])?></span><em>+<?=e((string)$option['score'])?>分</em></label><?php endforeach;?><p>符合条件可勾选，基本条件总分最高 50 分。</p></fieldset>
 <?php
}
