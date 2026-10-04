<?php
declare(strict_types=1);

function self_assessment_levels(): array {
    return ['完全不符合'=>0.0,'较不符合'=>0.25,'基本符合'=>0.5,'比较符合'=>0.75,'完全符合'=>1.0];
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
