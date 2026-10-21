<?php
function render_condition_categories_page(PDO $pdo): void {
    $categories=condition_categories($pdo);$counts=array_fill_keys(array_keys($categories),0);
    foreach($pdo->query("SELECT dim_code,dim_name FROM scoring_standard WHERE status!='retired' AND category!='basic_quality' GROUP BY dim_code")->fetchAll() as $row)$counts[condition_category($pdo,$row)]++;
    admin_header('基本条件分类','condition_categories');
    page_head('题目管理 / 分类维护','基本条件分类','按分类组织评分维度，让题目管理更清晰。'); ?>
    <section class="category-maintenance">
      <div class="category-overview"><div><span class="category-overview-icon" aria-hidden="true">▥</span><div><h2>分类目录 <span><?=count($categories)?></span></h2><p>分类名称同步显示在基本题目的分类标签中</p></div></div><a class="btn secondary" href="?page=standards">查看基本题目 →</a></div>
      <form class="category-create" method="post" action="?page=condition_categories&action=create">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <label for="category-new-name">新增分类<small>名称最多 20 个字</small></label>
        <input id="category-new-name" name="category_name" maxlength="20" required placeholder="输入分类名称，例如：职业资格" autocomplete="off">
        <button class="btn primary">＋ 新增分类</button>
      </form>
      <div class="category-card-grid">
      <?php $index=0;foreach($categories as $id=>$name):$index++; ?>
        <article class="category-maintenance-card">
          <header><span class="category-number"><?=str_pad((string)$index,2,'0',STR_PAD_LEFT)?></span><span class="category-count"><?=$counts[$id]?> 个评分维度</span></header>
          <h2><?=e($name)?></h2><p>用于基本条件题目的分类展示</p>
          <div class="category-card-actions">
            <details class="category-action"><summary>编辑名称</summary><form method="post" action="?page=condition_categories&action=edit">
              <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="category_id" value="<?=e($id)?>">
              <label>分类名称<input name="category_name" value="<?=e($name)?>" required maxlength="20"></label><button class="btn primary">保存修改</button>
            </form></details>
            <details class="category-action category-delete"><summary>删除分类</summary><form method="post" action="?page=condition_categories&action=delete">
              <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="category_id" value="<?=e($id)?>">
              <?php if(count($categories)>1):?><p>删除后，原分类下的维度将转入所选分类，题目和评分保留。</p>
              <label>转入分类<select name="target_category" required><?php foreach($categories as $otherId=>$otherName):if($otherId===$id)continue;?><option value="<?=e($otherId)?>"><?=e($otherName)?></option><?php endforeach;?></select></label>
              <button class="btn danger" data-confirm-message="确认删除此分类并转移所属维度？">确认删除</button>
              <?php else:?><p>请至少保留一个分类。新增其他分类后可删除此分类。</p><?php endif;?>
            </form></details>
          </div>
        </article>
      <?php endforeach;?>
      </div>
    </section>
    <?php admin_footer();
}
