<?php
// Versioned, document-derived conditions use explicit single-choice tiers.
function document_condition_groups(PDO $pdo): array {
    $version=(int)$pdo->query("SELECT MAX(version) FROM scoring_standard WHERE status='published' AND category!='basic_quality'")->fetchColumn();
    $st=$pdo->prepare("SELECT * FROM scoring_standard WHERE version=? AND status='published' AND dim_code LIKE 'custom_doc20261007_%' ORDER BY sort,id");$st->execute([$version]);$groups=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$groups[$row['dim_code']][]=$row;
    return $groups;
}
function validate_document_conditions(array $data,array $groups): void {
    foreach($groups as $code=>$rows){$answer=$data['conditions'][$code]??null;
        if(!is_string($answer)||!in_array($answer,array_column($rows,'tier_label'),true))throw new InvalidArgumentException('请完成基本条件：'.$rows[0]['dim_name']);
    }
}
function render_document_conditions(array $groups): void {
    ?><section class="document-conditions"><h3>基本条件测评</h3><p>按实际情况选择，每项只取一档；工作履历在全部经历中选择最高一档，不重复累加。工具能力由本人自评，本部分原始满分29分，折算为50分。</p><div class="two"><?php foreach($groups as $code=>$rows):?><label><?=e($rows[0]['dim_name'])?><select name="conditions[<?=e($code)?>]" required><option value="">请选择符合自身情况的一项</option><?php foreach($rows as $row):?><option value="<?=e($row['tier_label'])?>" <?=($_POST['conditions'][$code]??null)===$row['tier_label']?'selected':''?>><?=e($row['tier_label'])?></option><?php endforeach;?></select></label><?php endforeach;?></div></section><?php
}
