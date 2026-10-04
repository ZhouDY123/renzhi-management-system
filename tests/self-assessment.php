<?php
require __DIR__.'/../app/self_assessment.php';
foreach(array_keys(self_assessment_levels()) as $i=>$label){
    if(self_assessment_score($label,3)!==$i*.75)throw new RuntimeException('Incorrect level score');
}
foreach(['', '正确', '6', ['完全符合']] as $invalid){
    try{self_assessment_score($invalid,3);throw new RuntimeException('Invalid rating accepted');}catch(InvalidArgumentException $e){}
}
echo "PASS: five rating levels and invalid answers\n";
