<?php
declare(strict_types=1);

function practical_length(string $text): int { return preg_match_all('/./us',$text) ?: strlen($text); }
function practical_size(int $bytes): string { return $bytes<1024?$bytes.' B':($bytes<1048576?round($bytes/1024,1).' KB':round($bytes/1048576,1).' MB'); }

function practical_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS practical_exam(session_id INTEGER PRIMARY KEY,enabled INTEGER NOT NULL DEFAULT 0,FOREIGN KEY(session_id) REFERENCES interview_session(id));
      CREATE TABLE IF NOT EXISTS practical_question(id INTEGER PRIMARY KEY AUTOINCREMENT,session_id INTEGER NOT NULL,position INTEGER NOT NULL,stem TEXT NOT NULL,FOREIGN KEY(session_id) REFERENCES practical_exam(session_id));
      CREATE TABLE IF NOT EXISTS practical_attempt(id INTEGER PRIMARY KEY AUTOINCREMENT,session_id INTEGER NOT NULL,candidate_id INTEGER NOT NULL,questions TEXT NOT NULL,submitted_at TEXT,created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),UNIQUE(session_id,candidate_id),FOREIGN KEY(session_id) REFERENCES interview_session(id),FOREIGN KEY(candidate_id) REFERENCES candidate(id));
      CREATE TABLE IF NOT EXISTS practical_file(id INTEGER PRIMARY KEY AUTOINCREMENT,attempt_id INTEGER NOT NULL,question_id INTEGER NOT NULL,storage_key TEXT NOT NULL,original_name TEXT NOT NULL,bytes INTEGER NOT NULL,note TEXT NOT NULL DEFAULT '',uploaded_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),UNIQUE(attempt_id,question_id),FOREIGN KEY(attempt_id) REFERENCES practical_attempt(id));
      CREATE TABLE IF NOT EXISTS practical_response(attempt_id INTEGER NOT NULL,question_id INTEGER NOT NULL,answer TEXT NOT NULL DEFAULT '',PRIMARY KEY(attempt_id,question_id),FOREIGN KEY(attempt_id) REFERENCES practical_attempt(id));
      CREATE INDEX IF NOT EXISTS practical_question_session ON practical_question(session_id,position)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS practical_post_question(id INTEGER PRIMARY KEY AUTOINCREMENT,post_id INTEGER NOT NULL,stem TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 1,position INTEGER NOT NULL DEFAULT 0,revision INTEGER NOT NULL DEFAULT 1,FOREIGN KEY(post_id) REFERENCES post(id));
      CREATE INDEX IF NOT EXISTS practical_post_question_post ON practical_post_question(post_id,position);
      CREATE TABLE IF NOT EXISTS practical_migration(name TEXT PRIMARY KEY)");
    if(!$pdo->query("SELECT 1 FROM practical_migration WHERE name='post_question_bank'")->fetchColumn()){
        $pdo->beginTransaction();
        try {
            $claim=$pdo->exec("INSERT OR IGNORE INTO practical_migration(name) VALUES('post_question_bank')");
            if($claim)$pdo->exec("INSERT INTO practical_post_question(post_id,stem,enabled,position) SELECT s.post_id,q.stem,MAX(COALESCE(pe.enabled,1)),MIN(q.position) FROM practical_question q JOIN interview_session s ON s.id=q.session_id LEFT JOIN practical_exam pe ON pe.session_id=s.id GROUP BY s.post_id,q.stem ORDER BY MIN(q.id)");
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
function practical_post_questions(PDO $pdo,int $postId,bool $activeOnly=false): array {
    $st=$pdo->prepare('SELECT id,stem,enabled,position,revision FROM practical_post_question WHERE post_id=?'.($activeOnly?' AND enabled=1':'').' ORDER BY position,id');$st->execute([$postId]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function practical_manage_question(PDO $pdo,int $postId,string $operation,int $id=0,string $stem='',int $revision=0): void {
    if(!in_array($operation,['create','update','delete','toggle'],true))throw new InvalidArgumentException('无效的题目操作');
    $stem=trim($stem);
    if(in_array($operation,['create','update'],true)&&($stem===''||practical_length($stem)>5000))throw new InvalidArgumentException('请输入题目内容，每题最多 5000 字');
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT id FROM post WHERE id=?');$st->execute([$postId]);if(!$st->fetchColumn())throw new InvalidArgumentException('请选择有效岗位');
        if($operation==='create'){
            $qs=practical_post_questions($pdo,$postId);if(count($qs)>=30)throw new InvalidArgumentException('每个岗位最多维护 30 道实操题目');
            $position=$qs?max(array_column($qs,'position'))+1:1;
            $pdo->prepare('INSERT INTO practical_post_question(post_id,stem,enabled,position) VALUES(?,?,1,?)')->execute([$postId,$stem,$position]);
        }else{
            $st=$pdo->prepare('SELECT * FROM practical_post_question WHERE id=? AND post_id=?');$st->execute([$id,$postId]);$q=$st->fetch();
            if(!$q)throw new InvalidArgumentException('题目不存在或不属于当前岗位');
            if((int)$q['revision']!==$revision)throw new InvalidArgumentException('题目已被更新，请刷新页面后重新操作');
            if($operation==='delete')$pdo->prepare('DELETE FROM practical_post_question WHERE id=? AND post_id=?')->execute([$id,$postId]);
            elseif($operation==='toggle')$pdo->prepare('UPDATE practical_post_question SET enabled=1-enabled,revision=revision+1 WHERE id=? AND post_id=?')->execute([$id,$postId]);
            else $pdo->prepare('UPDATE practical_post_question SET stem=?,revision=revision+1 WHERE id=? AND post_id=?')->execute([$stem,$id,$postId]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function practical_questions(PDO $pdo,int $sessionId): array {
    $st=$pdo->prepare('SELECT id,stem,position FROM practical_question WHERE session_id=? ORDER BY position,id');$st->execute([$sessionId]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function practical_save_config(PDO $pdo,int $sessionId,array $stems,bool $enabled): void {
    $clean=[];
    foreach($stems as $stem){if(!is_string($stem))throw new InvalidArgumentException('题目内容无效');$stem=trim($stem);if($stem==='')continue;if(practical_length($stem)>5000)throw new InvalidArgumentException('每题最多 5000 字');$clean[]=$stem;}
    if(count($clean)>30||($enabled&&!$clean))throw new InvalidArgumentException('请设置 1–30 道实操题后再开放答题');
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT status FROM interview_session WHERE id=?');$st->execute([$sessionId]);$status=$st->fetchColumn();
        if(!$status||in_array($status,['done','canceled'],true))throw new InvalidArgumentException('场次已结束或取消，不能修改实操设置');
        $st=$pdo->prepare('SELECT COUNT(*) FROM practical_attempt WHERE session_id=?');$st->execute([$sessionId]);
        if($st->fetchColumn()&&array_column(practical_questions($pdo,$sessionId),'stem')!==$clean)throw new InvalidArgumentException('已有应聘者开始作答，题目已锁定；仍可暂停或开放入口');
        $pdo->prepare('INSERT INTO practical_exam(session_id,enabled) VALUES(?,?) ON CONFLICT(session_id) DO UPDATE SET enabled=excluded.enabled')->execute([$sessionId,(int)$enabled]);
        if(array_column(practical_questions($pdo,$sessionId),'stem')!==$clean){
            $pdo->prepare('DELETE FROM practical_question WHERE session_id=?')->execute([$sessionId]);
            $st=$pdo->prepare('INSERT INTO practical_question(session_id,position,stem) VALUES(?,?,?)');
            foreach($clean as $i=>$stem)$st->execute([$sessionId,$i+1,$stem]);
        }
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function practical_sessions(PDO $pdo,string $name,string $mobile): array {
    $st=$pdo->prepare("SELECT DISTINCT s.id,s.interview_date,s.time_range,s.location,p.name post_name,c.id candidate_id,c.name,c.mobile,a.submitted_at FROM interview_candidate ic JOIN interview_session s ON s.id=ic.session_id JOIN candidate c ON c.id=ic.candidate_id JOIN post p ON p.id=s.post_id LEFT JOIN practical_attempt a ON a.session_id=s.id AND a.candidate_id=c.id WHERE c.name=? AND c.mobile=? AND s.status IN ('pending','scoring') AND (a.id IS NOT NULL OR EXISTS(SELECT 1 FROM practical_post_question q WHERE q.post_id=s.post_id AND q.enabled=1)) ORDER BY s.interview_date DESC,s.id DESC");
    $st->execute([$name,$mobile]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function practical_access(PDO $pdo,int $sessionId,int $candidateId): array {
    $st=$pdo->prepare("SELECT s.*,p.name post_name,c.name candidate_name FROM interview_candidate ic JOIN interview_session s ON s.id=ic.session_id JOIN candidate c ON c.id=ic.candidate_id JOIN post p ON p.id=s.post_id WHERE s.id=? AND c.id=? AND s.status IN ('pending','scoring')");$st->execute([$sessionId,$candidateId]);$row=$st->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new InvalidArgumentException('当前场次未开放实操、已结束，或您未被安排在该场次，请联系工作人员。');return $row;
}
function practical_start(PDO $pdo,int $sessionId,int $candidateId): array {
    $pdo->beginTransaction();
    try {
        $session=practical_access($pdo,$sessionId,$candidateId);
        $st=$pdo->prepare('SELECT * FROM practical_attempt WHERE session_id=? AND candidate_id=?');$st->execute([$sessionId,$candidateId]);$existing=$st->fetch(PDO::FETCH_ASSOC);
        if($existing){$pdo->commit();return $existing;}
        $qs=practical_post_questions($pdo,(int)$session['post_id'],true);
        if(!$qs)throw new InvalidArgumentException('本岗位尚未设置已启用的实操题目');
        $pdo->prepare('INSERT OR IGNORE INTO practical_attempt(session_id,candidate_id,questions) VALUES(?,?,?)')->execute([$sessionId,$candidateId,json_encode($qs,JSON_UNESCAPED_UNICODE)]);
        $st=$pdo->prepare('SELECT * FROM practical_attempt WHERE session_id=? AND candidate_id=?');$st->execute([$sessionId,$candidateId]);$attempt=$st->fetch(PDO::FETCH_ASSOC);$pdo->commit();return $attempt;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function practical_attempt(PDO $pdo,int $attemptId,int $candidateId,bool $editable=true): array {
    $st=$pdo->prepare('SELECT * FROM practical_attempt WHERE id=? AND candidate_id=?');$st->execute([$attemptId,$candidateId]);$attempt=$st->fetch(PDO::FETCH_ASSOC);
    if(!$attempt)throw new InvalidArgumentException('答题记录无效，请重新进入场次');
    practical_access($pdo,(int)$attempt['session_id'],$candidateId);
    if($editable&&$attempt['submitted_at'])throw new InvalidArgumentException('实操已提交，不能再修改成果');return $attempt;
}
function practical_bytes(string $value): int {
    $value=trim($value);$n=(float)$value;return (int)($n*match(strtolower(substr($value,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1});
}
function practical_upload_limit(): int {
    $limits=[500*1024*1024];foreach(['upload_max_filesize','post_max_size'] as $setting){$n=practical_bytes((string)ini_get($setting));if($n>0)$limits[]=$setting==='post_max_size'?max(1,$n-65536):$n;}return min($limits);
}
function practical_extensions(): array {return ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','jpg','jpeg','png','gif','webp','heic','mp4','mov','m4v','webm','mkv','avi'];}
function practical_validate_file(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('文件未上传成功，请重新选择；单个文件上限 '.round(practical_upload_limit()/1048576,1).' MB');
    $name=basename(str_replace('\\','/',(string)($file['name']??'')));
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(!in_array($ext,practical_extensions(),true)||practical_length($name)>180||preg_match('/[\x00-\x1f\x7f]/',$name))throw new InvalidArgumentException('文件格式或名称不支持，请上传页面列出的成果格式');
    if(($file['size']??0)<=0||$file['size']>practical_upload_limit())throw new InvalidArgumentException('文件为空或超过单个文件大小上限');
    return $name;
}
function practical_storage(): string {
    // Follow the existing database's private storage location, never the public document root.
    $dir=getenv('PRACTICAL_STORAGE_DIR')?:dirname(DB_PATH).'/practical-files';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('无法创建成果保存目录，请联系管理员');
    $real=realpath($dir);$web=realpath($_SERVER['DOCUMENT_ROOT']??'');
    if(!$real||($web&&(strtolower($real)===strtolower($web)||str_starts_with(strtolower($real),strtolower($web).DIRECTORY_SEPARATOR))))throw new RuntimeException('成果目录必须位于网站公开目录之外，请联系管理员配置');
    return $real;
}
function practical_file_path(string $key): string {
    if(!preg_match('/^[a-f0-9]{48}\.bin$/D',$key))throw new RuntimeException('成果文件标识无效');return practical_storage().DIRECTORY_SEPARATOR.$key;
}
function practical_files(PDO $pdo,int $attemptId): array {
    $st=$pdo->prepare('SELECT * FROM practical_file WHERE attempt_id=?');$st->execute([$attemptId]);$out=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['question_id']]=$row;return $out;
}
function practical_answers(PDO $pdo,int $attemptId): array {
    $out=[];foreach(practical_files($pdo,$attemptId) as $qid=>$file)$out[$qid]=$file['note'];
    $st=$pdo->prepare('SELECT question_id,answer FROM practical_response WHERE attempt_id=?');$st->execute([$attemptId]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['question_id']]=$row['answer'];return $out;
}
function practical_save_answer(PDO $pdo,int $attemptId,int $questionId,string $answer): void {
    $pdo->prepare('INSERT INTO practical_response(attempt_id,question_id,answer) VALUES(?,?,?) ON CONFLICT(attempt_id,question_id) DO UPDATE SET answer=excluded.answer')->execute([$attemptId,$questionId,$answer]);
}
function practical_upload(PDO $pdo,int $attemptId,int $candidateId,int $questionId,array $file,string $note): void {
    $attempt=practical_attempt($pdo,$attemptId,$candidateId);
    if(!in_array($questionId,array_map('intval',array_column(json_decode($attempt['questions'],true),'id')),true))throw new InvalidArgumentException('题目不属于当前实操');
    if(practical_length($note)>2000)throw new InvalidArgumentException('回答最多 2000 字');
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){
        $pdo->beginTransaction();
        try {
            practical_attempt($pdo,$attemptId,$candidateId);
            $existing=practical_files($pdo,$attemptId)[$questionId]??null;
            practical_save_answer($pdo,$attemptId,$questionId,$note);
            if($existing)$pdo->prepare('UPDATE practical_file SET note=? WHERE id=?')->execute([$note,$existing['id']]);$pdo->commit();return;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    $name=practical_validate_file($file);$key=bin2hex(random_bytes(24)).'.bin';$path=practical_file_path($key);
    if(!is_uploaded_file($file['tmp_name'])||!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('文件保存失败，请重新上传');
    $old=null;
    try {
        if(filesize($path)!==(int)$file['size'])throw new RuntimeException('上传文件不完整，请重新上传');
        $pdo->beginTransaction();practical_attempt($pdo,$attemptId,$candidateId);
        $old=practical_files($pdo,$attemptId)[$questionId]??null;
        $pdo->prepare("INSERT INTO practical_file(attempt_id,question_id,storage_key,original_name,bytes,note) VALUES(?,?,?,?,?,?) ON CONFLICT(attempt_id,question_id) DO UPDATE SET storage_key=excluded.storage_key,original_name=excluded.original_name,bytes=excluded.bytes,note=excluded.note,uploaded_at=datetime('now','localtime')")->execute([$attemptId,$questionId,$key,$name,$file['size'],$note]);
        practical_save_answer($pdo,$attemptId,$questionId,$note);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($path);throw $e;}
    if($old)@unlink(practical_file_path($old['storage_key']));
}
function practical_submit(PDO $pdo,int $attemptId,int $candidateId): void {
    $pdo->beginTransaction();
    try {
        $attempt=practical_attempt($pdo,$attemptId,$candidateId,false);
        if(!$attempt['submitted_at']){
            $files=practical_files($pdo,$attemptId);
            foreach($files as $file)if(!is_file(practical_file_path($file['storage_key'])))throw new InvalidArgumentException('已保存的成果文件缺失，请重新上传后提交');
            $pdo->prepare("UPDATE practical_attempt SET submitted_at=datetime('now','localtime') WHERE id=? AND submitted_at IS NULL")->execute([$attemptId]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function practical_download(array $file): never {
    $path=practical_file_path($file['storage_key']);if(!is_file($path)){http_response_code(404);exit('成果文件不存在，请联系管理员');}
    header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header("Content-Disposition: attachment; filename=\"practical-file\"; filename*=UTF-8''".rawurlencode($file['original_name']));header('Content-Length: '.filesize($path));readfile($path);exit;
}

function practical_question_image_upload(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('图片上传失败，请选择图片重试');
    $tmp=(string)($file['tmp_name']??'');
    if(!is_uploaded_file($tmp))throw new InvalidArgumentException('无效的上传文件');
    if(filesize($tmp)>5*1048576)throw new InvalidArgumentException('每张图片不能超过 5 MB');
    $info=@getimagesize($tmp);$types=[IMAGETYPE_PNG=>'png',IMAGETYPE_JPEG=>'jpg',IMAGETYPE_GIF=>'gif',IMAGETYPE_WEBP=>'webp'];
    if(!$info||!isset($types[$info[2]])||$info[0]*$info[1]>40000000)throw new InvalidArgumentException('请上传 PNG、JPG、GIF 或 WebP 图片，像素不超过 4000 万');
    $dir=ROOT_PATH.'/data/practical-question-images';
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create image directory');
    $name=bin2hex(random_bytes(16)).'.'.$types[$info[2]];
    if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Cannot save image');
    return '/practical.php?action=question_image&image='.$name;
}
function practical_question_image_serve(PDO $pdo,?array $auth): never {
    $name=(string)($_GET['image']??'');$allowed=can('admin','hr','interviewer');
    if(!$allowed&&$auth){
        $st=$pdo->prepare('SELECT pa.questions FROM practical_attempt pa JOIN candidate c ON c.id=pa.candidate_id WHERE c.name=? AND c.mobile=?');
        $st->execute([$auth['name'],$auth['mobile']]);
        foreach($st as $row)if(str_contains($row['questions'],$name)){$allowed=true;break;}
    }
    if(!$allowed||!preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/D',$name)||!is_file(ROOT_PATH.'/data/practical-question-images/'.$name)){http_response_code(404);exit;}
    $types=['png'=>'image/png','jpg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp'];
    header('Content-Type: '.$types[pathinfo($name,PATHINFO_EXTENSION)]);header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Length: '.filesize(ROOT_PATH.'/data/practical-question-images/'.$name));
    readfile(ROOT_PATH.'/data/practical-question-images/'.$name);exit;
}
