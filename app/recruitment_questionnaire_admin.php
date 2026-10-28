<?php
require_once __DIR__.'/recruitment_questionnaire.php';
function render_recruitment_questionnaire_admin(PDO $pdo): void {
 if(!can('admin','hr')){http_response_code(403);return;}
 $questions=recruitment_questionnaire_questions($pdo);
 $version=recruitment_questionnaire_version($questions);$error='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $json=($_POST['response_format']??'')==='json';
  try{
   if(!is_array($_POST['questions']??null))throw new InvalidArgumentException('请至少保留一道题目。');
   save_recruitment_questionnaire($pdo,$_POST['questions'],(string)($_POST['version']??''),(int)admin_user()['id']);
   audit('questionnaire_questions_save','questions:'.count($_POST['questions']));
   if($json){header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>true,'version'=>recruitment_questionnaire_version(recruitment_questionnaire_questions($pdo))]);exit;}
   flash('调查问卷题目已保存，新打开的问卷生效。');
   redirect('/index.php?page=interviews&tab=questionnaire');
  }catch(InvalidArgumentException $e){
   $error=$e->getMessage();
   if($json){http_response_code(422);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>false,'error'=>$error],JSON_UNESCAPED_UNICODE);exit;}
   if(is_array($_POST['questions']??null)&&count($_POST['questions'])<=60){
    $questions=array_values(array_filter($_POST['questions'],'is_string'));
    if(!$questions)$questions=[''];
    $version=(string)($_POST['version']??'');
   }
  }
 }
 admin_header('调查问卷题目维护','interviews');
 page_head('面试管理 / 调查问卷','调查问卷题目维护','维护应聘者在面试前填写的调查问卷，所有题目均为必填文本题。','<a class="btn secondary" href="?page=interviews">返回面试管理</a><button type="button" class="btn primary" data-add-question>＋ 新增题目</button>');
 ?>
 <form method="post" class="panel questionnaire-editor" id="questionnaire-editor">
  <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="version" value="<?=e($version)?>">
  <div class="questionnaire-editor-head"><h2>题目列表 <small data-question-count><?=count($questions)?> 道题</small></h2><span>统一必填 · 拖动调整顺序</span></div>
  <?php if($error):?><div class="form-error" role="alert"><?=e($error)?></div><?php endif;?>
  <div data-question-list><?php foreach($questions as $index=>$question):?>
   <article class="questionnaire-edit-row">
    <div class="questionnaire-row-summary"><button type="button" class="questionnaire-drag" draggable="true" aria-label="拖动排序，也可按上下方向键调整" title="拖动排序 / 上下方向键">⠿</button><span class="questionnaire-number" data-question-number><?=sprintf('%02d',$index+1)?></span><span class="questionnaire-stem" data-question-stem><?=e($question)?></span><small class="questionnaire-type">文本 · 必填</small><button type="button" class="questionnaire-edit-toggle" data-edit-question aria-haspopup="dialog">编辑</button><details class="questionnaire-more"><summary aria-label="更多操作" title="更多操作">···</summary><div><button type="button" data-remove-question>删除题目</button></div></details></div>
    <input type="hidden" name="questions[]" value="<?=e($question)?>">
   </article>
  <?php endforeach;?></div>
  <div class="questionnaire-editor-footer"><small role="status" data-editor-status>删除和排序后自动保存</small><span>已提交记录保留原内容</span></div>
 </form>
 <dialog class="questionnaire-dialog" id="questionnaire-dialog" aria-labelledby="questionnaire-dialog-title">
  <form id="questionnaire-dialog-form">
   <div class="questionnaire-dialog-head"><h2 id="questionnaire-dialog-title">新增题目</h2><button type="button" data-close-question aria-label="关闭弹窗">×</button></div>
   <div class="questionnaire-dialog-body"><label for="questionnaire-content">题目内容</label><textarea id="questionnaire-content" rows="6" maxlength="1000" required placeholder="请输入题目内容"></textarea><p>文本题 · 应聘者必填</p><div class="form-error" role="alert" data-dialog-error hidden></div></div>
   <div class="questionnaire-dialog-footer"><button type="button" class="btn secondary" data-close-question>取消</button><button type="submit" class="btn primary">保存题目</button></div>
  </form>
 </dialog>
 <script src="/assets/questionnaire-editor.js?v=3" defer></script>
 <?php admin_footer();
}
