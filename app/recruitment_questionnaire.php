<?php
declare(strict_types=1);
function recruitment_questionnaire_questions(PDO $pdo): array {
 $st=$pdo->prepare('SELECT value FROM app_setting WHERE key=?');
 $st->execute(['recruitment_questionnaire_questions']);$value=$st->fetchColumn();
 return $value===false?recruitment_questionnaire_defaults():json_decode($value,true,512,JSON_THROW_ON_ERROR);
}
function recruitment_questionnaire_version(array $questions): string {
 return hash('sha256',json_encode($questions,JSON_UNESCAPED_UNICODE));
}
function recruitment_questionnaire_for_attempt(PDO $pdo,array &$auth,bool $legacySubmission=false): array {
 return $auth['questionnaire_questions']??=($legacySubmission?recruitment_questionnaire_defaults():recruitment_questionnaire_questions($pdo));
}
function save_recruitment_questionnaire(PDO $pdo,array $questions,string $version,int $userId): void {
 if(count($questions)<1||count($questions)>60)throw new InvalidArgumentException('请保留 1 至 60 道题目。');
 foreach($questions as &$question){
  if(!is_string($question))throw new InvalidArgumentException('题目内容格式不正确。');
  $question=trim($question);
  if(preg_match('/\A.{1,1000}\z/us',$question)!==1)throw new InvalidArgumentException('题目不能为空，且不能超过 1000 个字。');
 }unset($question);
 $pdo->beginTransaction();
 try{
  if(!hash_equals(recruitment_questionnaire_version(recruitment_questionnaire_questions($pdo)),$version))throw new InvalidArgumentException('题目已被其他人更新，请刷新页面后重新修改。');
  $pdo->prepare("INSERT INTO app_setting(key,value,label,updated_by) VALUES(?,?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_by=excluded.updated_by,updated_at=datetime('now','localtime')")->execute(['recruitment_questionnaire_questions',json_encode(array_values($questions),JSON_UNESCAPED_UNICODE),'调查问卷题目',$userId]);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function recruitment_questionnaire_defaults(): array {return ['请简述自己的受教育背景（学历和专业）、职业技能和擅长赛道。','您对应聘岗位的认知是基于干事创业？实现财富自由？或是短期练手？','您应聘这个岗位的职业优势是什么？匹配度具体有哪些方面？','您使用AI智能工具的名称和应用程度？客观评价运用AI辅助工作的能力（按10分判定标准）。','本次应聘工作是您的第几份工作？从事过行业？担任职务或其它？','简单判定自己属于哪一种类型？抗压、挑战自我、逆来顺受或不愿受委屈。','您参加工作后就职过几家公司？上家公司离职的原因？能否接受尽职背调？','您的人生偶像是谁？最喜欢做和最不喜欢做的事情？优点是什么？缺点是什么？','您的性格特点描述？朋友圈？血型？是否已结婚、怀孕或备孕、或已订婚？','您日常生活中最喜欢和谁交流？最烦的事情？是否有过劳动仲裁、诉讼的经历？','目前有无兼职或持有其他公司的股份？您希望获得的职业期望与薪资待遇？','您心目中的职业规划是什么？如何提升自己的技能？或您的设想或企求？','您如何理解“学历、经验、能力”的关系？为失误担责，为错误买单是否接受？','“失败是成功之母”您有接受一次失败或屡屡失败的心理素质或坚持的底线？','在AI未来发展的道路上，您如何成就自己？准备好了吗？'];}
