<?php
declare(strict_types=1);

function render_grouped_standards_page(string $page, PDO $pdo): bool {
    if ($page !== 'standards') return false;
    admin_header('评分标准','standards');
    $rows=$pdo->query('SELECT * FROM scoring_standard WHERE status!="retired" ORDER BY category,dim_code,sort,id')->fetchAll();
    $conditionGroups=[];$suzhiGroups=[];$initialSection=$_SESSION['standards_active_section']??'conditions';unset($_SESSION['standards_active_section']);
    foreach($rows as $row){$target=$row['category']==='basic_quality'?'suzhiGroups':'conditionGroups';${$target}[$row['dim_code']][]=$row;}
    page_head('职位管理 / 评分体系','基本条件与基本素质','两类通用评分维度均可独立维护档位、分值和启用状态。'); ?>
    <section class="grouped-standards">
      <div class="standard-section-switch" role="tablist" aria-label="评分内容切换"><button type="button" class="btn <?=$initialSection==='conditions'?'is-active':''?>" role="tab" aria-selected="<?=$initialSection==='conditions'?'true':'false'?>" data-standard-show="conditions">基本条件测评</button><button type="button" class="btn <?=$initialSection==='suzhi'?'is-active':''?>" role="tab" aria-selected="<?=$initialSection==='suzhi'?'true':'false'?>" data-standard-show="suzhi">基本素质测评</button></div>
      <div id="standard-conditions" class="standard-tab-panel" data-standard-panel="conditions" <?=$initialSection==='suzhi'?'hidden':''?>>
        <article class="panel grouped-standard-intro"><div><b>一、基本条件自动评分</b><p>年龄、健康状况、学历、工作经历等客观信息；应聘者填写或选择后按规则自动评分。</p></div></article>
        <?=render_standard_dimension_search('conditions','搜索基本条件维度，例如：年龄、学历') ?>
        <?=render_standard_dimension_form('conditions')?>
        <?=render_standard_dimension_cards($conditionGroups)?>
      </div>
      <div id="standard-suzhi" class="standard-tab-panel" data-standard-panel="suzhi" <?=$initialSection==='conditions'?'hidden':''?>>
        <article class="panel grouped-standard-intro suzhi-intro"><div><b>二、基本素质测评</b><p>沟通协作、逻辑分析、责任意识、学习适应等通用维度；与基本条件采用同样的评分档位管理方式。</p></div></article>
        <?=render_standard_dimension_search('suzhi','搜索基本素质维度，例如：沟通、责任') ?>
        <?=render_standard_dimension_cards($suzhiGroups)?>
        <?php if(!$suzhiGroups):?><div class="panel empty-state"><b>暂未设置基本素质维度</b><p>点击右上角“新增基本素质维度”开始配置。</p></div><?php endif;?>
      </div>
    </section><?php admin_footer();return true;
}

function render_standard_dimension_form(string $scope): string {ob_start();?>
<form class="panel form-panel standard-dimension-form" method="post" action="?page=standards&action=standard_dimension_create"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="standard_scope" value="<?=e($scope)?>"><div class="form-title"><h2>新增<?= $scope==='suzhi'?'基本素质':'基本条件'?>维度</h2></div><label>维度名称<input name="dim_name" placeholder="例如：沟通能力" required></label><label>评分方式<select name="dimension_type" data-dimension-type><option value="answer">按答案评分</option><option value="range">按数值区间评分</option></select></label><div class="new-dimension-tier-list" data-new-dimension-tier-list><div class="new-dimension-tier"><div class="tier-answer-field"><label>答案选项<input name="answer_label[]" placeholder="例如：优秀" required></label></div><div class="form-grid tier-range-fields" hidden><label>区间最小值<input name="range_min[]" type="number" placeholder="例如：3" disabled></label><label>区间最大值（不含）<input name="range_max[]" type="number" placeholder="例如：5" disabled></label></div><label class="tier-correct-field" hidden>答案属性<select name="tier_correct[]" disabled><option value="1">正确答案</option><option value="0">错误答案（本题 0 分）</option></select></label><label>对应分值<input name="tier_value[]" type="number" min="0" step="0.1" required></label></div></div><button type="button" class="btn secondary add-initial-tier" data-add-initial-tier>＋ 添加评分档位</button><button class="btn primary create-dimension-button">创建维度</button></form>
<?php return (string)ob_get_clean();}

function render_standard_dimension_search(string $scope,string $placeholder): string {ob_start();?>
<div class="standard-search" role="search" aria-label="<?= $scope==='suzhi'?'基本素质':'基本条件'?>维度搜索"><label class="standard-search-field"><span aria-hidden="true">⌕</span><input type="search" data-standard-search="<?=e($scope)?>" placeholder="<?=e($placeholder)?>" autocomplete="off"><button type="button" class="standard-search-clear" data-standard-search-clear="<?=e($scope)?>" aria-label="清除搜索" hidden>×</button></label><small data-standard-search-result="<?=e($scope)?>"></small></div>
<?php return (string)ob_get_clean();}

function render_standard_dimension_cards(array $groups): string {ob_start();foreach($groups as $code=>$rules):$first=$rules[0];$hasDraft=(bool)array_filter($rules,fn($rule)=>$rule['status']==='draft');$hasPublished=(bool)array_filter($rules,fn($rule)=>$rule['status']==='published');$isNumeric=in_array($first['match_type'],['range','bool_range'],true)||in_array($code,['age','work_years','prof_years','group_co','listed_co','private_co'],true);$saveAction=str_starts_with($code,'custom_')?'standard_custom_group_save':'standard_group_save';?><form class="panel dimension-score-card" data-numeric="<?=$isNumeric?'1':'0'?>" method="post" action="?page=standards&action=<?=$saveAction?>"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="dim_code" value="<?=e($code)?>"><header><div><small><?=e(standard_category_label($first['category']))?></small><h2><?=e($first['dim_name'])?></h2><p><?= $isNumeric?'按数值区间直接设置分值':'按答案或条件直接设置分值' ?></p></div><div class="dimension-actions"><span><?=count($rules)?> 个评分档位</span><?php if($hasPublished):?><em class="badge green">已启用</em><?php endif;?><?php if($hasDraft):?><em class="badge amber">待启用</em><button class="btn secondary" name="op" value="publish" formaction="?page=standards&action=<?=$saveAction?>">启用该维度</button><?php endif;?></div></header><div class="dimension-score-list"><?php foreach($rules as $rule):?><div class="dimension-score-row"><input type="hidden" name="rule_id[]" value="<?=$rule['id']?>"><div><b><?=e($rule['tier_label'])?></b><small><?=e(standard_rule_text($rule['match_type'],$rule['match_rule']))?></small></div><label>分值<input name="tier_value[]" type="number" min="0" step="0.1" value="<?=e((string)$rule['tier_value'])?>"></label></div><?php endforeach;?></div><footer><button formaction="?page=standards&action=standard_dimension_delete" class="btn danger" data-confirm-message="将删除整个评分维度及全部档位，确认继续？">删除维度</button><button class="btn primary">保存<?=e($first['dim_name'])?>评分</button></footer></form><?php endforeach;return (string)ob_get_clean();}

function standard_category_label(string $category): string {return ['basic_info'=>'基本信息','exp_qualification'=>'履历与资质','work_history'=>'工作履历与背景','skills'=>'技能与能力','basic_quality'=>'基本素质'][$category] ?? '评分维度';}
