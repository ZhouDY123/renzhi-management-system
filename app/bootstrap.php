<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');
session_name('renzhi_session');
session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax','path'=>'/']);
session_start();

const ROOT_PATH = __DIR__ . '/..';
const DB_PATH = ROOT_PATH . '/data/app.db';

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
    return $pdo;
}

function public_base_url(): string {
    $configured = rtrim((string)getenv('RENZHI_PUBLIC_BASE_URL'), '/');
    if ($configured !== '') return $configured;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/i', $host)) {
        $address = gethostbyname(gethostname());
        $port = (string)parse_url('http://'.($host ?: '127.0.0.1:8080'), PHP_URL_PORT);
        $host = $address !== gethostname() ? $address.($port !== '' ? ':'.$port : '') : '127.0.0.1:8080';
    }
    return $scheme.'://'.$host;
}

function migrate(): void {
    $pdo = db();
    $pdo->exec(file_get_contents(ROOT_PATH . '/migrations/001_init.sql'));
    upgrade_schema($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM user')->fetchColumn() === 0) seed($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM scoring_standard')->fetchColumn() === 0) seed_catalog($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM question_post')->fetchColumn() === 0 && (int)$pdo->query('SELECT COUNT(*) FROM post')->fetchColumn() > 0) {
        $pdo->prepare("INSERT INTO question_post(post_id,q_type,stem,answer,point_desc,score,status,sort) VALUES(1,'short',?,?,?,?,1,1)")->execute(['请简述集团预算管理的三个核心目标。','资源配置、经营约束、绩效评价','答案应覆盖资源配置、过程控制与绩效评价，并结合实际案例。',40]);
    }
}

function upgrade_schema(PDO $pdo): void {
    $columns=[
        'candidate'=>['health TEXT','politics TEXT','group_co_years INTEGER DEFAULT 0','group_co_mgmt INTEGER DEFAULT 0','listed_co_years INTEGER DEFAULT 0','listed_co_mgmt INTEGER DEFAULT 0','private_co_years INTEGER DEFAULT 0','private_co_mgmt INTEGER DEFAULT 0','work_bg TEXT','computer_skill TEXT','language TEXT','custom_values TEXT','intent_post_id INTEGER'],
        'answer'=>["post_snapshot TEXT NOT NULL DEFAULT '{}'",'question_set_id INTEGER NOT NULL DEFAULT 0'],
        'result'=>['standard_version INTEGER NOT NULL DEFAULT 1',"weight_snapshot TEXT NOT NULL DEFAULT '{\"basic_conditions\":50,\"basic_quality\":25,\"professional\":25}'"],
        'question_set_item'=>['q_type TEXT','stem_snapshot TEXT','options_snapshot TEXT','answer_snapshot TEXT','score_snapshot REAL'],
        'interview_score'=>['detail_json TEXT', 'total_score REAL', 'strengths TEXT', 'risks TEXT', 'development TEXT', 'recommendation TEXT', 'salary_range TEXT', 'available_date TEXT'],
        'interview_score_draft'=>['detail_json TEXT', 'total_score REAL', 'strengths TEXT', 'risks TEXT', 'development TEXT', 'recommendation TEXT', 'salary_range TEXT', 'available_date TEXT']
    ];
    foreach($columns as $table=>$defs){$existing=array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(),'name');foreach($defs as $def){$name=strtok($def,' ');if(!in_array($name,$existing,true))$pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$def);}}
    // 人才先入库，再由 HR 为其登记一个面试岗位。
    $pdo->exec("CREATE TABLE IF NOT EXISTS interview_pre_register (id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER NOT NULL UNIQUE, post_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'registered', created_by INTEGER, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')), FOREIGN KEY(candidate_id) REFERENCES candidate(id), FOREIGN KEY(post_id) REFERENCES post(id), FOREIGN KEY(created_by) REFERENCES user(id))");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_interview_pre_register_post ON interview_pre_register(post_id,status)');
    // 将曾以“18至29岁”这类文本录入的年龄档位修正为真正的数值区间。
    $ageRules=$pdo->query("SELECT id,tier_label FROM scoring_standard WHERE dim_code='age' AND match_type='eq'")->fetchAll();
    $fixAge=$pdo->prepare("UPDATE scoring_standard SET match_type='range',match_rule=? WHERE id=?");
    foreach($ageRules as $rule){if(preg_match('/^(\d+)至(\d+)岁$/u',$rule['tier_label'],$m)){$fixAge->execute([json_encode(['min'=>(int)$m[1],'max'=>(int)$m[2]+1],JSON_UNESCAPED_UNICODE),(int)$rule['id']]);}}
    $suffixes=['group_co'=>'集团公司工作履历','listed_co'=>'上市公司工作履历','private_co'=>'非上市公司工作履历','work_years'=>'参加工作年限','prof_years'=>'从事专业年限'];$shortRules=$pdo->query("SELECT id,dim_code,tier_label FROM scoring_standard WHERE match_type='eq'")->fetchAll();$rename=$pdo->prepare('UPDATE scoring_standard SET tier_label=?,match_rule=? WHERE id=?');foreach($shortRules as $rule){$suffix=$suffixes[$rule['dim_code']]??null;if($suffix&&preg_match('/^(\d+)至(\d+)年$/u',$rule['tier_label'],$m)){$label=$m[1].'至'.$m[2].'年'.$suffix;$rename->execute([$label,json_encode(['value'=>$label],JSON_UNESCAPED_UNICODE),(int)$rule['id']]);}}
}

