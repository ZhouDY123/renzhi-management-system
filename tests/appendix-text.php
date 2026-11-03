<?php
require __DIR__.'/../app/appendix.php';
$q=['id'=>1,'title'=>'家庭成员信息','options_json'=>'[]','supplement'=>'说明'];
foreach(['','   ',"\t\n","\u{3000}\u{00A0}","\u{200B}\u{FEFF}",'！？...---','😊👍','12345'] as $value){
 try{appendix_validate_responses([$q],['appendix'=>[1=>['value'=>$value]]]);throw new RuntimeException('Invalid text accepted');}catch(InvalidArgumentException $e){}
}
foreach(['父亲，50岁，职员','无','John, 30 years old','张三\n母亲：李四'] as $value){$r=appendix_validate_responses([$q],['appendix'=>[1=>['value'=>$value]]]);if($r['1']['value']!==$value)throw new RuntimeException('Valid text changed');}
foreach(['  ','!!!','😊'] as $note){try{appendix_validate_responses([$q],['appendix'=>[1=>['value'=>'正常回答','note'=>$note]]]);throw new RuntimeException('Invalid note accepted');}catch(InvalidArgumentException $e){}}
$q['options_json']='["0","1"]';appendix_validate_responses([$q],['appendix'=>[1=>['value'=>'0','note'=>'']]]);
echo "PASS: blank, unicode spaces, invisible characters, punctuation, emoji and digits rejected; text and configured numeric choices accepted\n";
