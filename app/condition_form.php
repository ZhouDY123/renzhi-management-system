<?php
require_once __DIR__.'/condition_order.php';
function save_condition_tier_order(PDO $pdo,string $code,mixed $order,array $newIds): void {
    if(!is_array($order))throw new RuntimeException('档位顺序格式不正确');
    $st=$pdo->prepare("SELECT id,sort FROM scoring_standard WHERE dim_code=? AND status!='retired'");$st->execute([$code]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);$ids=array_map('intval',array_column($rows,'id'));$sorted=[];
    foreach($order as $token){if(!is_string($token)||!preg_match('/^(existing|new):(\d+)$/D',$token,$m))throw new RuntimeException('档位顺序无效');$id=$m[1]==='new'?($newIds[(int)$m[2]]??null):(int)$m[2];if($id!==null)$sorted[]=$id;}
    if(count($sorted)!==count($ids)||count(array_unique($sorted))!==count($sorted)||array_diff($sorted,$ids))throw new RuntimeException('档位已变化，请刷新后重试');
    $base=$rows?min(array_column($rows,'sort')):0;$update=$pdo->prepare('UPDATE scoring_standard SET sort=? WHERE id=? AND dim_code=?');foreach($sorted as $index=>$id)$update->execute([$base+$index,$id,$code]);
}
function rename_condition_dimension(PDO $pdo,string $code,mixed $name): void {
    if(!is_string($name)||trim($name)===''||strlen($name)>600)throw new RuntimeException('请填写有效的维度名称（不超过 200 字）');
    $name=trim($name);
    $check=$pdo->prepare("SELECT 1 FROM scoring_standard WHERE dim_code<>? AND dim_name=? AND status!='retired' LIMIT 1");$check->execute([$code,$name]);
    if($check->fetchColumn())throw new RuntimeException('已存在同名评分维度，请使用其他名称');
    $pdo->prepare("UPDATE scoring_standard SET dim_name=? WHERE dim_code=? AND status!='retired'")->execute([$name,$code]);
}
function condition_text_config(PDO $pdo,string $code): array {
    $st=$pdo->prepare('SELECT value FROM app_setting WHERE key=?');$st->execute(['condition_text:'.$code]);
    $config=json_decode((string)$st->fetchColumn(),true);
    return is_array($config)?$config:['question'=>'','required'=>false];
}
function save_condition_text(PDO $pdo,string $code,mixed $questions,mixed $required): void {
    $st=$pdo->prepare("SELECT id,tier_label FROM scoring_standard WHERE dim_code=? AND category!='basic_quality' AND status!='retired'");$st->execute([$code]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows||!is_array($questions)||!is_array($required))throw new RuntimeException('文本问题配置无效');
    $tiers=[];foreach($rows as $row){$question=$questions[$row['id']]??'';if(!is_string($question)||strlen($question)>2000)throw new RuntimeException('文本问题格式不正确或过长');$question=trim($question);if($question!=='')$tiers[$row['tier_label']]=['question'=>$question,'required'=>isset($required[$row['id']])];}
    $pdo->prepare("INSERT INTO app_setting(key,value,label) VALUES(?,?,'基本条件文本问题') ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute(['condition_text:'.$code,json_encode(['tiers'=>$tiers],JSON_UNESCAPED_UNICODE)]);
}
function selected_condition_text(array $rows,mixed $answer): array {
    if(!is_string($answer))return [];
    $config=$rows[0]['text_question']??[];
    return isset($config['tiers'])?($config['tiers'][$answer]??[]):$config;
}
function condition_text_responses(array $data,array $groups): array {
    $answers=[];
    foreach($groups as $code=>$rows){$config=selected_condition_text($rows,$data['conditions'][$code]??null);$question=$config['question']??'';if($question==='')continue;
        $value=$data['condition_text'][$code]??'';
        if(!is_string($value)||strlen($value)>8000)throw new InvalidArgumentException('文本回答格式不正确或过长：'.$question);
        $value=trim($value);if(!empty($config['required'])&&$value==='')throw new InvalidArgumentException('请填写：'.$question);
        $answers[$code]=['dimension'=>$rows[0]['dim_name'],'option'=>$data['conditions'][$code]??'','question'=>$question,'required'=>!empty($config['required']),'value'=>$value];
    }
    return $answers;
}
// Match explicit identity dimensions, not incidental words in job qualifications.
function condition_is_job_related(array $rows): bool {
    // if(in_array($rows[0]['dim_code']??'',['age','health','politics','gender','blood_type','marital_status','religion','ethnicity'],true))return false;
    // $identityNames=['年龄','周岁年龄','出生日期','性别','政治面貌','政治身份','党员','中共党员','党派','党籍','宗教','宗教信仰','民族','种族','肤色','健康','健康状况','身体状况','慢性病','残疾','血型','婚姻','婚姻状况','婚育状况','怀孕','生育计划','家庭结构'];
    // foreach(array_column($rows,'dim_name') as $name){
    //     $name=preg_replace('/[\s：:？?（）()]/u','',(string)$name);
    //     $name=preg_replace('/^(?:是否是|是否为|是否)/u','',$name);
    //     if(in_array($name,$identityNames,true))return false;
    // }
    return true;
}
// Dimensions are independently published, not restricted to an import prefix or one global version.
function document_condition_groups(PDO $pdo): array {
    $st=$pdo->query("SELECT s.* FROM scoring_standard s WHERE s.status='published' AND s.category!='basic_quality' AND s.version=(SELECT MAX(v.version) FROM scoring_standard v WHERE v.dim_code=s.dim_code AND v.status='published' AND v.category!='basic_quality') ORDER BY s.sort,s.id");$groups=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$groups[$row['dim_code']][]=$row;
    foreach($groups as $code=>&$rows){$config=condition_text_config($pdo,$code);$rows[0]['text_question']=isset($config['tiers'])?$config:['tiers'=>[]];}unset($rows);
    return order_condition_groups($pdo,array_filter($groups,'condition_is_job_related'));
}
function condition_maxima(array $groups): array {
    $maxima=[];foreach($groups as $code=>$rows)$maxima[$code]=max(array_map(fn($row)=>(float)$row['tier_value'],$rows));return $maxima;
}
function validate_document_conditions(array $data,array $groups): void {
    condition_text_responses($data,$groups);
    if(!$groups)throw new InvalidArgumentException('暂无已启用的岗位相关基本条件题目，请联系 HR。');
    foreach($groups as $code=>$rows){$answer=$data['conditions'][$code]??null;
        if(!is_string($answer)||!in_array($answer,array_column($rows,'tier_label'),true))throw new InvalidArgumentException('请完成基本条件：'.$rows[0]['dim_name']);
    }
}
function render_document_conditions(array $groups): void {
    $rawMax=rtrim(rtrim(number_format(array_sum(condition_maxima($groups)),2,'.',''),'0'),'.');
    ?><section class="document-conditions"><h3>基本条件测评</h3><p>按实际情况选择，每项只取一档；工作履历在全部经历中选择最高一档，不重复累加。工具能力由本人自评，本部分原始满分<?=e($rawMax)?>分，折算为50分。</p><?php if(!$groups):?><div class="form-error">暂无已启用的岗位相关基本条件题目，请联系 HR。</div><?php endif;?><div class="two"><?php foreach($groups as $code=>$rows):?><div class="condition-field"><label><?=e($rows[0]['dim_name'])?><select name="conditions[<?=e($code)?>]" required><option value="" disabled hidden <?=empty($_POST['conditions'][$code])?'selected':''?>>请选择符合自身情况的一项</option><?php foreach($rows as $row):?><option value="<?=e($row['tier_label'])?>" <?=($_POST['conditions'][$code]??null)===$row['tier_label']?'selected':''?>><?=e($row['tier_label'])?></option><?php endforeach;?></select></label><?php $config=$rows[0]['text_question']??[];$text=selected_condition_text($rows,$_POST['conditions'][$code]??null);?><label class="condition-text-answer" data-condition-text="<?=e($code)?>" data-config="<?=e(json_encode($config,JSON_UNESCAPED_UNICODE))?>" <?=empty($text['question'])?'hidden':''?>><span class="condition-followup-heading"><span class="condition-required-star" data-text-required aria-hidden="true" <?=empty($text['required'])?'hidden':''?>>*</span><span data-text-title><?=e($text['question']??'')?></span> <small data-text-hint><?=!empty($text['required'])?'必填':'选填'?></small></span><textarea name="condition_text[<?=e($code)?>]" rows="3" maxlength="2000" <?=empty($text['question'])?'disabled':(!empty($text['required'])?'required':'')?> placeholder="请填写您的回答"><?=e(is_string($_POST['condition_text'][$code]??null)?$_POST['condition_text'][$code]:'')?></textarea><small class="condition-followup-note">补充说明，不计入测评分数</small></label></div><?php endforeach;?></div></section><?php
}