function seed_catalog(PDO $pdo): void {
    $dims=[['basic_info','age','年龄','35岁以下','range','{"max":35}',5],['exp_qualification','edu','学历','本科','eq','{"value":"本科"}',4],['exp_qualification','edu','学历','硕士及以上','in','{"values":["硕士","博士"]}',5],['work_history','work_years','工作年限','10年以上','range','{"min":10}',5],['skills','computer','计算机能力','熟练','eq','{"value":"熟练"}',3]];
    $st=$pdo->prepare('INSERT INTO scoring_standard(category,dim_code,dim_name,tier_label,match_type,match_rule,tier_value,version,status,sort) VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach($dims as $i=>$d)$st->execute([...$d,1,'published',$i+1]);
    $qs=[['suzhi','pinzhi','single','面对紧急任务时，您通常如何处理？','["立即梳理优先级并协调资源","等待上级给出完整指令","先处理容易完成的事项"]','立即梳理优先级并协调资源',20],['suzhi','strategy','single','跨部门意见不一致时，最有效的做法是？','["围绕共同目标明确分歧","坚持本部门方案","暂时搁置问题"]','围绕共同目标明确分歧',20]];
    $st=$pdo->prepare('INSERT INTO question_base(group_type,dim_code,q_type,stem,options,answer,score,status,sort) VALUES(?,?,?,?,?,?,?,1,?)');
    foreach($qs as $i=>$q)$st->execute([...$q,$i+1]);
}

function seed(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $users = [
            ['admin', '系统管理员', 'admin', '13800000000'], ['hr', '张海峰', 'hr', '13800000001'],
            ['lisi', '李四', 'interviewer', '13800000002'], ['wangwu', '王五', 'interviewer', '13800000003']
        ];
        $st = $pdo->prepare('INSERT INTO user(username,password_hash,real_name,role,mobile) VALUES(?,?,?,?,?)');
        foreach ($users as $u) $st->execute([$u[0], password_hash('admin123', PASSWORD_DEFAULT), $u[1], $u[2], $u[3]]);
        $posts = [
            ['集团财务经理','集团总部','财务序列','recruiting','负责集团财务管理、预算与经营分析'],
            ['信息化项目经理','数字科技公司','技术序列','recruiting','负责集团信息化项目规划与交付'],
            ['品牌运营总监','品牌中心','管理序列','recruiting','负责品牌战略、内容和营销活动'],
            ['人力资源经理','集团总部','职能序列','reserve','负责人力资源规划与人才发展']
        ];
        $st=$pdo->prepare('INSERT INTO post(name,company,series,status,duty,q_apply_token) VALUES(?,?,?,?,?,?)');
        foreach($posts as $p) $st->execute([...$p, bin2hex(random_bytes(8))]);
        $pdo->prepare('INSERT INTO candidate_pre_register(post_id,name,mobile,status,created_by) VALUES(?,?,?,?,?)')->execute([1,'陈晓宁','13800138000','answered',2]);
        $pdo->prepare('INSERT INTO candidate(name,mobile,gender,birth_date,edu,school_tier,major,title,work_years,prof_years) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute(['陈晓宁','13800138000','女','1989-06-12','本科','一本院校','会计学','中级会计师',12,10]);
        $cid=(int)$pdo->lastInsertId();
        $snap=json_encode(['name'=>'陈晓宁','mobile'=>'13800138000','edu'=>'本科'],JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO answer(candidate_id,post_id,candidate_snapshot,idempotency_key) VALUES(?,?,?,?)')->execute([$cid,1,$snap,bin2hex(random_bytes(12))]);
        $aid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO result(answer_id,eval_score,suzhi_score,postq_score,total_score) VALUES(?,?,?,?,?)')->execute([$aid,88,82,81.3,84.6]);
        $pdo->prepare('INSERT INTO interview_session(post_id,interview_date,time_range,location,qr_token,created_by) VALUES(?,?,?,?,?,?)')->execute([1,date('Y-m-d'),'14:00-16:00','第一会议室',bin2hex(random_bytes(8)),2]);
        $sid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO interview_candidate(session_id,candidate_id,answer_id,seq) VALUES(?,?,?,1)')->execute([$sid,$cid,$aid]);
        $pdo->prepare('INSERT INTO session_interviewer(session_id,user_id,weight,is_lead) VALUES(?,?,?,?)')->execute([$sid,3,1,1]);
        $pdo->prepare('INSERT INTO session_interviewer(session_id,user_id,weight,is_lead) VALUES(?,?,?,?)')->execute([$sid,4,1,0]);
        $pdo->commit();
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function check_csrf(): void { if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) { http_response_code(419); exit('请求已过期，请返回重试'); } }
function redirect(string $url): never { header('Location: '.$url); exit; }
function admin_user(): ?array { return $_SESSION['admin']??null; }
function require_admin(): void { if(!admin_user()) redirect('/index.php?page=login'); }
function can(string ...$roles): bool { $u=admin_user(); return $u&&($u['role']==='admin'||in_array($u['role'],$roles,true)); }
function flash(string $message, string $type='ok'): void { $_SESSION['flash']=[$message,$type]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function token(int $bytes=8): string { return bin2hex(random_bytes($bytes)); }
function post_status(string $s): string { return ['recruiting'=>'招聘中','reserve'=>'储备中','stop'=>'已停止'][$s]??$s; }
function first_char(string $value): string { return preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY)[0]??'?'; }
function audit(string $action,string $target='',array $detail=[]): void { db()->prepare('INSERT INTO op_log(user_id,action,target,detail,ip) VALUES(?,?,?,?,?)')->execute([admin_user()['id']??null,$action,$target,json_encode($detail,JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'cli']); }
function setting(string $key,string $default=''): string { $st=db()->prepare('SELECT value FROM app_setting WHERE key=?');$st->execute([$key]);$v=$st->fetchColumn();return $v===false?$default:(string)$v; }
function rate_limit_check(string $scope,string $identity,int $max=10,int $window=300,int $block=300): bool { $pdo=db();$key=hash('sha256',$scope.'|'.$identity);$now=time();$st=$pdo->prepare('SELECT * FROM rate_limit WHERE limit_key=?');$st->execute([$key]);$r=$st->fetch();if($r&&(int)$r['blocked_until']>$now)return false;if(!$r||$now-(int)$r['window_start']>$window){$pdo->prepare('INSERT INTO rate_limit(limit_key,attempts,window_start,blocked_until) VALUES(?,1,?,0) ON CONFLICT(limit_key) DO UPDATE SET attempts=1,window_start=excluded.window_start,blocked_until=0')->execute([$key,$now]);return true;}$attempts=(int)$r['attempts']+1;$until=$attempts>$max?$now+$block:0;$pdo->prepare('UPDATE rate_limit SET attempts=?,blocked_until=? WHERE limit_key=?')->execute([$attempts,$until,$key]);return $attempts<=$max; }
function rate_limit_clear(string $scope,string $identity): void { db()->prepare('DELETE FROM rate_limit WHERE limit_key=?')->execute([hash('sha256',$scope.'|'.$identity)]); }

migrate();
