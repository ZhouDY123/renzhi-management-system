<?php
require_once __DIR__.'/recruitment_questionnaire.php';
function render_recruitment_questionnaire_admin(PDO $pdo): void {
 if(!can('admin','hr')){http_response_code(403);return;}
 $questions=recruitment_questionnaire_questions($pdo);
 $version=recruitment_questionnaire_version($questions);$error='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  try{
   if(!is_array($_POST['questions']??null))throw new InvalidArgumentException('请至少保留一道题目。');
   save_recruitment_questionnaire($pdo,$_POST['questions'],(string)($_POST['version']??''),(int)admin_user()['id']);
   audit('questionnaire_questions_save','questions:'.count($_POST['questions']));
   flash('调查问卷题目已保存，新打开的问卷生效。');
   redirect('/index.php?page=interviews&tab=questionnaire');
  }catch(InvalidArgumentException $e){
   $error=$e->getMessage();
   if(is_array($_POST['questions']??null)&&count($_POST['questions'])<=60){
    $questions=array_values(array_filter($_POST['questions'],'is_string'));
    if(!$questions)$questions=[''];
    $version=(string)($_POST['version']??'');
   }
  }
 }
 admin_header('调查问卷题目维护','interviews');
 page_head('面试管理 / 调查问卷','调查问卷题目维护','维护应聘者在面试前填写的调查问卷，所有题目均为必填文本题。','<a class="btn secondary" href="?page=interviews">返回面试管理</a><button type="button" class="btn secondary" data-add-question>＋ 新增题目</button><button type="submit" form="questionnaire-editor" class="btn primary">保存修改</button>');
 ?>
 <form method="post" class="panel questionnaire-editor" id="questionnaire-editor">
  <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="version" value="<?=e($version)?>">
  <div class="questionnaire-editor-head"><h2>题目列表 <small data-question-count><?=count($questions)?> 道题</small></h2><span>统一必填 · 拖动调整顺序</span></div>
  <?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
  <div data-question-list><?php foreach($questions as $index=>$question):?>
   <article class="questionnaire-edit-row">
    <div class="questionnaire-row-summary"><button type="button" class="questionnaire-drag" draggable="true" aria-label="拖动排序，也可按上下方向键调整" title="拖动排序 / 上下方向键">⠿</button><span class="questionnaire-number" data-question-number><?=sprintf('%02d',$index+1)?></span><span class="questionnaire-stem" data-question-stem><?=e($question)?></span><small class="questionnaire-type">文本 · 必填</small><button type="button" class="questionnaire-edit-toggle" data-edit-question aria-expanded="true">收起</button><details class="questionnaire-more"><summary aria-label="更多操作" title="更多操作">···</summary><div><button type="button" data-remove-question>删除题目</button></div></details></div>
    <div class="questionnaire-inline-editor"><label><span>题目内容</span><textarea name="questions[]" rows="3" maxlength="1000" required aria-label="题目内容"><?=e($question)?></textarea></label><div class="questionnaire-inline-footer"><small>修改后点击右上角「保存修改」</small><button type="button" data-finish-question>完成编辑</button></div></div>
   </article>
  <?php endforeach;?></div>
  <div class="questionnaire-editor-footer"><small role="status" data-editor-status>修改仅在保存后生效</small><span>已提交记录保留原内容</span></div>
 </form>
 <script src="/assets/questionnaire-editor.js?v=2" defer></script>
 <?php admin_footer();
}
