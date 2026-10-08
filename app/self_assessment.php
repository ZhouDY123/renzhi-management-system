<?php
declare(strict_types=1);

function self_assessment_levels(): array {
    return ['完全不符合'=>0.0,'较不符合'=>0.25,'基本符合'=>0.5,'比较符合'=>0.75,'完全符合'=>1.0];
}

function basic_rating_questions_for_post(PDO $pdo, int $postId): array {
    if($postId<=0)return [];
    $st=$pdo->prepare("SELECT id,q_type,stem,options,answer,score FROM question_base WHERE group_type='suzhi' AND post_id=? AND status=1 ORDER BY sort,id");
    $st->execute([$postId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function basic_rating_post_id(PDO $pdo, mixed $value): int {
    $id=filter_var($value,FILTER_VALIDATE_INT);
    $st=$pdo->prepare('SELECT id FROM post WHERE id=?');$st->execute([$id?:0]);
    if(!$id||!$st->fetchColumn())throw new RuntimeException('请选择有效的所属岗位');
    return $id;
}

function self_assessment_score(mixed $answer, float $maximum): float {
    $levels=self_assessment_levels();
    if(!is_string($answer)||!array_key_exists($answer,$levels))throw new InvalidArgumentException('请为每一道基本素质和专业技能题选择一个符合程度');
    return round(max(0,$maximum)*$levels[$answer],4);
}

// Adapt future attempts only. Do not rewrite published papers or historical answers.
function self_assessment_questions(array $questions): array {
    foreach($questions as &$question){
        $question['q_type']='rating';
        $question['options']=json_encode(array_keys(self_assessment_levels()),JSON_UNESCAPED_UNICODE);
        $question['answer']=json_encode(['type'=>'self_rating_v1','levels'=>self_assessment_levels()],JSON_UNESCAPED_UNICODE);
    }
    unset($question);
    return $questions;
}

// Keep the original answer strings and native radio validation used by scoring.
function render_self_assessment_stars(array $question): void {
    $escape=fn(string $value)=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    $name='answers['.$question['scope'].'_'.$question['id'].']';
    ?><div class="rating-stars" role="radiogroup" aria-label="<?=$escape($question['stem'])?>"><?php foreach(array_keys(self_assessment_levels()) as $level=>$label):?><label class="rating-star" title="<?=$escape($label)?>"><input type="radio" name="<?=$escape($name)?>" value="<?=$escape($label)?>" aria-label="<?=$escape($label)?>" required><span aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false"><path d="M15.32 4.47 Q16.00 3.30 16.68 4.47 L19.79 9.78 Q20.11 10.34 20.75 10.48 L26.76 11.79 Q28.08 12.08 27.18 13.08 L23.09 17.68 Q22.66 18.16 22.72 18.81 L23.33 24.93 Q23.46 26.27 22.23 25.73 L16.60 23.26 Q16.00 23.00 15.40 23.26 L9.77 25.73 Q8.54 26.27 8.67 24.93 L9.28 18.81 Q9.34 18.16 8.91 17.68 L4.82 13.08 Q3.92 12.08 5.24 11.79 L11.25 10.48 Q11.89 10.34 12.21 9.78 Z"/></svg></span></label><?php endforeach;?></div><div class="rating-star-ends"><span>完全不符合</span><span>完全符合</span></div><div class="rating-star-answer" aria-live="polite">请选择符合程度</div><?php
}
