<?php
require_once __DIR__.'/condition_order.php';
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
    return order_condition_groups($pdo,array_filter($groups,'condition_is_job_related'));
}
function condition_maxima(array $groups): array {
    $maxima=[];foreach($groups as $code=>$rows)$maxima[$code]=max(array_map(fn($row)=>(float)$row['tier_value'],$rows));return $maxima;
}
function validate_document_conditions(array $data,array $groups): void {
    if(!$groups)throw new InvalidArgumentException('暂无已启用的岗位相关基本条件题目，请联系 HR。');
    foreach($groups as $code=>$rows){$answer=$data['conditions'][$code]??null;
        if(!is_string($answer)||!in_array($answer,array_column($rows,'tier_label'),true))throw new InvalidArgumentException('请完成基本条件：'.$rows[0]['dim_name']);
    }
}
function render_document_conditions(array $groups): void {
    $rawMax=rtrim(rtrim(number_format(array_sum(condition_maxima($groups)),2,'.',''),'0'),'.');
    ?><section class="document-conditions"><h3>基本条件测评</h3><p>按实际情况选择，每项只取一档；工作履历在全部经历中选择最高一档，不重复累加。工具能力由本人自评，本部分原始满分<?=e($rawMax)?>分，折算为50分。</p><?php if(!$groups):?><div class="form-error">暂无已启用的岗位相关基本条件题目，请联系 HR。</div><?php endif;?><div class="two"><?php foreach($groups as $code=>$rows):?><label><?=e($rows[0]['dim_name'])?><select name="conditions[<?=e($code)?>]" required><option value="">请选择符合自身情况的一项</option><?php foreach($rows as $row):?><option value="<?=e($row['tier_label'])?>" <?=($_POST['conditions'][$code]??null)===$row['tier_label']?'selected':''?>><?=e($row['tier_label'])?></option><?php endforeach;?></select></label><?php endforeach;?></div></section><?php
}
